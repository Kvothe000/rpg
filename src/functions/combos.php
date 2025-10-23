<?php
// combos_functions.php

function popular_combos_base($conexao) {
    $combos_base = [
        // COMBOS DE GUERREIRO
        [
            'nome' => 'Combo de Fúria',
            'descricao' => 'Golpe Duplo → Ataque Giratório → Fúria Berserk',
            'sequencia' => '["Golpe Duplo", "Ataque Giratório", "Fúria Berserk"]',
            'classe_req' => 'Guerreiro',
            'nivel_requerido' => 5,
            'efeito_combo' => '{"tipo": "dano_extra", "valor": 75, "duracao": 2, "status_aplicado": 5}',
            'icone' => '💥'
        ],
        [
            'nome' => 'Combo Defensivo', 
            'descricao' => 'Postura Defensiva → Contra-Ataque → Investida Implacável',
            'sequencia' => '["Postura Defensiva", "Contra-Ataque", "Investida Implacável"]',
            'classe_req' => 'Guerreiro',
            'nivel_requerido' => 8,
            'efeito_combo' => '{"tipo": "buff_defesa", "valor": 15, "duracao": 3, "mitigacao_extra": 10}',
            'icone' => '🛡️'
        ],

        // COMBOS DE MAGO
        [
            'nome' => 'Combo Arcano',
            'descricao' => 'Missil Mágico → Explosão Arcana → Tempestade de Mana',
            'sequencia' => '["Missil Mágico", "Explosão Arcana", "Tempestade de Mana"]',
            'classe_req' => 'Mago',
            'nivel_requerido' => 6,
            'efeito_combo' => '{"tipo": "dano_magico_extra", "valor": 50, "duracao": 2, "status_aplicado": 1}',
            'icone' => '🔮'
        ],
        [
            'nome' => 'Combo Elemental',
            'descricao' => 'Toque Chocante → Rajada de Gelo → Labareda',
            'sequencia' => '["Toque Chocante", "Rajada de Gelo", "Labareda"]',
            'classe_req' => 'Mago', 
            'nivel_requerido' => 10,
            'efeito_combo' => '{"tipo": "multi_status", "status": [1, 3, 6], "dano_extra": 100}',
            'icone' => '⚡'
        ],

        // COMBOS GERAIS
        [
            'nome' => 'Combo Rápido',
            'descricao' => 'Ataque Rápido → Golpe Preciso → Finalizador',
            'sequencia' => '["Ataque Rápido", "Golpe Preciso", "Finalizador"]',
            'classe_req' => NULL,
            'nivel_requerido' => 3,
            'efeito_combo' => '{"tipo": "critico_extra", "valor": 25, "duracao": 1}',
            'icone' => '🎯'
        ]
    ];

    foreach ($combos_base as $combo) {
        $sql_check = "SELECT id FROM combos_base WHERE nome = ?";
        $stmt = $conexao->prepare($sql_check);
        $stmt->bind_param("s", $combo['nome']);
        $stmt->execute();
        
        if ($stmt->get_result()->num_rows == 0) {
            $sql = "INSERT INTO combos_base (nome, descricao, sequencia, classe_req, nivel_requerido, efeito_combo, icone) 
                    VALUES (?, ?, ?, ?, ?, ?, ?)";
            $stmt = $conexao->prepare($sql);
            $stmt->bind_param("ssssiss", 
                $combo['nome'], $combo['descricao'], $combo['sequencia'],
                $combo['classe_req'], $combo['nivel_requerido'], $combo['efeito_combo'],
                $combo['icone']
            );
            $stmt->execute();
        }
    }
}

