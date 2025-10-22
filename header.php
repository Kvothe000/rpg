<?php
// header.php

// Incluir conexão
include_once 'db_connect.php'; // Alterado para include_once

// Incluir game_logic.php UMA VEZ
if (!function_exists('roll_d100')) {
    include_once 'game_logic.php'; // Alterado para include_once
}
// (NOVO LOCAL) Lógica de Logout Centralizada
if (isset($_GET['logout'])) {
    session_destroy(); // Destrói a sessão
    header('Location: login.php'); // Redireciona para o login
    exit; // Interrompe o script
}

// Variável para mensagens sutis de corrupção
$mensagem_corrupcao_sutil = "";

// Verifica se a sessão está ativa
if (isset($_SESSION['player_id'])) {
    $player_id = $_SESSION['player_id'];
    // ---> ATUALIZADO: Selecionar a coluna 'corrupcao' <---
    $sql_player = "SELECT *, corrupcao FROM personagens WHERE id = ?"; // Adiciona corrupcao
    $stmt_player = $conexao->prepare($sql_player);
    $stmt_player->bind_param("i", $player_id);
    $stmt_player->execute();
    $player_data = $stmt_player->get_result()->fetch_assoc();
    // -----------------------------------------------------

    if (!$player_data) {
        session_destroy();
        header('Location: login.php');
        exit;
    }
    
    // Calcula o recurso (Mana ou Fúria)
    $recurso_nome = ($player_data['classe_base'] === 'Mago' || $player_data['classe_base'] === 'Sacerdote') ? 'Mana' : 'Fúria';
    
    // Carrega bônus de equipamentos
    $equip_bonus = carregar_stats_equipados($player_id, $conexao);
    
    // Calcula os atributos totais (base + equipamento)
    $stats_totais = [
        'str' => $player_data['str'] + $equip_bonus['bonus_str'],
        'dex' => $player_data['dex'] + $equip_bonus['bonus_dex'],
        'con' => $player_data['con'] + $equip_bonus['bonus_con'],
        'int_stat' => $player_data['int_stat'] + $equip_bonus['bonus_int'],
        'wis' => $player_data['wis'] + $equip_bonus['bonus_wis'],
        'cha' => $player_data['cha'] + $equip_bonus['bonus_cha']
    ];
    
    // Calcula o limite de carga
    $limite_carga = calcular_limite_carga($player_data['str'], $player_id, $conexao);
}
// ---> NOVO: LÓGICA PARA EFEITO SUTIL DE CORRUPÇÃO <---
    $nivel_corrupcao = $player_data['corrupcao'] ?? 0;
    if ($nivel_corrupcao > 3) { // Exemplo: Efeitos começam a partir de Corrupção 4
        $chance_efeito = min(5 + ($nivel_corrupcao * 2), 50); // Aumenta a chance com a corrupção, max 50%
        if (mt_rand(1, 100) <= $chance_efeito) {
            $mensagens_sutis = [
                "Você ouve um sussurro que some rapidamente...",
                "Sua visão escurece por um breve instante.",
                "Uma imagem fugaz de Elara cruza sua mente.",
                "Um arrepio percorre sua espinha sem motivo aparente.",
                "[Sistema?]: ...erro... fragmento instável...",
                "A interface pisca em vermelho por um momento."
            ];
            $mensagem_corrupcao_sutil = $mensagens_sutis[array_rand($mensagens_sutis)];
        }
    }
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo isset($titulo_pagina) ? $titulo_pagina : 'RPG MUD - Arcana Duality'; ?></title>
    <link rel="stylesheet" href="style.css">
    <style>
        .corrupcao-sutil-msg {
            position: fixed; /* Ou absolute, dependendo do layout */
            bottom: 10px;
            left: 10px;
            background-color: rgba(138, 43, 226, 0.6); /* Fundo roxo translúcido */
            color: #FF00FF; /* Cor magenta */
            padding: 8px 12px;
            border-radius: 4px;
            font-size: 0.85em;
            font-style: italic;
            z-index: 1001; /* Acima de outros elementos */
            opacity: 0;
            animation: fadeInOut 5s ease-in-out;
        }

        @keyframes fadeInOut {
            0% { opacity: 0; }
            20% { opacity: 1; }
            80% { opacity: 1; }
            100% { opacity: 0; }
        }
    </style>
    </head>
    <body>
    <header>
        <?php if (isset($player_data)): ?>
        <div class="status-bar">
             <div class="status-block">
                 <div class="resource-bar">
                     <span class="resource-label" style="color: var(--accent-arcane-glow);">Corrupção</span>
                     <span style="color: var(--accent-arcane-glow); font-weight: bold;">
                         <?php echo $player_data['corrupcao'] ?? 0; ?>
                     </span>
                 </div>
             </div>
            </div>

        <?php endif; ?>
    </header>

        <?php if (!empty($mensagem_corrupcao_sutil)): ?>
            <div class="corrupcao-sutil-msg" id="corrupcaoMsg">
                <?php echo htmlspecialchars($mensagem_corrupcao_sutil); ?>
            </div>
            <script>
                // Remove a mensagem após a animação para não acumular divs
                setTimeout(() => {
                    const msgDiv = document.getElementById('corrupcaoMsg');
                    if (msgDiv) {
                        msgDiv.remove();
                    }
                }, 5000); // Tempo igual à duração da animação
            </script>
        <?php endif; ?>
