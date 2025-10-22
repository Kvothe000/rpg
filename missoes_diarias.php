<?php
// missoes_diarias.php
session_start();
include 'db_connect.php';
include 'daily_quests_functions.php';

if (!isset($_SESSION['player_id'])) {
    header('Location: login.php');
    exit;
}

$player_id = $_SESSION['player_id'];
$titulo_pagina = "Missões Diárias";

// Gerar missões diárias se necessário
gerar_missoes_diarias($player_id, $conexao);

// Carregar missões do jogador
$missoes_hoje = get_missoes_diarias_jogador($player_id, $conexao);

include 'header.php';
?>

<div class="container fade-in">
    <!-- CABEÇALHO -->
    <div class="section section-arcane text-center">
        <h1 style="color: var(--accent-arcane);">📅 MISSÕES DIÁRIAS</h1>
        <p style="color: var(--text-secondary);">
            Complete missões diárias para ganhar recompensas especiais
        </p>
        <div class="daily-reset-info">
            <span class="reset-timer">🕒 Próximo reset: <?php echo date('H:i', strtotime('tomorrow')); ?></span>
        </div>
    </div>

    <!-- MISSÕES DO DIA -->
    <div class="section section-vital">
        <h2 class="section-header vital">🎯 MISSÕES DE HOJE</h2>
        
        <?php if ($missoes_hoje && $missoes_hoje->num_rows > 0): 
            $total_missoes = 0;
            $completadas = 0;
            $missoes_hoje->data_seek(0); // Reset pointer
        ?>
        <div class="daily-quests-grid">
            <?php while($missao = $missoes_hoje->fetch_assoc()): 
                $total_missoes++;
                if ($missao['completada']) $completadas++;
            ?>
            <div class="daily-quest-card <?php echo $missao['completada'] ? 'completed' : 'active'; ?>">
                <div class="quest-status">
                    <?php if ($missao['completada']): ?>
                        <div class="status-badge completed">✅ COMPLETADA</div>
                    <?php else: ?>
                        <div class="status-badge active">🎯 EM ANDAMENTO</div>
                    <?php endif; ?>
                </div>
                
                <div class="quest-content">
                    <div class="quest-header">
                        <div class="quest-icon-large">
                            <?php 
                            $icones = [
                                'combate' => '⚔️',
                                'ecos' => '👻',
                                'economia' => '💰',
                                'progresso' => '📈'
                            ];
                            echo $icones[$missao['tipo']] ?? '🎯';
                            ?>
                        </div>
                        <div class="quest-title">
                            <h3><?php echo $missao['titulo']; ?></h3>
                            <span class="quest-type"><?php echo ucfirst($missao['tipo']); ?></span>
                        </div>
                    </div>
                    
                    <p class="quest-description"><?php echo $missao['descricao']; ?></p>
                    
                    <div class="quest-progress-detailed">
                        <div class="progress-info">
                            <span>Progresso:</span>
                            <span><?php echo $missao['progresso_atual']; ?>/<?php echo $missao['objetivo']; ?></span>
                        </div>
                        <div class="progress-bar-large">
                            <div class="progress-fill" style="width: <?php echo $missao['progresso_percentual']; ?>%"></div>
                        </div>
                    </div>
                </div>
                
                <div class="quest-rewards">
                    <h4>Recompensas</h4>
                    <div class="rewards-grid">
                        <div class="reward-item">
                            <span class="reward-icon">🪙</span>
                            <span class="reward-value"><?php echo $missao['recompensa_ouro']; ?></span>
                        </div>
                        <div class="reward-item">
                            <span class="reward-icon">⭐</span>
                            <span class="reward-value"><?php echo $missao['recompensa_xp']; ?></span>
                        </div>
                    </div>
                    
                    <?php if ($missao['completada']): ?>
                        <div class="quest-completed-time">
                            Completada: <?php echo date('H:i', strtotime($missao['data_completada'])); ?>
                        </div>
                    <?php else: ?>
                        <div class="quest-actions">
                            <?php 
                            $acoes = [
                                'matar_monstros' => ['text' => 'Ir para Combate', 'url' => 'combate_portal.php'],
                                'completar_missoes' => ['text' => 'Ver Ecos', 'url' => 'ecos.php'],
                                'gastar_ouro' => ['text' => 'Ver Loja', 'url' => 'cidade.php#loja'],
                                'evoluir_affinity' => ['text' => 'Ver Ecos', 'url' => 'ecos.php'],
                                'usar_habilidades' => ['text' => 'Ir para Combate', 'url' => 'combate_portal.php']
                            ];
                            $acao = $acoes[$missao['tipo_objetivo']] ?? ['text' => 'Explorar', 'url' => 'cidade.php'];
                            ?>
                            <a href="<?php echo $acao['url']; ?>" class="btn btn-primary">
                                <?php echo $acao['text']; ?>
                            </a>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
            <?php endwhile; ?>
        </div>
        
        <!-- RESUMO DO DIA -->
        <div class="daily-summary">
            <div class="summary-card">
                <h3>Resumo Diário</h3>
                <div class="summary-stats">
                    <div class="stat-item">
                        <span class="stat-label">Missões Completadas</span>
                        <span class="stat-value"><?php echo $completadas; ?>/<?php echo $total_missoes; ?></span>
                    </div>
                    <div class="stat-item">
                        <span class="stat-label">Ouro Ganho Hoje</span>
                        <span class="stat-value">
                            <?php
                            $sql_ouro_hoje = "SELECT SUM(dqb.recompensa_ouro) as total 
                                            FROM player_daily_quests pdq
                                            JOIN daily_quests_base dqb ON pdq.quest_id = dqb.id
                                            WHERE pdq.player_id = $player_id 
                                            AND pdq.data_ativacao = CURDATE()
                                            AND pdq.completada = TRUE";
                            $ouro_total = $conexao->query($sql_ouro_hoje)->fetch_assoc()['total'] ?? 0;
                            echo $ouro_total;
                            ?> 🪙
                        </span>
                    </div>
                    <div class="stat-item">
                        <span class="stat-label">XP Ganho Hoje</span>
                        <span class="stat-value">
                            <?php
                            $sql_xp_hoje = "SELECT SUM(dqb.recompensa_xp) as total 
                                          FROM player_daily_quests pdq
                                          JOIN daily_quests_base dqb ON pdq.quest_id = dqb.id
                                          WHERE pdq.player_id = $player_id 
                                          AND pdq.data_ativacao = CURDATE()
                                          AND pdq.completada = TRUE";
                            $xp_total = $conexao->query($sql_xp_hoje)->fetch_assoc()['total'] ?? 0;
                            echo $xp_total;
                            ?> ⭐
                        </span>
                    </div>
                </div>
            </div>
        </div>
        
        <?php else: ?>
        <div class="empty-state">
            <div class="empty-icon">📅</div>
            <h3>Nenhuma Missão Hoje</h3>
            <p>Suas missões diárias aparecerão aqui amanhã!</p>
            <p>Próximo reset: <strong><?php echo date('H:i', strtotime('tomorrow')); ?></strong></p>
        </div>
        <?php endif; ?>
    </div>

    <!-- MISSÕES DA SEMANA (FUTURO) -->
    <div class="section section-arcane">
        <h2 class="section-header">🗓️ MISSÕES DA SEMANA <small>(Em Breve)</small></h2>
        <div class="coming-soon">
            <p>Desafios semanais com recompensas épicas em breve!</p>
        </div>
    </div>