function verificar_combo($player_id, $skill_nome, $turno_atual, $conexao) {
    $resultado = ['combo_ativado' => false, 'mensagem' => '', 'efeito' => null];
    
    // Busca combos disponíveis para o jogador
    $sql_combos = "SELECT cb.*, pcp.sequencia_atual, pcp.id as progresso_id, pcp.expira_em_turno
                   FROM combos_base cb
                   LEFT JOIN player_combo_progress pcp ON cb.id = pcp.combo_id AND pcp.player_id = ? AND pcp.ativo = TRUE
                   WHERE (cb.classe_req IS NULL OR cb.classe_req = (SELECT classe_base FROM personagens WHERE id = ?))
                   AND cb.nivel_requerido <= (SELECT level FROM personagens WHERE id = ?)";
    
    $stmt = $conexao->prepare($sql_combos);
    $stmt->bind_param("iii", $player_id, $player_id, $player_id);
    $stmt->execute();
    $combos = $stmt->get_result();

    while ($combo = $combos->fetch_assoc()) {
        $sequencia = json_decode($combo['sequencia'], true);
        
        // ✅ CORREÇÃO: Verificar se expira_em_turno existe e é válido
        $expira_em_turno = $combo['expira_em_turno'] ?? null;
        $sequencia_atual = isset($combo['sequencia_atual']) ? json_decode($combo['sequencia_atual'], true) : [];
        
        // Verifica se expirou (apenas se tiver progresso)
        if ($expira_em_turno && $turno_atual > $expira_em_turno && $combo['progresso_id']) {
            $conexao->query("UPDATE player_combo_progress SET ativo = FALSE WHERE id = {$combo['progresso_id']}");
            continue;
        }
        
        // Verifica próximo passo do combo
        $proximo_passo = count($sequencia_atual);
        
        if ($skill_nome === $sequencia[$proximo_passo]) {
            // Acertou o próximo passo
            $sequencia_atual[] = $skill_nome;
            $novo_sequencia_json = json_encode($sequencia_atual);
            
            if ($combo['progresso_id']) {
                // Atualiza progresso existente
                $conexao->query("UPDATE player_combo_progress SET sequencia_atual = '$novo_sequencia_json' WHERE id = {$combo['progresso_id']}");
            } else {
                // Cria novo progresso
                $expira_em = $turno_atual + $combo['duracao_combo'];
                $sql_insert = "INSERT INTO player_combo_progress (player_id, combo_id, sequencia_atual, turno_inicio, expira_em_turno) 
                               VALUES (?, ?, ?, ?, ?)";
                $stmt = $conexao->prepare($sql_insert);
                $stmt->bind_param("iisii", $player_id, $combo['id'], $novo_sequencia_json, $turno_atual, $expira_em);
                $stmt->execute();
                $combo['progresso_id'] = $conexao->insert_id;
                $expira_em_turno = $expira_em; // ✅ Atualiza para a UI
            }
            
            // Verifica se completou o combo
            if (count($sequencia_atual) == count($sequencia)) {
                // COMBO COMPLETO!
                $efeito = json_decode($combo['efeito_combo'], true);
                $resultado = ativar_combo($player_id, $combo['id'], $efeito, $conexao);
                $resultado['mensagem'] = "🎊 **COMBO COMPLETO!** {$combo['icone']} {$combo['nome']} - {$combo['descricao']}";
                
                // Limpa progresso
                $conexao->query("UPDATE player_combo_progress SET ativo = FALSE WHERE id = {$combo['progresso_id']}");
            } else {
                $resultado['mensagem'] = "⚡ **Combo em Progresso** ({$combo['nome']}) - " . (count($sequencia_atual)) . "/" . count($sequencia) . " passos";
            }
            
            break;
        } else {
            // Errou a sequência - reseta este combo (apenas se tiver progresso)
            if ($combo['progresso_id']) {
                $conexao->query("UPDATE player_combo_progress SET ativo = FALSE WHERE id = {$combo['progresso_id']}");
                $resultado['mensagem'] = "❌ Combo {$combo['nome']} resetado! Sequência errada.";
            }
        }
    }
    
    return $resultado;
}

