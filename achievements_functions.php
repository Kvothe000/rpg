<?php
// achievements_functions.php

function popular_achievements_base($conexao) {
    $conquistas_base = [
        // COMBATE
        [
            'titulo' => 'Primeiro Sangue',
            'descricao' => 'Derrote seu primeiro monstro',
            'categoria' => 'combate',
            'tipo_objetivo' => 'monstros_derrotados',
            'objetivo' => 1,
            'recompensa_ouro' => 100,
            'recompensa_xp' => 200,
            'icone' => '🩸',
            'raridade' => 'comum'
        ],
        [
            'titulo' => 'Caçador de Rank C',
            'descricao' => 'Derrote 50 monstros Rank C ou superior',
            'categoria' => 'combate',
            'tipo_objetivo' => 'monstros_derrotados', 
            'objetivo' => 50,
            'recompensa_ouro' => 2000,
            'recompensa_xp' => 5000,
            'icone' => '⚔️',
            'raridade' => 'raro'
        ],

        // PROGRESSO
        [
            'titulo' => 'Iniciante',
            'descricao' => 'Alcance o nível 10',
            'categoria' => 'progresso',
            'tipo_objetivo' => 'nivel_personagem',
            'objetivo' => 10,
            'recompensa_ouro' => 1000,
            'recompensa_xp' => 2000,
            'icone' => '⭐',
            'raridade' => 'comum'
        ],
        [
            'titulo' => 'Mestre do Nexus',
            'descricao' => 'Alcance o nível 50',
            'categoria' => 'progresso',
            'tipo_objetivo' => 'nivel_personagem',
            'objetivo' => 50,
            'recompensa_ouro' => 10000,
            'recompensa_xp' => 25000,
            'icone' => '👑',
            'raridade' => 'lendario'
        ],

        // ECOS
        [
            'titulo' => 'Colecionador de Almas',
            'descricao' => 'Recrute 5 Ecos diferentes',
            'categoria' => 'ecos',
            'tipo_objetivo' => 'ecos_recrutados',
            'objetivo' => 5,
            'recompensa_ouro' => 1500,
            'recompensa_xp' => 3000,
            'icone' => '👻',
            'raridade' => 'raro'
        ],
        [
            'titulo' => 'Lazarento de Ouro',
            'descricao' => 'Acumule 10.000 de ouro',
            'categoria' => 'colecionavel',
            'tipo_objetivo' => 'ouro_coletado',
            'objetivo' => 10000,
            'recompensa_ouro' => 5000,
            'recompensa_xp' => 8000,
            'icone' => '💰',
            'raridade' => 'epico'
        ],

        // MISSÕES
        [
            'titulo' => 'Tarefeiro',
            'descricao' => 'Complete 25 missões diárias',
            'categoria' => 'progresso',
            'tipo_objetivo' => 'missoes_completas',
            'objetivo' => 25,
            'recompensa_ouro' => 3000,
            'recompensa_xp' => 6000,
            'icone' => '✅',
            'raridade' => 'raro'
        ],

        // HABILIDADES
        [
            'titulo' => 'Aprendiz Arcano',
            'descricao' => 'Aprenda 10 habilidades diferentes',
            'categoria' => 'progresso',
            'tipo_objetivo' => 'habilidades_aprendidas',
            'objetivo' => 10,
            'recompensa_ouro' => 2000,
            'recompensa_xp' => 4000,
            'icone' => '📚',
            'raridade' => 'raro'
        ]
    ];

    foreach ($conquistas_base as $achievement) {
        $sql_check = "SELECT id FROM achievements_base WHERE titulo = ?";
        $stmt = $conexao->prepare($sql_check);
        $stmt->bind_param("s", $achievement['titulo']);
        $stmt->execute();
        
        if ($stmt->get_result()->num_rows == 0) {
            $sql = "INSERT INTO achievements_base (titulo, descricao, categoria, tipo_objetivo, objetivo, recompensa_ouro, recompensa_xp, icone, raridade) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)";
            $stmt = $conexao->prepare($sql);
            $stmt->bind_param("ssssiiiss", 
                $achievement['titulo'], $achievement['descricao'], $achievement['categoria'],
                $achievement['tipo_objetivo'], $achievement['objetivo'], $achievement['recompensa_ouro'],
                $achievement['recompensa_xp'], $achievement['icone'], $achievement['raridade']
            );
            $stmt->execute();
        }
    }
}

