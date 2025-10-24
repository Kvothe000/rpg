<?php
// templates/partials/header.php
// As variáveis $player_data, $stats_totais, $limite_carga, $pagina_atual
// já foram carregadas pelo bootstrap.php e disponibilizadas pelo render_template().
?>
<header class="game-header">
    <div class="status-bar">
        <div class="status-bar-group">
            <span class="status-icon vital">❤️</span>
            <div class="bar-container">
                <div class="bar-fill hp" style="width: <?php echo ($player_data['hp_atual'] / $player_data['hp_max']) * 100; ?>%;"></div>
                <span class="bar-text"><?php echo $player_data['hp_atual'] . " / " . $player_data['hp_max']; ?></span>
            </div>
        </div>

        <div class="status-bar-group">
            <span class="status-icon arcane">🔮</span>
            <div class="bar-container">
                <div class="bar-fill mana" style="width: <?php echo ($player_data['mana_atual'] / $player_data['mana_max']) * 100; ?>%;"></div>
                <span class="bar-text"><?php echo $player_data['mana_atual'] . " / " . $player_data['mana_max']; ?></span>
            </div>
        </div>

        <div class="status-bar-group">
            <span class="status-icon level">⭐</span>
            <div class="bar-container">
                <div class="bar-fill xp" style="width: <?php echo ($player_data['xp_atual'] / $player_data['xp_proximo_level']) * 100; ?>%;"></div>
                <span class="bar-text">Nível <?php echo $player_data['level']; ?></span>
            </div>
        </div>

        <div class="status-bar-group simple-text">
            <span class="status-icon gold">💰</span> <?php echo $player_data['ouro']; ?> Ouro
        </div>
        <div class="status-bar-group simple-text">
            <span class="status-icon load">🎒</span> <?php echo ($limite_carga['carga_atual'] ?? 0) . " / " . ($limite_carga['max_carga'] ?? 0); ?> Kg
        </div>

        <div class="status-bar-group logout">
            <a href="?logout=1" title="Deslogar">🚪</a>
        </div>
    </div>

    <nav class="game-nav">
        <a href="cidade.php" class="nav-tab <?php echo ($pagina_atual ?? '') == 'cidade' ? 'active' : ''; ?>">⚡ Nexus</a>
        <a href="personagem.php" class="nav-tab <?php echo ($pagina_atual ?? '') == 'personagem' ? 'active' : ''; ?>">👤 Personagem</a>
        <a href="inventario.php" class="nav-tab <?php echo ($pagina_atual ?? '') == 'inventario' ? 'active' : ''; ?>">🎒 Inventário</a>
        <a href="mapa.php" class="nav-tab <?php echo ($pagina_atual ?? '') == 'mapa' ? 'active' : ''; ?>">🧭 Mapa</a>
        <a href="ecos.php" class="nav-tab <?php echo ($pagina_atual ?? '') == 'ecos' ? 'active' : ''; ?>">👻 Ecos</a>
        <a href="missoes_diarias.php" class="nav-tab <?php echo ($pagina_atual ?? '') == 'missoes' ? 'active' : ''; ?>">📅 Missões</a>
        <a href="achievements.php" class="nav-tab <?php echo ($pagina_atual ?? '') == 'achievements' ? 'active' : ''; ?>">🏆 Conquistas</a>
    </nav>
</header>