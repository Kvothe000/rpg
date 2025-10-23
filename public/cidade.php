<?php
session_start();
include_once 'db_connect.php';
include_once 'game_logic.php';
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

// Missões diárias (exemplo) - Removido pois agora carrega do banco
// $missoes_diarias = [...];

// ✅ Carregar NPCs disponíveis
$sql_npcs = "SELECT * FROM npcs_base WHERE localizacao = 'cidade_central' OR localizacao = 'nexus'";
$npcs_result = $conexao->query($sql_npcs);
$npcs_disponiveis = [];

if ($npcs_result && $npcs_result->num_rows > 0) {
    while($npc = $npcs_result->fetch_assoc()) {
        $npcs_disponiveis[] = $npc;
    }
}

// Verifica se o jogador tem a quest "O Custo do Poder" ativa para mostrar o link
$quest_id_custo_poder = 1;
$sql_check_quest_cidade = "SELECT id FROM player_quests WHERE player_id = ? AND quest_id = ? AND status IN ('aceita', 'em_progresso')";
$stmt_check_cidade = $conexao->prepare($sql_check_quest_cidade);
$stmt_check_cidade->bind_param("ii", $player_id, $quest_id_custo_poder);
$stmt_check_cidade->execute();
$tem_quest_ativa = $stmt_check_cidade->get_result()->num_rows > 0;
$stmt_check_cidade->close(); // Fechar statement

include 'header.php';
?>

