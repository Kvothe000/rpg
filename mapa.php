<?php
// mapa.php - CORREÇÕES CRÍTICAS
session_start();
include_once 'db_connect.php';

// ✅ VERIFICAÇÃO DE SEGURANÇA REFORÇADA
if (!isset($_SESSION['player_id'])) {
    header('Location: login.php');
    exit;
}

$player_id = (int)$_SESSION['player_id'];

// ✅ CARREGAR DADOS COM VERIFICAÇÃO
$sql_player = "SELECT nome, level, classe_base, fama_rank, ouro, hp_atual, hp_max, mana_atual, mana_max, xp_atual, xp_proximo_level FROM personagens WHERE id = ?";
$stmt = $conexao->prepare($sql_player);
$stmt->bind_param("i", $player_id);
$stmt->execute();
$result = $stmt->get_result();
$player_data = $result->fetch_assoc();

if (!$player_data) {
    session_destroy();
    header('Location: login.php');
    exit;
}

// ✅ DEBUG: VERIFICAR DADOS (REMOVER DEPOIS)
echo "<!-- DEBUG: ";
print_r($player_data);
echo " -->";

$titulo_pagina = "Mapa Dimensional - Nexus";
$pagina_atual = 'mapa';

// ✅ CARREGAR DUNGEON SYSTEM APÓS VERIFICAR DADOS
include_once 'dungeon_system.php';
$dungeonSystem = new DungeonSystem($conexao);

// ✅ VERIFICAR SE O PLAYER TEM LEVEL VÁLIDO
if ($player_data['level'] > 1000) { // Level suspeito
    // CORRIGIR LEVEL - isso é um bug grave!
    $conexao->query("UPDATE personagens SET level = 1 WHERE id = $player_id");
    $player_data['level'] = 1;
    error_log("BUG: Player $player_id tinha level {$player_data['level']} - Corrigido para 1");
}

// DUNGEONS DINÂMICAS (APENAS SE DADOS ESTIVEREM OK)
try {
    $dungeons_dinamicas = [
        'evento_especial' => $dungeonSystem->gerar_dungeon_dinamica($player_id, 'medio'),
        'desafio_diario' => $dungeonSystem->gerar_dungeon_dinamica($player_id, 'dificil')
    ];
} catch (Exception $e) {
    // Fallback se der erro
    $dungeons_dinamicas = [
        'evento_especial' => [
            'nome' => 'Caverna de Teste',
            'dificuldade' => 'medio',
            'nivel_recomendado' => $player_data['level'],
            'modificadores' => ['efeito' => 'normal', 'dados_efeito' => ['descricao' => 'Dungeon padrão']]
        ]
    ];
    error_log("Erro ao gerar dungeon: " . $e->getMessage());
}

// REGIÕES DO MAPA CORRIGIDAS
$regioes = [
    'floresta_corrompida' => [
        'nome' => 'Floresta Corrompida',
        'nivel' => '1-10',
        'status' => $player_data['level'] >= 1 ? 'descoberta' : 'oculta',
        'descricao' => 'Uma floresta onde a Fenda Arcana deixou suas marcas.',
        'portais' => ['E', 'D'],
        'cor' => '#2E8B57',
        'coordenadas' => 'x: 120, y: 80'
    ],
    'montanhas_gélidas' => [
        'nome' => 'Montanhas Gélidas', 
        'nivel' => '10-25',
        'status' => $player_data['level'] >= 10 ? 'descoberta' : 'oculta',
        'descricao' => 'Picos congelados onde criaturas antigas habitam.',
        'portais' => ['C', 'B'],
        'cor' => '#4682B4',
        'coordenadas' => 'x: 300, y: 40'
    ],
    'abismo_eterno' => [
        'nome' => 'Abismo Eterno',
        'nivel' => '25-50', 
        'status' => $player_data['level'] >= 25 ? 'descoberta' : 'oculta',
        'descricao' => 'Onde a própria realidade se desfaz em paradoxos.',
        'portais' => ['A', 'S'],
        'cor' => '#4B0082',
        'coordenadas' => 'x: 200, y: 200'
    ]
];

include 'header.php';
?>