function ativar_combo($player_id, $combo_id, $efeito, $conexao) {
    $mensagem_extra = "";
    
    switch ($efeito['tipo']) {
        case 'dano_extra':
            // Aplica bônus de dano nos próximos turnos
            $_SESSION['combo_bonus_dano'] = [
                'valor' => $efeito['valor'],
                'duracao' => $efeito['duracao'],
                'turno_ativado' => $_SESSION['combate_ativo']['turno_atual'] ?? 1
            ];
            $mensagem_extra = " +{$efeito['valor']} de dano nos próximos {$efeito['duracao']} turnos!";
            break;
            
        case 'buff_defesa':
            $_SESSION['combo_buff_defesa'] = [
                'valor' => $efeito['valor'],
                'duracao' => $efeito['duracao'],
                'turno_ativado' => $_SESSION['combate_ativo']['turno_atual'] ?? 1
            ];
            $mensagem_extra = " +{$efeito['valor']} de defesa nos próximos {$efeito['duracao']} turnos!";
            break;
            
        case 'dano_magico_extra':
            $_SESSION['combo_bonus_magico'] = [
                'valor' => $efeito['valor'],
                'duracao' => $efeito['duracao'], 
                'turno_ativado' => $_SESSION['combate_ativo']['turno_atual'] ?? 1
            ];
            $mensagem_extra = " +{$efeito['valor']} de dano mágico nos próximos {$efeito['duracao']} turnos!";
            break;
            
        case 'critico_extra':
            $_SESSION['combo_bonus_critico'] = [
                'valor' => $efeito['valor'],
                'duracao' => $efeito['duracao'],
                'turno_ativado' => $_SESSION['combate_ativo']['turno_atual'] ?? 1
            ];
            $mensagem_extra = " +{$efeito['valor']}% de chance crítica nos próximos {$efeito['duracao']} turnos!";
            break;
            
        case 'multi_status':
            // Aplica múltiplos status effects
            if (isset($efeito['status'])) {
                $combate_id = $_SESSION['combate_id'] ?? "player_" . $player_id;
                foreach ($efeito['status'] as $status_id) {
                    // Aplica no monstro (ajuste o ID do monstro conforme seu sistema)
                    $monstro_id = $_SESSION['combate_ativo']['monstro']['id'] ?? 1;
                    aplicar_status_effect($combate_id, $monstro_id, 'monstro', $status_id, $conexao);
                }
                $mensagem_extra = " Múltiplos status effects aplicados!";
            }
            break;
    }
    
    // Aplica status se especificado
    if (isset($efeito['status_aplicado'])) {
        $combate_id = $_SESSION['combate_id'] ?? "player_" . $player_id;
        $monstro_id = $_SESSION['combate_ativo']['monstro']['id'] ?? 1;
        aplicar_status_effect($combate_id, $monstro_id, 'monstro', $efeito['status_aplicado'], $conexao);
    }
    
    // Registra achievement de combo
    if (function_exists('atualizar_progresso_achievement')) {
        atualizar_progresso_achievement($player_id, 'combos_realizados', 1, $conexao);
    }
    
    return [
        'combo_ativado' => true,
        'mensagem' => $mensagem_extra,
        'efeito' => $efeito
    ];
}

function get_combos_ativos($player_id, $conexao) {
    $sql = "SELECT cb.*, pcp.sequencia_atual, pcp.expira_em_turno,
                   (SELECT COUNT(*) FROM combos_base cb2 WHERE cb2.classe_req = cb.classe_req OR cb2.classe_req IS NULL) as total_classe
            FROM player_combo_progress pcp
            JOIN combos_base cb ON pcp.combo_id = cb.id
            WHERE pcp.player_id = ? AND pcp.ativo = TRUE
            ORDER BY cb.nivel_requerido";
    
    $stmt = $conexao->prepare($sql);
    $stmt->bind_param("i", $player_id);
    $stmt->execute();
    
    return $stmt->get_result();
}

function aplicar_bonus_combo($dano_base, $player_id) {
    $dano_final = $dano_base;
    
    // Bônus de dano físico
    if (isset($_SESSION['combo_bonus_dano'])) {
        $bonus = $_SESSION['combo_bonus_dano'];
        $turnos_passados = ($_SESSION['combate_ativo']['turno_atual'] ?? 1) - $bonus['turno_ativado'];
        
        if ($turnos_passados < $bonus['duracao']) {
            $dano_final += $bonus['valor'];
        } else {
            unset($_SESSION['combo_bonus_dano']);
        }
    }
    
    // Bônus de dano mágico
    if (isset($_SESSION['combo_bonus_magico'])) {
        $bonus = $_SESSION['combo_bonus_magico'];
        $turnos_passados = ($_SESSION['combate_ativo']['turno_atual'] ?? 1) - $bonus['turno_ativado'];
        
        if ($turnos_passados < $bonus['duracao']) {
            $dano_final += $bonus['valor'];
        } else {
            unset($_SESSION['combo_bonus_magico']);
        }
    }
    
    return $dano_final;
}

function get_bonus_critico_combo() {
    if (isset($_SESSION['combo_bonus_critico'])) {
        $bonus = $_SESSION['combo_bonus_critico'];
        $turnos_passados = ($_SESSION['combate_ativo']['turno_atual'] ?? 1) - $bonus['turno_ativado'];
        
        if ($turnos_passados < $bonus['duracao']) {
            return $bonus['valor'];
        } else {
            unset($_SESSION['combo_bonus_critico']);
        }
    }
    
    return 0;
}
?>