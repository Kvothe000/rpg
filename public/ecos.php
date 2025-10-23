<?php
session_start();
date_default_timezone_set('America/Sao_Paulo');
include_once 'db_connect.php'; // ✅ Use include_once
include_once 'game_logic.php'; // ✅ Use include_once  
include_once 'daily_quests_functions.php';

$titulo_pagina = "Exército de Sombras - Ecos";
$pagina_atual = 'ecos';

// Verifica login
if (!isset($_SESSION['player_id'])) {
    header('Location: login.php');
    exit;
}

$player_id = $_SESSION['player_id'];
$mensagem_feedback = ""; 

// =============================================================================
// PARTE 1: PROCESSAR AÇÕES DO SISTEMA DE ECOS
// =============================================================================

// AÇÃO: Recrutar Eco usando Núcleo
if (isset($_GET['acao']) && $_GET['acao'] === 'recrutar') {
    $sql_check_nucleo = "SELECT id, quantidade FROM inventario WHERE id_personagem = $player_id AND id_item_base = 5 AND equipado = FALSE LIMIT 1";
    $nucleo = $conexao->query($sql_check_nucleo)->fetch_assoc();

    if ($nucleo) {
        // Consome o Núcleo
        if ($nucleo['quantidade'] > 1) {
            $conexao->query("UPDATE inventario SET quantidade = quantidade - 1 WHERE id = {$nucleo['id']}");
        } else {
            $conexao->query("DELETE FROM inventario WHERE id = {$nucleo['id']}");
        }

        // Sorteia um Eco (com probabilidades baseadas no lore)
        $roll = mt_rand(1, 100);
        if ($roll <= 60) {
            $id_eco_sorteado = 1; // 60% - Goblin Fantasma
        } elseif ($roll <= 90) {
            $id_eco_sorteado = 2; // 30% - Lobo Espectral  
        } else {
            $id_eco_sorteado = 3; // 10% - Cavaleiro das Sombras (Raro!)
        }
        
        $conexao->query("INSERT INTO personagem_ecos (id_personagem, id_eco_base, status_eco) VALUES ($player_id, $id_eco_sorteado, 'Descansando')");
        $nome_eco_novo = $conexao->query("SELECT nome FROM ecos_base WHERE id = $id_eco_sorteado")->fetch_assoc()['nome'];
        
        $raridade_cor = $id_eco_sorteado == 3 ? 'rarity-rare' : ($id_eco_sorteado == 2 ? 'rarity-uncommon' : 'rarity-common');
        $mensagem_feedback = "<div class='feedback feedback-success'><span class='{$raridade_cor}'>✨ ECO RECRUTADO!</span> A alma de <strong>{$nome_eco_novo}</strong> se juntou ao seu exército.</div>";
        
        // Após recrutar Eco, adicione:
        atualizar_progresso_achievement($player_id, 'ecos_recrutados', 1, $conexao);
    } else {
        $mensagem_feedback = "<div class='feedback feedback-error'>❌ Você precisa de um <strong>Núcleo de Eco Fraco</strong> para recrutar novas almas.</div>";
    }
}

// AÇÃO: Enviar Eco em Missão
else if (isset($_GET['acao']) && $_GET['acao'] === 'enviar_missao') {
    $id_personagem_eco = (int)$_GET['eco_id']; 
    $duracao_horas = (int)$_GET['horas']; 
    
    $sql_check_eco = "SELECT id FROM personagem_ecos WHERE id = $id_personagem_eco AND id_personagem = $player_id AND status_eco = 'Descansando'";
    $eco_valido = $conexao->query($sql_check_eco)->fetch_assoc();
    
    if ($eco_valido && in_array($duracao_horas, [1, 4, 8])) {
        $sql_update_missao = "UPDATE personagem_ecos SET 
                                status_eco = 'Em Missao',
                                tempo_retorno_missao = NOW() + INTERVAL {$duracao_horas} HOUR
                              WHERE id = $id_personagem_eco";
        
        $conexao->query($sql_update_missao);
        $mensagem_feedback = "<div class='feedback feedback-success'>📤 Eco enviado em missão por <strong>{$duracao_horas}h</strong>. Retornará com recursos da Fenda.</div>";
        
    } else {
        $mensagem_feedback = "<div class='feedback feedback-error'>Não foi possível enviar este Eco em missão.</div>";
    }
}

