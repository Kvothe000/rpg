<?php
session_start();
include_once 'db_connect.php';
include_once 'achievements_functions.php';
include 'header.php';

if (!isset($_SESSION['player_id'])) {
    header('Location: login.php');
    exit;
}

$player_id = $_SESSION['player_id'];
$titulo_pagina = "Conquistas";

// Carrega achievements do jogador
$achievements = get_achievements_jogador($player_id, $conexao);

// Estatísticas
$sql_stats = "SELECT 
    COUNT(*) as total,
    SUM(desbloqueada) as desbloqueadas
    FROM player_achievements 
    WHERE player_id = $player_id";
$stats = $conexao->query($sql_stats)->fetch_assoc();
?>

<div class="container fade-in">
    <!-- CABEÇALHO -->
    <div class="section section-arcane text-center">
        <h1 style="color: var(--accent-arcane);">🏆 CONQUISTAS</h1>
        <p style="color: var(--text-secondary);">
            Suas jornadas, suas glórias, seu legado
        </p>
    </div>

    <!-- ESTATÍSTICAS -->
    <div class="section section-vital">
        <div class="achievements-stats">
            <div class="stat-card">
                <div class="stat-icon">🏆</div>
                <div class="stat-info">
                    <div class="stat-value"><?php echo $stats['desbloqueadas'] ?? 0; ?>/<?php echo $stats['total'] ?? 0; ?></div>
                    <div class="stat-label">Conquistas Desbloqueadas</div>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon">⭐</div>
                <div class="stat-info">
                    <div class="stat-value">
                        <?php
                        $sql_xp_total = "SELECT SUM(ab.recompensa_xp) as total 
                                        FROM player_achievements pa
                                        JOIN achievements_base ab ON pa.achievement_id = ab.id
                                        WHERE pa.player_id = $player_id AND pa.desbloqueada = TRUE";
                        echo $conexao->query($sql_xp_total)->fetch_assoc()['total'] ?? 0;
                        ?>
                    </div>
                    <div class="stat-label">XP Total em Conquistas</div>
                </div>
            </div>
        </div>
    </div>

    <!-- LISTA DE ACHIEVEMENTS -->
    <div class="section section-arcane">
        <h2 class="section-header">🎯 TODAS AS CONQUISTAS</h2>
        
        <div class="achievements-filters">
            <button class="filter-btn active" data-filter="all">Todas</button>
            <button class="filter-btn" data-filter="desbloqueada">Desbloqueadas</button>
            <button class="filter-btn" data-filter="progresso">Em Progresso</button>
        </div>

        <div class="achievements-grid">
            <?php if ($achievements->num_rows > 0): 
                $categoria_atual = '';
                while($ach = $achievements->fetch_assoc()):
                    if ($ach['categoria'] != $categoria_atual):
                        $categoria_atual = $ach['categoria'];
            ?>
            <div class="category-header">
                <h3><?php echo ucfirst($categoria_atual); ?></h3>
            </div>
            <?php endif; ?>

            <div class="achievement-card <?php echo $ach['desbloqueada'] ? 'unlocked' : 'locked'; ?> <?php echo $ach['raridade']; ?>" data-status="<?php echo $ach['desbloqueada'] ? 'desbloqueada' : 'progresso'; ?>">
                <div class="achievement-icon">
                    <?php echo $ach['icone']; ?>
                </div>
                
                <div class="achievement-content">
                    <div class="achievement-header">
                        <h4><?php echo $ach['titulo']; ?></h4>
                        <span class="rarity-badge <?php echo $ach['raridade']; ?>">
                            <?php echo strtoupper($ach['raridade']); ?>
                        </span>
                    </div>
                    
                    <p class="achievement-desc"><?php echo $ach['descricao']; ?></p>
                    
                    <div class="achievement-progress">
                        <div class="progress-info">
                            <span>Progresso:</span>
                            <span><?php echo $ach['progresso_atual']; ?>/<?php echo $ach['objetivo']; ?></span>
                        </div>
                        <div class="progress-bar">
                            <div class="progress-fill" style="width: <?php echo $ach['progresso_percentual']; ?>%"></div>
                        </div>
                    </div>
                </div>
                
                <div class="achievement-rewards">
                    <div class="reward-item">
                        <span class="reward-icon">💰</span>
                        <span class="reward-value"><?php echo $ach['recompensa_ouro']; ?></span>
                    </div>
                    <div class="reward-item">
                        <span class="reward-icon">⭐</span>
                        <span class="reward-value"><?php echo $ach['recompensa_xp']; ?></span>
                    </div>
                    
                    <?php if ($ach['desbloqueada']): ?>
                        <div class="unlocked-time">
                            Desbloqueada: <?php echo date('d/m/Y H:i', strtotime($ach['data_desbloqueio'])); ?>
                        </div>
                    <?php else: ?>
                        <div class="achievement-status">
                            🔒 Em Progresso
                        </div>
                    <?php endif; ?>
                </div>
            </div>
            <?php endwhile; ?>
            <?php else: ?>
            <div class="empty-state">
                <div class="empty-icon">🏆</div>
                <h3>Nenhuma Conquista</h3>
                <p>As conquistas aparecerão aqui conforme você progride no jogo!</p>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<style>
