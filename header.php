<?php
// header.php

// Incluir conexão
include 'db_connect.php';

// Incluir game_logic.php UMA VEZ
if (!function_exists('roll_d100')) {
    include 'game_logic.php';
}
// (NOVO LOCAL) Lógica de Logout Centralizada
if (isset($_GET['logout'])) {
    session_destroy(); // Destrói a sessão
    header('Location: login.php'); // Redireciona para o login
    exit; // Interrompe o script
}
// Verifica se a sessão está ativa
if (isset($_SESSION['player_id'])) {
    $player_id = $_SESSION['player_id'];
    $sql_player = "SELECT * FROM personagens WHERE id = $player_id";
    $player_data = $conexao->query($sql_player)->fetch_assoc();
    
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
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo isset($titulo_pagina) ? $titulo_pagina : 'RPG MUD - Arcana Duality'; ?></title>
    <link rel="stylesheet" href="style.css">
</head>
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