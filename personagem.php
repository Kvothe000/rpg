<?php
session_start();
include_once 'db_connect.php'; // ✅ Use include_once
include_once 'game_logic.php'; // ✅ Use include_once  
include_once 'daily_quests_functions.php';


$titulo_pagina = "Ficha do Personagem";
$pagina_atual = 'personagem';

// =============================================================================
// VERIFICAÇÃO DE LOGIN E SEGURANÇA
// =============================================================================

if (!isset($_SESSION['player_id'])) {
    header('Location: login.php');
    exit;
}

$player_id = $_SESSION['player_id'];
$mensagem_feedback = "";

// =============================================================================
// PROCESSAMENTO DE AÇÕES
// =============================================================================

// Gastar Pontos de Atributo
if (isset($_GET['acao']) && $_GET['acao'] === 'gastar_pa') {
    $sql_check = "SELECT * FROM personagens WHERE id = $player_id";
    $player_check = $conexao->query($sql_check)->fetch_assoc();

    if ($player_check && $player_check['pontos_atributo_disponiveis'] > 0) {
        $atributo_para_upar = $_GET['att']; 
        $atributos_validos = ['str', 'dex', 'con', 'int_stat', 'wis', 'cha'];
        
        if (in_array($atributo_para_upar, $atributos_validos)) {
            $conexao->query("UPDATE personagens SET `$atributo_para_upar` = `$atributo_para_upar` + 1, pontos_atributo_disponiveis = pontos_atributo_disponiveis - 1 WHERE id = $player_id");
            
            // Recalcula Stats Derivados
            $player_data_atualizado = $conexao->query("SELECT * FROM personagens WHERE id = $player_id")->fetch_assoc();
            $equip_bonus = carregar_stats_equipados($player_id, $conexao);
            $stats_para_calculo = [
                'str' => $player_data_atualizado['str'] + $equip_bonus['bonus_str'],
                'con' => $player_data_atualizado['con'] + $equip_bonus['bonus_con'],
                'int_stat' => $player_data_atualizado['int_stat'] + $equip_bonus['bonus_int'],
                'wis' => $player_data_atualizado['wis'] + $equip_bonus['bonus_wis']
            ];
            $derivados = calcular_stats_derivados($stats_para_calculo, $player_data_atualizado['level'], $player_data_atualizado['classe_base']);
            $conexao->query("UPDATE personagens SET hp_max = {$derivados['hp_max']}, mana_max = {$derivados['recurso_max']} WHERE id = $player_id");
            
            $mensagem_feedback = "<div class='feedback feedback-success'>✨ Atributo BASE <strong>" . strtoupper($atributo_para_upar) . "</strong> aumentado!</div>";
        }
    }
}

// Aprender Habilidade
else if (isset($_GET['acao']) && $_GET['acao'] === 'aprender_skill') {
    $id_skill_para_aprender = (int)$_GET['skill_id'];
    $sql_player = "SELECT pontos_habilidade_disponiveis, classe_base FROM personagens WHERE id = $player_id";
    $player_data = $conexao->query($sql_player)->fetch_assoc();
    $sql_skill = "SELECT nome, custo_ph, classe_req FROM skills_base WHERE id = $id_skill_para_aprender";
    $skill_data = $conexao->query($sql_skill)->fetch_assoc();

    if ($skill_data) {
        $sql_check_possui = "SELECT id FROM personagem_skills WHERE id_personagem = $player_id AND id_skill_base = $id_skill_para_aprender";
        $ja_possui = $conexao->query($sql_check_possui)->num_rows;

        if ($ja_possui > 0) {
            $mensagem_feedback = "<div class='feedback feedback-error'>❌ Você já conhece esta habilidade.</div>";
        } else if ($player_data['pontos_habilidade_disponiveis'] < $skill_data['custo_ph']) {
            $mensagem_feedback = "<div class='feedback feedback-error'>❌ Pontos de Habilidade (PH) insuficientes.</div>";
        } else if ($skill_data['classe_req'] != NULL && $skill_data['classe_req'] != $player_data['classe_base']) {
            $mensagem_feedback = "<div class='feedback feedback-error'>❌ Sua classe ({$player_data['classe_base']}) não pode aprender esta habilidade.</div>";
        } else {
            $conexao->query("INSERT INTO personagem_skills (id_personagem, id_skill_base, skill_level) VALUES ($player_id, $id_skill_para_aprender, 1)");
            $custo = $skill_data['custo_ph'];
            $conexao->query("UPDATE personagens SET pontos_habilidade_disponiveis = pontos_habilidade_disponiveis - $custo WHERE id = $player_id");
            $mensagem_feedback = "<div class='feedback feedback-success'>🎯 Você aprendeu: <strong>{$skill_data['nome']}</strong>!</div>";
        }
    }
}