.achievements-stats {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
    gap: 20px;
    margin-bottom: 30px;
}

.stat-card {
    display: flex;
    align-items: center;
    gap: 20px;
    padding: 25px;
    background: var(--bg-primary);
    border: 2px solid var(--accent-arcane);
    border-radius: 12px;
}

.stat-icon {
    font-size: 3em;
    opacity: 0.8;
}

.stat-value {
    font-size: 2.5em;
    font-weight: bold;
    color: var(--accent-vital);
    margin-bottom: 5px;
}

.stat-label {
    color: var(--text-secondary);
    font-size: 0.9em;
}

.achievements-filters {
    display: flex;
    gap: 10px;
    margin-bottom: 25px;
    justify-content: center;
    flex-wrap: wrap;
}

.filter-btn {
    padding: 10px 20px;
    background: var(--bg-tertiary);
    border: 1px solid var(--bg-tertiary);
    border-radius: 20px;
    color: var(--text-secondary);
    cursor: pointer;
    transition: all 0.3s ease;
    font-weight: bold;
}

.filter-btn:hover,
.filter-btn.active {
    background: var(--accent-arcane);
    color: white;
    border-color: var(--accent-arcane);
}

.achievements-grid {
    display: flex;
    flex-direction: column;
    gap: 15px;
}

.category-header {
    margin: 30px 0 15px 0;
    padding-bottom: 10px;
    border-bottom: 2px solid var(--accent-arcane);
}

.category-header h3 {
    color: var(--accent-arcane);
    margin: 0;
    font-size: 1.4em;
}

.achievement-card {
    display: grid;
    grid-template-columns: 80px 1fr 200px;
    gap: 20px;
    padding: 20px;
    background: var(--bg-primary);
    border: 2px solid var(--bg-tertiary);
    border-radius: 12px;
    transition: all 0.3s ease;
}

.achievement-card:hover {
    transform: translateY(-3px);
    box-shadow: 0 5px 20px rgba(138, 43, 226, 0.1);
}

.achievement-card.unlocked {
    border-color: var(--accent-vital);
    background: linear-gradient(135deg, var(--bg-primary), rgba(76, 175, 80, 0.05));
}

.achievement-card.locked {
    opacity: 0.7;
}

