<?php
session_start();
include_once 'db_connect.php'; // ✅ Use include_once
include_once 'game_logic.php'; // ✅ Use include_once  
include_once 'daily_quests_functions.php';
include_once 'dialogue_system_functions.php'; // ✅ NOVO: Sistema de diálogo

// Verifica login
if (!isset($_SESSION['player_id'])) {
    header('Location: login.php');
    exit;
}

$player_id = $_SESSION['player_id'];
$titulo_pagina = "NEXUS - Cidade Central";
$pagina_atual = 'cidade';

// Carrega dados do jogador
$sql_player = "SELECT * FROM personagens WHERE id = $player_id";
$player_data = $conexao->query($sql_player)->fetch_assoc();

// Sistema de recursos
$recurso_nome = ($player_data['classe_base'] === 'Mago' || $player_data['classe_base'] === 'Sacerdote') ? 'Mana' : 'Fúria'; 

// Transmissões globais (exemplo)
$transmissoes_globais = [
    ["hora" => "22:15", "tipo" => "CONSELHO", "mensagem" => "Taxa de drop de Fragmentos Raros no Portal D aumentada por 24 horas."],
    ["hora" => "22:12", "tipo" => "SISTEMA", "mensagem" => "JOGADOR XTREME_WARRIOR atingiu o Nível 201"],
    ["hora" => "21:50", "tipo" => "MERCADO", "mensagem" => "O preço das Essências de Mana caiu 15% devido à sobreoferta."]
];

// Missões diárias (exemplo)
$missoes_diarias = [
    [
        "nome" => "Patrulha do Distrito Leste",
        "status" => "pendente",
        "recompensa" => "2.500 XP, 500 Ouro",
        "descricao" => "Elimine 5 Slimes Corrompidos no Portal E"
    ],
    [
        "nome" => "Coleta de Essência",
        "status" => "disponivel", 
        "recompensa" => "1 Núcleo de Eco Fraco",
        "descricao" => "Colete 10 Fragmentos de Mana no Portal C"
    ]
];

// ✅ Carregar NPCs disponíveis (VERSÃO FINAL SEM DEBUG)
$sql_npcs = "SELECT * FROM npcs_base WHERE localizacao = 'cidade_central' OR localizacao = 'nexus'";
$npcs_result = $conexao->query($sql_npcs);
$npcs_disponiveis = [];

if ($npcs_result && $npcs_result->num_rows > 0) {
    while($npc = $npcs_result->fetch_assoc()) {
        $npcs_disponiveis[] = $npc;
    }
}

include 'header.php'; 
?>