// Escolher Subclasse
else if (isset($_GET['acao']) && $_GET['acao'] === 'escolher_subclasse') {
    $id_subclasse_escolhida = (int)$_GET['subclasse_id'];
    $sql_player = "SELECT level, classe_base, subclasse FROM personagens WHERE id = $player_id";
    $player_data_check = $conexao->query($sql_player)->fetch_assoc();
    $sql_subclasse = "SELECT nome, classe_base_req FROM subclasses_base WHERE id = $id_subclasse_escolhida";
    $subclasse_data = $conexao->query($sql_subclasse)->fetch_assoc();

    if (!$subclasse_data) {
        $mensagem_feedback = "<div class='feedback feedback-error'>❌ Subclasse inválida.</div>";
    } else if ($player_data_check['level'] < 10) {
        $mensagem_feedback = "<div class='feedback feedback-error'>❌ Você precisa ser Nível 10 para Despertar.</div>";
    } else if ($player_data_check['subclasse'] != NULL) {
        $mensagem_feedback = "<div class='feedback feedback-error'>❌ Você já escolheu seu caminho.</div>";
    } else if ($player_data_check['classe_base'] != $subclasse_data['classe_base_req']) {
        $mensagem_feedback = "<div class='feedback feedback-error'>❌ Esta subclasse não pertence à sua classe base.</div>";
    } else {
        $nome_subclasse = $conexao->real_escape_string($subclasse_data['nome']);
        $conexao->query("UPDATE personagens SET subclasse = '{$nome_subclasse}' WHERE id = $player_id");
        $conexao->query("UPDATE personagens SET hp_atual = hp_max, mana_atual = mana_max WHERE id = $player_id");
        $mensagem_feedback = "<div class='feedback feedback-success' style='border: 2px solid var(--accent-arcane);'>";
        $mensagem_feedback .= "⚡ <strong>DESPERTAR COMPLETO!</strong><br>";
        $mensagem_feedback .= "Você se tornou um: <span style='color: var(--accent-arcane);'><strong>{$nome_subclasse}</strong></span>!";
        $mensagem_feedback .= "</div>";
    }
}

// Melhorar Habilidade
else if (isset($_GET['acao']) && $_GET['acao'] === 'upar_skill') {
    $id_personagem_skill_unica = (int)$_GET['skill_id'];
    
    $sql_player_ph = "SELECT pontos_habilidade_disponiveis FROM personagens WHERE id = $player_id";
    $player_ph_data = $conexao->query($sql_player_ph)->fetch_assoc();
    $ph_disponivel = $player_ph_data['pontos_habilidade_disponiveis'];
    
    $sql_skill_info = "SELECT ps.skill_level, sb.nome, sb.custo_ph_upgrade 
                       FROM personagem_skills ps
                       JOIN skills_base sb ON ps.id_skill_base = sb.id
                       WHERE ps.id = $id_personagem_skill_unica AND ps.id_personagem = $player_id";
    $skill_info = $conexao->query($sql_skill_info)->fetch_assoc();

    if ($skill_info) {
        if ($ph_disponivel >= $skill_info['custo_ph_upgrade']) {
            $custo = $skill_info['custo_ph_upgrade'];
            $conexao->query("UPDATE personagens SET pontos_habilidade_disponiveis = pontos_habilidade_disponiveis - $custo WHERE id = $player_id");
            $conexao->query("UPDATE personagem_skills SET skill_level = skill_level + 1 WHERE id = $id_personagem_skill_unica");
            
            $mensagem_feedback = "<div class='feedback feedback-success'>📈 Sua skill <strong>{$skill_info['nome']}</strong> subiu para o Nível " . ($skill_info['skill_level'] + 1) . "!</div>";
        } else {
            $mensagem_feedback = "<div class='feedback feedback-error'>❌ PH Insuficiente para melhorar esta skill (Custo: {$skill_info['custo_ph_upgrade']} PH).</div>";
        }
    } else {
        $mensagem_feedback = "<div class='feedback feedback-error'>❌ Erro: Habilidade não encontrada ou não pertence a você.</div>";
    }
}

// =============================================================================
// CARREGAMENTO DE DADOS
// =============================================================================

// Carrega dados do jogador
$sql_player = "SELECT * FROM personagens WHERE id = $player_id";
$player_data = $conexao->query($sql_player)->fetch_assoc();

if (!$player_data) {
    session_destroy();
    header('Location: login.php');
    exit;
}

// Carrega bônus de equipamentos
$equip_bonus = carregar_stats_equipados($player_id, $conexao);

// Calcula atributos totais
$stats_totais = [
    'str' => $player_data['str'] + $equip_bonus['bonus_str'],
    'dex' => $player_data['dex'] + $equip_bonus['bonus_dex'],
    'con' => $player_data['con'] + $equip_bonus['bonus_con'],
    'int_stat' => $player_data['int_stat'] + $equip_bonus['bonus_int'],
    'wis' => $player_data['wis'] + $equip_bonus['bonus_wis'],
    'cha' => $player_data['cha'] + $equip_bonus['bonus_cha']
];

// Carrega habilidades aprendidas
$sql_skills_aprendidas = "SELECT 
                            ps.id AS id_personagem_skill, 
                            sb.nome, 
                            sb.descricao, 
                            ps.skill_level, 
                            sb.custo_ph_upgrade 
                          FROM personagem_skills ps
                          JOIN skills_base sb ON ps.id_skill_base = sb.id
                          WHERE ps.id_personagem = $player_id";
$result_skills_aprendidas = $conexao->query($sql_skills_aprendidas);

// Carrega habilidades disponíveis
$classe_jogador = $player_data['classe_base'];
$sql_skills_disponiveis = "SELECT * FROM skills_base 
                           WHERE (classe_req = '{$classe_jogador}' OR classe_req IS NULL)
                           AND id NOT IN (SELECT id_skill_base FROM personagem_skills WHERE id_personagem = $player_id)";