<div class="container fade-in">
    <!-- CABEÇALHO -->
    <div class="section section-arcane text-center">
        <h1 style="color: var(--accent-arcane); text-shadow: 0 0 20px var(--accent-arcane-glow);">
            🗺️ MAPA DIMENSIONAL - NEXUS
        </h1>
        <p style="color: var(--text-secondary);">
            Explore dimensões instáveis e descubra segredos cósmicos
        </p>
    </div>

    <!-- RESUMO DO PERSONAGEM CORRIGIDO -->
    <div class="grid-2-col">
        <div class="section section-vital">
            <h2 class="section-header vital">👤 STATUS DO CAÇADOR</h2>
            <div class="character-summary">
                <div class="char-info-grid">
                    <div class="char-info-item">
                        <div class="char-label">Nome</div>
                        <div class="char-value"><?php echo htmlspecialchars($player_data['nome'] ?? 'Desconhecido'); ?></div>
                    </div>
                    <div class="char-info-item">
                        <div class="char-label">Nível</div>
                        <div class="char-value" id="player-level"><?php echo (int)($player_data['level'] ?? 1); ?></div>
                    </div>
                    <div class="char-info-item">
                        <div class="char-label">Classe</div>
                        <div class="char-value"><?php echo htmlspecialchars($player_data['classe_base'] ?? 'Aventureiro'); ?></div>
                    </div>
                    <div class="char-info-item">
                        <div class="char-label">Rank</div>
                        <div class="char-value"><?php echo htmlspecialchars($player_data['fama_rank'] ?? 'Iniciante'); ?></div>
                    </div>
                </div>
                
                <div class="progress-item">
                    <div class="progress-label">
                        <span>Progresso para o próximo nível</span>
                        <span><?php echo (int)($player_data['xp_atual'] ?? 0); ?>/<?php echo (int)($player_data['xp_proximo_level'] ?? 100); ?></span>
                    </div>
                    <div class="progress-bar">
                        <div class="progress-fill xp-fill" style="width: <?php echo min(100, (($player_data['xp_atual'] ?? 0) / max(1, ($player_data['xp_proximo_level'] ?? 100))) * 100); ?>%"></div>
                    </div>
                </div>
            </div>
        </div>

        <!-- ESTATÍSTICAS RÁPIDAS -->
        <div class="section section-arcane">
            <h2 class="section-header">📊 ESTATÍSTICAS</h2>
            <div class="stat-grid-small">
                <div class="stat-card-small">
                    <div class="stat-icon">💰</div>
                    <div class="stat-content">
                        <div class="stat-value gold-value"><?php echo number_format($player_data['ouro'] ?? 0); ?></div>
                        <div class="stat-label">Ouro</div>
                    </div>
                </div>
                
                <div class="stat-card-small">
                    <div class="stat-icon">⚔️</div>
                    <div class="stat-content">
                        <div class="stat-value"><?php echo count($regioes); ?></div>
                        <div class="stat-label">Regiões</div>
                    </div>
                </div>
                
                <div class="stat-card-small">
                    <div class="stat-icon">❤️</div>
                    <div class="stat-content">
                        <div class="stat-value"><?php echo (int)($player_data['hp_atual'] ?? 0); ?>/<?php echo (int)($player_data['hp_max'] ?? 100); ?></div>
                        <div class="stat-label">Vida</div>
                    </div>
                </div>
                
                <div class="stat-card-small">
                    <div class="stat-icon">🔷</div>
                    <div class="stat-content">
                        <div class="stat-value"><?php echo (int)($player_data['mana_atual'] ?? 0); ?>/<?php echo (int)($player_data['mana_max'] ?? 50); ?></div>
                        <div class="stat-label">
                            <?php echo (($player_data['classe_base'] ?? '') === 'Mago' || ($player_data['classe_base'] ?? '') === 'Sacerdote') ? 'Mana' : 'Fúria'; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- MAPA INTERATIVO SIMPLIFICADO -->
    <div class="section section-vital">
        <h2 class="section-header vital">🌍 MAPA INTERATIVO</h2>
        <div class="world-map-container">
            <div class="world-map">
                <?php foreach ($regioes as $id => $regiao): ?>
                <div class="map-region <?php echo $regiao['status']; ?>" 
                     style="border-color: <?php echo $regiao['cor']; ?>; left: <?php echo explode(': ', $regiao['coordenadas'])[1] ?? '100px'; ?>; top: <?php echo explode(', y: ', $regiao['coordenadas'])[1] ?? '100px'; ?>;"
                     data-region="<?php echo $id; ?>"
                     data-tooltip="<?php echo $regiao['nome']; ?> - Nv. <?php echo $regiao['nivel']; ?>">
                    
                    <div class="region-icon">📍</div>
                    <div class="region-name"><?php echo htmlspecialchars($regiao['nome']); ?></div>
                    <div class="region-level">Nv. <?php echo $regiao['nivel']; ?></div>
                    
                    <?php if ($regiao['status'] == 'descoberta'): ?>
                    <div class="region-portals">
                        <?php foreach ($regiao['portais'] as $portal): ?>
                            <span class="portal-marker">🌀</span>
                        <?php endforeach; ?>
                    </div>
                    <?php endif; ?>
                </div>
                <?php endforeach; ?>
                
                <!-- PLAYER POSITION -->
                <div class="player-position" style="left: 150px; top: 100px;">
                    <div class="player-marker">⚡</div>
                    <div class="player-name"><?php echo htmlspecialchars($player_data['nome'] ?? 'Jogador'); ?></div>
                </div>
            </div>
        </div>
    </div>

    <!-- PORTAIS FIXOS (SEU SISTEMA ORIGINAL) -->
    <div class="section section-vital">
        <h2 class="section-header vital">🌌 PORTAL DIMENSIONAIS</h2>
        <p style="color: var(--text-secondary); margin-bottom: 20px;">
            Escolha qual portal você deseja explorar. Cuidado com as dimensões instáveis.
        </p>

        <?php
        // SEU CÓDIGO ORIGINAL DE PORTAIS AQUI
        $portais = [
            'E' => [
                'nome' => 'Campo dos Slimes',
                'tipo' => 'Floresta',
                'nivel_minimo' => 1,
                'descricao' => 'Monstros fracos, loot comum. Ideal para iniciantes.',
                'ouro_base' => 50,
                'xp_base' => 25,
                'chance_item' => 30,
                'dificuldade' => 'facil',
                'dificuldade_label' => '⭐ Fácil',
                'dificuldade_cor' => '#50C878'
            ],
            // ... outros portais
        ];
        ?>
        
        <div class="portals-grid">
            <?php foreach ($portais as $rank => $portal): 
                $nivel_jogador = (int)($player_data['level'] ?? 1);
                $disponivel = $nivel_jogador >= $portal['nivel_minimo'];
                $status_class = $disponivel ? 'disponivel' : 'bloqueado';
            ?>
            <div class="portal-card <?php echo $portal['dificuldade']; ?> <?php echo $status_class; ?>">
                <!-- SEU CÓDIGO ORIGINAL DE CARDS DE PORTAL -->
                <div class="portal-header" style="border-left-color: <?php echo $portal['dificuldade_cor']; ?>">
                    <div class="portal-icon">
                        <?php 
                        $icons = [
                            'Floresta' => '🌲', 'Acampamento' => '🏕️', 'Ruínas' => '🏛️',
                            'Caverna' => '🕳️', 'Abismo' => '🌑', 'Cidade' => '🏙️'
                        ];
                        echo $icons[$portal['tipo']] ?? '🌀';
                        ?>
                    </div>
                    <div class="portal-title">
                        <h3>Rank <?php echo $rank; ?>: <?php echo htmlspecialchars($portal['nome']); ?></h3>
                        <span class="portal-type"><?php echo $portal['tipo']; ?></span>
                    </div>
                    <div class="portal-level" style="background: <?php echo $portal['dificuldade_cor']; ?>">
                        Nv. <?php echo $portal['nivel_minimo']; ?>+
                    </div>
                </div>
                
                <div class="portal-content">
                    <div class="portal-description">
                        <?php echo htmlspecialchars($portal['descricao']); ?>
                    </div>
                    
                    <div class="portal-rewards">
                        <div class="reward-item">
                            <span class="reward-icon">💰</span>
                            <span class="reward-text">Ouro: <?php echo number_format($portal['ouro_base']); ?>+</span>
                        </div>
                        <div class="reward-item">
                            <span class="reward-icon">🎒</span>
                            <span class="reward-text">Itens: <?php echo $portal['chance_item']; ?>%</span>
                        </div>
                        <div class="reward-item">
                            <span class="reward-icon">⚡</span>
                            <span class="reward-text">XP: <?php echo number_format($portal['xp_base']); ?></span>
                        </div>
                    </div>
                </div>
                
                <div class="portal-footer">
                    <div class="portal-difficulty" style="color: <?php echo $portal['dificuldade_cor']; ?>">
                        <?php echo $portal['dificuldade_label']; ?>
                    </div>
                    
                    <div class="portal-actions">
                        <?php if ($disponivel): ?>
                            <a href="combate_portal.php?rank=<?php echo $rank; ?>" class="btn btn-primary">
                                🚀 Explorar Portal
                            </a>
                        <?php else: ?>
                            <span class="btn btn-disabled" title="Nível necessário: <?php echo $portal['nivel_minimo']; ?>">
                                🔒 Nível <?php echo $portal['nivel_minimo']; ?>+
                            </span>
                        <?php endif; ?>
                    </div>
                </div>
                
                <?php if (!$disponivel): ?>
                <div class="portal-lock-overlay">
                    <div class="lock-icon">🔒</div>
                    <div class="lock-text">Nível <?php echo $portal['nivel_minimo']; ?>+ necessário</div>
                </div>
                <?php endif; ?>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