function atualizar_progresso_achievement($player_id, $tipo_objetivo, $quantidade, $conexao) {
    // Busca achievements do tipo que ainda não foram completados
    $sql = "SELECT ab.*, pa.progresso_atual, pa.desbloqueada 
            FROM achievements_base ab
            LEFT JOIN player_achievements pa ON ab.id = pa.achievement_id AND pa.player_id = ?
            WHERE ab.tipo_objetivo = ? AND (pa.desbloqueada IS NULL OR pa.desbloqueada = FALSE)";
    
    $stmt = $conexao->prepare($sql);
    $stmt->bind_param("is", $player_id, $tipo_objetivo);
    $stmt->execute();
    $achievements = $stmt->get_result();

    while ($achievement = $achievements->fetch_assoc()) {
        $progresso_atual = $achievement['progresso_atual'] ?? 0;
        $novo_progresso = min($progresso_atual + $quantidade, $achievement['objetivo']);
        
        if (!$achievement['desbloqueada']) {
            // Insere ou atualiza o progresso
            if ($achievement['progresso_atual'] === null) {
                $sql_insert = "INSERT INTO player_achievements (player_id, achievement_id, progresso_atual) 
                               VALUES (?, ?, ?)";
                $stmt_insert = $conexao->prepare($sql_insert);
                $stmt_insert->bind_param("iii", $player_id, $achievement['id'], $novo_progresso);
                $stmt_insert->execute();
            } else {
                $sql_update = "UPDATE player_achievements SET progresso_atual = ? 
                               WHERE player_id = ? AND achievement_id = ?";
                $stmt_update = $conexao->prepare($sql_update);
                $stmt_update->bind_param("iii", $novo_progresso, $player_id, $achievement['id']);
                $stmt_update->execute();
            }
            
            // Verifica se completou
            if ($novo_progresso >= $achievement['objetivo']) {
                desbloquear_achievement($player_id, $achievement['id'], $conexao);
            }
        }
    }
}

function desbloquear_achievement($player_id, $achievement_id, $conexao) {
    // Busca dados da achievement
    $sql_achievement = "SELECT * FROM achievements_base WHERE id = ?";
    $stmt = $conexao->prepare($sql_achievement);
    $stmt->bind_param("i", $achievement_id);
    $stmt->execute();
    $achievement = $stmt->get_result()->fetch_assoc();

    if ($achievement) {
        // Dar recompensas
        $conexao->query("UPDATE personagens SET 
                        ouro = ouro + {$achievement['recompensa_ouro']}, 
                        xp_atual = xp_atual + {$achievement['recompensa_xp']} 
                        WHERE id = $player_id");

        // Marcar como desbloqueada
        $conexao->query("UPDATE player_achievements SET 
                        desbloqueada = TRUE, 
                        data_desbloqueio = NOW() 
                        WHERE player_id = $player_id AND achievement_id = $achievement_id");

        // Se não existia o registro, cria
        if ($conexao->affected_rows == 0) {
            $conexao->query("INSERT INTO player_achievements (player_id, achievement_id, progresso_atual, desbloqueada, data_desbloqueio) 
                            VALUES ($player_id, $achievement_id, {$achievement['objetivo']}, TRUE, NOW())");
        }

        return $achievement;
    }
    return false;
}

function get_achievements_jogador($player_id, $conexao) {
    $sql = "SELECT ab.*, 
                   COALESCE(pa.progresso_atual, 0) as progresso_atual,
                   COALESCE(pa.desbloqueada, FALSE) as desbloqueada,
                   pa.data_desbloqueio,
                   (COALESCE(pa.progresso_atual, 0) / ab.objetivo * 100) as progresso_percentual
            FROM achievements_base ab
            LEFT JOIN player_achievements pa ON ab.id = pa.achievement_id AND pa.player_id = ?
            ORDER BY pa.desbloqueada DESC, ab.categoria, ab.objetivo";
    
    $stmt = $conexao->prepare($sql);
    $stmt->bind_param("i", $player_id);
    $stmt->execute();
    
    return $stmt->get_result();
}
?>