$result_skills_disponiveis = $conexao->query($sql_skills_disponiveis);

// Carrega subclasses disponíveis
$result_subclasses_disponiveis = null;
if ($player_data['level'] >= 10 && $player_data['subclasse'] == NULL) {
    $sql_subclasses = "SELECT * FROM subclasses_base WHERE classe_base_req = '{$classe_jogador}'";
    $result_subclasses_disponiveis = $conexao->query($sql_subclasses);
}

include 'header.php';
?>

<div class="container fade-in">
    <!-- CABEÇALHO -->
    <div class="section section-arcane text-center">
        <h1 style="color: var(--accent-arcane); text-shadow: 0 0 20px var(--accent-arcane-glow);">
            ⚡ PERFIL DO CAÇADOR
        </h1>
        <p style="color: var(--text-secondary);">
            Sua jornada, seu poder, seu destino
        </p>
    </div>

    <?php echo $mensagem_feedback; ?>

    <!-- ALERTA DE DESPERTAR -->
    <?php if ($result_subclasses_disponiveis && $result_subclasses_disponiveis->num_rows > 0): ?>
    <div class="section section-awakening">
        <div class="awakening-alert">
            <div class="awakening-icon">🌌</div>
            <div class="awakening-content">
                <h3>O DESPERTAR AGUARDA</h3>
                <p>Seu poder atingiu o ápice. Escolha seu caminho - esta decisão moldará seu destino para sempre.</p>
            </div>
        </div>
        
        <div class="subclass-grid">
            <?php while($sub = $result_subclasses_disponiveis->fetch_assoc()): ?>
            <div class="subclass-card">
                <div class="subclass-header">
                    <h4><?php echo $sub['nome']; ?></h4>
                    <span class="subclass-badge">Nível 10</span>
                </div>
                <div class="subclass-description">
                    <?php echo $sub['descricao']; ?>
                </div>
                <div class="subclass-actions">
                    <a href="?acao=escolher_subclasse&subclasse_id=<?php echo $sub['id']; ?>" class="btn btn-awakening">
                        🌟 Escolher Caminho
                    </a>
                </div>
            </div>
            <?php endwhile; ?>
        </div>
    </div>
    <?php endif; ?>

    <!-- RESUMO DO PERSONAGEM -->
    <div class="grid-2-col">
        <div class="section section-vital">
            <h2 class="section-header vital">👤 STATUS PRINCIPAL</h2>
            <div class="character-main">
                <div class="char-identity">
                    <div class="char-avatar">
                        <div class="avatar-icon">⚔️</div>
                    </div>
                    <div class="char-details">
                        <h3><?php echo htmlspecialchars($player_data['nome']); ?></h3>
                        <div class="char-tags">
                            <span class="char-tag level">Nível <?php echo $player_data['level']; ?></span>
                            <span class="char-tag class"><?php echo $player_data['classe_base']; ?></span>
                            <?php if ($player_data['subclasse']): ?>
                            <span class="char-tag subclass"><?php echo $player_data['subclasse']; ?></span>
                            <?php endif; ?>
                            <span class="char-tag rank"><?php echo $player_data['fama_rank']; ?></span>
                        </div>
                    </div>
                </div>
                
                <div class="char-progress">
                    <div class="progress-item">
                        <div class="progress-label">
                            <span>Experiência</span>
                            <span><?php echo $player_data['xp_atual']; ?>/<?php echo $player_data['xp_proximo_level']; ?></span>
                        </div>
                        <div class="progress-bar">
                            <div class="progress-fill xp-fill" style="width: <?php echo ($player_data['xp_atual'] / $player_data['xp_proximo_level']) * 100; ?>%"></div>
                        </div>
                    </div>
                </div>

                <div class="char-resources-grid">
                    <div class="resource-card">
                        <div class="resource-icon">❤️</div>
                        <div class="resource-info">
                            <div class="resource-value"><?php echo $player_data['hp_atual']; ?>/<?php echo $player_data['hp_max']; ?></div>
                            <div class="resource-label">Vitalidade</div>
                        </div>
                        <div class="resource-bar">
                            <div class="bar-fill hp-fill" style="width: <?php echo ($player_data['hp_atual'] / $player_data['hp_max']) * 100; ?>%"></div>
                        </div>
                    </div>
                    
                    <div class="resource-card">
                        <div class="resource-icon">🔷</div>
                        <div class="resource-info">
                            <div class="resource-value"><?php echo $player_data['mana_atual']; ?>/<?php echo $player_data['mana_max']; ?></div>
                            <div class="resource-label">
                                <?php echo ($player_data['classe_base'] === 'Mago' || $player_data['classe_base'] === 'Sacerdote') ? 'Mana' : 'Fúria'; ?>
                            </div>
                        </div>
                        <div class="resource-bar">
                            <div class="bar-fill mana-fill" style="width: <?php echo ($player_data['mana_atual'] / $player_data['mana_max']) * 100; ?>%"></div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- PONTOS DISPONÍVEIS -->
        <div class="section section-arcane">
            <h2 class="section-header">🎯 PONTOS DE PROGRESSO</h2>
            <div class="points-grid">
                <div class="point-card">
                    <div class="point-icon">⭐</div>
                    <div class="point-content">
                        <div class="point-value"><?php echo $player_data['pontos_atributo_disponiveis']; ?></div>
                        <div class="point-label">Pontos de Atributo</div>
                        <div class="point-description">Use para aumentar atributos base</div>
                    </div>
                </div>
                
                <div class="point-card">
                    <div class="point-icon">🎯</div>
                    <div class="point-content">
                        <div class="point-value"><?php echo $player_data['pontos_habilidade_disponiveis']; ?></div>
                        <div class="point-label">Pontos de Habilidade</div>
                        <div class="point-description">Aprenda e melhore skills</div>
                    </div>
                </div>
            </div>
            <div class="section section-arcane">
    <h2 class="section-header">👻 VÍNCULOS COM ECOS</h2>
    <div class="ecos-affinity-grid">
        <?php
        // QUERY CORRIGIDA - pegar nome da tabela ecos_base
        $sql_ecos_affinity = "SELECT eb.nome, pe.affinity_level, pe.affinity_xp, 
                             eb.rank_eco, eb.tipo_eco,
                             (pe.affinity_level * 100) as xp_necessario
                              FROM personagem_ecos pe
                              JOIN ecos_base eb ON pe.id_eco_base = eb.id
                              WHERE pe.id_personagem = $player_id
                              ORDER BY pe.affinity_level DESC, pe.affinity_xp DESC";
        $result_ecos_affinity = $conexao->query($sql_ecos_affinity);
        
        if ($result_ecos_affinity && $result_ecos_affinity->num_rows > 0): 
            while($eco = $result_ecos_affinity->fetch_assoc()):
                $percentual_xp = min(100, ($eco['affinity_xp'] / $eco['xp_necessario']) * 100);
        ?>
        <div class="affinity-card">
            <div class="affinity-header">
                <h4><?php echo htmlspecialchars($eco['nome']); ?></h4>
                <span class="eco-rank <?php echo strtolower($eco['rank_eco']); ?>"><?php echo $eco['rank_eco']; ?></span>
            </div>
            <div class="affinity-type"><?php echo $eco['tipo_eco']; ?></div>
            <div class="affinity-level">Vínculo Nv. <?php echo $eco['affinity_level']; ?></div>
            <div class="affinity-bar">
                <div class="affinity-fill" style="width: <?php echo $percentual_xp; ?>%"></div>
            </div>
            <div class="affinity-xp"><?php echo $eco['affinity_xp']; ?>/<?php echo $eco['xp_necessario']; ?> XP</div>
        </div>
        <?php endwhile; ?>
        <?php else: ?>
        <div class="empty-state-small">
            <p>Nenhum Eco recrutado ainda</p>
            <a href="ecos.php" class="btn btn-small">Recrutar Ecos</a>
        </div>
        <?php endif; ?>
    </div>
