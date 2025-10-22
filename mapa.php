<?php
// mapa.php - ATUALIZADO COM NOVO SISTEMA DE MAPA INTERATIVO
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

// (ATUALIZADO) Trazemos a definição dos portais para aqui
$portais = [
    'E' => [
        'nome' => 'Campo dos Slimes',
        'tipo' => 'Floresta',
        'nivel_minimo' => 1,
        'dificuldade' => '⭐ Fácil',
        'dificuldade_cor' => '#50C878'
    ],
    'D' => [
        'nome' => 'Acampamento Goblin',
        'tipo' => 'Acampamento',
        'nivel_minimo' => 5,
        'dificuldade' => '🔥 Médio',
        'dificuldade_cor' => '#FFD166'
    ],
    'C' => [
        'nome' => 'Ruínas Assombradas',
        'tipo' => 'Ruínas',
        'nivel_minimo' => 15,
        'dificuldade' => '💀 Difícil',
        'dificuldade_cor' => '#FF4444'
    ],
    'B' => [
        'nome' => 'Picos Congelados',
        'tipo' => 'Montanha',
        'nivel_minimo' => 25,
        'dificuldade' => '⚔️ Muito Difícil',
        'dificuldade_cor' => '#3A86FF'
    ],
    'A' => [
        'nome' => 'Coração do Abismo',
        'tipo' => 'Abismo',
        'nivel_minimo' => 40,
        'dificuldade' => '☠️ Lendário',
        'dificuldade_cor' => '#8A2BE2'
    ],
    'S' => [
        'nome' => 'Fenda Temporal',
        'tipo' => 'Paradoxo',
        'nivel_minimo' => 50,
        'dificuldade' => '🌌 Divino',
        'dificuldade_cor' => '#FF00FF'
    ]
];

// REGIÕES DO MAPA CORRIGIDAS (Coordenadas ajustadas)
$regioes = [
    'floresta_corrompida' => [
        'nome' => 'Floresta Corrompida',
        'nivel' => '1-10',
        'status' => $player_data['level'] >= 1 ? 'descoberta' : 'oculta',
        'portais' => ['E', 'D'],
        'cor' => '#2E8B57',
        'coordenadas' => 'x: 50, y: 50' // <-- MUDADO
    ],
    'montanhas_gélidas' => [ 
        'nome' => 'Montanhas Gélidas', 
        'nivel' => '10-25',
        'status' => $player_data['level'] >= 10 ? 'descoberta' : 'oculta',
        'portais' => ['C', 'B'],
        'cor' => '#4682B4',
        'coordenadas' => 'x: 400, y: 220' // <-- MUDADO (mais à direita)
    ],
    'abismo_eterno' => [
        'nome' => 'Abismo Eterno',
        'nivel' => '25-50', 
        'status' => $player_data['level'] >= 25 ? 'descoberta' : 'oculta',
        'portais' => ['A', 'S'],
        'cor' => '#4B0082',
        'coordenadas' => 'x: 50, y: 280' // <-- MUDADO (mais para baixo)
    ]
];

// (NOVO) Lógica de Flash Message (para caso de derrota)
$flash_message = "";
if (isset($_SESSION['flash_message'])) {
    $flash_message = $_SESSION['flash_message'];
    unset($_SESSION['flash_message']); // Limpa a mensagem
}

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

    <!-- MENSAGEM DE FEEDBACK -->
    <?php if ($flash_message): ?>
        <div class="feedback feedback-error" style="border-color: var(--status-hp); color: var(--status-hp);">
            <?php echo $flash_message; ?>
        </div>
    <?php endif; ?>

    

    <!-- MAPA INTERATIVO ATUALIZADO -->
    <div class="section section-vital">
        <h2 class="section-header vital">🌍 MAPA INTERATIVO</h2>
        <div class="world-map-container">
            <div class="world-map">
                <?php foreach ($regioes as $id => $regiao): ?>
                    <?php
                        // Extrair coordenadas de forma segura
                        $coords_str = $regiao['coordenadas'] ?? 'x: 100, y: 100';
                        $coords_parts = explode(', ', $coords_str); 
                        $left_val = '100'; 
                        $top_val = '100';
                        
                        if (count($coords_parts) === 2) {
                            $left_part = explode(': ', $coords_parts[0]); 
                            $top_part = explode(': ', $coords_parts[1]);
                            if (count($left_part) === 2) $left_val = trim($left_part[1]);
                            if (count($top_part) === 2) $top_val = trim($top_part[1]);
                        }
                        $left_css = $left_val . 'px'; 
                        $top_css = $top_val . 'px';
                    ?>
                    <div class="map-region <?php echo $regiao['status']; ?>"
                         style="border-color: <?php echo $regiao['cor']; ?>; left: <?php echo $left_css; ?>; top: <?php echo $top_css; ?>;"
                         data-region="<?php echo $id; ?>">
                        
                        <div class="region-header">
                            <span class="region-icon">📍</span>
                            <span class="region-name"><?php echo htmlspecialchars($regiao['nome']); ?></span>
                            <span class="region-level">(Nv. <?php echo $regiao['nivel']; ?>)</span>
                        </div>
                        
                        <?php if ($regiao['status'] == 'descoberta'): ?>
                            <div class="region-portals-list">
                                <?php foreach ($regiao['portais'] as $rank): // Loop pelos Ranks (E, D, C...) ?>
                                    <?php if (isset($portais[$rank])): // Verifica se o portal existe
                                        $portal = $portais[$rank];
                                        $disponivel = $player_data['level'] >= $portal['nivel_minimo'];
                                    ?>
                                        <div class="portal-link-item <?php echo $disponivel ? 'unlocked' : 'locked'; ?>">
                                            <span class="portal-difficulty" style="color: <?php echo $portal['dificuldade_cor']; ?>">
                                                <?php echo $portal['dificuldade']; ?>
                                            </span>
                                            <span class="portal-name"><?php echo $portal['nome']; ?> (Rank <?php echo $rank; ?>)</span>
                                            
                                            <?php if ($disponivel): ?>
                                                <a href="combate_portal.php?rank=<?php echo $rank; ?>" class="btn-enter-portal">ENTRAR</a>
                                            <?php else: ?>
                                                <span class="btn-enter-locked" title="Requer Nível <?php echo $portal['nivel_minimo']; ?>">Nv. <?php echo $portal['nivel_minimo']; ?>+</span>
                                            <?php endif; ?>
                                        </div>
                                    <?php endif; ?>
                                <?php endforeach; ?>
                            </div>
                        <?php else: // Região Oculta ?>
                            <div class="region-locked-overlay">
                                <span class="lock-icon">🔒</span>
                                <span class="lock-text">Requer Nível <?php echo explode('-', $regiao['nivel'])[0]; ?>+</span>
                            </div>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>

    <!-- PORTAIS FIXOS (SEÇÃO REMOVIDA - AGORA INTEGRADA NO MAPA) -->