<div class="container fade-in">
    <!-- TÍTULO PRINCIPAL -->
    <div class="section section-arcane text-center">
        <h1 style="color: var(--accent-arcane); text-shadow: 0 0 20px var(--accent-arcane-glow);">
            ⚡ NEXUS - CIDADE CENTRAL ⚡
        </h1>
        <p style="color: var(--text-secondary);">
            O coração da resistência humana contra a Fenda Arcana
        </p>
    </div>

    <!-- TRANSMISSÕES GLOBAIS -->
    <div class="section section-arcane">
        <h2 class="section-header">
            📡 [TRANSMISSÃO GLOBAL] - CONSELHO DA GUILDA
        </h2>
        <div class="transmission-feed">
            <?php foreach ($transmissoes_globais as $transmissao): ?>
                <div class="transmission-item">
                    <span class="transmission-time">[<?php echo $transmissao['hora']; ?>]</span>
                    <span class="transmission-type transmission-<?php echo strtolower($transmissao['tipo']); ?>">
                        <?php echo $transmissao['tipo']; ?>:
                    </span>
                    <span class="transmission-message"><?php echo $transmissao['mensagem']; ?></span>
                </div>
            <?php endforeach; ?>
        </div>
    </div>

    <div class="grid-2-col">
        <!-- MISSÕES DIÁRIAS -->
        <div class="section section-arcane">
            <h2 class="section-header">📅 MISSÕES DIÁRIAS</h2>
            <div class="daily-quests-preview">
                <?php
                // Carrega missões diárias do jogador
                $missoes_hoje = get_missoes_diarias_jogador($player_id, $conexao);
                
                if ($missoes_hoje && $missoes_hoje->num_rows > 0):
                    $completadas = 0;
                    while($missao = $missoes_hoje->fetch_assoc()):
                        if ($missao['completada']) $completadas++;
                ?>
                <div class="quest-card <?php echo $missao['completada'] ? 'completed' : 'active'; ?>">
                    <div class="quest-icon">
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
                    <div class="quest-info">
                        <h4><?php echo $missao['titulo']; ?></h4>
                        <p><?php echo $missao['descricao']; ?></p>
                        <div class="quest-progress">
                            <div class="progress-bar">
                                <div class="progress-fill" style="width: <?php echo $missao['progresso_percentual']; ?>%"></div>
                            </div>
                            <span class="progress-text">
                                <?php echo $missao['progresso_atual']; ?>/<?php echo $missao['objetivo']; ?>
                            </span>
                        </div>
                    </div>
                    <div class="quest-reward">
                        <div class="reward-gold">+<?php echo $missao['recompensa_ouro']; ?>🪙</div>
                        <div class="reward-xp">+<?php echo $missao['recompensa_xp']; ?>⭐</div>
                    </div>
                </div>
                <?php endwhile; ?>
                
                <div class="quests-summary">
                    <div class="summary-item">
                        <span>Progresso Diário:</span>
                        <strong><?php echo $completadas; ?>/3</strong>
                    </div>
                    <a href="missoes_diarias.php" class="btn btn-primary">Ver Todas as Missões</a>
                </div>
                
                <?php else: ?>
                <div class="empty-quests">
                    <p>Nenhuma missão diária disponível hoje.</p>
                    <p>Novas missões aparecerão em <strong><?php echo date('H:i', strtotime('tomorrow')); ?></strong></p>
                </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- EXÉRCITO DE SOMBRAS -->
        <div class="section section-arcane">
            <h2 class="section-header">👻 [EXÉRCITO DE SOMBRAS]</h2>
            <div class="eco-status">
                <div class="eco-active">
                    <h4 style="color: var(--accent-vital);">Eco Ladrão</h4>
                    <p>🕐 Retorno em: <strong>2:15:43</strong></p>
                    <p>💰 Recompensa Estimada: <strong>150-300 Ouro</strong></p>
                </div>
                <div class="eco-actions">
                    <a href="ecos.php" class="btn btn-primary">GERENCIAR ECOS</a>
                    <a href="ecos.php?acao=coletar" class="btn btn-success">COLETAR RECOMPENSAS</a>
                </div>
            </div>
        </div>
    </div>

    <!-- ✅ NOVO: NPCs INTERATIVOS -->
    <div class="section section-vital">
        <h2 class="section-header vital">🗣️ [HABITANTES DO NEXUS]</h2>
        <div class="npcs-grid">
            <?php if (!empty($npcs_disponiveis)): ?>
                <?php foreach($npcs_disponiveis as $npc): ?>
                    <?php 
                    // Verificar reputação com o NPC
                    $reputacao = get_reputacao_faccao($player_id, $npc['faccao'], $conexao);
                    $relacionamento = calcular_relacionamento($reputacao);
                    ?>
                    <div class="npc-card" data-faccao="<?php echo $npc['faccao']; ?>">
                        <div class="npc-header">
                            <div class="npc-icon"><?php echo $npc['icone']; ?></div>
                            <div class="npc-info">
                                <h4><?php echo $npc['nome']; ?></h4>
                                <div class="npc-faccao">
                                    <span class="faccao-badge faccao-<?php echo $npc['faccao']; ?>">
                                        <?php 
                                        $faccoes_nomes = [
                                            'guilda' => '🏛️ Guilda',
                                            'faccao_oculta' => '🌑 Facção Oculta', 
                                            'neutro' => '⚖️ Neutro'
                                        ];
                                        echo $faccoes_nomes[$npc['faccao']] ?? '🎭';
                                        ?>
                                    </span>
                                    <span class="relationship-status relationship-<?php echo $relacionamento; ?>">
                                        <?php echo ucfirst($relacionamento); ?>
                                    </span>
                                </div>
                            </div>
                        </div>
                        <p class="npc-desc"><?php echo $npc['descricao']; ?></p>
                        <div class="npc-actions">
                            <a href="npc_interact.php?npc_id=<?php echo $npc['id']; ?>" class="btn btn-dialogue">
                                💬 Conversar
                            </a>
                            <?php if ($npc['faccao'] == 'guilda'): ?>
                                <span class="reputation-display">Rep: <?php echo $reputacao; ?></span>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="no-npcs">
                    <p>Nenhum NPC disponível no momento.</p>
                    <p>Explore mais para encontrar habitantes importantes.</p>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- PORTAIS E AÇÕES PRINCIPAIS -->
    <div class="section section-arcane">
        <h2 class="section-header">🧭 [PORTAIS DE COMBATE]</h2>
        <div class="portals-grid">
            <div class="portal-card">
                <h4>Portal Rank E</h4>
                <p>Campo de Treinamento</p>
                <p class="portal-desc">Monstros: Slimes Fracos</p>
                <p class="portal-level">Nível 1-5</p>
                <a href="combate_portal.php?rank=E" class="btn btn-primary">ENTRAR NO PORTAL</a>
            </div>
            
            <div class="portal-card">
                <h4>Portal Rank D</h4>
                <p>Acampamento Goblin</p>
                <p class="portal-desc">Monstros: Goblins Ágeis</p>
                <p class="portal-level">Nível 5-15</p>
                <a href="combate_portal.php?rank=D" class="btn btn-primary">ENTRAR NO PORTAL</a>
            </div>
            
            <div class="portal-card">
                <h4>Portal Rank C</h4>
                <p>Ruínas Assombradas</p>
                <p class="portal-desc">Monstros: Esqueletos Guerreiros</p>
                <p class="portal-level">Nível 15+</p>
                <a href="combate_portal.php?rank=C" class="btn btn-primary">ENTRAR NO PORTAL</a>
            </div>
        </div>
    </div>

    <!-- SERVIÇOS DA CIDADE -->
    <div class="section section-vital">
        <h2 class="section-header vital">🏪 [SERVIÇOS DO NEXUS]</h2>
        <div class="services-grid">
            <a href="inventario.php" class="service-card">
                <span class="service-icon">🎒</span>
                <span class="service-name">INVENTÁRIO</span>
                <span class="service-desc">Gerenciar itens e equipamentos</span>
            </a>
            
            <a href="loja.php" class="service-card">
                <span class="service-icon">🏪</span>
                <span class="service-name">LOJA</span>
                <span class="service-desc">Comprar itens e consumíveis</span>
            </a>

            <a href="personagem.php" class="service-card">
                <span class="service-icon">👤</span>
                <span class="service-name">PERSONAGEM</span>
                <span class="service-desc">Ver status e gastar pontos</span>
            </a>
            
            <a href="mapa.php" class="service-card">
                <span class="service-icon">🗺️</span>
                <span class="service-name">MAPA</span>
                <span class="service-desc">Explorar locais disponíveis</span>
            </a>
            
            <a href="ecos.php" class="service-card">
                <span class="service-icon">👻</span>
                <span class="service-name">EXÉRCITO</span>
                <span class="service-desc">Gerenciar seus Ecos</span>
            </a>

            <!-- ✅ NOVO: Diálogos com NPCs -->
            <a href="#npcs-section" class="service-card">
                <span class="service-icon">🗣️</span>
                <span class="service-name">HABITANTES</span>
                <span class="service-desc">Conversar com NPCs</span>
            </a>
        </div>
    </div>