</div>
            <!-- ESTATÍSTICAS DE COMBATE -->
            <div class="combat-stats">
                <h4>⚔️ Estatísticas de Combate</h4>
                <div class="combat-grid">
                    <div class="combat-stat">
                        <span class="combat-label">Dano Físico</span>
                        <span class="combat-value"><?php echo $equip_bonus['dano_min_total']; ?>-<?php echo $equip_bonus['dano_max_total']; ?></span>
                    </div>
                    <div class="combat-stat">
                        <span class="combat-label">Mitigação</span>
                        <span class="combat-value">+<?php echo $equip_bonus['mitigacao_total']; ?></span>
                    </div>
                    <div class="combat-stat">
                        <span class="combat-label">Chance Crítico</span>
                        <span class="combat-value"><?php echo number_format(($stats_totais['dex'] * 0.5), 1); ?>%</span>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- ATRIBUTOS -->
    <div class="section section-vital">
        <h2 class="section-header vital">📊 ATRIBUTOS</h2>
        
        <div class="attributes-grid">
            <?php
            $atributos_config = [
                'str' => ['nome' => 'Força', 'icon' => '💪', 'cor' => 'var(--status-hp)', 'desc' => 'Aumenta dano físico e carga'],
                'dex' => ['nome' => 'Destreza', 'icon' => '🎯', 'cor' => 'var(--accent-vital)', 'desc' => 'Aumenta chance de crítico e esquiva'],
                'con' => ['nome' => 'Constituição', 'icon' => '🛡️', 'cor' => 'var(--status-gold)', 'desc' => 'Aumenta HP e resistências'],
                'int_stat' => ['nome' => 'Inteligência', 'icon' => '🧠', 'cor' => 'var(--status-mana)', 'desc' => 'Aumenta dano mágico e mana'],
                'wis' => ['nome' => 'Sabedoria', 'icon' => '📚', 'cor' => 'var(--accent-arcane)', 'desc' => 'Aumenta cura e percepção'],
                'cha' => ['nome' => 'Carisma', 'icon' => '✨', 'cor' => 'var(--accent-essence)', 'desc' => 'Melhora interações e preços']
            ];
            
            $tem_pontos_pa = $player_data['pontos_atributo_disponiveis'] > 0;
            
            foreach ($atributos_config as $atributo => $config): 
                $valor_base = $player_data[$atributo];
                $bonus_equip = $equip_bonus['bonus_' . $atributo] ?? 0;
                $valor_total = $stats_totais[$atributo];
            ?>
            <div class="attribute-card">
                <div class="attribute-header" style="border-left-color: <?php echo $config['cor']; ?>">
                    <div class="attribute-icon"><?php echo $config['icon']; ?></div>
                    <div class="attribute-name"><?php echo $config['nome']; ?></div>
                    <div class="attribute-total" style="color: <?php echo $config['cor']; ?>">
                        <?php echo $valor_total; ?>
                    </div>
                </div>
                
                <div class="attribute-breakdown">
                    <div class="breakdown-item">
                        <span class="breakdown-label">Base</span>
                        <span class="breakdown-value"><?php echo $valor_base; ?></span>
                    </div>
                    <div class="breakdown-item">
                        <span class="breakdown-label">Equipamento</span>
                        <span class="breakdown-value <?php echo $bonus_equip > 0 ? 'positive' : ''; ?>">
                            +<?php echo $bonus_equip; ?>
                        </span>
                    </div>
                </div>
                
                <div class="attribute-description">
                    <?php echo $config['desc']; ?>
                </div>
                
                <div class="attribute-actions">
                    <?php if ($tem_pontos_pa): ?>
                    <a href="?acao=gastar_pa&att=<?php echo $atributo; ?>" class="btn-attribute-upgrade">
                        <span class="upgrade-icon">⬆️</span>
                        <span class="upgrade-text">Melhorar</span>
                    </a>
                    <?php else: ?>
                    <span class="btn-attribute-disabled">
                        <span class="upgrade-icon">⏸️</span>
                        <span class="upgrade-text">Sem PA</span>
                    </span>
                    <?php endif; ?>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>

    <!-- HABILIDADES -->
    <div class="section section-arcane">
        <div class="skills-header">
            <h2 class="section-header">🎯 HABILIDADES</h2>
            <div class="skills-tabs">
                <button class="tab-btn active" data-tab="learn">Aprender Habilidades</button>
                <button class="tab-btn" data-tab="known">Grimório</button>
            </div>
        </div>

        <!-- ABA: APRENDER HABILIDADES -->
        <div class="tab-content active" id="learn-tab">
            <?php if ($result_skills_disponiveis->num_rows > 0): ?>
            <div class="skills-grid">
                <?php while($skill = $result_skills_disponiveis->fetch_assoc()): 
                    $pode_aprender = $player_data['pontos_habilidade_disponiveis'] >= $skill['custo_ph'];
                ?>
                <div class="skill-card <?php echo $pode_aprender ? 'available' : 'locked'; ?>">
                    <div class="skill-icon">⚡</div>
                    <div class="skill-info">
                        <h4><?php echo $skill['nome']; ?></h4>
                        <p class="skill-description"><?php echo $skill['descricao']; ?></p>
                        <div class="skill-cost">
                            <span class="cost-label">Custo:</span>
                            <span class="cost-value <?php echo $pode_aprender ? 'affordable' : 'expensive'; ?>">
                                <?php echo $skill['custo_ph']; ?> PH
                            </span>
                        </div>
                    </div>
                    <div class="skill-actions">
                        <?php if ($pode_aprender): ?>
                        <a href="?acao=aprender_skill&skill_id=<?php echo $skill['id']; ?>" class="btn-skill-learn">
                            Aprender
                        </a>
                        <?php else: ?>
                        <span class="btn-skill-locked">
                            PH Insuficiente
                        </span>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endwhile; ?>
            </div>
            <?php else: ?>
            <div class="empty-state">
                <div class="empty-icon">📚</div>
                <h3>Nenhuma Habilidade Disponível</h3>
                <p>Você já aprendeu todas as habilidades disponíveis para sua classe atual.</p>
            </div>
            <?php endif; ?>
        </div>

        <!-- ABA: GRIMÓRIO -->
        <div class="tab-content" id="known-tab">
            <?php if ($result_skills_aprendidas->num_rows > 0): ?>
            <div class="grimorio-grid">
                <?php while($skill = $result_skills_aprendidas->fetch_assoc()): 
                    $pode_melhorar = $player_data['pontos_habilidade_disponiveis'] >= $skill['custo_ph_upgrade'];
                ?>
                <div class="grimorio-card">
                    <div class="skill-level">
                        <span class="level-badge">Nv. <?php echo $skill['skill_level']; ?></span>
                    </div>
                    <div class="skill-content">
                        <h4><?php echo $skill['nome']; ?></h4>
                        <p class="skill-description"><?php echo $skill['descricao']; ?></p>
                        <div class="upgrade-info">
                            <span class="upgrade-cost <?php echo $pode_melhorar ? 'affordable' : 'expensive'; ?>">
                                Próximo nível: <?php echo $skill['custo_ph_upgrade']; ?> PH
                            </span>
                        </div>
                    </div>
                    <div class="skill-actions">
                        <?php if ($pode_melhorar): ?>
                        <a href="?acao=upar_skill&skill_id=<?php echo $skill['id_personagem_skill']; ?>" class="btn-skill-upgrade">
                            Melhorar
                        </a>
                        <?php else: ?>
                        <span class="btn-upgrade-locked">
                            Aguardando PH
                        </span>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endwhile; ?>
            </div>
            <?php else: ?>
            <div class="empty-state">
                <div class="empty-icon">🎯</div>
                <h3>Grimório Vazio</h3>
                <p>Aprenda habilidades na aba "Aprender Habilidades" para preencher seu grimório.</p>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<style>