<div class="container fade-in">
    <div class="section section-arcane text-center">
        <h1 style="color: var(--accent-arcane); text-shadow: 0 0 20px var(--accent-arcane-glow);">
            ⚡ NEXUS - CIDADE CENTRAL ⚡
        </h1>
        <p style="color: var(--text-secondary);">
            O coração da resistência humana contra a Fenda Arcana
        </p>
    </div>

    <div class="section section-arcane">
        <h2 class="section-header">
            📡 [TRANSMISSÃO GLOBAL] - CONSELHO DA GUILDA
        </h2>
        <div class="transmission-feed">
            <?php foreach ($transmissoes_globais as $transmissao): ?>
                <div class="transmission-item">
                    <span class="transmission-time">[<?php echo htmlspecialchars($transmissao['hora']); ?>]</span>
                    <span class="transmission-type transmission-<?php echo strtolower(htmlspecialchars($transmissao['tipo'])); ?>">
                        <?php echo htmlspecialchars($transmissao['tipo']); ?>:
                    </span>
                    <span class="transmission-message"><?php echo htmlspecialchars($transmissao['mensagem']); ?></span>
                </div>
            <?php endforeach; ?>
        </div>
    </div>

    <div class="grid-2-col">
        <div class="section section-arcane">
            <h2 class="section-header">📅 MISSÕES DIÁRIAS</h2>
            <div class="daily-quests-preview">
                <?php
                // Carrega missões diárias do jogador
                $missoes_hoje = get_missoes_diarias_jogador($player_id, $conexao);

                if ($missoes_hoje && $missoes_hoje->num_rows > 0):
                    $completadas = 0;
                    $total_missoes_dia = $missoes_hoje->num_rows; // Pega o total antes do loop
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
                        <h4><?php echo htmlspecialchars($missao['titulo']); ?></h4>
                        <p><?php echo htmlspecialchars($missao['descricao']); ?></p>
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
                        <strong><?php echo $completadas; ?>/<?php echo $total_missoes_dia; ?></strong>
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

        <div class="section section-arcane">
            <h2 class="section-header">👻 [EXÉRCITO DE SOMBRAS]</h2>
            <div class="eco-status">
                 <?php
                 // Carrega UM Eco em missão (ou o mais próximo de retornar) para preview
                 $sql_eco_preview = "SELECT pe.id, eb.nome, pe.status_eco, pe.tempo_retorno_missao, eb.bonus_ouro_hora
                                     FROM personagem_ecos pe
                                     JOIN ecos_base eb ON pe.id_eco_base = eb.id
                                     WHERE pe.id_personagem = $player_id
                                     ORDER BY CASE WHEN pe.status_eco = 'Em Missao' THEN 1 ELSE 2 END, pe.tempo_retorno_missao ASC
                                     LIMIT 1";
                 $eco_preview = $conexao->query($sql_eco_preview)->fetch_assoc();
                 ?>
                 <?php if ($eco_preview): ?>
                    <?php if ($eco_preview['status_eco'] == 'Em Missao'):
                        $agora = new DateTime();
                        $retorno = new DateTime($eco_preview['tempo_retorno_missao']);
                        $tempo_restante_formatado = "Em Missão";
                        if ($agora < $retorno) {
                            $intervalo = $agora->diff($retorno);
                            $tempo_restante_formatado = $intervalo->format('%H:%I:%S');
                        } else {
                            $tempo_restante_formatado = "Pronto!";
                        }
                    ?>
                        <div class="eco-active">
                            <h4 style="color: var(--accent-vital);"><?php echo htmlspecialchars($eco_preview['nome']); ?></h4>
                            <p>🕐 Retorno em: <strong><?php echo $tempo_restante_formatado; ?></strong></p>
                            <p>💰 Recompensa Estimada: <strong><?php echo ($eco_preview['bonus_ouro_hora'] * 1); /* Ajustar cálculo se necessário */ ?> Ouro</strong></p>
                        </div>
                    <?php else: ?>
                        <div class="eco-active">
                            <h4 style="color: var(--accent-vital);"><?php echo htmlspecialchars($eco_preview['nome']); ?></h4>
                            <p><strong>Status:</strong> Descansando</p>
                            <p>Pronto para nova missão!</p>
                        </div>
                    <?php endif; ?>
                 <?php else: ?>
                    <div class="eco-active">
                        <p>Nenhum Eco recrutado ainda.</p>
                    </div>
                 <?php endif; ?>

                <div class="eco-actions">
                    <a href="ecos.php" class="btn btn-primary">GERENCIAR ECOS</a>
                    <a href="ecos.php?acao=coletar_todos" class="btn btn-success">COLETAR RECOMPENSAS</a> </div>
            </div>
        </div>
    </div>

    <div class="section section-vital" id="npcs-section">
        <h2 class="section-header vital">🗣️ [HABITANTES DO NEXUS]</h2>
        <div class="npcs-grid">
            <?php if (!empty($npcs_disponiveis)): ?>
                <?php foreach($npcs_disponiveis as $npc): ?>
                    <?php
                    // Verificar reputação com o NPC
                    $reputacao = get_reputacao_faccao($player_id, $npc['faccao'], $conexao);
                    $relacionamento = calcular_relacionamento($reputacao);
                    ?>
                    <div class="npc-card" data-faccao="<?php echo htmlspecialchars($npc['faccao']); ?>">
                        <div class="npc-header">
                            <div class="npc-icon"><?php echo htmlspecialchars($npc['icone']); ?></div>
                            <div class="npc-info">
                                <h4><?php echo htmlspecialchars($npc['nome']); ?></h4>
                                <div class="npc-faccao">
                                    <span class="faccao-badge faccao-<?php echo htmlspecialchars($npc['faccao']); ?>">
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
                        <p class="npc-desc"><?php echo htmlspecialchars($npc['descricao']); ?></p>
                        <div class="npc-actions">
                            <a href="npc_interact.php?npc_id=<?php echo $npc['id']; ?>" class="btn btn-dialogue">
                                💬 Conversar
                            </a>
                            <?php if ($npc['faccao'] != 'neutro'): // Mostrar reputação para facções ?>
                                <span class="reputation-display">Rep: <?php echo $reputacao; ?></span>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="no-npcs">
                    <p>Nenhum NPC disponível no momento.</p>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <div class="section section-arcane">
        <h2 class="section-header">🧭 [PORTAIS DE COMBATE]</h2>
        <div class="portals-grid">
            <?php if ($tem_quest_ativa): ?>
            <div class="portal-card mission-portal"> <h4>Área de Treinamento</h4>
                 <p>Missão Ativa</p>
                 <p class="portal-desc">Eliminar Slimes de Mana Fracos</p>
                 <p class="portal-level">Nível 1+</p>
                 <a href="combate_portal.php?missao=custo_poder" class="btn btn-primary glow-effect">INICIAR MISSÃO</a>
             </div>
            <?php endif; ?>

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
    max-height: 150px; /* Limita altura */
    overflow-y: auto; /* Adiciona scroll */
}