</div>

<style>
/* ESTILOS ESPECÍFICOS DA PÁGINA DA CIDADE */
.grid-2-col {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 20px;
    margin-bottom: 25px;
}

@media (max-width: 768px) {
    .grid-2-col {
        grid-template-columns: 1fr;
    }
}

.transmission-feed {
    background: var(--bg-primary);
    border: 1px solid var(--accent-arcane);
    border-radius: 5px;
    padding: 15px;
}

.transmission-item {
    padding: 8px 0;
    border-bottom: 1px solid var(--bg-secondary);
}

.transmission-item:last-child {
    border-bottom: none;
}

.transmission-time {
    color: var(--accent-vital);
    font-weight: bold;
}

.transmission-type {
    font-weight: bold;
    margin: 0 5px;
}

.transmission-conselho { color: var(--accent-arcane); }
.transmission-sistema { color: var(--accent-vital); }
.transmission-mercado { color: var(--status-gold); }

/* ✅ NOVO: Estilos para NPCs */
.npcs-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
    gap: 20px;
    margin-top: 15px;
}

.npc-card {
    background: var(--bg-primary);
    border: 2px solid;
    border-radius: 12px;
    padding: 20px;
    transition: all 0.3s ease;
    box-shadow: 0 4px 15px rgba(0,0,0,0.1);
}

.npc-card[data-faccao="guilda"] {
    border-color: var(--accent-vital);
    background: linear-gradient(135deg, var(--bg-primary) 0%, rgba(80, 200, 120, 0.05) 100%);
}

.npc-card[data-faccao="faccao_oculta"] {
    border-color: var(--accent-arcane);
    background: linear-gradient(135deg, var(--bg-primary) 0%, rgba(138, 43, 226, 0.05) 100%);
}

.npc-card[data-faccao="neutro"] {
    border-color: var(--bg-tertiary);
    background: var(--bg-primary);
}

.npc-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 8px 25px rgba(0,0,0,0.15);
}

.npc-header {
    display: flex;
    align-items: center;
    gap: 15px;
    margin-bottom: 15px;
}