/* ESTILOS ESPECÍFICOS DA FICHA */
.section-awakening {
    background: linear-gradient(135deg, var(--accent-arcane), var(--accent-essence));
    border: 2px solid var(--accent-arcane);
    margin-bottom: 25px;
}

.awakening-alert {
    display: flex;
    align-items: center;
    gap: 20px;
    padding: 20px;
    background: rgba(0, 0, 0, 0.3);
    border-radius: 8px;
    margin-bottom: 20px;
}

.awakening-icon {
    font-size: 3em;
}

.awakening-content h3 {
    color: white;
    margin-bottom: 5px;
    font-size: 1.4em;
}

.awakening-content p {
    color: rgba(255, 255, 255, 0.8);
    margin: 0;
}

.subclass-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
    gap: 20px;
}

.subclass-card {
    background: var(--bg-primary);
    border: 1px solid var(--accent-arcane);
    border-radius: 8px;
    padding: 20px;
    transition: all 0.3s ease;
}

.subclass-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 10px 30px rgba(138, 43, 226, 0.3);
}

.subclass-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 15px;
}

.subclass-header h4 {
    color: var(--accent-arcane);
    margin: 0;
}

.subclass-badge {
    background: var(--accent-vital);
    color: var(--bg-primary);
    padding: 4px 8px;
    border-radius: 12px;
    font-size: 0.8em;
    font-weight: bold;
}