</div>

<style>
/* ESTILOS PARA MISSÕES DIÁRIAS */
.daily-quests-preview {
    display: flex;
    flex-direction: column;
    gap: 15px;
}

.quest-card {
    display: flex;
    align-items: center;
    gap: 15px;
    padding: 15px;
    background: var(--bg-primary);
    border: 1px solid var(--bg-tertiary);
    border-radius: 8px;
    transition: all 0.3s ease;
}

.quest-card:hover {
    border-color: var(--accent-arcane);
    transform: translateY(-2px);
}

.quest-card.completed {
    opacity: 0.7;
    background: var(--bg-secondary);
}

.quest-icon {
    font-size: 2em;
}

.quest-info {
    flex: 1;
}

.quest-info h4 {
    margin: 0 0 5px 0;
    color: var(--text-primary);
}

.quest-info p {
    margin: 0 0 10px 0;
    color: var(--text-secondary);
    font-size: 0.9em;
}

.quest-progress {
    display: flex;
    align-items: center;
    gap: 10px;
}

.progress-bar {
    flex: 1;
    height: 6px;
    background: var(--bg-tertiary);
    border-radius: 3px;
    overflow: hidden;
}

.progress-fill {
    height: 100%;
    background: linear-gradient(90deg, var(--accent-vital), var(--accent-arcane));
    border-radius: 3px;
    transition: width 0.5s ease;
}

.progress-text {
    font-size: 0.8em;
    color: var(--text-secondary);
    min-width: 60px;
    text-align: right;
}

.quest-reward {
    display: flex;
    flex-direction: column;
    gap: 5px;
    text-align: center;
}

.reward-gold, .reward-xp {
    font-size: 0.9em;
    font-weight: bold;
}

.reward-gold { color: var(--status-gold); }
.reward-xp { color: var(--accent-arcane); }

.quests-summary {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 15px;
    background: var(--bg-secondary);
    border-radius: 8px;
    margin-top: 20px;
}

.summary-item {
    display: flex;
    flex-direction: column;
    gap: 5px;
}

.summary-item span {
    color: var(--text-secondary);
    font-size: 0.9em;
}

.summary-item strong {
    color: var(--accent-vital);
    font-size: 1.2em;
}

.empty-quests {
    text-align: center;
    padding: 40px 20px;
    color: var(--text-secondary);
}

.empty-quests p {
    margin: 10px 0;
}

/* ESTILOS PÁGINA DEDICADA */
.daily-reset-info {
    margin-top: 10px;
}

.reset-timer {
    background: var(--bg-secondary);
    padding: 8px 15px;
    border-radius: 20px;
    font-size: 0.9em;
    color: var(--text-secondary);
}

.daily-quests-grid {
    display: flex;
    flex-direction: column;
    gap: 20px;
}