</div>

<style>
/* MAPA INTERATIVO */
.world-map-container {
    background: var(--bg-primary);
    border: 2px solid var(--accent-arcane);
    border-radius: 15px;
    padding: 30px;
    position: relative;
    min-height: 500px;
}

.world-map {
    position: relative;
    width: 100%;
    height: 400px;
    background: 
        radial-gradient(circle at 20% 30%, rgba(138, 43, 226, 0.1) 0%, transparent 50%),
        radial-gradient(circle at 80% 70%, rgba(80, 200, 120, 0.1) 0%, transparent 50%),
        linear-gradient(135deg, var(--bg-secondary) 0%, var(--bg-primary) 100%);
    border-radius: 10px;
    border: 1px solid var(--bg-tertiary);
}

.map-region {
    position: absolute;
    padding: 15px;
    border: 2px solid;
    border-radius: 10px;
    background: var(--bg-primary);
    box-shadow: 0 4px 15px rgba(0,0,0,0.1);
    transition: all 0.3s ease;
    min-width: 120px;
    text-align: center;
}

.map-region.descoberta {
    cursor: pointer;
    opacity: 1;
}

.map-region.descoberta:hover {
    transform: scale(1.05);
    box-shadow: 0 8px 25px rgba(138, 43, 226, 0.3);
}