.subclass-description {
    color: var(--text-secondary);
    margin-bottom: 20px;
    line-height: 1.5;
}

.btn-awakening {
    display: block;
    text-align: center;
    background: linear-gradient(135deg, var(--accent-arcane), var(--accent-essence));
    color: white;
    padding: 12px 20px;
    border-radius: 6px;
    text-decoration: none;
    font-weight: bold;
    transition: all 0.3s ease;
}

.btn-awakening:hover {
    transform: scale(1.05);
    box-shadow: 0 5px 15px rgba(138, 43, 226, 0.4);
}

.character-main {
    display: flex;
    flex-direction: column;
    gap: 25px;
}

.char-identity {
    display: flex;
    align-items: center;
    gap: 20px;
}

.char-avatar {
    width: 80px;
    height: 80px;
    background: var(--bg-secondary);
    border: 2px solid var(--accent-vital);
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
}

.avatar-icon {
    font-size: 2.5em;
}

.char-details h3 {
    color: var(--accent-vital);
    font-size: 1.8em;
    margin-bottom: 10px;
}

.char-tags {
    display: flex;
    gap: 8px;
    flex-wrap: wrap;
}

.char-tag {
    padding: 4px 8px;
    border-radius: 12px;
    font-size: 0.8em;
    font-weight: bold;
}

.char-tag.level {
    background: var(--accent-arcane);
    color: white;
}

.char-tag.class {
    background: var(--accent-vital);
    color: var(--bg-primary);
}

.char-tag.subclass {
    background: var(--accent-essence);
    color: var(--bg-primary);
}

.char-tag.rank {
    background: var(--status-gold);
    color: var(--bg-primary);
}

.char-progress {
    background: var(--bg-secondary);
    padding: 15px;
    border-radius: 8px;
}

.progress-item {
    margin-bottom: 10px;
}

.progress-label {
    display: flex;
    justify-content: space-between;
    margin-bottom: 5px;
    color: var(--text-secondary);
    font-size: 0.9em;
}

.progress-bar {
    height: 8px;
    background: var(--bg-tertiary);
    border-radius: 4px;
    overflow: hidden;
}

.progress-fill {
    height: 100%;
    border-radius: 4px;
    transition: width 0.5s ease;
}

.xp-fill {
    background: linear-gradient(90deg, var(--accent-arcane), var(--accent-essence));
}

.char-resources-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 15px;
}

.resource-card {
    background: var(--bg-secondary);
    padding: 15px;
    border-radius: 8px;
    display: flex;
    align-items: center;
    gap: 15px;
}

.resource-icon {
    font-size: 2em;
}

.resource-info {
    flex: 1;
}

.resource-value {
    font-size: 1.3em;
    font-weight: bold;
    color: var(--text-primary);
    margin-bottom: 2px;
}

.resource-label {
    color: var(--text-secondary);
    font-size: 0.9em;
}

.resource-bar {
    width: 80px;
    height: 8px;
    background: var(--bg-tertiary);
    border-radius: 4px;
    overflow: hidden;
}

.points-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 15px;
    margin-bottom: 25px;
}

.point-card {
    background: var(--bg-primary);
    padding: 20px;
    border-radius: 8px;
    border: 1px solid var(--bg-tertiary);
    display: flex;
    align-items: center;
    gap: 15px;
}

.point-icon {
    font-size: 2.5em;
    opacity: 0.8;
}

.point-value {
    font-size: 2em;
    font-weight: bold;
    color: var(--accent-vital);
    margin-bottom: 5px;
}

.point-label {
    font-weight: bold;
    color: var(--text-primary);
    margin-bottom: 5px;
}

.point-description {
    color: var(--text-secondary);
    font-size: 0.9em;
}

.combat-stats {
    background: var(--bg-primary);
    padding: 20px;
    border-radius: 8px;
    border: 1px solid var(--bg-tertiary);
}

.combat-stats h4 {
    color: var(--accent-vital);
    margin-bottom: 15px;
    border-bottom: 1px solid var(--bg-tertiary);
    padding-bottom: 8px;
}

.combat-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
    gap: 15px;
}

.combat-stat {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 10px 0;
    border-bottom: 1px solid var(--bg-secondary);
}