.transmission-item {
    padding: 8px 0;
    border-bottom: 1px solid var(--bg-secondary);
    font-size: 0.9em;
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

/* Missões Diárias Preview */
.daily-quests-preview {
    display: flex;
    flex-direction: column;
    gap: 10px;
}

.quest-card {
    display: flex;
    align-items: center;
    gap: 15px;
    padding: 12px;
    background: var(--bg-primary);
    border: 1px solid var(--bg-tertiary);
    border-radius: 8px;
    transition: all 0.3s ease;
}

.quest-card.completed {
    opacity: 0.7;
    background: var(--bg-secondary); /* Fundo um pouco diferente para completas */
}

.quest-icon {
    font-size: 1.8em;
    opacity: 0.8;
}

.quest-info {
    flex: 1;
}

.quest-info h4 {
    margin: 0 0 3px 0;
    color: var(--text-primary);
    font-size: 1em;
}

.quest-info p {
    margin: 0 0 8px 0;
    color: var(--text-secondary);
    font-size: 0.85em;
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
    min-width: 50px;
    text-align: right;
}

.quest-reward {
    display: flex;
    flex-direction: column;
    gap: 4px;
    text-align: right;
    font-size: 0.85em;
}

.reward-gold { color: var(--status-gold); }
.reward-xp { color: var(--accent-arcane); }

.quests-summary {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 10px 15px;
    background: var(--bg-secondary);
    border-radius: 8px;
    margin-top: 15px;
}

.summary-item span {
    color: var(--text-secondary);
    font-size: 0.9em;
}

.summary-item strong {
    color: var(--accent-vital);
    font-size: 1.1em;
    margin-left: 5px;
}

.empty-quests {
    text-align: center;
    padding: 20px;
    color: var(--text-secondary);
    font-style: italic;
}
.empty-quests p { margin: 5px 0; }

/* Exército de Sombras Preview */
.eco-status {
    background: var(--bg-primary);
    padding: 15px;
    border-radius: 8px;
    border: 1px solid var(--bg-tertiary);
}
.eco-active h4 { margin-bottom: 5px; }
.eco-active p { margin: 3px 0; font-size: 0.9em; color: var(--text-secondary); }
.eco-actions { margin-top: 15px; display: flex; gap: 10px; justify-content: center; }


/* NPCs INTERATIVOS */
.npcs-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); /* Ajustado minmax */
    gap: 15px; /* Reduzido gap */
    margin-top: 15px;
}

.npc-card {
    background: var(--bg-primary);
    border: 2px solid;
    border-radius: 10px; /* Levemente menor */
    padding: 15px; /* Reduzido padding */
    transition: all 0.3s ease;
    box-shadow: 0 3px 10px rgba(0,0,0,0.1); /* Sombra mais sutil */
}

.npc-card[data-faccao="guilda"] {
    border-color: var(--accent-vital);
    background: linear-gradient(135deg, var(--bg-primary) 0%, rgba(80, 200, 120, 0.05) 100%);
}
.npc-card[data-faccao="faccao_oculta"] {
    border-color: var(--accent-arcane);
    background: linear-gradient(135deg, var(--bg-primary) 0%, rgba(138, 43, 226, 0.05) 100%);
}
.npc-card[data-faccao="neutro"] { border-color: var(--bg-tertiary); }
.npc-card:hover { transform: translateY(-3px); box-shadow: 0 6px 15px rgba(0,0,0,0.15); }