// AÇÃO: Coletar Recompensa da Missão
else if (isset($_GET['acao']) && $_GET['acao'] === 'coletar') {
    $id_personagem_eco = (int)$_GET['eco_id'];
    
    $sql_check_coleta = "SELECT pe.id, eb.nome, eb.rank_eco, eb.bonus_ouro_hora, eb.chance_material_raro, pe.tempo_retorno_missao
                         FROM personagem_ecos pe
                         JOIN ecos_base eb ON pe.id_eco_base = eb.id
                         WHERE pe.id = $id_personagem_eco 
                           AND pe.id_personagem = $player_id 
                           AND pe.status_eco = 'Em Missao'
                           AND pe.tempo_retorno_missao <= NOW()";
                           
    $eco_pronto = $conexao->query($sql_check_coleta)->fetch_assoc();

    if ($eco_pronto) {
        // Calcula recompensas baseadas no rank do Eco
        $multiplicador_rank = [
            'E' => 1.0,
            'D' => 1.5, 
            'C' => 2.0,
            'B' => 3.0,
            'A' => 5.0
        ];
        
        $rank_eco = $eco_pronto['rank_eco'];
        $multiplicador = $multiplicador_rank[$rank_eco] ?? 1.0;
        
        $ouro_ganho = floor($eco_pronto['bonus_ouro_hora'] * $multiplicador);
        $xp_ganho = floor($ouro_ganho * 0.1); // XP baseado no ouro
        
        // Chance de itens especiais
        $material_ganho_id = null;
        $quantidade_material = 0;
        $chance_ajustada = $eco_pronto['chance_material_raro'] * $multiplicador;
        
        if (mt_rand(1, 100) / 100.0 <= $chance_ajustada) {
            $materiais_possiveis = [2, 10, 15]; // IDs de materiais
            $material_ganho_id = $materiais_possiveis[array_rand($materiais_possiveis)];
            $quantidade_material = mt_rand(1, 3 * $multiplicador); 
        }

        // Adiciona recompensas ao jogador
        $conexao->query("UPDATE personagens SET ouro = ouro + $ouro_ganho, xp_atual = xp_atual + $xp_ganho WHERE id = $player_id");
        $mensagem_feedback = "<div class='feedback feedback-success'>";
        $mensagem_feedback .= "💰 <strong>{$eco_pronto['nome']}</strong> retornou! ";
        $mensagem_feedback .= "Você recebeu <span class='gold-value'>{$ouro_ganho} Ouro</span> e <span class='xp-value'>{$xp_ganho} XP</span>.";
        $mensagem_feedback .= "</div>";
        
        // Processa loot de material se houve sorte
        if ($material_ganho_id) {
            $player_data_temp = $conexao->query("SELECT * FROM personagens WHERE id=$player_id")->fetch_assoc(); 
            $loot_material_msg = processar_auto_loot($player_id, $player_data_temp, $conexao, $material_ganho_id, $quantidade_material);
            $mensagem_feedback .= "<div class='feedback feedback-warning'>📦 " . strip_tags($loot_material_msg) . "</div>";
        }

        // NA AÇÃO 'coletar', APÓS dar as recompensas, adicione:
        $affinity_xp_ganho = 10 + ($multiplicador * 5); // Base 10 + bônus por rank
        $level_up = ganhar_affinity($player_id, $id_personagem_eco, $affinity_xp_ganho, $conexao);

        // Atualizar missão de completar missões de eco
        atualizar_progresso_missao($player_id, 'completar_missoes', 1, $conexao);

if ($level_up) {
    $mensagem_feedback .= "<div class='feedback feedback-success'>🌟 <strong>Vínculo Fortalecido!</strong> Affinity aumentou para o nível " . ($affinity_data['affinity_level'] + 1) . "!</div>";
}

        // Reseta o status do Eco
        $conexao->query("UPDATE personagem_ecos SET 
                            status_eco = 'Descansando',
                            tempo_retorno_missao = NULL
                          WHERE id = $id_personagem_eco");
                          
    } else {
        $mensagem_feedback = "<div class='feedback feedback-error'>Este Eco ainda não retornou da missão ou não está pronto para coleta.</div>";
    }
}

// =============================================================================
// PARTE 2: CARREGAR DADOS PARA EXIBIÇÃO
// =============================================================================

// Carrega dados do jogador
$sql_player = "SELECT * FROM personagens WHERE id = $player_id";
$player_data = $conexao->query($sql_player)->fetch_assoc();
if (!$player_data) { session_destroy(); header('Location: login.php'); exit; }

// Carrega os Ecos do jogador
$sql_ecos_jogador = "SELECT pe.id, pe.status_eco, pe.tempo_retorno_missao, 
                            eb.nome, eb.rank_eco, eb.tipo_eco, eb.bonus_ouro_hora,
                            eb.descricao, eb.chance_material_raro
                     FROM personagem_ecos pe
                     JOIN ecos_base eb ON pe.id_eco_base = eb.id
                     WHERE pe.id_personagem = $player_id
                     ORDER BY 
                         CASE eb.rank_eco 
                             WHEN 'S' THEN 6 WHEN 'A' THEN 5 WHEN 'B' THEN 4 
                             WHEN 'C' THEN 3 WHEN 'D' THEN 2 WHEN 'E' THEN 1 
                         END DESC,
                         pe.status_eco";
$result_ecos = $conexao->query($sql_ecos_jogador);

// Conta Núcleos de Eco disponíveis
$sql_count_nucleos = "SELECT SUM(quantidade) AS total FROM inventario WHERE id_personagem = $player_id AND id_item_base = 5";
$total_nucleos = $conexao->query($sql_count_nucleos)->fetch_assoc()['total'] ?? 0;

// Estatísticas do Exército
$total_ecos = $result_ecos->num_rows;
$ecos_ativos = 0;
$ecos_em_missao = 0;
$rendimento_total_hora = 0;

if ($total_ecos > 0) {
    $result_ecos->data_seek(0); // Reset do ponteiro
    while($eco = $result_ecos->fetch_assoc()) {
        if ($eco['status_eco'] == 'Descansando') {
            $ecos_ativos++;
        } else {
            $ecos_em_missao++;
        }
        $rendimento_total_hora += $eco['bonus_ouro_hora'];
    }
    $result_ecos->data_seek(0); // Reset novamente para o loop de exibição
}

// Inicializar habilidades base se não existirem - VERSÃO CORRIGIDA
$sql_check_habilidades = "SELECT COUNT(*) as total FROM eco_habilidades_base";
$result_check = $conexao->query($sql_check_habilidades);
if ($result_check->fetch_assoc()['total'] == 0) {
    
    // PRIMEIRO: Verificar quais IDs de ecos_base realmente existem
    $sql_ecos_existentes = "SELECT id FROM ecos_base ORDER BY id";
    $result_ecos = $conexao->query($sql_ecos_existentes);
    $ecos_ids = [];
    while($row = $result_ecos->fetch_assoc()) {
        $ecos_ids[] = $row['id'];
    }
    
    // SE não houver ecos_base, criar alguns básicos
    if (empty($ecos_ids)) {
        $ecos_base = [
            [1, 'Goblin Fantasma', 'E', 'Espectral', 'Um goblin que perdeu sua forma física mas não sua ganância', 20, 0.05],
            [2, 'Lobo Espectral', 'D', 'Primal', 'Espírito de um lobo que caça nas fronteiras da Fenda', 35, 0.08],
            [3, 'Cavaleiro das Sombras', 'C', 'Guardião', 'Alma de um cavaleiro que jurou proteger o equilíbrio dimensional', 60, 0.15]
        ];
        
        foreach ($ecos_base as $eco) {
            $conexao->query("INSERT INTO ecos_base (id, nome, rank_eco, tipo_eco, descricao, bonus_ouro_hora, chance_material_raro) 
                            VALUES ($eco[0], '$eco[1]', '$eco[2]', '$eco[3]', '$eco[4]', $eco[5], $eco[6])");
        }
        $ecos_ids = [1, 2, 3];
    }
    
    // AGORA criar habilidades apenas para os ecos_base que existem
    $habilidades_base = [];
    
    if (in_array(1, $ecos_ids)) {
        $habilidades_base = array_merge($habilidades_base, [
            [1, 2, 'Pilhagem Fantasma', 'passiva', 'Aumenta ouro de missões em 10%', '{"bonus_ouro": 0.1}'],
            [1, 4, 'Furtividade Espectral', 'passiva', 'Aumenta chance de loot raro em 15%', '{"bonus_loot": 0.15}'],
            [1, 6, 'Assalto Dimensional', 'ativa', 'Missões produzem 2x ouro por 4h', '{"multiplicador_ouro": 2.0, "duracao": 4}']
        ]);
    }
    
    if (in_array(2, $ecos_ids)) {
        $habilidades_base = array_merge($habilidades_base, [
            [2, 2, 'Faro da Fenda', 'passiva', 'Aumenta XP de missões em 20%', '{"bonus_xp": 0.2}'],
            [2, 5, 'Uivo da Lua', 'passiva', 'Reduz tempo de missão em 25%', '{"reducao_tempo": 0.25}'],
            [2, 8, 'Alcateia Espectral', 'ativa', 'Todos os Ecos ganham +50% eficiência por 2h', '{"bonus_geral": 0.5, "duracao": 2}']
        ]);
    }
    
    if (in_array(3, $ecos_ids)) {
        $habilidades_base = array_merge($habilidades_base, [
            [3, 3, 'Proteção Espectral', 'passiva', 'Reduz dano recebido pelo jogador em 5%', '{"reducao_dano": 0.05}'],
            [3, 6, 'Juramento da Guarda', 'passiva', 'Aumenta chance de crítico do jogador em 8%', '{"bonus_critico": 0.08}'],
            [3, 10, 'Legião das Sombras', 'ativa', 'Desbloqueia missão especial da Entidade Guardiã', '{"missao_especial": true}']
        ]);
    }
    
    foreach ($habilidades_base as $hab) {
        $conexao->query("INSERT INTO eco_habilidades_base 
                        (id_eco_base, nivel_affinity_requerido, nome_habilidade, tipo_habilidade, descricao, efeito_bonus) 
                        VALUES ($hab[0], $hab[1], '$hab[2]', '$hab[3]', '$hab[4]', '$hab[5]')");
    }
    
    echo "<!-- ✅ Sistema de habilidades inicializado -->";
}

include 'header.php'; 
?>

<div class="container fade-in">
    <!-- CABEÇALHO DO EXÉRCITO DE SOMBRAS -->
    <div class="section section-arcane text-center">
        <h1 style="color: var(--accent-arcane); text-shadow: 0 0 20px var(--accent-arcane-glow);">
            👻 EXÉRCITO DE SOMBRAS
        </h1>
        <p style="color: var(--text-secondary);">
            Almas resgatadas da Fenda Arcana que agora servem sob seu comando
        </p>
    </div>

    <?php echo $mensagem_feedback; ?>

    <!-- PAINEL DE RECURSOS E RECRUTAMENTO -->
    <div class="grid-2-col">
        <div class="section section-vital">
            <h2 class="section-header vital">📦 RECURSOS DE RECRUTAMENTO</h2>
            <div class="resources-panel">
                <div class="resource-item">
                    <div class="resource-icon">💰</div>
                    <div class="resource-info">
                        <div class="resource-name">Ouro Disponível</div>
                        <div class="resource-value gold-value"><?php echo number_format($player_data['ouro']); ?></div>
                    </div>
                </div>
                
                <div class="resource-item">
                    <div class="resource-icon">🔄</div>
                    <div class="resource-info">
                        <div class="resource-name">Núcleos de Eco</div>
                        <div class="resource-value"><?php echo $total_nucleos; ?> disponíveis</div>
                    </div>
                </div>
                
                <div class="resource-item">
                    <div class="resource-icon">⚡</div>
                    <div class="resource-info">
                        <div class="resource-name">Rendimento Total/h</div>
                        <div class="resource-value gold-value"><?php echo number_format($rendimento_total_hora); ?> Ouro</div>
                    </div>
                </div>
            </div>
            
            <?php if ($total_nucleos > 0): ?>
                <div class="text-center" style="margin-top: 20px;">
                    <a href="?acao=recrutar" class="btn btn-primary glow-effect" style="font-size: 1.1em;">
                        🎯 USAR NÚCLEO PARA RECRUTAR ECO
                    </a>
                    <p style="color: var(--text-secondary); margin-top: 10px; font-size: 0.9em;">
                        Invoca uma alma aleatória da Fenda Arcana para juntar-se ao seu exército
                    </p>
                </div>
            <?php else: ?>
                <div class="text-center" style="margin-top: 20px; padding: 20px; background: var(--bg-primary); border-radius: 8px;">
                    <p style="color: var(--text-secondary); margin-bottom: 15px;">
                        💡 <strong>Como obter Núcleos:</strong>
                    </p>
                    <ul style="text-align: left; color: var(--text-secondary); font-size: 0.9em;">
                        <li>Derrote monstros raros em Portais Rank C+</li>
                        <li>Complete missões diárias do Conselho</li>
                        <li>Compre na Loja da Guilda por 1000 Ouro</li>
                    </ul>
                </div>
            <?php endif; ?>
        </div>

        <!-- ESTATÍSTICAS DO EXÉRCITO -->
        <div class="section section-arcane">
            <h2 class="section-header">📊 ESTATÍSTICAS DO EXÉRCITO</h2>
            <div class="army-stats">
                <div class="stat-grid">
                    <div class="stat-card">
                        <div class="stat-value"><?php echo $total_ecos; ?></div>
                        <div class="stat-label">Total de Ecos</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-value"><?php echo $ecos_ativos; ?></div>
                        <div class="stat-label">Disponíveis</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-value"><?php echo $ecos_em_missao; ?></div>
                        <div class="stat-label">Em Missão</div>
                    </div>
                </div>
                
                <div class="army-progress">
                    <div class="progress-label">
                        <span>Poder Total do Exército</span>
                        <span><?php echo min(100, $total_ecos * 10); ?>%</span>
                    </div>
                    <div class="progress-bar">
                        <div class="progress-fill" style="width: <?php echo min(100, $total_ecos * 10); ?>%"></div>
                    </div>
                </div>
                
                <div class="next-unlock">
                    <h4 style="color: var(--accent-vital); margin-bottom: 10px;">🎯 Próximo Desbloqueio</h4>
                    <p style="color: var(--text-secondary); font-size: 0.9em;">
                        <?php
                        if ($total_ecos < 3) {
                            echo "Recrute <strong>".(3 - $total_ecos)." Eco(s)</strong> para desbloquear Missões em Grupo";
                        } elseif ($total_ecos < 5) {
                            echo "Recrute <strong>".(5 - $total_ecos)." Eco(s)</strong> para desbloquear Missões Avançadas";
                        } else {
                            echo "🎊 <strong>Exército Máximo</strong> - Todas as missões desbloqueadas!";
                        }
                        ?>
                    </p>
                </div>
            </div>
        </div>
    </div>

    <!-- LISTA DE ECOS - VERSÃO COM AFFINITY -->
<div class="section section-vital">
    <h2 class="section-header vital">👥 SEUS ECOS - VÍNCULOS DA ALMA</h2>
    
    <?php if ($result_ecos->num_rows > 0): ?>
        <div class="ecos-grid">
            <?php while($eco = $result_ecos->fetch_assoc()): ?>
                <?php
                // LÓGICA DE STATUS E TEMPO (mantida do seu código)
                $status_texto = $eco['status_eco'];
                $acao_pronta = false;
                $tempo_restante_formatado = "";
                $status_class = "";

                if ($eco['status_eco'] == 'Em Missao') {
                    $agora = new DateTime();
                    $retorno = new DateTime($eco['tempo_retorno_missao']);
                    
                    if ($agora >= $retorno) {
                        $status_texto = "Pronto para Coletar";
                        $acao_pronta = true;
                        $status_class = "status-ready";
                    } else {
                        $intervalo = $agora->diff($retorno);
                        $tempo_restante_formatado = $intervalo->format('%Hh %Im %Ss');
                        $status_texto = "Em Missão";
                        $status_class = "status-busy";
                    }
                } else {
                    $status_class = "status-available";
                }
                
                // Carrega affinity e habilidades
                $sql_affinity = "SELECT affinity_level, affinity_xp, habilidades_desbloqueadas 
                                FROM personagem_ecos WHERE id = ?";
                $stmt_affinity = $conexao->prepare($sql_affinity);
                $stmt_affinity->bind_param("i", $eco['id']);
                $stmt_affinity->execute();
                $affinity_data = $stmt_affinity->get_result()->fetch_assoc();
                $affinity_level = $affinity_data['affinity_level'] ?? 1;
                $affinity_xp = $affinity_data['affinity_xp'] ?? 0;
                $xp_necessario = $affinity_level * 100;
                
                // CORREÇÃO: Verificar se id_eco_base existe e usar prepared statement
                $habilidades = [];
                if (isset($eco['id_eco_base']) && !empty($eco['id_eco_base'])) {
                    $sql_habilidades = "SELECT * FROM eco_habilidades_base 
                                       WHERE id_eco_base = ? 
                                       ORDER BY nivel_affinity_requerido";
                    $stmt_hab = $conexao->prepare($sql_habilidades);
                    $stmt_hab->bind_param("i", $eco['id_eco_base']);
                    $stmt_hab->execute();
                    $habilidades_result = $stmt_hab->get_result();
                    $habilidades = $habilidades_result->fetch_all(MYSQLI_ASSOC);
                }
                
                // Cor baseada no Rank
                $rank_colors = [
                    'E' => 'rarity-common',
                    'D' => 'rarity-uncommon', 
                    'C' => 'rarity-rare',
                    'B' => 'rarity-epic',
                    'A' => 'rarity-legendary',
                    'S' => 'rarity-essence'
                ];
                $rank_class = $rank_colors[$eco['rank_eco']] ?? 'rarity-common';
                ?>
                
                <div class="eco-card <?php echo $status_class; ?>">
                    <!-- CABEÇALHO COM AFFINITY -->
                    <div class="eco-header">
                        <div class="eco-basic-info">
                            <h4 class="<?php echo $rank_class; ?>"><?php echo $eco['nome']; ?></h4>
                            <div class="affinity-info">
                                <span class="affinity-level">Vínculo Nv. <?php echo $affinity_level; ?></span>
                                <div class="affinity-bar">
                                    <div class="affinity-fill" style="width: <?php echo min(100, ($affinity_xp / $xp_necessario) * 100); ?>%"></div>
                                </div>
                                <small><?php echo $affinity_xp; ?>/<?php echo $xp_necessario; ?> XP</small>
                            </div>
                        </div>
                        <div class="eco-type"><?php echo $eco['tipo_eco']; ?></div>
                    </div>
                    
                    <div class="eco-description">
                        <p><?php echo $eco['descricao']; ?></p>
                    </div>
                    
                    <!-- HABILIDADES DESBLOQUEADAS -->
                    <div class="eco-habilidades">
                        <h5>🎯 Habilidades da Alma</h5>
                        <div class="habilidades-list">
                            <?php if (!empty($habilidades)): ?>
                                <?php foreach($habilidades as $hab): 
                                    $desbloqueada = $affinity_level >= $hab['nivel_affinity_requerido'];
                                    $hab_class = $desbloqueada ? 'habilidade-desbloqueada' : 'habilidade-bloqueada';
                                ?>
                                    <div class="habilidade-item <?php echo $hab_class; ?>">
                                        <div class="habilidade-icon">
                                            <?php echo $hab['tipo_habilidade'] == 'ativa' ? '⚡' : '🛡️'; ?>
                                        </div>
                                        <div class="habilidade-info">
                                            <strong><?php echo $hab['nome_habilidade']; ?></strong>
                                            <span class="habilidade-desc"><?php echo $hab['descricao']; ?></span>
                                            <?php if (!$desbloqueada): ?>
                                                <small class="nivel-requerido">Vínculo Nv. <?php echo $hab['nivel_affinity_requerido']; ?></small>
                                            <?php else: ?>
                                                <small class="habilidade-ativa">✅ ATIVA</small>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <div class="habilidade-item habilidade-bloqueada">
                                    <div class="habilidade-info">
                                        <strong>Habilidades não disponíveis</strong>
                                        <span class="habilidade-desc">Este eco não possui habilidades registradas</span>
                                    </div>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <div class="eco-stats">
                        <div class="eco-stat">
                            <span class="stat-label">Rendimento/h</span>
                            <span class="stat-value gold-value"><?php echo $eco['bonus_ouro_hora']; ?> Ouro</span>
                        </div>
                        <div class="eco-stat">
                            <span class="stat-label">Chance de Loot</span>
                            <span class="stat-value"><?php echo ($eco['chance_material_raro'] * 100); ?>%</span>
                        </div>
                    </div>
                    
                    <div class="eco-status <?php echo $status_class; ?>">
                        <span class="status-indicator"></span>
                        <span class="status-text"><?php echo $status_texto; ?></span>
                        <?php if ($tempo_restante_formatado): ?>
                            <span class="status-time">(<?php echo $tempo_restante_formatado; ?>)</span>
                        <?php endif; ?>
                    </div>
                    
                    <div class="eco-actions">
                        <?php if ($eco['status_eco'] == 'Descansando'): ?>
                            <div class="mission-buttons">
                                <a href="?acao=enviar_missao&eco_id=<?php echo $eco['id']; ?>&horas=1" class="btn btn-success btn-small">1h</a>
                                <a href="?acao=enviar_missao&eco_id=<?php echo $eco['id']; ?>&horas=4" class="btn btn-success btn-small">4h</a>
                                <a href="?acao=enviar_missao&eco_id=<?php echo $eco['id']; ?>&horas=8" class="btn btn-success btn-small">8h</a>
                            </div>
                        <?php elseif ($acao_pronta): ?>
                            <a href="?acao=coletar&eco_id=<?php echo $eco['id']; ?>" class="btn btn-warning btn-full">
                                🎁 COLETAR RECOMPENSAS
                            </a>
                        <?php else: ?>
                            <div class="btn btn-small btn-disabled">Aguardando Retorno</div>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endwhile; ?>
        </div>
    <?php else: ?>
        <div class="empty-state">
            <div class="empty-icon">👻</div>
            <h3 style="color: var(--text-secondary); margin-bottom: 10px;">Exército Vazio</h3>
            <p style="color: var(--text-secondary); margin-bottom: 20px;">
                Você ainda não recrutou nenhuma alma para seu exército.<br>
                Use um Núcleo de Eco para começar a recrutar.
            </p>
            <?php if ($total_nucleos > 0): ?>
                <a href="?acao=recrutar" class="btn btn-primary">RECRUTAR PRIMEIRO ECO</a>
            <?php else: ?>
                <a href="combate_portal.php?rank=C" class="btn btn-primary">CAÇAR POR NÚCLEOS</a>
            <?php endif; ?>
        </div>
    <?php endif; ?>
</div>

<style>
/* ESTILOS ESPECÍFICOS PARA A PÁGINA DE ECOS */
.grid-2-col {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 20px;
    margin-bottom: 25px;
}

@media (max-width: 968px) {
    .grid-2-col {
        grid-template-columns: 1fr;
    }
}

.resources-panel {
    display: flex;
    flex-direction: column;
    gap: 15px;
}

.resource-item {
    display: flex;
    align-items: center;
    gap: 15px;
    padding: 15px;
    background: var(--bg-primary);
    border-radius: 8px;
    border: 1px solid var(--bg-tertiary);
}

.resource-icon {
    font-size: 2em;
    width: 60px;
    text-align: center;
}

.resource-info {
    flex: 1;
}

.resource-name {
    color: var(--text-secondary);
    font-size: 0.9em;
    margin-bottom: 5px;
}

.resource-value {
    font-size: 1.2em;
    font-weight: bold;
}

.army-stats {
    display: flex;
    flex-direction: column;
    gap: 20px;
}

.stat-grid {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 10px;
}

.stat-card {
    background: var(--bg-primary);
    padding: 15px;
    border-radius: 8px;
    text-align: center;
    border: 1px solid var(--bg-tertiary);
}

.stat-value {
    font-size: 2em;
    font-weight: bold;
    color: var(--accent-vital);
    margin-bottom: 5px;
}

.stat-label {
    color: var(--text-secondary);
    font-size: 0.9em;
}

.army-progress {
    background: var(--bg-primary);
    padding: 15px;
    border-radius: 8px;
    border: 1px solid var(--bg-tertiary);
}

.progress-label {
    display: flex;
    justify-content: space-between;
    margin-bottom: 8px;
    color: var(--text-secondary);
    font-size: 0.9em;
}

.progress-bar {
    width: 100%;
    height: 12px;
    background: var(--bg-tertiary);
    border-radius: 6px;
    overflow: hidden;
}

.progress-fill {
    height: 100%;
    background: linear-gradient(90deg, var(--accent-vital), var(--accent-arcane));
    border-radius: 6px;
    transition: width 0.5s ease;
}

.next-unlock {
    background: var(--bg-primary);
    padding: 15px;
    border-radius: 8px;
    border: 1px solid var(--accent-vital);
}

.ecos-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(350px, 1fr));
    gap: 20px;
}

.eco-card {
    background: var(--bg-primary);
    border: 2px solid;
    border-radius: 12px;
    padding: 20px;
    transition: all 0.3s ease;
    position: relative;
    overflow: hidden;
}

.eco-card.status-available {
    border-color: var(--accent-vital);
    background: linear-gradient(135deg, var(--bg-primary) 0%, rgba(80, 200, 120, 0.1) 100%);
}

.eco-card.status-busy {
    border-color: var(--accent-arcane);
    background: linear-gradient(135deg, var(--bg-primary) 0%, rgba(138, 43, 226, 0.1) 100%);
}

.eco-card.status-ready {
    border-color: var(--status-gold);
    background: linear-gradient(135deg, var(--bg-primary) 0%, rgba(255, 215, 0, 0.1) 100%);
    animation: pulse-glow 2s ease-in-out infinite;
}

.eco-header {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    margin-bottom: 15px;
}

.eco-basic-info h4 {
    margin: 0 0 5px 0;
    font-size: 1.2em;
}

.eco-rank {
    padding: 2px 8px;
    border-radius: 12px;
    font-size: 0.8em;
    font-weight: bold;
}

.eco-type {
    background: var(--bg-tertiary);
    padding: 4px 8px;
    border-radius: 6px;
    font-size: 0.8em;
    color: var(--text-secondary);
}

.eco-description {
    color: var(--text-secondary);
    font-size: 0.9em;
    margin-bottom: 15px;
    line-height: 1.4;
}

.eco-stats {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 10px;
    margin-bottom: 15px;
}

.eco-stat {
    background: var(--bg-tertiary);
    padding: 8px;
    border-radius: 6px;
    text-align: center;
}

.stat-label {
    display: block;
    color: var(--text-secondary);
    font-size: 0.8em;
    margin-bottom: 3px;
}

.stat-value {
    display: block;
    font-weight: bold;
    font-size: 0.9em;
}

.eco-status {
    display: flex;
    align-items: center;
    gap: 8px;
    margin-bottom: 15px;
    padding: 8px;
    border-radius: 6px;
    font-size: 0.9em;
    font-weight: bold;
}

.status-available .eco-status { background: rgba(80, 200, 120, 0.2); color: var(--accent-vital); }
.status-busy .eco-status { background: rgba(138, 43, 226, 0.2); color: var(--accent-arcane); }
.status-ready .eco-status { background: rgba(255, 215, 0, 0.2); color: var(--status-gold); }

.status-indicator {
    width: 8px;
    height: 8px;
    border-radius: 50%;
}

.status-available .status-indicator { background: var(--accent-vital); }
.status-busy .status-indicator { background: var(--accent-arcane); }
.status-ready .status-indicator { background: var(--status-gold); }

.status-time {
    color: var(--text-secondary);
    font-weight: normal;
}

.eco-actions {
    margin-top: 15px;
}

.mission-buttons {
    display: flex;
    gap: 8px;
    justify-content: center;
}

.btn-small {
    padding: 8px 12px;
    font-size: 0.85em;
}

.btn-full {
    width: 100%;
    text-align: center;
}

.btn-disabled {
    background: var(--bg-tertiary) !important;
    color: var(--text-secondary) !important;
    border-color: var(--text-secondary) !important;
    cursor: not-allowed;
    width: 100%;
    text-align: center;
}

.empty-state {
    text-align: center;
    padding: 40px 20px;
    background: var(--bg-primary);
    border: 2px dashed var(--bg-tertiary);
    border-radius: 12px;
}

.empty-icon {
    font-size: 4em;
    margin-bottom: 20px;
    opacity: 0.5;
}

.featured-missions {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
    gap: 20px;
}

.mission-featured {
    background: var(--bg-primary);
    border: 2px solid var(--accent-arcane);
    border-radius: 12px;
    padding: 20px;
    position: relative;
}

.mission-badge {
    position: absolute;
    top: -10px;
    right: 20px;
    background: var(--accent-arcane);
    color: white;
    padding: 5px 10px;
    border-radius: 15px;
    font-size: 0.8em;
    font-weight: bold;
}

.mission-featured h4 {
    color: var(--accent-vital);
    margin-bottom: 10px;
}

.mission-featured p {
    color: var(--text-secondary);
    margin-bottom: 15px;
    font-size: 0.9em;
}

.mission-rewards {
    display: flex;
    flex-direction: column;
    gap: 5px;
    margin-bottom: 15px;
}

.reward-item {
    font-size: 0.85em;
    color: var(--text-primary);
}

.mission-requirements {
    font-size: 0.85em;
    color: var(--text-secondary);
    padding-top: 10px;
    border-top: 1px solid var(--bg-tertiary);
}

.xp-value {
    color: var(--accent-vital);
    font-weight: bold;
}

@keyframes pulse-glow {
    0%, 100% { box-shadow: 0 0 10px var(--status-gold); }
    50% { box-shadow: 0 0 20px var(--status-gold); }
}
</style>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Atualização em tempo real dos temporizadores
    function updateTimers() {
        const timeElements = document.querySelectorAll('.status-time');
        timeElements.forEach(element => {
            const text = element.textContent;
            const match = text.match(/(\d+)h (\d+)m (\d+)s/);
            if (match) {
                let hours = parseInt(match[1]);
                let minutes = parseInt(match[2]);
                let seconds = parseInt(match[3]);
                
                // Decrementa o tempo
                seconds--;
                if (seconds < 0) {
                    seconds = 59;
                    minutes--;
                    if (minutes < 0) {
                        minutes = 59;
                        hours--;
                        if (hours < 0) {
                            // Tempo esgotado - recarrega a página
                            location.reload();
                            return;
                        }
                    }
                }
                
                // Atualiza o display
                element.textContent = `(${hours.toString().padStart(2, '0')}h ${minutes.toString().padStart(2, '0')}m ${seconds.toString().padStart(2, '0')}s)`;
            }
        });
    }
    
    // Atualiza a cada segundo
    setInterval(updateTimers, 1000);
    
    // Efeitos de hover nos cards de Eco
    const ecoCards = document.querySelectorAll('.eco-card');
    ecoCards.forEach(card => {
        card.addEventListener('mouseenter', function() {
            this.style.transform = 'translateY(-5px)';
        });
        card.addEventListener('mouseleave', function() {
            this.style.transform = 'translateY(0)';
        });
    });
});
</script>

<?php include 'footer.php'; ?>