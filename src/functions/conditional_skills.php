<?php
// conditional_skills_functions.php

function verificar_condicoes_habilidade($player_id, $skill_id, $combate_data, $conexao) {
    $sql_skill = "SELECT condicoes_uso FROM skills_base WHERE id = ?";
    $stmt = $conexao->prepare($sql_skill);
    $stmt->bind_param("i", $skill_id);
    $stmt->execute();
    $skill = $stmt->get_result()->fetch_assoc();
    
    if (!$skill || !$skill['condicoes_uso']) {
        return ['pode_usar' => true, 'mensagem' => ''];
    }
    
    $condicoes = json_decode($skill['condicoes_uso'], true);
    $player_data = $conexao->query("SELECT * FROM personagens WHERE id = $player_id")->fetch_assoc();
    
    foreach ($condicoes as $tipo => $valor) {
        switch ($tipo) {
            case 'hp_minimo':
                $hp_percent = ($player_data['hp_atual'] / $player_data['hp_max']) * 100;
                if ($hp_percent > $valor) {
                    return ['pode_usar' => false, 'mensagem' => "❌ Requer HP abaixo de {$valor}% (atual: " . round($hp_percent) . "%)"];
                }
                break;
                
            case 'hp_maximo':
                $hp_percent = ($player_data['hp_atual'] / $player_data['hp_max']) * 100;
                if ($hp_percent < $valor) {
                    return ['pode_usar' => false, 'mensagem' => "❌ Requer HP acima de {$valor}% (atual: " . round($hp_percent) . "%)"];
                }
                break;
                
            case 'mana_minimo':
                $mana_atual = $combate_data['jogador_mana_atual'] ?? $player_data['mana_atual'];
                $mana_max = $player_data['mana_max'];
                $mana_percent = ($mana_atual / $mana_max) * 100;
                if ($mana_percent > $valor) {
                    return ['pode_usar' => false, 'mensagem' => "❌ Requer Mana abaixo de {$valor}% (atual: " . round($mana_percent) . "%)"];
                }
                break;
                
            case 'turno_minimo':
                if (($combate_data['turno_atual'] ?? 1) < $valor) {
                    return ['pode_usar' => false, 'mensagem' => "❌ Disponível a partir do turno {$valor}"];
                }
                break;
                
            case 'turno_maximo':
                if (($combate_data['turno_atual'] ?? 1) > $valor) {
                    return ['pode_usar' => false, 'mensagem' => "❌ Disponível apenas até o turno {$valor}"];
                }
                break;
                
            case 'alvo_hp_minimo':
                $monstro_hp_atual = $combate_data['monstro']['hp_atual'] ?? 100;
                $monstro_hp_max = $combate_data['monstro']['hp_max'] ?? 100;
                $monstro_hp_percent = ($monstro_hp_atual / $monstro_hp_max) * 100;
                if ($monstro_hp_percent > $valor) {
                    return ['pode_usar' => false, 'mensagem' => "❌ Alvo deve ter HP abaixo de {$valor}% (atual: " . round($monstro_hp_percent) . "%)"];
                }
                break;
                
            case 'precisa_status':
                // Verifica se tem status específico ativo
                if (function_exists('get_status_ativos')) {
                    $combate_id = $_SESSION['combate_id'] ?? "player_" . $player_id;
                    $status_ativos = get_status_ativos($combate_id, $player_id, 'player', $conexao);
                    $tem_status = false;
                    while ($status = $status_ativos->fetch_assoc()) {
                        if ($status['id'] == $valor) {
                            $tem_status = true;
                            break;
                        }
                    }
                    if (!$tem_status) {
                        return ['pode_usar' => false, 'mensagem' => "❌ Requer status específico ativo"];
                    }
                }
                break;
                
            case 'recursos_alternativos':
                // Usa HP como custo adicional
                if ($valor == 'hp_custo') {
                    $mana_atual = $combate_data['jogador_mana_atual'] ?? $player_data['mana_atual'];
                    $custo_hp = $mana_atual * 0.5; // 50% da mana atual como HP
                    if ($player_data['hp_atual'] <= $custo_hp) {
                        return ['pode_usar' => false, 'mensagem' => "❌ HP insuficiente para custo alternativo"];
                    }
                }
                break;
        }
    }
    
    return ['pode_usar' => true, 'mensagem' => ''];
}

function aplicar_custo_alternativo($player_id, $skill_id, $combate_data, $conexao) {
    $sql_skill = "SELECT condicoes_uso FROM skills_base WHERE id = ?";
    $stmt = $conexao->prepare($sql_skill);
    $stmt->bind_param("i", $skill_id);
    $stmt->execute();
    $skill = $stmt->get_result()->fetch_assoc();
    
    if (!$skill || !$skill['condicoes_uso']) {
        return false;
    }
    
    $condicoes = json_decode($skill['condicoes_uso'], true);
    $player_data = $conexao->query("SELECT * FROM personagens WHERE id = $player_id")->fetch_assoc();
    
    foreach ($condicoes as $tipo => $valor) {
        if ($tipo === 'recursos_alternativos' && $valor == 'hp_custo') {
            $mana_atual = $combate_data['jogador_mana_atual'] ?? $player_data['mana_atual'];
            $custo_hp = $mana_atual * 0.5;
            
            // Atualiza HP no combate
            $combate_data['jogador_hp_atual'] -= $custo_hp;
            $_SESSION['combate_ativo']['jogador_hp_atual'] = $combate_data['jogador_hp_atual'];
            
            // Atualiza HP no banco (opcional)
            $conexao->query("UPDATE personagens SET hp_atual = hp_atual - $custo_hp WHERE id = $player_id");
            
            return [
                'tipo' => 'hp_custo',
                'valor' => $custo_hp,
                'mensagem' => "💔 Usou {$custo_hp} de HP como custo alternativo!"
            ];
        }
    }
    
    return false;
}

function get_habilidades_condicionais_disponiveis($player_id, $combate_data, $conexao) {
    $sql_skills = "SELECT sb.*, ps.skill_level 
                   FROM skills_base sb
                   JOIN personagem_skills ps ON sb.id = ps.id_skill_base
                   WHERE ps.id_personagem = ? AND sb.condicoes_uso IS NOT NULL";
    
    $stmt = $conexao->prepare($sql_skills);
    $stmt->bind_param("i", $player_id);
    $stmt->execute();
    $habilidades = $stmt->get_result();
    
    $disponiveis = [];
    
    while ($skill = $habilidades->fetch_assoc()) {
        $condicao = verificar_condicoes_habilidade($player_id, $skill['id'], $combate_data, $conexao);
        if ($condicao['pode_usar']) {
            $disponiveis[] = $skill;
        }
    }
    
    return $disponiveis;
}

// Função auxiliar para debug
function debug_condicoes_habilidade($player_id, $skill_id, $combate_data, $conexao) {
    $resultado = verificar_condicoes_habilidade($player_id, $skill_id, $combate_data, $conexao);
    echo "<pre>Debug Condições - Player: $player_id, Skill: $skill_id\n";
    echo "Pode usar: " . ($resultado['pode_usar'] ? 'SIM' : 'NÃO') . "\n";
    echo "Mensagem: " . $resultado['mensagem'] . "\n";
    echo "Combate Data: " . print_r($combate_data, true) . "</pre>";
    return $resultado;
}
?>