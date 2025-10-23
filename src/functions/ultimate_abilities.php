<?php
// ultimate_abilities_functions.php

function verificar_desbloqueio_ultimate($player_id, $ultimate_id, $conexao) {
    $sql_ultimate = "SELECT * FROM ultimate_abilities_base WHERE id = ?";
    $stmt = $conexao->prepare($sql_ultimate);
    $stmt->bind_param("i", $ultimate_id);
    $stmt->execute();
    $ultimate = $stmt->get_result()->fetch_assoc();
    
    if (!$ultimate) {
        return ['desbloqueada' => false, 'mensagem' => 'Ultimate não encontrada'];
    }
    
    $player_data = $conexao->query("SELECT * FROM personagens WHERE id = $player_id")->fetch_assoc();
    $condicoes = json_decode($ultimate['condicoes_desbloqueio'], true);
    
    foreach ($condicoes as $tipo => $valor) {
        switch ($tipo) {
            case 'nivel':
                if ($player_data['level'] < $valor) {
                    return ['desbloqueada' => false, 'mensagem' => "Requer nível {$valor} (atual: {$player_data['level']})"];
                }
                break;
                
            case 'classe':
                if ($player_data['classe_base'] != $valor) {
                    return ['desbloqueada' => false, 'mensagem' => "Requer classe {$valor}"];
                }
                break;
                
            case 'habilidades_aprendidas':
                $sql_count = "SELECT COUNT(*) as total FROM personagem_skills WHERE id_personagem = $player_id";
                $total_skills = $conexao->query($sql_count)->fetch_assoc()['total'];
                if ($total_skills < $valor) {
                    return ['desbloqueada' => false, 'mensagem' => "Requer {$valor} habilidades aprendidas (atual: {$total_skills})"];
                }
                break;
                
            case 'mana_maximo':
                if ($player_data['mana_max'] < $valor) {
                    return ['desbloqueada' => false, 'mensagem' => "Requer {$valor} de mana máxima (atual: {$player_data['mana_max']})"];
                }
                break;
                
            case 'hp_maximo':
                if ($player_data['hp_max'] < $valor) {
                    return ['desbloqueada' => false, 'mensagem' => "Requer {$valor} de HP máximo (atual: {$player_data['hp_max']})"];
                }
                break;
                
            case 'inteligencia':
                if ($player_data['int_stat'] < $valor) {
                    return ['desbloqueada' => false, 'mensagem' => "Requer {$valor} de inteligência (atual: {$player_data['int_stat']})"];
                }
                break;
                
            case 'mortes':
                // Contar mortes do jogador (precisa de tabela de estatísticas)
                $mortes = 0; // Implemente conforme seu sistema
                if ($mortes < $valor) {
                    return ['desbloqueada' => false, 'mensagem' => "Requer {$valor} mortes para aprender"];
                }
                break;
                
            case 'habilidades_cura':
                // Contar habilidades de cura aprendidas
                $sql_heal = "SELECT COUNT(*) as total FROM personagem_skills ps 
                            JOIN skills_base sb ON ps.id_skill_base = sb.id 
                            WHERE ps.id_personagem = $player_id AND sb.nome LIKE '%cura%'";
                $total_heal = $conexao->query($sql_heal)->fetch_assoc()['total'] ?? 0;
                if ($total_heal < $valor) {
                    return ['desbloqueada' => false, 'mensagem' => "Requer {$valor} habilidades de cura (atual: {$total_heal})"];
                }
                break;
        }
    }
    
    // Se passou em todas as condições, desbloqueia
    $sql_check = "SELECT id FROM player_ultimate_abilities WHERE player_id = ? AND ultimate_id = ?";
    $stmt = $conexao->prepare($sql_check);
    $stmt->bind_param("ii", $player_id, $ultimate_id);
    $stmt->execute();
    
    if ($stmt->get_result()->num_rows == 0) {
        $sql_insert = "INSERT INTO player_ultimate_abilities (player_id, ultimate_id, desbloqueada, usos_totais) 
                       VALUES (?, ?, TRUE, 0)";
        $stmt = $conexao->prepare($sql_insert);
        $stmt->bind_param("ii", $player_id, $ultimate_id);
        $stmt->execute();
    } else {
        $conexao->query("UPDATE player_ultimate_abilities SET desbloqueada = TRUE WHERE player_id = $player_id AND ultimate_id = $ultimate_id");
    }
    
    return ['desbloqueada' => true, 'mensagem' => "🎉 Ultimate '{$ultimate['nome']}' desbloqueada!"];
}