<body>
    <header>
        <?php if (isset($player_data)): ?>
        <!-- Barra de Status -->
        <div class="status-bar">
            <div class="status-block">
                <strong><?php echo htmlspecialchars($player_data['nome']); ?></strong>
                <div>Nv: <?php echo $player_data['level']; ?> | <?php echo $player_data['classe_base']; ?></div>
            </div>
            
            <div class="status-block">
                <div class="resource-bar">
                    <span class="resource-label">HP:</span>
                    <span class="hp-value"><?php echo $player_data['hp_atual']; ?></span>/<?php echo $player_data['hp_max']; ?>
                </div>
                <div class="resource-bar">
                    <span class="resource-label"><?php echo $recurso_nome; ?>:</span>
                    <span class="mana-value"><?php echo $player_data['mana_atual']; ?></span>/<?php echo $player_data['mana_max']; ?>
                </div>
            </div>
            
            <div class="status-block">
                <div class="resource-bar">
                    <span class="resource-label">Ouro:</span>
                    <span class="gold-value"><?php echo $player_data['ouro']; ?></span>
                </div>
                <div class="resource-bar">
                    <span class="resource-label">Rank:</span>
                    <span class="rank-value"><?php echo $player_data['fama_rank']; ?></span>
                </div>
            </div>
            
            <div class="status-block">
                <div class="resource-bar">
                    <span class="resource-label">Carga:</span>
                    <span style="color: <?php echo ($limite_carga['peso_atual'] > $limite_carga['max_carga']) ? 'var(--status-hp)' : 'var(--text-primary)'; ?>">
                        <?php echo number_format($limite_carga['peso_atual'], 1); ?>kg
                    </span>/<?php echo $limite_carga['max_carga']; ?>kg
                </div>
                <div class="resource-bar">
                    <span class="resource-label">XP:</span>
                    <span class="xp-value"><?php echo $player_data['xp_atual']; ?></span>/<?php echo $player_data['xp_proximo_level']; ?>
                </div>
            </div>
        </div>

        <!-- Navegação por Abas -->
        <nav class="nav-tabs">
            <a href="cidade.php" class="<?php echo (isset($pagina_atual) && $pagina_atual == 'cidade') ? 'active' : ''; ?>">
                🏠 Nexus
            </a>
            <a href="combate_portal.php" class="<?php echo (isset($pagina_atual) && $pagina_atual == 'combate') ? 'active' : ''; ?>">
                ⚔️ Combate
            </a>
            <a href="inventario.php" class="<?php echo (isset($pagina_atual) && $pagina_atual == 'inventario') ? 'active' : ''; ?>">
                🎒 Inventário
            </a>
            <a href="personagem.php" class="<?php echo (isset($pagina_atual) && $pagina_atual == 'personagem') ? 'active' : ''; ?>">
                👤 Personagem
            </a>
            <a href="ecos.php" class="<?php echo (isset($pagina_atual) && $pagina_atual == 'ecos') ? 'active' : ''; ?>">
                👻 Ecos
            </a>
            <a href="loja.php" class="<?php echo (isset($pagina_atual) && $pagina_atual == 'loja') ? 'active' : ''; ?>">
                🏪 Loja
            </a>
            <a href="mapa.php" class="<?php echo (isset($pagina_atual) && $pagina_atual == 'mapa') ? 'active' : ''; ?>">
                🗺️ Mapa
            </a>
            <a href="achievements.php" class="<?php echo (isset($pagina_atual) && $pagina_atual == 'achievements') ? 'active' : ''; ?>">
                🏆 Achievements
            </a>
            <a href="?logout=true" class="btn-vender">
                🚪 Sair
            </a>
        </nav>
        <?php endif; ?>
    </header>

    <main class="container">