</div>

<style>
/* MAPA INTERATIVO ATUALIZADO */
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
    box-shadow: 0 4px 15px rgba(0,0,0,0.3);
    transition: all 0.3s ease;
    min-width: 280px; /* Aumentado */
    z-index: 5;
}

.map-region.descoberta {
    opacity: 1;
    z-index: 10;
    cursor: default;
}

.map-region.descoberta:hover {
    transform: scale(1.03);
    box-shadow: 0 8px 30px rgba(0,0,0,0.5);
}

.map-region.oculta {
    opacity: 0.3;
    filter: blur(2px);
    cursor: not-allowed;
    z-index: 1;
}

.region-header {
    display: flex;
    align-items: center;
    gap: 8px;
    margin-bottom: 10px;
    padding-bottom: 8px;
    border-bottom: 1px solid var(--bg-tertiary);
}
.region-icon { font-size: 1.2em; }
.region-name { font-weight: bold; color: var(--text-primary); flex: 1; }
.region-level { color: var(--text-secondary); font-size: 0.8em; }

.region-portals-list {
    display: flex;
    flex-direction: column;
    gap: 10px;
}

.portal-link-item {
    display: grid;
    grid-template-columns: 90px 1fr 80px;
    align-items: center;
    gap: 10px;
    padding: 8px;
    background: var(--bg-secondary);
    border-radius: 6px;
}

.portal-link-item.locked {
    opacity: 0.6;
}

.portal-difficulty {
    font-size: 0.8em;
    font-weight: bold;
}

.portal-name {
    font-size: 0.9em;
    color: var(--text-primary);
}

.btn-enter-portal {
    background: var(--accent-vital);
    color: var(--bg-primary);
    padding: 5px 10px;
    border-radius: 4px;
    text-decoration: none;
    font-weight: bold;
    font-size: 0.8em;
    text-align: center;
    transition: all 0.2s ease;
}
.btn-enter-portal:hover {
    background: var(--accent-arcane);
    color: white;
    transform: scale(1.05);
}

.btn-enter-locked {
    background: var(--bg-tertiary);
    color: var(--text-secondary);
    padding: 5px 10px;
    border-radius: 4px;
    font-weight: bold;
    font-size: 0.8em;
    text-align: center;
    cursor: not-allowed;
}

.region-locked-overlay {
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    padding: 20px;
}
.lock-icon { font-size: 2em; }
.lock-text { color: var(--text-secondary); margin-top: 5px; }

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

/* FEEDBACK MESSAGES */
.feedback {
    padding: 15px;
    border-radius: 8px;
    margin: 15px 0;
    text-align: center;
    font-weight: bold;
    border: 2px solid;
}

.feedback-error {
    background: rgba(239, 71, 111, 0.1);
    border-color: var(--status-hp);
    color: var(--status-hp);
}

/* DUNGEONS DINÂMICAS (MANTIDO PARA COMPATIBILIDADE) */
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
// SISTEMA DE MAPA INTERATIVO ATUALIZADO
document.addEventListener('DOMContentLoaded', function() {
    // REGIÕES CLICÁVEIS (AGORA APENAS PARA FEEDBACK VISUAL)
    document.querySelectorAll('.map-region.descoberta').forEach(region => {
        region.addEventListener('click', function() {
            // Feedback visual apenas - a navegação agora é pelos botões específicos
            this.style.transform = 'scale(1.02)';
            setTimeout(() => {
                this.style.transform = 'scale(1)';
            }, 200);
        });
    });
    
    // TEMPORIZADOR DAS DUNGEONS (MANTIDO PARA COMPATIBILIDADE)
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
    
    // ENTRAR NA DUNGEON (MANTIDO PARA COMPATIBILIDADE)
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

<?php include 'footer.php'; ?>