.map-region.oculta {
    opacity: 0.3;
    filter: blur(2px);
    cursor: not-allowed;
}

.region-icon {
    font-size: 1.5em;
    margin-bottom: 5px;
}

.region-name {
    font-weight: bold;
    color: var(--text-primary);
    margin-bottom: 5px;
}

.region-level {
    color: var(--text-secondary);
    font-size: 0.8em;
    margin-bottom: 8px;
}

.region-portals {
    display: flex;
    justify-content: center;
    gap: 5px;
}

.portal-marker {
    font-size: 1.2em;
    animation: pulse 2s infinite;
}

.player-position {
    position: absolute;
    text-align: center;
    z-index: 10;
}

.player-marker {
    font-size: 2em;
    animation: float 3s ease-in-out infinite;
}

.player-name {
    margin-top: 5px;
    font-weight: bold;
    color: var(--accent-vital);
    font-size: 0.8em;
}

/* DUNGEONS DINÂMICAS */
.dynamic-dungeons-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(400px, 1fr));
    gap: 20px;
}

.dynamic-dungeon-card {
    background: var(--bg-primary);
    border: 2px solid var(--accent-arcane);
    border-radius: 12px;
    overflow: hidden;
    transition: all 0.3s ease;
}

.dynamic-dungeon-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 15px 30px rgba(138, 43, 226, 0.3);
}