.npc-icon {
    font-size: 2.5em;
    width: 60px;
    height: 60px;
    display: flex;
    align-items: center;
    justify-content: center;
    background: var(--bg-secondary);
    border-radius: 50%;
    border: 2px solid currentColor;
}

.npc-card[data-faccao="guilda"] .npc-icon {
    color: var(--accent-vital);
}

.npc-card[data-faccao="faccao_oculta"] .npc-icon {
    color: var(--accent-arcane);
}

.npc-info h4 {
    margin: 0 0 8px 0;
    color: var(--text-primary);
    font-size: 1.2em;
}

.npc-faccao {
    display: flex;
    gap: 10px;
    align-items: center;
    flex-wrap: wrap;
}

.faccao-badge {
    padding: 4px 10px;
    border-radius: 15px;
    font-size: 0.8em;
    font-weight: bold;
}

.faccao-guilda {
    background: var(--accent-vital);
    color: white;
}

.faccao-faccao_oculta {
    background: var(--accent-arcane);
    color: white;
}

.faccao-neutro {
    background: var(--bg-tertiary);
    color: var(--text-secondary);
}

.relationship-status {
    padding: 3px 8px;
    border-radius: 10px;
    font-size: 0.75em;
    font-weight: bold;
}

.relationship-inimigo { background: var(--status-hp); color: white; }
.relationship-hostil { background: #FF6B35; color: white; }
.relationship-neutro { background: var(--bg-tertiary); color: var(--text-secondary); }
.relationship-aliado { background: var(--accent-vital); color: white; }
.relationship-idolo { background: gold; color: black; }

.npc-desc {
    color: var(--text-secondary);
    margin-bottom: 15px;
    line-height: 1.5;
    font-size: 0.95em;
}

.npc-actions {
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 10px;
}

.btn-dialogue {
    background: var(--accent-arcane);
    color: white;
    padding: 8px 16px;
    border-radius: 8px;
    text-decoration: none;
    font-size: 0.9em;
    transition: all 0.3s ease;
    flex: 1;
    text-align: center;
}

.btn-dialogue:hover {
    background: var(--accent-vital);
    transform: translateY(-2px);
}

.reputation-display {
    color: var(--text-secondary);
    font-size: 0.8em;
    font-weight: bold;
    padding: 5px 10px;
    background: var(--bg-secondary);
    border-radius: 8px;
}

.no-npcs {
    text-align: center;
    padding: 40px 20px;
    color: var(--text-secondary);
    grid-column: 1 / -1;
}

/* Estilos existentes mantidos */
.portals-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
    gap: 15px;
}

.portal-card {
    background: var(--bg-primary);
    border: 2px solid var(--accent-arcane);
    border-radius: 6px;
    padding: 15px;
    text-align: center;
    transition: all 0.3s ease;
}

.portal-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 8px 20px rgba(138, 43, 226, 0.3);
}

.portal-desc {
    color: var(--text-secondary);
    font-size: 0.9em;
    margin: 8px 0;
}

.portal-level {
    color: var(--accent-vital);
    font-weight: bold;
    margin-bottom: 12px;
}

.services-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 15px;
}

.service-card {
    display: flex;
    flex-direction: column;
    align-items: center;
    background: var(--bg-primary);
    border: 2px solid var(--accent-vital);
    border-radius: 6px;
    padding: 20px;
    text-decoration: none;
    color: var(--text-primary);
    transition: all 0.3s ease;
    text-align: center;
}

.service-card:hover {
    background: var(--accent-vital);
    color: var(--bg-primary);
    transform: translateY(-3px);
}

.service-icon {
    font-size: 2em;
    margin-bottom: 10px;
}

.service-name {
    font-weight: bold;
    margin-bottom: 5px;
}

.service-desc {
    font-size: 0.8em;
    color: inherit;
    opacity: 0.8;
}
/* Estilos de DEBUG */
.debug-error {
    background: #ff4444;
    color: white;
    padding: 15px;
    border-radius: 8px;
    margin: 10px 0;
    border-left: 5px solid #cc0000;
}

.debug-success {
    background: #44ff44;
    color: #006600;
    padding: 15px;
    border-radius: 8px;
    margin: 10px 0;
    border-left: 5px solid #00cc00;
}

.debug-warning {
    background: #ffaa00;
    color: #664400;
    padding: 15px;
    border-radius: 8px;
    margin: 10px 0;
    border-left: 5px solid #cc8800;
}

.debug-npc {
    background: var(--bg-secondary);
    padding: 10px;
    margin: 5px 0;
    border-radius: 5px;
    border-left: 3px solid var(--accent-arcane);
    font-size: 0.9em;
}
</style>

<?php include 'footer.php'; ?>