.daily-quest-card {
    display: grid;
    grid-template-columns: 1fr 300px;
    gap: 20px;
    padding: 20px;
    background: var(--bg-primary);
    border: 2px solid var(--bg-tertiary);
    border-radius: 12px;
    transition: all 0.3s ease;
}

.daily-quest-card:hover {
    border-color: var(--accent-arcane);
    transform: translateY(-3px);
    box-shadow: 0 5px 20px rgba(138, 43, 226, 0.1);
}

.daily-quest-card.completed {
    border-color: var(--accent-vital);
    background: linear-gradient(135deg, var(--bg-primary), rgba(76, 175, 80, 0.05));
}

.quest-status {
    grid-column: 1 / -1;
    margin-bottom: 15px;
}

.status-badge {
    display: inline-block;
    padding: 6px 12px;
    border-radius: 20px;
    font-size: 0.8em;
    font-weight: bold;
}

.status-badge.completed {
    background: var(--accent-vital);
    color: white;
}

.status-badge.active {
    background: var(--accent-arcane);
    color: white;
}

.quest-content {
    display: flex;
    flex-direction: column;
    gap: 15px;
}

.quest-header {
    display: flex;
    align-items: center;
    gap: 15px;
}

.quest-icon-large {
    font-size: 3em;
}

.quest-title h3 {
    margin: 0 0 5px 0;
    color: var(--text-primary);
}

.quest-type {
    background: var(--bg-tertiary);
    color: var(--text-secondary);
    padding: 3px 8px;
    border-radius: 12px;
    font-size: 0.8em;
    font-weight: bold;
}

.quest-description {
    color: var(--text-secondary);
    line-height: 1.5;
    margin: 0;
}

.quest-progress-detailed {
    background: var(--bg-secondary);
    padding: 15px;
    border-radius: 8px;
}

.progress-info {
    display: flex;
    justify-content: space-between;
    margin-bottom: 8px;
    font-size: 0.9em;
    color: var(--text-secondary);
}

.progress-bar-large {
    height: 10px;
    background: var(--bg-tertiary);
    border-radius: 5px;
    overflow: hidden;
}

.quest-rewards {
    border-left: 1px solid var(--bg-tertiary);
    padding-left: 20px;
    display: flex;
    flex-direction: column;
    gap: 15px;
}

.quest-rewards h4 {
    margin: 0;
    color: var(--text-primary);
    font-size: 1.1em;
}

.rewards-grid {
    display: flex;
    flex-direction: column;
    gap: 10px;
}

.reward-item {
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 8px 12px;
    background: var(--bg-secondary);
    border-radius: 6px;
}

.reward-icon {
    font-size: 1.2em;
}

.reward-value {
    font-weight: bold;
    color: var(--text-primary);
}

.quest-completed-time {
    font-size: 0.8em;
    color: var(--text-secondary);
    text-align: center;
    padding: 10px;
    background: var(--bg-tertiary);
    border-radius: 6px;
}

.quest-actions {
    margin-top: auto;
}

.daily-summary {
    margin-top: 30px;
}

.summary-card {
    background: var(--bg-primary);
    border: 2px solid var(--accent-arcane);
    border-radius: 12px;
    padding: 25px;
}

.summary-card h3 {
    margin: 0 0 20px 0;
    color: var(--accent-arcane);
    text-align: center;
}

.summary-stats {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 20px;
}

.stat-item {
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 8px;
    padding: 15px;
    background: var(--bg-secondary);
    border-radius: 8px;
}

.stat-label {
    color: var(--text-secondary);
    font-size: 0.9em;
}

.stat-value {
    font-weight: bold;
    color: var(--accent-vital);
    font-size: 1.2em;
}

.coming-soon {
    text-align: center;
    padding: 40px 20px;
    color: var(--text-secondary);
    background: var(--bg-secondary);
    border-radius: 8px;
}

.empty-state {
    text-align: center;
    padding: 60px 20px;
}

.empty-icon {
    font-size: 4em;
    margin-bottom: 20px;
    opacity: 0.5;
}

.empty-state h3 {
    color: var(--text-secondary);
    margin-bottom: 10px;
}

.empty-state p {
    color: var(--text-secondary);
    margin: 5px 0;
}

/* RESPONSIVIDADE */
@media (max-width: 968px) {
    .daily-quest-card {
        grid-template-columns: 1fr;
    }
    
    .quest-rewards {
        border-left: none;
        border-top: 1px solid var(--bg-tertiary);
        padding-left: 0;
        padding-top: 20px;
    }
    
    .quests-summary {
        flex-direction: column;
        gap: 15px;
        text-align: center;
    }
    
    .summary-stats {
        grid-template-columns: 1fr;
    }
}

@media (max-width: 768px) {
    .quest-card {
        flex-direction: column;
        text-align: center;
    }
    
    .quest-progress {
        flex-direction: column;
        gap: 5px;
    }
    
    .progress-text {
        text-align: center;
    }
    
    .quest-header {
        flex-direction: column;
        text-align: center;
    }
}
</style>

<?php include 'footer.php'; ?>