.combat-stat:last-child {
    border-bottom: none;
}

.combat-label {
    color: var(--text-secondary);
    font-size: 0.9em;
}

.combat-value {
    font-weight: bold;
    color: var(--accent-arcane);
}

.attributes-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
    gap: 20px;
}

.attribute-card {
    background: var(--bg-primary);
    border: 1px solid var(--bg-tertiary);
    border-radius: 8px;
    overflow: hidden;
    transition: all 0.3s ease;
}

.attribute-card:hover {
    border-color: var(--accent-arcane);
    transform: translateY(-2px);
}

.attribute-header {
    display: flex;
    align-items: center;
    gap: 15px;
    padding: 20px;
    background: var(--bg-secondary);
    border-left: 4px solid;
}

.attribute-icon {
    font-size: 1.8em;
}

.attribute-name {
    flex: 1;
    font-weight: bold;
    color: var(--text-primary);
    font-size: 1.1em;
}

.attribute-total {
    font-size: 1.5em;
    font-weight: bold;
}

.attribute-breakdown {
    padding: 15px 20px;
    background: var(--bg-secondary);
}

.breakdown-item {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 5px 0;
}

.breakdown-label {
    color: var(--text-secondary);
    font-size: 0.9em;
}

.breakdown-value {
    font-weight: bold;
    color: var(--text-primary);
}

.breakdown-value.positive {
    color: var(--accent-vital);
}

.attribute-description {
    padding: 15px 20px;
    color: var(--text-secondary);
    font-size: 0.9em;
    border-bottom: 1px solid var(--bg-tertiary);
}

.attribute-actions {
    padding: 15px 20px;
}

.btn-attribute-upgrade {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    padding: 10px 15px;
    background: var(--accent-vital);
    color: var(--bg-primary);
    border-radius: 6px;
    text-decoration: none;
    font-weight: bold;
    transition: all 0.2s ease;
}

.btn-attribute-upgrade:hover {
    background: var(--accent-arcane);
    transform: scale(1.05);
}

.btn-attribute-disabled {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    padding: 10px 15px;
    background: var(--bg-tertiary);
    color: var(--text-secondary);
    border-radius: 6px;
    font-weight: bold;
    opacity: 0.6;
}

.skills-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 25px;
    flex-wrap: wrap;
    gap: 15px;
}

.skills-tabs {
    display: flex;
    background: var(--bg-secondary);
    border-radius: 8px;
    padding: 5px;
}

.tab-btn {
    padding: 10px 20px;
    background: transparent;
    border: none;
    border-radius: 6px;
    color: var(--text-secondary);
    cursor: pointer;
    transition: all 0.2s ease;
    font-weight: bold;
}

.tab-btn:hover {
    color: var(--text-primary);
}

.tab-btn.active {
    background: var(--accent-arcane);
    color: white;
}

.tab-content {
    display: none;
}

.tab-content.active {
    display: block;
}

.skills-grid, .grimorio-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(350px, 1fr));
    gap: 20px;
}

.skill-card, .grimorio-card {
    background: var(--bg-primary);
    border: 1px solid var(--bg-tertiary);
    border-radius: 8px;
    padding: 20px;
    transition: all 0.3s ease;
    display: flex;
    gap: 15px;
}

.skill-card:hover, .grimorio-card:hover {
    border-color: var(--accent-arcane);
    transform: translateY(-2px);
}

.skill-card.available {
    border-left: 4px solid var(--accent-vital);
}

.skill-card.locked {
    border-left: 4px solid var(--text-secondary);
    opacity: 0.7;
}

.skill-icon {
    font-size: 2em;
    opacity: 0.8;
}

.skill-info {
    flex: 1;
}

.skill-info h4 {
    color: var(--text-primary);
    margin-bottom: 8px;
    font-size: 1.1em;
}

.skill-description {
    color: var(--text-secondary);
    font-size: 0.9em;
    margin-bottom: 12px;
    line-height: 1.4;
}

.skill-cost {
    display: flex;
    align-items: center;
    gap: 8px;
}

.cost-label {
    color: var(--text-secondary);
    font-size: 0.9em;
}

.cost-value {
    font-weight: bold;
}

.cost-value.affordable {
    color: var(--accent-vital);
}

.cost-value.expensive {
    color: var(--status-hp);
}

.skill-actions {
    display: flex;
    align-items: flex-start;
}

.btn-skill-learn, .btn-skill-upgrade {
    padding: 8px 15px;
    background: var(--accent-vital);
    color: var(--bg-primary);
    border-radius: 6px;
    text-decoration: none;
    font-weight: bold;
    font-size: 0.9em;
    transition: all 0.2s ease;
}

.btn-skill-learn:hover, .btn-skill-upgrade:hover {
    background: var(--accent-arcane);
    transform: scale(1.05);
}

.btn-skill-locked, .btn-upgrade-locked {
    padding: 8px 15px;
    background: var(--bg-tertiary);
    color: var(--text-secondary);
    border-radius: 6px;
    font-size: 0.9em;
    opacity: 0.6;
}

.grimorio-card {
    border-left: 4px solid var(--accent-arcane);
}

.skill-level {
    display: flex;
    align-items: flex-start;
}

.level-badge {
    background: var(--accent-arcane);
    color: white;
    padding: 4px 8px;
    border-radius: 12px;
    font-size: 0.8em;
    font-weight: bold;
}