function usar_ultimate_ability($player_id, $ultimate_id, $combate_data, $conexao) {
    $sql_ultimate = "SELECT uab.*, pua.cooldown_restante 
                     FROM ultimate_abilities_base uab
                     JOIN player_ultimate_abilities pua ON uab.id = pua.ultimate_id
                     WHERE pua.player_id = ? AND pua.ultimate_id = ? AND pua.desbloqueada = TRUE";
    
    $stmt = $conexao->prepare($sql_ultimate);
    $stmt->bind_param("ii", $player_id, $ultimate_id);
    $stmt->execute();
    $ultimate = $stmt->get_result()->fetch_assoc();
    
    if (!$ultimate) {
        return ['sucesso' => false, 'mensagem' => 'Ultimate não desbloqueada ou não encontrada'];
    }
    
    if ($ultimate['cooldown_restante'] > 0) {
        return ['sucesso' => false, 'mensagem' => "Ultimate em recarga! {$ultimate['cooldown_restante']} turnos restantes"];
    }
    
    if ($combate_data['jogador_mana_atual'] < $ultimate['custo_mana']) {
        return ['sucesso' => false, 'mensagem' => 'Mana insuficiente para usar ultimate'];
    }
    
    // Aplica custo de mana
    $combate_data['jogador_mana_atual'] -= $ultimate['custo_mana'];
    $_SESSION['combate_ativo']['jogador_mana_atual'] = $combate_data['jogador_mana_atual'];
    
    // Atualiza cooldown e usos
    $conexao->query("UPDATE player_ultimate_abilities SET 
                    cooldown_restante = {$ultimate['cooldown_turnos']},
                    usos_totais = usos_totais + 1,
                    data_ultimo_uso = NOW()
                    WHERE player_id = $player_id AND ultimate_id = $ultimate_id");
    
    // Aplica efeito da ultimate
    $efeito = aplicar_efeito_ultimate($player_id, $ultimate, $combate_data, $conexao);
    
    return [
        'sucesso' => true,
        'mensagem' => "✨ **ULTIMATE ATIVADA!** {$ultimate['icone']} {$ultimate['nome']} - {$efeito['mensagem']}",
        'efeito' => $efeito,
        'ultimate' => $ultimate
    ];
}

function aplicar_efeito_ultimate($player_id, $ultimate, $combate_data, $conexao) {
    $efeito_principal = json_decode($ultimate['efeito_principal'], true);
    $mensagem = "";
    
    switch ($ultimate['tipo']) {
        case 'dano':
            if ($ultimate['alvo'] == 'area') {
                // Dano em área
                $dano_base = $efeito_principal['dano_base'] ?? 100;
                $multiplicador = $efeito_principal['multiplicador'] ?? 1.0;
                $dano_extra = $efeito_principal['dano_extra'] ?? 0;
                
                $player_data = $conexao->query("SELECT * FROM personagens WHERE id = $player_id")->fetch_assoc();
                $atributo = strpos($ultimate['nome'], 'Arcana') !== false ? 'int_stat' : 'str';
                $atributo_valor = $player_data[$atributo] ?? 10;
                
                $dano_total = ($dano_base + ($multiplicador * $atributo_valor) + $dano_extra);
                
                // Aplica dano ao monstro
                $combate_data['monstro']['hp_atual'] -= $dano_total;
                $_SESSION['combate_ativo']['monstro']['hp_atual'] = $combate_data['monstro']['hp_atual'];
                
                $mensagem = "Causou {$dano_total} de dano em área!";
                
                // Aplica status secundário se houver
                if (isset($efeito_principal['status_aplicado']) && function_exists('aplicar_status_effect')) {
                    $combate_id = $_SESSION['combate_id'] ?? "player_" . $player_id;
                    $monstro_id = $combate_data['monstro']['id'] ?? 1;
                    aplicar_status_effect($combate_id, $monstro_id, 'monstro', $efeito_principal['status_aplicado'], $conexao, $efeito_principal['intensidade'] ?? 1);
                    $mensagem .= " + Status aplicado!";
                }
            }
            break;
            
        case 'cura':
            if ($ultimate['alvo'] == 'self' || $ultimate['alvo'] == 'aliados') {
                $cura_base = $efeito_principal['cura_base'] ?? 100;
                $multiplicador = $efeito_principal['multiplicador'] ?? 1.0;
                $cura_extra = $efeito_principal['cura_extra'] ?? 0;
                
                $player_data = $conexao->query("SELECT * FROM personagens WHERE id = $player_id")->fetch_assoc();
                $atributo_valor = $player_data['wis'] ?? 10;
                
                $cura_total = ($cura_base + ($multiplicador * $atributo_valor) + $cura_extra);
                $novo_hp = min($player_data['hp_max'], $combate_data['jogador_hp_atual'] + $cura_total);
                
                $combate_data['jogador_hp_atual'] = $novo_hp;
                $_SESSION['combate_ativo']['jogador_hp_atual'] = $novo_hp;
                
                $mensagem = "Curou {$cura_total} de HP!";
                
                // Ressuscitar se for o caso
                if (isset($efeito_principal['ressuscitar']) && $efeito_principal['ressuscitar']) {
                    $mensagem = "Ressuscitou com HP e Mana completos!";
                }
            }
            break;
            
        case 'suporte':
            if ($ultimate['alvo'] == 'self') {
                // Buffs/proteções
                if (isset($efeito_principal['protecao'])) {
                    $_SESSION['ultimate_protecao'] = [
                        'valor' => $efeito_principal['protecao'],
                        'duracao' => $efeito_principal['duracao'] ?? 2,
                        'turno_ativado' => $combate_data['turno_atual']
                    ];
                    $mensagem = "Proteção ativada por {$efeito_principal['duracao']} turnos!";
                }
                
                if (isset($efeito_principal['turno_extra'])) {
                    $_SESSION['ultimate_turno_extra'] = true;
                    $mensagem = "Turno extra concedido!";
                }
            }
            break;
            
        case 'transformacao':
            // Buffs temporários
            $_SESSION['ultimate_buff'] = [
                'dano_dobrado' => $efeito_principal['dano_dobrado'] ?? false,
                'duracao' => $efeito_principal['duracao'] ?? 3,
                'turno_ativado' => $combate_data['turno_atual'],
                'velocidade_extra' => $efeito_principal['velocidade_extra'] ?? 0
            ];
            $mensagem = "Poder liberado por {$efeito_principal['duracao']} turnos!";
            break;
    }
    
    return ['mensagem' => $mensagem, 'dados' => $efeito_principal];
}

function get_ultimates_disponiveis($player_id, $combate_data, $conexao) {
    $sql = "SELECT uab.*, pua.cooldown_restante, pua.usos_totais
            FROM ultimate_abilities_base uab
            JOIN player_ultimate_abilities pua ON uab.id = pua.ultimate_id
            WHERE pua.player_id = ? AND pua.desbloqueada = TRUE
            ORDER BY uab.nivel_requerido";
    
    $stmt = $conexao->prepare($sql);
    $stmt->bind_param("i", $player_id);
    $stmt->execute();
    
    return $stmt->get_result();
}

function processar_cooldown_ultimates($player_id, $conexao) {
    // Reduz cooldown de todas as ultimates
    $conexao->query("UPDATE player_ultimate_abilities 
                    SET cooldown_restante = GREATEST(0, cooldown_restante - 1) 
                    WHERE player_id = $player_id AND cooldown_restante > 0");
}

function get_buff_ultimate_ativo() {
    $buff = $_SESSION['ultimate_buff'] ?? null;
    if ($buff) {
        $turnos_passados = ($_SESSION['combate_ativo']['turno_atual'] ?? 1) - $buff['turno_ativado'];
        if ($turnos_passados >= $buff['duracao']) {
            unset($_SESSION['ultimate_buff']);
            return null;
        }
    }
    return $buff;
}
?>