.dungeon-header {
    padding: 20px;
    color: white;
    text-align: center;
    position: relative;
}

.dungeon-badge {
    position: absolute;
    top: 10px;
    right: 10px;
    background: rgba(255,255,255,0.2);
    padding: 5px 10px;
    border-radius: 15px;
    font-size: 0.8em;
    font-weight: bold;
}

.dungeon-header h3 {
    margin: 10px 0;
    font-size: 1.3em;
}

.dungeon-modifier {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 8px;
    font-size: 0.9em;
    opacity: 0.9;
}

.dungeon-content {
    padding: 20px;
}

.dungeon-stats {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 15px;
    margin-bottom: 20px;
}

.stat {
    text-align: center;
    padding: 10px;
    background: var(--bg-secondary);
    border-radius: 8px;
}

.stat-label {
    display: block;
    color: var(--text-secondary);
    font-size: 0.8em;
    margin-bottom: 5px;
}

.stat-value {
    display: block;
    font-weight: bold;
    color: var(--accent-vital);
}

.dungeon-modifiers ul {
    list-style: none;
    padding: 0;
    margin: 10px 0;
}

.dungeon-modifiers li {
    padding: 5px 0;
    color: var(--text-secondary);
}

.rewards-grid {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 10px;
    margin-top: 10px;
}

.reward {
    padding: 8px;
    background: var(--bg-secondary);
    border-radius: 6px;
    text-align: center;
    font-size: 0.9em;
}

.dungeon-actions {
    padding: 20px;
    background: var(--bg-secondary);
    text-align: center;
    border-top: 1px solid var(--bg-tertiary);
}

.btn-enter-dungeon {
    background: linear-gradient(135deg, var(--accent-arcane), var(--accent-vital));
    color: white;
    border: none;
    padding: 12px 24px;
    border-radius: 8px;
    font-weight: bold;
    cursor: pointer;
    transition: all 0.3s ease;
}

.btn-enter-dungeon:hover {
    transform: translateY(-2px);
    box-shadow: 0 8px 20px rgba(138, 43, 226, 0.4);
}

.timer {
    margin-top: 10px;
    color: var(--text-secondary);
    font-size: 0.9em;
}

/* ANIMAÇÕES */
@keyframes pulse {
    0%, 100% { opacity: 1; }
    50% { opacity: 0.5; }
}

@keyframes float {
    0%, 100% { transform: translateY(0); }
    50% { transform: translateY(-10px); }
}
</style>

<script>
// SISTEMA DE MAPA INTERATIVO
document.addEventListener('DOMContentLoaded', function() {
    // REGIÕES CLICÁVEIS
    document.querySelectorAll('.map-region.descoberta').forEach(region => {
        region.addEventListener('click', function() {
            const regionId = this.dataset.region;
            alert(`Explorando região: ${this.dataset.tooltip}`);
            // Aqui você pode implementar navegação para a região
        });
    });
    
    // TEMPORIZADOR DAS DUNGEONS
    document.querySelectorAll('.timer').forEach(timer => {
        const endTime = parseInt(timer.dataset.end);
        
        function updateTimer() {
            const now = Math.floor(Date.now() / 1000);
            const remaining = endTime - now;
            
            if (remaining <= 0) {
                timer.innerHTML = '⏰ Evento Expirado';
                return;
            }
            
            const minutes = Math.floor(remaining / 60);
            const seconds = remaining % 60;
            timer.querySelector('.time-remaining').textContent = 
                `${minutes.toString().padStart(2, '0')}:${seconds.toString().padStart(2, '0')}`;
        }
        
        updateTimer();
        setInterval(updateTimer, 1000);
    });
    
    // ENTRAR NA DUNGEON
    document.querySelectorAll('.btn-enter-dungeon').forEach(btn => {
        btn.addEventListener('click', function() {
            const dungeonData = JSON.parse(this.dataset.dungeon);
            if (confirm(`Ingressar na dungeon "${dungeonData.nome}"?`)) {
                // Redirecionar para sistema de combate com dados da dungeon
                window.location.href = `combate_dungeon.php?dungeon=${btoa(JSON.stringify(dungeonData))}`;
            }
        });
    });
});
</script>