/* Cores por raridade */
.achievement-card.comum { border-left: 4px solid #adb5bd; }
.achievement-card.raro { border-left: 4px solid #3a86ff; }
.achievement-card.epico { border-left: 4px solid #c13cff; }
.achievement-card.lendario { border-left: 4px solid #ff6b35; }

.achievement-icon {
    font-size: 3em;
    display: flex;
    align-items: center;
    justify-content: center;
}

.achievement-content {
    display: flex;
    flex-direction: column;
    gap: 10px;
}

.achievement-header {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    gap: 15px;
}

.achievement-header h4 {
    margin: 0;
    color: var(--text-primary);
    font-size: 1.2em;
}

.rarity-badge {
    padding: 4px 8px;
    border-radius: 12px;
    font-size: 0.7em;
    font-weight: bold;
    white-space: nowrap;
}

.rarity-badge.comum { background: #adb5bd; color: white; }
.rarity-badge.raro { background: #3a86ff; color: white; }
.rarity-badge.epico { background: #c13cff; color: white; }
.rarity-badge.lendario { background: #ff6b35; color: white; }

.achievement-desc {
    color: var(--text-secondary);
    margin: 0;
    line-height: 1.4;
}

.achievement-progress {
    background: var(--bg-secondary);
    padding: 12px;
    border-radius: 8px;
}

.progress-info {
    display: flex;
    justify-content: space-between;
    margin-bottom: 8px;
    font-size: 0.9em;
    color: var(--text-secondary);
}

.progress-bar {
    height: 8px;
    background: var(--bg-tertiary);
    border-radius: 4px;
    overflow: hidden;
}

.progress-fill {
    height: 100%;
    background: linear-gradient(90deg, var(--accent-vital), var(--accent-arcane));
    border-radius: 4px;
    transition: width 0.5s ease;
}

.achievement-rewards {
    display: flex;
    flex-direction: column;
    gap: 10px;
    border-left: 1px solid var(--bg-tertiary);
    padding-left: 20px;
}

.reward-item {
    display: flex;
    align-items: center;
    gap: 8px;
    padding: 8px 12px;
    background: var(--bg-secondary);
    border-radius: 6px;
}

.reward-icon {
    font-size: 1.1em;
}

.reward-value {
    font-weight: bold;
    color: var(--text-primary);
}

.unlocked-time {
    font-size: 0.8em;
    color: var(--text-secondary);
    text-align: center;
    padding: 8px;
    background: var(--bg-tertiary);
    border-radius: 6px;
}

.achievement-status {
    text-align: center;
    padding: 8px;
    background: var(--bg-tertiary);
    border-radius: 6px;
    font-size: 0.9em;
    color: var(--text-secondary);
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
    margin: 0;
}

@media (max-width: 968px) {
    .achievement-card {
        grid-template-columns: 60px 1fr;
    }
    
    .achievement-rewards {
        grid-column: 1 / -1;
        border-left: none;
        border-top: 1px solid var(--bg-tertiary);
        padding-left: 0;
        padding-top: 15px;
        flex-direction: row;
        justify-content: space-between;
    }
}

@media (max-width: 768px) {
    .achievements-stats {
        grid-template-columns: 1fr;
    }
    
    .stat-card {
        flex-direction: column;
        text-align: center;
        gap: 15px;
    }
    
    .achievement-header {
        flex-direction: column;
        align-items: flex-start;
        gap: 8px;
    }
    
    .achievement-rewards {
        flex-direction: column;
    }
}
</style>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Filtros
    const filterBtns = document.querySelectorAll('.filter-btn');
    const achievementCards = document.querySelectorAll('.achievement-card');
    
    filterBtns.forEach(btn => {
        btn.addEventListener('click', function() {
            const filter = this.getAttribute('data-filter');
            
            // Atualizar botões ativos
            filterBtns.forEach(b => b.classList.remove('active'));
            this.classList.add('active');
            
            // Filtrar cards
            achievementCards.forEach(card => {
                const status = card.getAttribute('data-status');
                
                if (filter === 'all' || filter === status) {
                    card.style.display = 'grid';
                } else {
                    card.style.display = 'none';
                }
            });
        });
    });
});
</script>

<?php include 'footer.php'; ?>