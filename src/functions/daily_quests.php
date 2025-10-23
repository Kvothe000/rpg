<?php
// daily_quests_functions.php

function gerar_missoes_diarias($player_id, $conexao) {
    // Verifica se já tem missões hoje
    $hoje = date('Y-m-d');
    $sql_check = "SELECT COUNT(*) as total FROM player_daily_quests 
                  WHERE player_id = ? AND data_ativacao = ?";
    $stmt = $conexao->prepare($sql_check);
    $stmt->bind_param("is", $player_id, $hoje);
    $stmt->execute();
    $result = $stmt->get_result()->fetch_assoc();
    
    if ($result['total'] > 0) {
        return; // Já tem missões hoje
    }

    // Limpa missões antigas (mais de 2 dias)
    $dois_dias_atras = date('Y-m-d', strtotime('-2 days'));
    $conexao->query("DELETE FROM player_daily_quests WHERE player_id = $player_id AND data_ativacao < '$dois_dias_atras'");

    // Pega rank do jogador
    $sql_rank = "SELECT fama_rank FROM personagens WHERE id = $player_id";
    $rank_jogador = $conexao->query($sql_rank)->fetch_assoc()['fama_rank'];

    // Gera 3 missões aleatórias adequadas ao rank
    $sql_missoes = "SELECT * FROM daily_quests_base 
                    WHERE rank_requerido <= ? 
                    ORDER BY RAND() LIMIT 3";
    $stmt = $conexao->prepare($sql_missoes);
    $stmt->bind_param("s", $rank_jogador);
    $stmt->execute();
    $missoes = $stmt->get_result();

    while ($missao = $missoes->fetch_assoc()) {
        $sql_insert = "INSERT INTO player_daily_quests (player_id, quest_id, data_ativacao) 
                       VALUES (?, ?, ?)";
        $stmt_insert = $conexao->prepare($sql_insert);
        $stmt_insert->bind_param("iis", $player_id, $missao['id'], $hoje);
        $stmt_insert->execute();
    }
}

function get_missoes_diarias_jogador($player_id, $conexao) {
    $hoje = date('Y-m-d');
    
    $sql = "SELECT pdq.*, dqb.*, 
            (pdq.progresso_atual / dqb.objetivo * 100) as progresso_percentual
            FROM player_daily_quests pdq
            JOIN daily_quests_base dqb ON pdq.quest_id = dqb.id
            WHERE pdq.player_id = ? AND pdq.data_ativacao = ?
            ORDER BY pdq.completada ASC, dqb.tipo";
    
    $stmt = $conexao->prepare($sql);
    $stmt->bind_param("is", $player_id, $hoje);
    $stmt->execute();
    
    return $stmt->get_result();
}

function atualizar_progresso_missao($player_id, $tipo_objetivo, $quantidade, $conexao) {
    $hoje = date('Y-m-d');
    
    $sql = "UPDATE player_daily_quests pdq
            JOIN daily_quests_base dqb ON pdq.quest_id = dqb.id
            SET pdq.progresso_atual = LEAST(pdq.progresso_atual + ?, dqb.objetivo)
            WHERE pdq.player_id = ? 
            AND pdq.data_ativacao = ? 
            AND dqb.tipo_objetivo = ?
            AND pdq.completada = FALSE";
    
    $stmt = $conexao->prepare($sql);
    $stmt->bind_param("iiss", $quantidade, $player_id, $hoje, $tipo_objetivo);
    $stmt->execute();

    // Verifica missões completadas
    verificar_missoes_completadas($player_id, $conexao);
}