.upgrade-info {
    margin-top: 10px;
}

.upgrade-cost {
    font-size: 0.9em;
    font-weight: bold;
}

.upgrade-cost.affordable {
    color: var(--accent-vital);
}

.upgrade-cost.expensive {
    color: var(--status-hp);
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
    .char-identity {
        flex-direction: column;
        text-align: center;
    }
    
    .char-tags {
        justify-content: center;
    }
    
    .char-resources-grid {
        grid-template-columns: 1fr;
    }
    
    .points-grid {
        grid-template-columns: 1fr;
    }
    
    .skills-header {
        flex-direction: column;
        align-items: stretch;
    }
    
    .skills-tabs {
        justify-content: center;
    }
    
    .skills-grid, .grimorio-grid {
        grid-template-columns: 1fr;
    }
    
    .subclass-grid {
        grid-template-columns: 1fr;
    }
    
    .attributes-grid {
        grid-template-columns: 1fr;
    }
    
    .combat-grid {
        grid-template-columns: 1fr;
    }
}

@media (max-width: 768px) {
    .skill-card, .grimorio-card {
        flex-direction: column;
        text-align: center;
    }
    
    .skill-actions {
        justify-content: center;
    }
    
    .awakening-alert {
        flex-direction: column;
        text-align: center;
    }
}
.ecos-affinity-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
    gap: 15px;
    margin-top: 15px;
}

.affinity-card {
    background: var(--bg-primary);
    border: 1px solid var(--bg-tertiary);
    border-radius: 8px;
    padding: 15px;
    transition: all 0.3s ease;
}

.affinity-card:hover {
    border-color: var(--accent-arcane);
    transform: translateY(-2px);
}

.affinity-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 8px;
}

.affinity-header h4 {
    margin: 0;
    color: var(--text-primary);
    font-size: 1.1em;
}

.eco-rank {
    padding: 3px 8px;
    border-radius: 12px;
    font-size: 0.8em;
    font-weight: bold;
    color: white;
}

.eco-rank.s { background: linear-gradient(135deg, #ff6b35, #ff8e53); }
.eco-rank.a { background: linear-gradient(135deg, #c13cff, #e14cff); }
.eco-rank.b { background: linear-gradient(135deg, #3a86ff, #6ba4ff); }
.eco-rank.c { background: linear-gradient(135deg, #38b000, #70e000); }
.eco-rank.d { background: linear-gradient(135deg, #ffd166, #ffde8a); }
.eco-rank.e { background: linear-gradient(135deg, #adb5bd, #ced4da); }

.affinity-type {
    color: var(--text-secondary);
    font-size: 0.9em;
    margin-bottom: 10px;
}

.affinity-level {
    font-weight: bold;
    color: var(--accent-vital);
    margin-bottom: 8px;
    font-size: 0.95em;
}

.affinity-bar {
    height: 8px;
    background: var(--bg-tertiary);
    border-radius: 4px;
    overflow: hidden;
    margin-bottom: 5px;
}

.affinity-fill {
    height: 100%;
    background: linear-gradient(90deg, var(--accent-arcane), var(--accent-essence));
    border-radius: 4px;
    transition: width 0.5s ease;
}

.affinity-xp {
    font-size: 0.8em;
    color: var(--text-secondary);
    text-align: center;
}

.empty-state-small {
    text-align: center;
    padding: 30px 20px;
    color: var(--text-secondary);
}

.empty-state-small p {
    margin-bottom: 15px;
}
</style>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Sistema de Abas
    const tabButtons = document.querySelectorAll('.tab-btn');
    const tabContents = document.querySelectorAll('.tab-content');
    
    tabButtons.forEach(button => {
        button.addEventListener('click', function() {
            const targetTab = this.getAttribute('data-tab');
            
            // Atualiza botões ativos
            tabButtons.forEach(btn => btn.classList.remove('active'));
            this.classList.add('active');
            
            // Mostra conteúdo da aba
            tabContents.forEach(content => {
                content.classList.remove('active');
                if (content.id === targetTab + '-tab') {
                    content.classList.add('active');
                }
            });
        });
    });
    
    // Efeitos visuais para cards
    const attributeCards = document.querySelectorAll('.attribute-card');
    attributeCards.forEach(card => {
        card.addEventListener('mouseenter', function() {
            this.style.transform = 'translateY(-5px)';
            this.style.boxShadow = '0 10px 25px rgba(138, 43, 226, 0.2)';
        });
        
        card.addEventListener('mouseleave', function() {
            this.style.transform = 'translateY(-2px)';
            this.style.boxShadow = 'none';
        });
    });
    
    // Animação para botões de upgrade
    const upgradeButtons = document.querySelectorAll('.btn-attribute-upgrade, .btn-skill-learn, .btn-skill-upgrade');
    upgradeButtons.forEach(button => {
        button.addEventListener('mouseenter', function() {
            this.style.transform = 'scale(1.05)';
        });
        
        button.addEventListener('mouseleave', function() {
            this.style.transform = 'scale(1)';
        });
    });
    
    // Destaque para atributos com bônus
    const positiveBonuses = document.querySelectorAll('.breakdown-value.positive');
    positiveBonuses.forEach(bonus => {
        bonus.style.animation = 'pulse 2s ease-in-out infinite';
    });
});
</script>

<?php include 'footer.php'; ?>