.npc-header { display: flex; align-items: center; gap: 12px; margin-bottom: 10px; } /* Reduzido gap e margin */
.npc-icon { font-size: 2em; width: 50px; height: 50px; /* Menor */ display: flex; align-items: center; justify-content: center; background: var(--bg-secondary); border-radius: 50%; border: 2px solid currentColor; }
.npc-card[data-faccao="guilda"] .npc-icon { color: var(--accent-vital); }
.npc-card[data-faccao="faccao_oculta"] .npc-icon { color: var(--accent-arcane); }
.npc-info h4 { margin: 0 0 5px 0; color: var(--text-primary); font-size: 1.1em; } /* Menor margin */
.npc-faccao { display: flex; gap: 8px; align-items: center; flex-wrap: wrap; }
.faccao-badge { padding: 3px 8px; border-radius: 12px; font-size: 0.75em; font-weight: bold; } /* Menor */
.faccao-guilda { background: var(--accent-vital); color: white; }
.faccao-faccao_oculta { background: var(--accent-arcane); color: white; }
.faccao-neutro { background: var(--bg-tertiary); color: var(--text-secondary); }
.relationship-status { padding: 2px 6px; border-radius: 8px; font-size: 0.7em; font-weight: bold; } /* Menor */
.relationship-inimigo { background: var(--status-hp); color: white; }
.relationship-hostil { background: #FF6B35; color: white; }
.relationship-neutro { background: var(--bg-tertiary); color: var(--text-secondary); }
.relationship-aliado { background: var(--accent-vital); color: white; }
.relationship-idolo { background: gold; color: black; }
.npc-desc { color: var(--text-secondary); margin-bottom: 12px; line-height: 1.4; font-size: 0.9em; } /* Reduzido margin e font-size */
.npc-actions { display: flex; justify-content: space-between; align-items: center; gap: 8px; }
.btn-dialogue { background: var(--accent-arcane); color: white; padding: 6px 12px; border-radius: 6px; text-decoration: none; font-size: 0.85em; transition: all 0.3s ease; flex: 1; text-align: center; } /* Menor */
.btn-dialogue:hover { background: var(--accent-vital); transform: translateY(-1px); }
.reputation-display { color: var(--text-secondary); font-size: 0.75em; font-weight: bold; padding: 4px 8px; background: var(--bg-secondary); border-radius: 6px; } /* Menor */
.no-npcs { text-align: center; padding: 30px 15px; color: var(--text-secondary); grid-column: 1 / -1; font-style: italic; }

/* Portais */
.portals-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(230px, 1fr)); gap: 15px; } /* Ajustado minmax */
.portal-card { background: var(--bg-primary); border: 2px solid var(--accent-arcane); border-radius: 8px; padding: 15px; text-align: center; transition: all 0.3s ease; position: relative; }
.portal-card.mission-portal { border-color: var(--accent-vital); } /* Destaca portal de missão */
.portal-card:hover { transform: translateY(-4px); box-shadow: 0 6px 15px rgba(138, 43, 226, 0.3); }
.portal-card h4 { margin-bottom: 5px; font-size: 1.1em; }
.portal-desc { color: var(--text-secondary); font-size: 0.85em; margin: 5px 0; }
.portal-level { color: var(--accent-vital); font-weight: bold; margin-bottom: 10px; font-size: 0.9em; }
.portal-card .btn { margin-top: 10px; }

/* Serviços */
.services-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: 15px; } /* Ajustado minmax */
.service-card { display: flex; flex-direction: column; align-items: center; background: var(--bg-primary); border: 2px solid var(--accent-vital); border-radius: 8px; padding: 15px; text-decoration: none; color: var(--text-primary); transition: all 0.3s ease; text-align: center; }
.service-card:hover { background: var(--accent-vital); color: var(--bg-primary); transform: translateY(-2px); }
.service-icon { font-size: 1.8em; margin-bottom: 8px; }
.service-name { font-weight: bold; margin-bottom: 4px; font-size: 1em; }
.service-desc { font-size: 0.75em; color: inherit; opacity: 0.8; }

/* Adicional para glow effect no botão da missão */
@keyframes glow {
  0%, 100% { box-shadow: 0 0 5px var(--accent-vital); }
  50% { box-shadow: 0 0 15px var(--accent-vital-glow); }
}
.glow-effect { animation: glow 2s ease-in-out infinite; }

</style>

<?php include 'footer.php'; ?>