function verificar_missoes_completadas($player_id, $conexao) {
    $hoje = date('Y-m-d');
    
    $sql = "SELECT pdq.id, dqb.recompensa_ouro, dqb.recompensa_xp, dqb.titulo
            FROM player_daily_quests pdq
            JOIN daily_quests_base dqb ON pdq.quest_id = dqb.id
            WHERE pdq.player_id = ? 
            AND pdq.data_ativacao = ?
            AND pdq.progresso_atual >= dqb.objetivo
            AND pdq.completada = FALSE";
    
    $stmt = $conexao->prepare($sql);
    $stmt->bind_param("is", $player_id, $hoje);
    $stmt->execute();
    $missoes_prontas = $stmt->get_result();

    while ($missao = $missoes_prontas->fetch_assoc()) {
        // Dar recompensa
        $conexao->query("UPDATE personagens SET ouro = ouro + {$missao['recompensa_ouro']}, xp_atual = xp_atual + {$missao['recompensa_xp']} WHERE id = $player_id");
        
        // Marcar como completada
        $conexao->query("UPDATE player_daily_quests SET completada = TRUE, data_completada = NOW() WHERE id = {$missao['id']}");
        // Na função verificar_missoes_completadas, adicione:
        atualizar_progresso_achievement($player_id, 'missoes_completas', 1, $conexao);
        
        // Trigger de level up (se você tiver essa função)
        if (function_exists('verificar_level_up')) {
            verificar_level_up($player_id, $conexao);
        }
    }
}

// Função para popular as missões base (executar uma vez)
function popular_missoes_base($conexao) {
    $missoes_base = [
        // COMBATE
        [
            'titulo' => 'Caçador Inicial',
            'descricao' => 'Derrote 10 monstros de qualquer rank',
            'tipo' => 'combate',
            'tipo_objetivo' => 'matar_monstros',
            'objetivo' => 10,
            'rank_requerido' => 'E',
            'recompensa_ouro' => 500,
            'recompensa_xp' => 1000
        ],
        [
            'titulo' => 'Mestre do Campo de Batalha',
            'descricao' => 'Derrote 25 monstros Rank C ou superior',
            'tipo' => 'combate', 
            'tipo_objetivo' => 'matar_monstros',
            'objetivo' => 25,
            'rank_requerido' => 'C',
            'recompensa_ouro' => 1500,
            'recompensa_xp' => 3000
        ],

        // ECOS
        [
            'titulo' => 'Lazarento de Almas',
            'descricao' => 'Complete 3 missões com seus Ecos',
            'tipo' => 'ecos',
            'tipo_objetivo' => 'completar_missoes', 
            'objetivo' => 3,
            'rank_requerido' => 'D',
            'recompensa_ouro' => 800,
            'recompensa_xp' => 1500
        ],
        [
            'titulo' => 'Vínculo Profundo',
            'descricao' => 'Aumente o nível de afinidade de qualquer Eco',
            'tipo' => 'ecos',
            'tipo_objetivo' => 'evoluir_affinity',
            'objetivo' => 1,
            'rank_requerido' => 'C',
            'recompensa_ouro' => 1200,
            'recompensa_xp' => 2000
        ],

        // ECONOMIA
        [
            'titulo' => 'Mercador Astuto',
            'descricao' => 'Gaste 2000 de ouro',
            'tipo' => 'economia',
            'tipo_objetivo' => 'gastar_ouro',
            'objetivo' => 2000,
            'rank_requerido' => 'D', 
            'recompensa_ouro' => 1000,
            'recompensa_xp' => 1500
        ],

        // PROGRESSO
        [
            'titulo' => 'Aprendiz Zeloso',
            'descricao' => 'Use habilidades 15 vezes em combate',
            'tipo' => 'progresso',
            'tipo_objetivo' => 'usar_habilidades',
            'objetivo' => 15,
            'rank_requerido' => 'D',
            'recompensa_ouro' => 600,
            'recompensa_xp' => 1200
        ]
    ];

    foreach ($missoes_base as $missao) {
        // Verifica se já existe
        $sql_check = "SELECT id FROM daily_quests_base WHERE titulo = ?";
        $stmt = $conexao->prepare($sql_check);
        $stmt->bind_param("s", $missao['titulo']);
        $stmt->execute();
        
        if ($stmt->get_result()->num_rows == 0) {
            $sql = "INSERT INTO daily_quests_base (titulo, descricao, tipo, tipo_objetivo, objetivo, rank_requerido, recompensa_ouro, recompensa_xp) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
            $stmt = $conexao->prepare($sql);
            $stmt->bind_param("ssssisii", 
                $missao['titulo'], $missao['descricao'], $missao['tipo'], 
                $missao['tipo_objetivo'], $missao['objetivo'], $missao['rank_requerido'],
                $missao['recompensa_ouro'], $missao['recompensa_xp']
            );
            $stmt->execute();
        }
    }
}
?>