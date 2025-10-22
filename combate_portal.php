<?php
session_start();
error_reporting(E_ALL);
ini_set('display_errors', 1);

date_default_timezone_set('America/Sao_Paulo');
include_once 'db_connect.php';
include_once 'game_logic.php'; 
include_once 'daily_quests_functions.php';
include_once 'achievements_functions.php';
include_once 'status_effects_functions.php';
include_once 'combos_functions.php';
include_once 'conditional_skills_functions.php';
include_once 'ultimate_abilities_functions.php'; 
include_once 'dungeon_system.php';



// =============================================================================
// VERIFICAÇÃO DE LOGIN E CARREGAMENTO INICIAL
// =============================================================================

if (!isset($_SESSION['player_id'])) {
    header('Location: login.php');
    exit;
}

$player_id = $_SESSION['player_id'];
$titulo_pagina = "Combate - Portal";
$pagina_atual = 'combate';

// Carrega dados do jogador
$sql_player = "SELECT * FROM personagens WHERE id = $player_id";
$player_data_base = $conexao->query($sql_player)->fetch_assoc();

if (!$player_data_base) {
    session_destroy();
    header('Location: login.php');
    exit;
}
// ✅ VERIFICAR DESBLOQUEIO DE ULTIMATES (adicionar após includes)
function verificar_desbloqueio_ultimates_automatico($player_id, $conexao) {
    $sql_ultimates = "SELECT id FROM ultimate_abilities_base";
    $ultimates = $conexao->query($sql_ultimates);
    
    $desbloqueadas = 0;
    while ($ultimate = $ultimates->fetch_assoc()) {
        $resultado = verificar_desbloqueio_ultimate($player_id, $ultimate['id'], $conexao);
        if ($resultado['desbloqueada']) {
            $desbloqueadas++;
        }
    }
    
    return $desbloqueadas;
}

// VERIFICAR SE VEIO DE DUNGEON DINÂMICA
if (isset($_GET['dungeon_data'])) {
    $dungeon_data_encoded = $_GET['dungeon_data'];
    $dungeon_data = json_decode(base64_decode($dungeon_data_encoded), true);
    
    // ✅ SALVAR DUNGEON NA SESSÃO
    $_SESSION['dungeon_atual'] = $dungeon_data;
    $_SESSION['combate_tipo'] = 'dungeon_dinamica';
    
    // ✅ REDIRECIONAR PARA O MESMO COMBATE, MAS COM FLAG DE DUNGEON
    header("Location: combate_portal.php?dungeon=1&rank=" . ($dungeon_data['dificuldade'] ?? 'D'));
    exit;
}

// ✅ MODIFICAR A GERAÇÃO DE MONSTRO PARA DUNGEONS
if (isset($_SESSION['dungeon_atual']) && $_SESSION['combate_tipo'] === 'dungeon_dinamica') {
    $dungeon = $_SESSION['dungeon_atual'];
    
    if (!isset($_SESSION['combate_ativo'])) {
        // ✅ USAR MONSTROS DA DUNGEON EM VEZ DE GERAR ALEATÓRIOS
        $monstros_dungeon = $dungeon['monstros'];
        $inimigo_atual_index = $_SESSION['inimigo_atual_index'] ?? 0;
        
        if ($inimigo_atual_index < count($monstros_dungeon)) {
            $monstro_dados = $monstros_dungeon[$inimigo_atual_index];
            $_SESSION['inimigo_atual_index'] = $inimigo_atual_index;
            
            // ✅ APLICAR MODIFICADORES DA DUNGEON
            $monstro_dados = aplicar_modificadores_dungeon($monstro_dados, $dungeon['modificadores']);
        } else {
            // ✅ TODOS OS MONSTROS DERROTADOS - IR PARA O CHEFE
            header("Location: combate_chefe.php");
            exit;
        }
    }
}

// ✅ FUNÇÃO PARA APLICAR MODIFICADORES
function aplicar_modificadores_dungeon($monstro, $modificadores) {
    $efeito = $modificadores['efeito'] ?? '';
    
    switch ($efeito) {
        case 'chuva_arcana':
            $monstro['dano_min'] *= 1.2;
            $monstro['dano_max'] *= 1.2;
            break;
        case 'escuridao_total':
            $monstro['dex'] += 5; // Mais chance de acerto
            break;
        case 'fenda_instavel':
            $monstro['hp_max'] *= 0.8;
            $monstro['hp_atual'] *= 0.8;
            break;
    }
    
    return $monstro;
}
// Inicializar combate_id se não existir
if (!isset($_SESSION['combate_id'])) {
    $_SESSION['combate_id'] = uniqid(); // ID único para o combate
}
$combate_id = $_SESSION['combate_id'];

// Carrega bônus de equipamentos
$equip_bonus = carregar_stats_equipados($player_id, $conexao);
$player_stats_total = [
    'nome' => $player_data_base['nome'],
    'level' => $player_data_base['level'],
    'hp_max' => $player_data_base['hp_max'],
    'mana_max' => $player_data_base['mana_max'],
    'str' => $player_data_base['str'] + $equip_bonus['bonus_str'],
    'dex' => $player_data_base['dex'] + $equip_bonus['bonus_dex'],
    'con' => $player_data_base['con'] + $equip_bonus['bonus_con'],
    'int_stat' => $player_data_base['int_stat'] + $equip_bonus['bonus_int'],
    'wis' => $player_data_base['wis'] + $equip_bonus['bonus_wis'],
    'cha' => $player_data_base['cha'] + $equip_bonus['bonus_cha']
];

$recurso_nome = (in_array($player_data_base['classe_base'], ['Mago', 'Sacerdote'])) ? 'Mana' : 'Fúria';

// =============================================================================
// CONFIGURAÇÃO DO COMBATE
// =============================================================================

$mensagem_combate = "";
$rank_escolhido = isset($_GET['rank']) ? strtoupper($_GET['rank']) : 'E';
$ranks_validos = ['E', 'D', 'C'];

if (!in_array($rank_escolhido, $ranks_validos)) {
    $rank_escolhido = 'E';
}

// Limpa sessão antiga se necessário
if (isset($_SESSION['combate_ativo']) && (!isset($_GET['acao']) || (isset($_SESSION['combate_rank']) && $_SESSION['combate_rank'] != $rank_escolhido))) {
    unset($_SESSION['combate_ativo']);
    unset($_SESSION['combate_rank']);
}

// =============================================================================
// INICIALIZAÇÃO DO COMBATE
// =============================================================================

// ✅ CHAMAR NO INÍCIO DO COMBATE (procure onde inicia o combate e adicione)
verificar_desbloqueio_ultimates_automatico($player_id, $conexao);

if (!isset($_SESSION['combate_ativo'])) {
    $monstro_dados = gerar_monstro($rank_escolhido, $conexao);
    
    if (!$monstro_dados) {
        die("Erro: Não foi possível gerar monstro para o Rank {$rank_escolhido}");
    }

    // Carrega skills do jogador
    $sql_skills = "SELECT
                    ps.id_skill_base, ps.skill_level,
                    sb.nome, sb.custo_mana, sb.dano_base, sb.multiplicador_atributo, sb.cooldown_turnos,
                    sb.dano_base_por_level, sb.multiplicador_por_level, sb.atributo_principal
                   FROM personagem_skills ps
                   JOIN skills_base sb ON ps.id_skill_base = sb.id
                   WHERE ps.id_personagem = $player_id";
    
    $result_skills = $conexao->query($sql_skills);
    $skills_prontas = [];
    
    if ($result_skills) {
        while ($skill = $result_skills->fetch_assoc()) {
            $skill['cooldown_restante'] = 0;
            $skills_prontas[$skill['id_skill_base']] = $skill;
        }
    }

    // Inicializa sessão de combate
    $_SESSION['combate_ativo'] = [
        'monstro' => $monstro_dados,
        'turno_atual' => 1,
        'dado_escalada' => 0,
        'jogador_hp_atual' => $player_data_base['hp_atual'],
        'jogador_mana_atual' => $player_data_base['mana_atual'],
        'skills_aprendidas' => $skills_prontas
    ];
    $_SESSION['combate_rank'] = $rank_escolhido;
    
    $mensagem_combate .= "<div class='log-entry'><span class='log-system'>⚔️ Iniciando combate contra <strong>{$monstro_dados['nome']}</strong> (Rank {$rank_escolhido})!</span></div>";
}

// =============================================================================
// FUNÇÃO DE PROCESSAR TURNO COM STATUS EFFECTS
// =============================================================================

function processar_turno_combate($combate_id, $player_id, $conexao) {
    $mensagens = [];
    
    // ✅ 1. PRIMEIRO: Processar status effects do turno
    $efeitos_status = processar_status_effects_turno($combate_id, $conexao);
    foreach ($efeitos_status as $efeito) {
        $mensagens[] = $efeito['mensagem'];
        
        // Se o alvo perdeu turno por status, termina aqui
        if (isset($efeito['perde_turno']) && $efeito['perde_turno'] && 
            $efeito['alvo_id'] == $player_id && $efeito['alvo_tipo'] == 'player') {
            $mensagens[] = "🎯 Você perdeu o turno devido a {$efeito['status_nome']}!";
            return ['mensagens' => $mensagens, 'perdeu_turno' => true];
        }
    }
    
    return ['mensagens' => $mensagens, 'perdeu_turno' => false];
}

// =============================================================================
// LÓGICA DE AÇÕES DO JOGADOR
// =============================================================================

$combate = &$_SESSION['combate_ativo'];
$monstro = &$combate['monstro'];
$skills_combate = $combate['skills_aprendidas'] ?? [];
$acao_jogador_realizada = false;

// ✅ DEFINIR COMBATE_ID (usando session ou criar um)
if (!isset($_SESSION['combate_id'])) {
    $_SESSION['combate_id'] = "combate_" . $player_id . "_" . time();
}
$combate_id = $_SESSION['combate_id'];

// ✅ PROCESSAR STATUS EFFECTS ANTES DAS AÇÕES
$resultado_status = processar_turno_combate($combate_id, $player_id, $conexao);
foreach ($resultado_status['mensagens'] as $msg_status) {
    $mensagem_combate .= "<div class='log-entry'><span class='log-status'>$msg_status</span></div>";
}

// ✅ SE PERDEU TURNO POR STATUS, PULA AÇÕES
if ($resultado_status['perdeu_turno']) {
    $acao_jogador_realizada = true; // Marca como realizado mesmo sem ação
} else {
    // AÇÃO: ATAQUE BÁSICO
    if (isset($_GET['acao']) && $_GET['acao'] === 'atacar') {
        $acao_jogador_realizada = true;
        
        // Cálculo de bônus de escalada
        $dado_escalada_bonus = ($combate['turno_atual'] > 1) ? min(3, $combate['turno_atual'] - 1) : 0;
        $combate['dado_escalada'] = $dado_escalada_bonus;
        
        // Teste de acerto
        $mod_acerto = $player_stats_total['str'] + $dado_escalada_bonus;
        $dc_monstro = 30 + ($monstro['dex'] ?? 10);
        $teste_acerto_jogador = teste_atributo($mod_acerto, $dc_monstro);
        
        $mensagem_combate .= "<div class='log-entry'>";
        $mensagem_combate .= "<span class='log-turn'>🎯 TURNO {$combate['turno_atual']} (Escalada: +{$dado_escalada_bonus})</span>";
        $mensagem_combate .= "<span class='log-action'>{$player_stats_total['nome']} usa <strong>ATAQUE BÁSICO</strong>... (DC: {$dc_monstro})</span>";
        
        if (in_array($teste_acerto_jogador['resultado'], ['sucesso', 'critico'])) {
            // Cálculo de dano
            $dano_arma_min = max(1, $equip_bonus['dano_min_total']);
            $dano_arma_max = max($dano_arma_min, $equip_bonus['dano_max_total']);
            $dano_rolado_arma = mt_rand($dano_arma_min, $dano_arma_max);
            $dano_bruto = $dano_rolado_arma + ($player_stats_total['str'] * 2);
            
            if ($teste_acerto_jogador['resultado'] === 'critico') {
                $dano_bruto *= 2;
                $mensagem_combate .= "<span class='log-critico'> **CRÍTICO!**</span>";
            }
            
            $dano_real = calcular_dano_mitigado($dano_bruto, $monstro['con'] ?? 10, 0);
            
            // ✅ CALCULAR DANO COM STATUS EFFECTS
            $dano_causado = calcular_dano_com_status($dano_real, $combate_id, $player_id, 'player', $conexao);
            // NO ATAQUE BÁSICO, APÓS calcular dano, ADICIONE:
$dano_combo = aplicar_bonus_combo($dano_causado, $player_id);
if ($dano_combo > $dano_causado) {
    $bonus = $dano_combo - $dano_causado;
    $mensagem_combate .= "<span class='log-combo-bonus'> +{$bonus} de bônus de combo!</span>";
    $dano_causado = $dano_combo;
}
// ✅ NO CÁLCULO DE DANO (ataque básico e habilidades), APÓS calcular dano com combo, ADICIONE:
$buff_ultimate = get_buff_ultimate_ativo();
if ($buff_ultimate && $buff_ultimate['dano_dobrado']) {
    $dano_causado *= 2;
    $mensagem_combate .= "<span class='log-ultimate-bonus'> ⚡ DANO DOBRADO pela Ultimate!</span>";
}
            $monstro['hp_atual'] -= $dano_causado;
            
            $mensagem_combate .= "<span class='log-damage'> Causa <strong>{$dano_causado} de dano</strong>!</span>";
        } else {
            $mensagem_combate .= "<span class='log-miss'> Errou o ataque!</span>";
        }
        
        $mensagem_combate .= "</div>";
    }
// ✅ AÇÃO: USAR ULTIMATE ABILITY (adicionar junto com as outras ações)
else if (isset($_GET['acao']) && $_GET['acao'] === 'usar_ultimate' && isset($_GET['ultimate_id'])) {
    $ultimate_id = (int)$_GET['ultimate_id'];
    
    $resultado_ultimate = usar_ultimate_ability($player_id, $ultimate_id, $combate, $conexao);
    
    if ($resultado_ultimate['sucesso']) {
        $acao_jogador_realizada = true;
        $mensagem_combate .= "<div class='log-ultimate'>" . $resultado_ultimate['mensagem'] . "</div>";
        
        // ✅ APLICAR BÔNUS DE ULTIMATE NO DANO
        $buff_ultimate = get_buff_ultimate_ativo();
        if ($buff_ultimate && $buff_ultimate['dano_dobrado']) {
            $_SESSION['ultimate_dano_dobrado'] = true;
        }
    } else {
        $mensagem_combate .= "<div class='log-entry'><span class='log-error'>{$resultado_ultimate['mensagem']}</span></div>";
    }
}
    // AÇÃO: USAR HABILIDADE
    else if (isset($_GET['acao']) && $_GET['acao'] === 'usar_skill' && isset($_GET['skill_id'])) {
    $id_skill_usada = (int)$_GET['skill_id'];
    
    if (isset($skills_combate[$id_skill_usada])) {
        $skill_usada = &$skills_combate[$id_skill_usada];
        $custo_mana = $skill_usada['custo_mana'] ?? 0;
        $cooldown_restante = $skill_usada['cooldown_restante'] ?? 0;
        $cooldown_turnos = $skill_usada['cooldown_turnos'] ?? 0;

        // ✅ VERIFICAR CONDIÇÕES DA HABILIDADE
        $condicoes = verificar_condicoes_habilidade($player_id, $skill_usada['id_skill_base'], $combate, $conexao);
        
        if (!$condicoes['pode_usar']) {
            $mensagem_combate .= "<div class='log-entry'><span class='log-error'>{$condicoes['mensagem']}</span></div>";
        }
        else if ($cooldown_restante > 0) {
            $mensagem_combate .= "<div class='log-entry'><span class='log-error'>Habilidade em recarga!</span></div>";
        }
        else if ($combate['jogador_mana_atual'] < $custo_mana) {
            $mensagem_combate .= "<div class='log-entry'><span class='log-error'>Mana insuficiente!</span></div>";
        }
        else {
            $acao_jogador_realizada = true;
            $combate['jogador_mana_atual'] -= $custo_mana;
            $skill_usada['cooldown_restante'] = $cooldown_turnos;
            
            // ✅ APLICAR CUSTO ALTERNATIVO (se houver)
            $custo_alternativo = aplicar_custo_alternativo($player_id, $skill_usada['id_skill_base'], $combate, $conexao);
            if ($custo_alternativo) {
                $mensagem_combate .= "<div class='log-entry'><span class='log-alternative'>{$custo_alternativo['mensagem']}</span></div>";
            }
            
            $dado_escalada_bonus = ($combate['turno_atual'] > 1) ? min(3, $combate['turno_atual'] - 1) : 0;
            $combate['dado_escalada'] = $dado_escalada_bonus;

            $skill_nome = $skill_usada['nome'] ?? "Habilidade #{$id_skill_usada}";
            $skill_level = $skill_usada['skill_level'] ?? 1;
            
            $mensagem_combate .= "<div class='log-entry'>";
            $mensagem_combate .= "<span class='log-turn'>✨ TURNO {$combate['turno_atual']} (Escalada: +{$dado_escalada_bonus})</span>";
            $mensagem_combate .= "<span class='log-skill'>{$player_stats_total['nome']} usa <strong>{$skill_nome} Nv.{$skill_level}</strong>!</span>";
                // Cálculo de dano da skill
                $nivel_skill = $skill_level;
                $dano_base = $skill_usada['dano_base'] ?? 0;
                $dano_por_level = $skill_usada['dano_base_por_level'] ?? 0;
                $multiplicador = $skill_usada['multiplicador_atributo'] ?? 0;
                $multiplicador_por_level = $skill_usada['multiplicador_por_level'] ?? 0;
                
                $atributo_nome = $skill_usada['atributo_principal'] ?? 'str';
                $atributo_valor = $player_stats_total[$atributo_nome] ?? 10;
                
                $dano_base_total = $dano_base + ($dano_por_level * ($nivel_skill - 1));
                $multiplicador_total = $multiplicador + ($multiplicador_por_level * ($nivel_skill - 1));
                $dano_bruto_skill = $dano_base_total + ($multiplicador_total * $atributo_valor);
                
                $dano_real = calcular_dano_mitigado($dano_bruto_skill, $monstro['con'] ?? 10, 0);
                
                // ✅ CALCULAR DANO COM STATUS EFFECTS
                $dano_causado = calcular_dano_com_status($dano_real, $combate_id, $player_id, 'player', $conexao);
                // NA AÇÃO DE USAR HABILIDADE, APÓS calcular o dano, ADICIONE:

// ✅ VERIFICAR E ATIVAR COMBOS
$resultado_combo = verificar_combo($player_id, $skill_nome, $combate['turno_atual'], $conexao);
if ($resultado_combo['mensagem']) {
    $mensagem_combate .= "<div class='log-combo'>" . $resultado_combo['mensagem'] . "</div>";
}

// ✅ APLICAR BÔNUS DE COMBO NO DANO
$dano_combo = aplicar_bonus_combo($dano_causado, $player_id);
if ($dano_combo > $dano_causado) {
    $bonus = $dano_combo - $dano_causado;
    $mensagem_combate .= "<span class='log-combo-bonus'> +{$bonus} de bônus de combo!</span>";
    $dano_causado = $dano_combo;
}

// ✅ APLICAR BÔNUS DE CRÍTICO DE COMBO
$bonus_critico = get_bonus_critico_combo();
if ($bonus_critico > 0) {
    // Aumenta chance de crítico (integre com seu sistema de crítico)
    $mensagem_combate .= "<span class='log-combo-bonus'> +{$bonus_critico}% crítico de combo!</span>";
}
// ✅ NO CÁLCULO DE DANO (ataque básico e habilidades), APÓS calcular dano com combo, ADICIONE:
$buff_ultimate = get_buff_ultimate_ativo();
if ($buff_ultimate && $buff_ultimate['dano_dobrado']) {
    $dano_causado *= 2;
    $mensagem_combate .= "<span class='log-ultimate-bonus'> ⚡ DANO DOBRADO pela Ultimate!</span>";
}

                $monstro['hp_atual'] -= $dano_causado;
                
                // ✅ APLICAR STATUS EFFECTS DA HABILIDADE (SE TIVER)
                if (isset($skill_usada['aplica_status']) && $skill_usada['aplica_status'] != NULL) {
                    $chance_aplicar = 70; // 70% de chance base
                    
                    if (rand(1, 100) <= $chance_aplicar) {
                        // Para monstro, precisamos do ID do monstro - ajuste conforme seu sistema
                        $monstro_id = $monstro['id'] ?? 1; // ID temporário
                        $status_aplicado = aplicar_status_effect($combate_id, $monstro_id, 'monstro', $skill_usada['aplica_status'], $conexao);
                        
                        if ($status_aplicado) {
                            $mensagem_combate .= "<span class='log-status'> 🎯 {$status_aplicado['icone']} {$status_aplicado['nome']} aplicado!</span>";
                        }
                    }
                }
                
                $mensagem_combate .= "<span class='log-damage'> Causa <strong>{$dano_causado} de dano</strong>! (Baseado em ".strtoupper($atributo_nome).")</span>";
                $mensagem_combate .= "</div>";
            }
        }
    }
    // ✅ NO FINAL DO PROCESSAMENTO DO TURNO (procure onde termina o turno), ADICIONE:
// Processar cooldown de ultimates
processar_cooldown_ultimates($player_id, $conexao);
}

// =============================================================================
// TURNO DO MONSTRO
// =============================================================================

if ($acao_jogador_realizada && isset($monstro['hp_atual']) && $monstro['hp_atual'] > 0) {
    
    $mensagem_combate .= "<div class='log-entry'>";
    $mensagem_combate .= "<span class='log-enemy'>{$monstro['nome']} ataca...</span>";
    
    // Teste de acerto do monstro
    $monstro_dex = $monstro['dex'] ?? 10;
    $monstro_str = $monstro['str'] ?? 5;
    $monstro_dano_min = max(1, $monstro['dano_min'] ?? 1);
    $monstro_dano_max = max($monstro_dano_min, $monstro['dano_max'] ?? 5);
    
    $mod_acerto_monstro = $monstro_dex + $combate['dado_escalada'];
    $dc_jogador = 30 + $player_stats_total['dex'];
    $teste_monstro = teste_atributo($mod_acerto_monstro, $dc_jogador);
    
    if (in_array($teste_monstro['resultado'], ['sucesso', 'critico'])) {
        // Teste de evasão do jogador
        $roll_evasao = roll_d100();
        $chance_evadir = $player_stats_total['dex'];
        
        if ($roll_evasao <= $chance_evadir) {
            $mensagem_combate .= "<span class='log-dodge'> Você <strong>EVADIU</strong> o ataque! ({$roll_evasao} <= {$chance_evadir} DEX)</span>";
        } else {
            // Monstro acertou - calcula dano
            $dano_monstro_rolado = mt_rand($monstro_dano_min, $monstro_dano_max);
            $dano_monstro_bruto = $dano_monstro_rolado + ($monstro_str * 1.5);
            
            if ($teste_monstro['resultado'] === 'critico') {
                $dano_monstro_bruto *= 2;
            }
            
            $dano_real_jogador = calcular_dano_mitigado($dano_monstro_bruto, $player_stats_total['con'], $equip_bonus['mitigacao_total']);
            $combate['jogador_hp_atual'] -= $dano_real_jogador;
            
            $mensagem_combate .= "<span class='log-enemy-damage'> Causa <strong>{$dano_real_jogador} de dano</strong>! (Falhou evasão: {$roll_evasao} > {$chance_evadir})</span>";
            
            // Verifica derrota do jogador
            if ($combate['jogador_hp_atual'] <= 0) {
                $ouro_atual = $player_data_base['ouro'];
                $ouro_perdido = floor($ouro_atual * 0.1);
                $ouro_restante = $ouro_atual - $ouro_perdido;
                
                $conexao->query("UPDATE personagens SET ouro = {$ouro_restante}, hp_atual = 1 WHERE id = {$player_id}");
                
                $_SESSION['flash_message'] = "
                    <h3>Você foi <strong>DERROTADO</strong>!</h3>
                    <p>Seu HP chegou a zero. Você perdeu {$ouro_perdido} Ouro ao ser resgatado.</p>
                ";
                
                unset($_SESSION['combate_ativo']);
                unset($_SESSION['combate_rank']);
                header("Location: mapa.php");
                exit;
            }
        }
    } else {
        $mensagem_combate .= "<span class='log-miss'> Monstro errou o ataque!</span>";
    }
    
    $mensagem_combate .= "</div>";
    
    // Reduz cooldowns das skills
    foreach ($skills_combate as &$skill) {
        if (isset($skill['cooldown_restante']) && $skill['cooldown_restante'] > 0) {
            $skill['cooldown_restante']--;
        }
    }
}

// =============================================================================
// VERIFICAÇÃO DE VITÓRIA
// =============================================================================

if (isset($monstro['hp_atual']) && $monstro['hp_atual'] <= 0) {
    $mensagem_combate .= "<div class='log-entry victory-message'>";
    $mensagem_combate .= "<h3>🎉 <strong>{$monstro['nome']} foi DERROTADO!</strong></h3>";
    
    // Recompensas
    $xp_ganho = $monstro['xp_recompensa'] ?? 0;
    $ouro_ganho = $monstro['ouro_recompensa'] ?? 0;
    
    if ($xp_ganho > 0 && $ouro_ganho > 0) {
        $conexao->query("UPDATE personagens SET xp_atual = xp_atual + {$xp_ganho}, ouro = ouro + {$ouro_ganho} WHERE id = {$player_id}");
        $mensagem_combate .= "<p>💰 Você ganhou <strong>{$xp_ganho} XP</strong> e <strong>{$ouro_ganho} Ouro</strong>!</p>";
    }

    // Atualizar missão de matar monstros
    atualizar_progresso_missao($player_id, 'matar_monstros', 1, $conexao);
    // Após derrotar monstro, adicione:
    atualizar_progresso_achievement($player_id, 'monstros_derrotados', 1, $conexao);

    //Verifica level up
    $player_data_atualizado = $conexao->query("SELECT * FROM personagens WHERE id = $player_id")->fetch_assoc();
    $mensagem_level_up = verificar_level_up($player_id, $player_data_atualizado, $conexao);
    $mensagem_combate .= $mensagem_level_up;
    
    // Processa loot
    $monstro_id_base = $monstro['id_base'] ?? 0;
    $mensagem_combate .= "<div class='loot-section'><strong>Loot Coletado:</strong><br>";
    
    // Loot específico por monstro (exemplo)
    if ($monstro_id_base == 1) { // Slime
        if (mt_rand(1, 100) <= 30) {
            $loot_msg = processar_auto_loot($player_id, $player_data_atualizado, $conexao, 2, mt_rand(1, 3)); // Fragmento de Slime
            $mensagem_combate .= "<div class='loot-item'>{$loot_msg}</div>";
        }
    } else if ($monstro_id_base == 3) { // Esqueleto
        if (mt_rand(1, 100) <= 40) {
            $id_equip = (mt_rand(1, 2) == 1) ? 8 : 9; // Espada ou Armadura
            $loot_msg = processar_auto_loot($player_id, $player_data_atualizado, $conexao, $id_equip, 1);
            $mensagem_combate .= "<div class='loot-item rare'>{$loot_msg}</div>";
        }
        // Sempre dropa ossos
        $loot_msg = processar_auto_loot($player_id, $player_data_atualizado, $conexao, 10, mt_rand(2, 5));
        $mensagem_combate .= "<div class='loot-item'>{$loot_msg}</div>";
    }
    
    $mensagem_combate .= "</div>";
    $mensagem_combate .= "</div>";
    
    // Salva estado do jogador e encerra combate
    $hp_final = max(1, $combate['jogador_hp_atual']);
    $mana_final = max(0, $combate['jogador_mana_atual']);
    $conexao->query("UPDATE personagens SET hp_atual = {$hp_final}, mana_atual = {$mana_final} WHERE id = {$player_id}");
    
    unset($_SESSION['combate_ativo']);
    unset($_SESSION['combate_rank']);
    // ✅ NO FINAL DO COMBATE (quando monstro é derrotado), ADICIONAR:
if (isset($_SESSION['dungeon_atual']) && $_SESSION['combate_tipo'] === 'dungeon_dinamica') {
    // ✅ INCREMENTAR INIMIGO ATUAL
    $_SESSION['inimigo_atual_index']++;
    
    $dungeon = $_SESSION['dungeon_atual'];
    $total_inimigos = count($dungeon['monstros']);
    $inimigo_atual = $_SESSION['inimigo_atual_index'];
    
    // ✅ ACUMULAR RECOMPENSAS
    if (!isset($_SESSION['recompensas_dungeon'])) {
        $_SESSION['recompensas_dungeon'] = [
            'ouro' => 0,
            'xp' => 0,
            'itens' => []
        ];
    }
    
    $_SESSION['recompensas_dungeon']['ouro'] += $monstro['ouro_recompensa'] ?? 0;
    $_SESSION['recompensas_dungeon']['xp'] += $monstro['xp_recompensa'] ?? 0;
    
    // ✅ VERIFICAR SE É O ÚLTIMO INIMIGO ANTES DO CHEFE
    if ($inimigo_atual >= $total_inimigos) {
        $mensagem_combate .= "<div class='log-entry log-system'>";
        $mensagem_combate .= "🎯 <strong>DUNGEON COMPLETA!</strong> Prepare-se para o CHEFE!";
        $mensagem_combate .= "</div>";
        
        // ✅ BOTÃO PARA LUTAR CONTRA O CHEFE
        $mensagem_combate .= "<div class='post-combat-actions'>";
        $mensagem_combate .= "<a href='combate_chefe.php' class='btn btn-primary'>⚔️ ENFRENTAR CHEFE</a>";
        $mensagem_combate .= "</div>";
    } else {
        // ✅ PRÓXIMO INIMIGO
        $proximo_inimigo = $dungeon['monstros'][$inimigo_atual];
        $mensagem_combate .= "<div class='log-entry log-system'>";
        $mensagem_combate .= "🔜 Próximo: <strong>{$proximo_inimigo['tipo']}</strong>";
        $mensagem_combate .= "</div>";
    }
}
}

// Incrementa turno se ação foi realizada
if ($acao_jogador_realizada && isset($_SESSION['combate_ativo'])) {
    $combate['turno_atual']++;
}

include 'header.php';
?>

<div class="container fade-in">
    <?php if (isset($_SESSION['combate_ativo'])): ?>
    
    <!-- CABEÇALHO DO COMBATE -->
    <div class="section section-arcane text-center combat-header">
        <h1>⚔️ PORTAL RANK <?php echo $rank_escolhido; ?></h1>
        <div class="turn-indicator">
            TURNO <?php echo $combate['turno_atual']; ?> • ESCALADA: +<?php echo $combate['dado_escalada']; ?>
        </div>
    </div>

    <!-- GRID DE COMBATENTES -->
    <div class="combat-grid">
        <!-- JOGADOR -->
        <div class="section section-vital combatant-card">
            <div class="combatant-header">
                <h3>🎯 <?php echo htmlspecialchars($player_stats_total['nome']); ?></h3>
                <span class="combatant-level">Nv. <?php echo $player_stats_total['level']; ?></span>
            </div>
    <!-- STATUS EFFECTS ATIVOS -->
    <div class="status-effects-container">
        <h4>🎭 Status Effects Ativos</h4>
    <div class="status-effects-grid">
        <?php
        // Status do jogador
        $status_jogador = get_status_ativos($combate_id, $player_id, 'player', $conexao);
        if ($status_jogador->num_rows > 0):
        ?>
        <div class="status-group">
            <h5>Jogador:</h5>
            <div class="status-list">
                <?php while($status = $status_jogador->fetch_assoc()): ?>
                <div class="status-item" style="border-left-color: <?php echo $status['cor']; ?>">
                    <span class="status-icon"><?php echo $status['icone']; ?></span>
                    <span class="status-name"><?php echo $status['nome']; ?></span>
                    <span class="status-duration"><?php echo $status['duracao_restante']; ?>T</span>
                    <?php if ($status['intensidade'] > 1): ?>
                    <span class="status-intensity">x<?php echo $status['intensidade']; ?></span>
                    <?php endif; ?>
                </div>
                <?php endwhile; ?>
            </div>
        </div>
        <?php endif; ?>
    </div>
    </div>
    <!-- COMBOS ATIVOS -->
<div class="combos-container">
    <h4>🎯 COMBOS ATIVOS</h4>
    <div class="combos-grid">
        <?php
        $combos_ativos = get_combos_ativos($player_id, $conexao);
        if ($combos_ativos && $combos_ativos->num_rows > 0):
            while($combo = $combos_ativos->fetch_assoc()):
                $sequencia = json_decode($combo['sequencia'], true);
                $sequencia_atual = json_decode($combo['sequencia_atual'], true);
                $progresso = count($sequencia_atual);
                $total_passos = count($sequencia);
                 // ✅ CORREÇÃO: Verificar se expira_em_turno existe
                $turnos_restantes = isset($combo['expira_em_turno']) ? ($combo['expira_em_turno'] - ($combate['turno_atual'] ?? 1)) : 0;
        ?>
        <div class="combo-card">
            <div class="combo-header">
                <span class="combo-icon"><?php echo $combo['icone']; ?></span>
                <span class="combo-name"><?php echo $combo['nome']; ?></span>
                <?php if ($turnos_restantes > 0): ?>
                <span class="combo-timer">⏳ <?php echo $turnos_restantes; ?>T</span>
                <?php endif; ?>
            </div>
            <div class="combo-progress">
                <div class="combo-steps">
                    <?php for($i = 0; $i < $total_passos; $i++): ?>
                        <div class="combo-step <?php echo $i < $progresso ? 'completed' : 'pending'; ?>">
                            <?php if ($i < $progresso): ?>
                                ✅
                            <?php else: ?>
                                <?php echo $i + 1; ?>
                            <?php endif; ?>
                        </div>
                        <?php if ($i < $total_passos - 1): ?>
                            <div class="combo-connector"></div>
                        <?php endif; ?>
                    <?php endfor; ?>
                </div>
                <div class="combo-next">
                    Próximo: <strong><?php echo $sequencia[$progresso] ?? 'Completo!'; ?></strong>
                </div>
            </div>
        </div>
        <?php endwhile; ?>
        <?php else: ?>
        <div class="no-combos">
            <p>Nenhum combo ativo. Use habilidades em sequência para ativar combos!</p>
        </div>
        <?php endif; ?>
    </div>
</div>

<!-- BÔNUS DE COMBOS ATIVOS -->
<div class="combo-bonuses">
    <h5>✨ Bônus de Combos Ativos</h5>
    <div class="bonus-list">
        <?php
        $bonus_ativos = [];
        if (isset($_SESSION['combo_bonus_dano'])) {
            $bonus = $_SESSION['combo_bonus_dano'];
            $turnos_restantes = $bonus['duracao'] - (($combate['turno_atual'] ?? 1) - $bonus['turno_ativado']);
            $bonus_ativos[] = "💥 +{$bonus['valor']} dano ({$turnos_restantes}T)";
        }
        if (isset($_SESSION['combo_bonus_magico'])) {
            $bonus = $_SESSION['combo_bonus_magico'];
            $turnos_restantes = $bonus['duracao'] - (($combate['turno_atual'] ?? 1) - $bonus['turno_ativado']);
            $bonus_ativos[] = "🔮 +{$bonus['valor']} dano mágico ({$turnos_restantes}T)";
        }
        if (isset($_SESSION['combo_bonus_critico'])) {
            $bonus = $_SESSION['combo_bonus_critico'];
            $turnos_restantes = $bonus['duracao'] - (($combate['turno_atual'] ?? 1) - $bonus['turno_ativado']);
            $bonus_ativos[] = "🎯 +{$bonus['valor']}% crítico ({$turnos_restantes}T)";
        }
        if (isset($_SESSION['combo_buff_defesa'])) {
            $bonus = $_SESSION['combo_buff_defesa'];
            $turnos_restantes = $bonus['duracao'] - (($combate['turno_atual'] ?? 1) - $bonus['turno_ativado']);
            $bonus_ativos[] = "🛡️ +{$bonus['valor']} defesa ({$turnos_restantes}T)";
        }
        
        if (!empty($bonus_ativos)):
            foreach($bonus_ativos as $bonus):
        ?>
        <div class="bonus-item"><?php echo $bonus; ?></div>
        <?php endforeach; ?>
        <?php else: ?>
        <div class="no-bonus">Nenhum bônus ativo</div>
        <?php endif; ?>
    </div>
</div>
            <div class="health-bar">
                <div class="bar-label">
                    <span>VIDA</span>
                    <span><?php echo $combate['jogador_hp_atual']; ?>/<?php echo $player_stats_total['hp_max']; ?></span>
                </div>
                <div class="bar-container">
                    <div class="bar-fill hp-fill" style="width: <?php echo ($combate['jogador_hp_atual'] / $player_stats_total['hp_max']) * 100; ?>%"></div>
                </div>
            </div>
            
            <div class="resource-bar">
                <div class="bar-label">
                    <span><?php echo strtoupper($recurso_nome); ?></span>
                    <span><?php echo $combate['jogador_mana_atual']; ?>/<?php echo $player_stats_total['mana_max']; ?></span>
                </div>
                <div class="bar-container">
                    <div class="bar-fill mana-fill" style="width: <?php echo ($combate['jogador_mana_atual'] / $player_stats_total['mana_max']) * 100; ?>%"></div>
                </div>
            </div>
            
            <div class="combatant-stats">
                <div class="stat-pair">
                    <span class="stat-label">FOR</span>
                    <span class="stat-value"><?php echo $player_stats_total['str']; ?></span>
                </div>
                <div class="stat-pair">
                    <span class="stat-label">DES</span>
                    <span class="stat-value"><?php echo $player_stats_total['dex']; ?></span>
                </div>
                <div class="stat-pair">
                    <span class="stat-label">CON</span>
                    <span class="stat-value"><?php echo $player_stats_total['con']; ?></span>
                </div>
            </div>
        </div>

        <!-- INIMIGO -->
        <div class="section section-arcane combatant-card">
            <div class="combatant-header">
                <h3>👹 <?php echo htmlspecialchars($monstro['nome']); ?></h3>
                <span class="combatant-rank">Rank <?php echo $rank_escolhido; ?></span>
            </div>
                    <!-- Status do monstro (se tiver sistema de monstros) -->
        <?php
        // $status_monstro = get_status_ativos($combate_id, $monstro_id, 'monstro', $conexao);
        // if ($status_monstro->num_rows > 0): 
        ?>
        <!-- Mostrar status do monstro aqui -->
        <?php // endif; ?>
            <div class="health-bar">
                <div class="bar-label">
                    <span>VIDA</span>
                    <span><?php echo $monstro['hp_atual']; ?>/<?php echo $monstro['hp_max']; ?></span>
                </div>
                <div class="bar-container">
                    <div class="bar-fill enemy-hp-fill" style="width: <?php echo ($monstro['hp_atual'] / $monstro['hp_max']) * 100; ?>%"></div>
                </div>
            </div>
            
            <div class="combatant-stats">
                <div class="stat-pair">
                    <span class="stat-label">FOR</span>
                    <span class="stat-value"><?php echo $monstro['str']; ?></span>
                </div>
                <div class="stat-pair">
                    <span class="stat-label">DES</span>
                    <span class="stat-value"><?php echo $monstro['dex']; ?></span>
                </div>
                <div class="stat-pair">
                    <span class="stat-label">CON</span>
                    <span class="stat-value"><?php echo $monstro['con']; ?></span>
                </div>
            </div>
        </div>
    </div>

    <!-- AÇÕES DE COMBATE -->
    <div class="section section-vital">
        <h2 class="section-header vital">🎮 AÇÕES</h2>
        
        <div class="actions-grid">
            <a href="?rank=<?php echo $rank_escolhido; ?>&acao=atacar" class="btn btn-primary combat-btn">
                <span class="btn-icon">⚔️</span>
                <span class="btn-text">ATAQUE BÁSICO</span>
                <span class="btn-desc">Ataque físico com sua arma</span>
            </a>
            
            <a href="inventario.php" class="btn combat-btn">
                <span class="btn-icon">🎒</span>
                <span class="btn-text">INVENTÁRIO</span>
                <span class="btn-desc">Usar itens e poções</span>
            </a>
            
            <a href="mapa.php" class="btn combat-btn">
                <span class="btn-icon">🏃‍♂️</span>
                <span class="btn-text">RETIRAR-SE</span>
                <span class="btn-desc">Fugir da batalha</span>
            </a>
        </div>
    </div>
<!-- ULTIMATE ABILITIES -->
<div class="ultimate-abilities-container">
    <h4>✨ ULTIMATE ABILITIES</h4>
    <div class="ultimate-abilities-grid">
        <?php
        $ultimates_disponiveis = get_ultimates_disponiveis($player_id, $combate, $conexao);
        if ($ultimates_disponiveis && $ultimates_disponiveis->num_rows > 0):
            while($ultimate = $ultimates_disponiveis->fetch_assoc()):
                $pode_usar = ($ultimate['cooldown_restante'] == 0) && ($combate['jogador_mana_atual'] >= $ultimate['custo_mana']);
                $efeito_principal = json_decode($ultimate['efeito_principal'], true);
        ?>
        <div class="ultimate-card <?php echo $pode_usar ? 'available' : 'unavailable'; ?>" style="border-color: <?php echo $ultimate['cor_efeito']; ?>">
            <div class="ultimate-header">
                <span class="ultimate-icon"><?php echo $ultimate['icone']; ?></span>
                <span class="ultimate-name"><?php echo $ultimate['nome']; ?></span>
                <span class="ultimate-type"><?php echo strtoupper($ultimate['tipo']); ?></span>
            </div>
            
            <div class="ultimate-description">
                <?php echo $ultimate['descricao']; ?>
            </div>
            
            <div class="ultimate-effects">
                <div class="effect-primary">
                    <?php
                    $efeito_texto = "";
                    if (isset($efeito_principal['dano_base'])) {
                        $efeito_texto = "💥 " . $efeito_principal['dano_base'] . " dano";
                    } elseif (isset($efeito_principal['cura_base'])) {
                        $efeito_texto = "💚 " . $efeito_principal['cura_base'] . " cura";
                    } elseif (isset($efeito_principal['protecao'])) {
                        $efeito_texto = "🛡️ " . $efeito_principal['protecao'] . "% proteção";
                    } elseif (isset($efeito_principal['turno_extra'])) {
                        $efeito_texto = "⏰ Turno Extra";
                    } elseif (isset($efeito_principal['dano_dobrado'])) {
                        $efeito_texto = "⚡ Dano Dobrado";
                    } elseif (isset($efeito_principal['ressuscitar'])) {
                        $efeito_texto = "🔥 Ressuscitar";
                    }
                    echo $efeito_texto;
                    ?>
                </div>
                <div class="effect-target">
                    🎯 <?php echo strtoupper($ultimate['alvo']); ?>
                </div>
            </div>
            
            <div class="ultimate-costs">
                <div class="cost-mana">
                    💠 <?php echo $ultimate['custo_mana']; ?> Mana
                </div>
                <div class="cost-cooldown">
                    ⏳ CD: <?php echo $ultimate['cooldown_turnos']; ?>T
                </div>
                <?php if ($ultimate['cooldown_restante'] > 0): ?>
                <div class="cooldown-active">
                    🔄 <?php echo $ultimate['cooldown_restante']; ?>T restantes
                </div>
                <?php endif; ?>
            </div>
            
            <div class="ultimate-actions">
                <?php if ($pode_usar): ?>
                <a href="?acao=usar_ultimate&ultimate_id=<?php echo $ultimate['id']; ?>" 
                   class="btn btn-ultimate"
                   style="background: <?php echo $ultimate['cor_efeito']; ?>">
                    🔥 USAR ULTIMATE
                </a>
                <?php else: ?>
                <span class="btn btn-ultimate-disabled">
                    <?php if ($ultimate['cooldown_restante'] > 0): ?>
                    ⏳ Recarga: <?php echo $ultimate['cooldown_restante']; ?>T
                    <?php else: ?>
                    💠 Mana Insuficiente
                    <?php endif; ?>
                </span>
                <?php endif; ?>
            </div>
            
            <div class="ultimate-stats">
                <small>Usos: <?php echo $ultimate['usos_totais']; ?> | Nv. <?php echo $ultimate['nivel_requerido']; ?>+</small>
            </div>
        </div>
        <?php endwhile; ?>
        <?php else: ?>
        <div class="no-ultimates">
            <p>Nenhuma Ultimate Ability desbloqueada ainda.</p>
            <p>🎯 Alcance nível 10+ e cumpra condições específicas para desbloquear.</p>
        </div>
        <?php endif; ?>
    </div>
</div>

<!-- BUFFS DE ULTIMATE ATIVOS -->
<?php
$buff_ultimate = get_buff_ultimate_ativo();
$protecao_ultimate = $_SESSION['ultimate_protecao'] ?? null;
$turno_extra = $_SESSION['ultimate_turno_extra'] ?? false;
?>
<div class="ultimate-buffs-container">
    <h5>⚡ Efeitos de Ultimate Ativos</h5>
    <div class="ultimate-buffs-list">
        <?php if ($buff_ultimate): ?>
        <div class="ultimate-buff-item" style="border-color: #FF9E00">
            <span class="buff-icon">⚡</span>
            <span class="buff-name">Explosão de Poder</span>
            <span class="buff-duration">
                <?php 
                $turnos_restantes = $buff_ultimate['duracao'] - (($combate['turno_atual'] ?? 1) - $buff_ultimate['turno_ativado']);
                echo "{$turnos_restantes}T restantes";
                ?>
            </span>
            <span class="buff-effect">Dano Dobrado</span>
        </div>
        <?php endif; ?>
        
        <?php if ($protecao_ultimate): ?>
        <div class="ultimate-buff-item" style="border-color: #3A86FF">
            <span class="buff-icon">🛡️</span>
            <span class="buff-name">Parede Indestrutível</span>
            <span class="buff-duration">
                <?php 
                $turnos_restantes = $protecao_ultimate['duracao'] - (($combate['turno_atual'] ?? 1) - $protecao_ultimate['turno_ativado']);
                echo "{$turnos_restantes}T restantes";
                ?>
            </span>
            <span class="buff-effect"><?php echo $protecao_ultimate['valor']; ?>% Proteção</span>
        </div>
        <?php endif; ?>
        
        <?php if ($turno_extra): ?>
        <div class="ultimate-buff-item" style="border-color: #FFD166">
            <span class="buff-icon">⏰</span>
            <span class="buff-name">Controle Temporal</span>
            <span class="buff-effect">Turno Extra Disponível</span>
        </div>
        <?php endif; ?>
        
        <?php if (!$buff_ultimate && !$protecao_ultimate && !$turno_extra): ?>
        <div class="no-ultimate-buffs">
            Nenhum efeito de ultimate ativo
        </div>
        <?php endif; ?>
    </div>
</div>
    <!-- HABILIDADES -->
    <?php if (!empty($skills_combate)): ?>
    <div class="section section-arcane">
        <h2 class="section-header">✨ HABILIDADES</h2>
        
        <div class="skills-grid">
            <?php foreach ($skills_combate as $id_skill => $skill): ?>
                <?php
                $nome_skill = $skill['nome'] ?? "Habilidade";
                $nivel_skill = $skill['skill_level'] ?? 1;
                $cooldown_restante = $skill['cooldown_restante'] ?? 0;
                $custo_mana = $skill['custo_mana'] ?? 0;
                $disponivel = ($cooldown_restante == 0) && ($combate['jogador_mana_atual'] >= $custo_mana);
                ?>
                
                <div class="skill-card <?php echo $disponivel ? 'available' : 'disabled'; ?>">
                    <?php if ($disponivel): ?>
                        <a href="?rank=<?php echo $rank_escolhido; ?>&acao=usar_skill&skill_id=<?php echo $id_skill; ?>" class="skill-link">
                    <?php endif; ?>
                    
                    <div class="skill-header">
                        <span class="skill-name"><?php echo $nome_skill; ?></span>
                        <span class="skill-level">Nv.<?php echo $nivel_skill; ?></span>
                    </div>
                    
                    <div class="skill-cost">
                        <span class="cost-icon">🔷</span>
                        <span class="cost-value"><?php echo $custo_mana; ?> Mana</span>
                    </div>
                    
                    <?php if ($cooldown_restante > 0): ?>
                        <div class="skill-cooldown">
                            <span class="cooldown-icon">⏳</span>
                            <span class="cooldown-value"><?php echo $cooldown_restante; ?> turno(s)</span>
                        </div>
                    <?php endif; ?>
                    
                    <?php if ($disponivel): ?>
                        </a>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        </div>
        <!-- HABILIDADES CONDICIONAIS DISPONÍVEIS -->
<div class="conditional-skills-container">
    <h4>⚡ HABILIDADES ESPECIAIS (Condicionais)</h4>
    <div class="conditional-skills-grid">
        <?php
        $habilidades_condicionais = get_habilidades_condicionais_disponiveis($player_id, $combate, $conexao);
        if (!empty($habilidades_condicionais)):
            foreach($habilidades_condicionais as $skill):
                $condicoes = json_decode($skill['condicoes_uso'], true);
        ?>
        <div class="conditional-skill-card">
            <div class="skill-condition-header">
                <span class="skill-icon"><?php echo $skill['icone'] ?? '⚡'; ?></span>
                <span class="skill-name"><?php echo $skill['nome']; ?></span>
                <span class="skill-level">Nv. <?php echo $skill['skill_level'] ?? 1; ?></span>
            </div>
            
            <div class="skill-description">
                <?php echo $skill['descricao']; ?>
            </div>
            
            <div class="skill-conditions">
                <strong>Condições:</strong>
                <?php
                foreach($condicoes as $tipo => $valor):
                    $condicao_texto = "";
                    switch($tipo):
                        case 'hp_minimo':
                            $condicao_texto = "HP ≤ {$valor}%";
                            break;
                        case 'hp_maximo':
                            $condicao_texto = "HP ≥ {$valor}%";
                            break;
                        case 'mana_minimo':
                            $condicao_texto = "Mana ≤ {$valor}%";
                            break;
                        case 'turno_minimo':
                            $condicao_texto = "Turno ≥ {$valor}";
                            break;
                        case 'turno_maximo':
                            $condicao_texto = "Turno ≤ {$valor}";
                            break;
                        case 'alvo_hp_minimo':
                            $condicao_texto = "Inimigo HP ≤ {$valor}%";
                            break;
                        case 'recursos_alternativos':
                            $condicao_texto = "Usa HP como custo";
                            break;
                    endswitch;
                ?>
                <span class="condition-badge"><?php echo $condicao_texto; ?></span>
                <?php endforeach; ?>
            </div>
            
            <div class="skill-cost">
                <span class="mana-cost">💠 <?php echo $skill['custo_mana']; ?> Mana</span>
                <?php if (isset($condicoes['recursos_alternativos'])): ?>
                <span class="hp-cost">❤️ Custo de HP</span>
                <?php endif; ?>
            </div>
            
            <div class="skill-action">
                <a href="?acao=usar_skill&skill_id=<?php echo $skill['id_skill_base'] ?? $skill['id']; ?>" 
                   class="btn btn-conditional">
                    Usar Habilidade
                </a>
            </div>
        </div>
        <?php endforeach; ?>
        <?php else: ?>
        <div class="no-conditional-skills">
            <p>Nenhuma habilidade condicional disponível no momento.</p>
            <p>⏳ Condições não atendidas ou não há habilidades aprendidas.</p>
        </div>
        <?php endif; ?>
    </div>
</div>
    </div>
    <?php endif; ?>

    <!-- LOG DE COMBATE -->
    <div class="section section-arcane">
        <h2 class="section-header">📜 HISTÓRICO</h2>
        <div class="combat-log">
            <?php echo $mensagem_combate; ?>
        </div>
    </div>

    <?php else: ?>
    <!-- COMBATE FINALIZADO -->
    <div class="section section-arcane text-center">
        <h1>🏁 COMBATE CONCLUÍDO</h1>
        
        <div class="combat-result">
            <?php echo $mensagem_combate; ?>
        </div>
        
        <div class="post-combat-actions">
            <a href="combate_portal.php?rank=<?php echo $rank_escolhido; ?>" class="btn btn-primary">
                🔄 NOVO COMBATE
            </a>
            <a href="mapa.php" class="btn">
                🗺️ VOLTAR AO MAPA
            </a>
            <a href="personagem.php" class="btn">
                👤 VER PERSONAGEM
            </a>
        </div>
    </div>
    <?php endif; ?>
</div>

<style>
/* ESTILOS ESPECÍFICOS DO COMBATE */
.combat-header {
    margin-bottom: 20px;
}

.turn-indicator {
    background: linear-gradient(135deg, var(--accent-arcane), var(--accent-arcane-glow));
    color: white;
    padding: 10px 20px;
    border-radius: 25px;
    font-weight: bold;
    margin-top: 10px;
    display: inline-block;
}

.combat-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 20px;
    margin-bottom: 25px;
}

@media (max-width: 768px) {
    .combat-grid {
        grid-template-columns: 1fr;
    }
}

.combatant-card {
    padding: 20px;
    border-radius: 12px;
}

.combatant-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 15px;
}

.combatant-header h3 {
    margin: 0;
    color: var(--accent-vital);
}

.combatant-level, .combatant-rank {
    background: var(--bg-primary);
    padding: 4px 8px;
    border-radius: 12px;
    font-size: 0.9em;
    color: var(--text-secondary);
}

.health-bar, .resource-bar {
    margin-bottom: 15px;
}

.bar-label {
    display: flex;
    justify-content: space-between;
    margin-bottom: 5px;
    font-size: 0.9em;
    color: var(--text-secondary);
}

.bar-container {
    width: 100%;
    height: 20px;
    background: var(--bg-primary);
    border-radius: 10px;
    overflow: hidden;
    border: 2px solid var(--bg-tertiary);
}

.bar-fill {
    height: 100%;
    border-radius: 8px;
    transition: width 0.5s ease;
}

.hp-fill {
    background: linear-gradient(90deg, var(--accent-vital), #00FF00);
}

.mana-fill {
    background: linear-gradient(90deg, var(--status-mana), #00bfff);
}

.enemy-hp-fill {
    background: linear-gradient(90deg, var(--accent-arcane), #8A2BE2);
}

.combatant-stats {
    display: flex;
    gap: 15px;
    justify-content: center;
}

.stat-pair {
    text-align: center;
    background: var(--bg-primary);
    padding: 8px 12px;
    border-radius: 8px;
    border: 1px solid var(--bg-tertiary);
}

.stat-label {
    display: block;
    font-size: 0.8em;
    color: var(--text-secondary);
    margin-bottom: 2px;
}

.stat-value {
    display: block;
    font-weight: bold;
    color: var(--accent-vital);
}

.actions-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 15px;
    margin-top: 15px;
}

.combat-btn {
    display: flex;
    flex-direction: column;
    align-items: center;
    padding: 15px;
    text-decoration: none;
    transition: all 0.3s ease;
}

.btn-icon {
    font-size: 2em;
    margin-bottom: 8px;
}

.btn-text {
    font-weight: bold;
    margin-bottom: 5px;
}

.btn-desc {
    font-size: 0.8em;
    color: var(--text-secondary);
}

.combat-btn:hover {
    transform: translateY(-3px);
}

.skills-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
    gap: 12px;
    margin-top: 15px;
}

.skill-card {
    background: var(--bg-primary);
    border: 2px solid;
    border-radius: 8px;
    padding: 12px;
    transition: all 0.3s ease;
}

.skill-card.available {
    border-color: var(--accent-vital);
}

.skill-card.available:hover {
    background: var(--accent-vital);
    transform: translateY(-2px);
}

.skill-card.disabled {
    border-color: var(--text-secondary);
    opacity: 0.6;
}

.skill-link {
    text-decoration: none;
    color: inherit;
    display: block;
}

.skill-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 8px;
}

.skill-name {
    font-weight: bold;
    font-size: 0.9em;
}

.skill-level {
    background: var(--accent-arcane);
    color: white;
    padding: 2px 6px;
    border-radius: 10px;
    font-size: 0.8em;
}

.skill-cost, .skill-cooldown {
    display: flex;
    align-items: center;
    gap: 5px;
    font-size: 0.8em;
    color: var(--text-secondary);
}

.combat-log {
    background: var(--bg-primary);
    border: 2px solid var(--accent-arcane);
    border-radius: 8px;
    padding: 20px;
    max-height: 400px;
    overflow-y: auto;
    font-family: "Courier New", monospace;
    line-height: 1.4;
}

.log-entry {
    margin-bottom: 10px;
    padding-bottom: 10px;
    border-bottom: 1px solid var(--bg-tertiary);
}

.log-entry:last-child {
    border-bottom: none;
    margin-bottom: 0;
}

.log-turn {
    color: var(--accent-arcane-glow);
    font-weight: bold;
    display: block;
    margin-bottom: 5px;
}

.log-system {
    color: var(--accent-vital);
    font-weight: bold;
}

.log-action {
    color: var(--text-primary);
    display: block;
}

.log-skill {
    color: var(--status-mana);
    font-weight: bold;
    display: block;
}

.log-damage {
    color: var(--accent-vital);
    font-weight: bold;
}

.log-enemy-damage {
    color: var(--status-hp);
    font-weight: bold;
}

.log-critico {
    color: var(--accent-arcane-glow);
    font-weight: bold;
}

.log-miss {
    color: var(--text-secondary);
    font-style: italic;
}

.log-dodge {
    color: var(--accent-vital);
    font-weight: bold;
}

.log-error {
    color: var(--status-hp);
    font-weight: bold;
}

.log-enemy {
    color: var(--accent-arcane);
    font-weight: bold;
    display: block;
}

.victory-message {
    text-align: center;
    background: linear-gradient(135deg, var(--bg-primary), rgba(80, 200, 120, 0.1));
    border: 2px solid var(--accent-vital);
    border-radius: 12px;
    padding: 20px;
    margin: 15px 0;
}

.victory-message h3 {
    color: var(--accent-vital);
    margin-bottom: 15px;
}

.loot-section {
    margin-top: 15px;
    padding: 15px;
    background: var(--bg-tertiary);
    border-radius: 8px;
}

.loot-item {
    margin: 5px 0;
    padding: 5px 10px;
    background: var(--bg-primary);
    border-radius: 5px;
    border-left: 3px solid var(--accent-vital);
}

.loot-item.rare {
    border-left-color: var(--accent-arcane);
    background: linear-gradient(135deg, var(--bg-primary), rgba(138, 43, 226, 0.1));
}

.post-combat-actions {
    display: flex;
    gap: 15px;
    justify-content: center;
    margin-top: 25px;
    flex-wrap: wrap;
}

.combat-result {
    padding: 20px;
    background: var(--bg-primary);
    border-radius: 8px;
    margin: 20px 0;
    border: 1px solid var(--bg-tertiary);
}
.status-effects-container {
    background: var(--bg-secondary);
    padding: 15px;
    border-radius: 8px;
    margin: 15px 0;
    border: 1px solid var(--bg-tertiary);
}

.status-effects-container h4 {
    margin: 0 0 10px 0;
    color: var(--accent-arcane);
    font-size: 1.1em;
}

.status-effects-grid {
    display: grid;
    grid-template-columns: 1fr;
    gap: 15px;
}

.status-group h5 {
    margin: 0 0 8px 0;
    color: var(--text-primary);
    font-size: 0.9em;
    font-weight: bold;
}

.status-list {
    display: flex;
    flex-wrap: wrap;
    gap: 8px;
}

.status-item {
    display: flex;
    align-items: center;
    gap: 6px;
    padding: 6px 10px;
    background: var(--bg-primary);
    border: 1px solid var(--bg-tertiary);
    border-left: 4px solid;
    border-radius: 6px;
    font-size: 0.8em;
}

.status-icon {
    font-size: 1.1em;
}

.status-name {
    font-weight: bold;
    color: var(--text-primary);
}

.status-duration {
    background: var(--bg-tertiary);
    color: var(--text-secondary);
    padding: 2px 6px;
    border-radius: 10px;
    font-size: 0.7em;
    font-weight: bold;
}

.status-intensity {
    background: var(--accent-arcane);
    color: white;
    padding: 2px 6px;
    border-radius: 10px;
    font-size: 0.7em;
    font-weight: bold;
}

/* Feedback de status no log de combate */
.status-message {
    padding: 8px 12px;
    margin: 5px 0;
    border-radius: 6px;
    font-weight: bold;
}

.status-damage {
    background: rgba(255, 107, 53, 0.1);
    border-left: 3px solid #FF6B35;
    color: #FF6B35;
}

.status-buff {
    background: rgba(76, 175, 80, 0.1);
    border-left: 3px solid #4CAF50;
    color: #4CAF50;
}

.status-debuff {
    background: rgba(139, 0, 0, 0.1);
    border-left: 3px solid #8B0000;
    color: #8B0000;
}

.status-control {
    background: rgba(58, 134, 255, 0.1);
    border-left: 3px solid #3A86FF;
    color: #3A86FF;
}
/* ESTILOS PARA COMBOS */
.combos-container {
    background: var(--bg-secondary);
    padding: 15px;
    border-radius: 8px;
    margin: 15px 0;
    border: 1px solid var(--accent-arcane);
}

.combos-container h4 {
    margin: 0 0 10px 0;
    color: var(--accent-arcane);
    font-size: 1.1em;
}

.combos-grid {
    display: flex;
    flex-direction: column;
    gap: 10px;
}

.combo-card {
    background: var(--bg-primary);
    border: 1px solid var(--bg-tertiary);
    border-radius: 8px;
    padding: 12px;
    transition: all 0.3s ease;
}

.combo-card:hover {
    border-color: var(--accent-arcane);
    transform: translateY(-2px);
}

.combo-header {
    display: flex;
    align-items: center;
    gap: 10px;
    margin-bottom: 8px;
}

.combo-icon {
    font-size: 1.2em;
}

.combo-name {
    font-weight: bold;
    color: var(--text-primary);
    flex: 1;
}

.combo-timer {
    background: var(--status-hp);
    color: white;
    padding: 2px 6px;
    border-radius: 10px;
    font-size: 0.7em;
    font-weight: bold;
}

.combo-progress {
    display: flex;
    flex-direction: column;
    gap: 8px;
}

.combo-steps {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 4px;
}

.combo-step {
    width: 24px;
    height: 24px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 0.8em;
    font-weight: bold;
}

.combo-step.completed {
    background: var(--accent-vital);
    color: white;
}

.combo-step.pending {
    background: var(--bg-tertiary);
    color: var(--text-secondary);
    border: 1px solid var(--text-secondary);
}

.combo-connector {
    width: 20px;
    height: 2px;
    background: var(--bg-tertiary);
}

.combo-next {
    text-align: center;
    font-size: 0.8em;
    color: var(--text-secondary);
}

.combo-next strong {
    color: var(--accent-vital);
}

.no-combos {
    text-align: center;
    padding: 20px;
    color: var(--text-secondary);
    font-style: italic;
}

/* BÔNUS DE COMBOS */
.combo-bonuses {
    background: var(--bg-secondary);
    padding: 12px;
    border-radius: 8px;
    margin: 10px 0;
    border: 1px solid var(--accent-vital);
}

.combo-bonuses h5 {
    margin: 0 0 8px 0;
    color: var(--accent-vital);
    font-size: 0.9em;
}

.bonus-list {
    display: flex;
    flex-wrap: wrap;
    gap: 8px;
}

.bonus-item {
    background: var(--bg-primary);
    padding: 6px 10px;
    border-radius: 15px;
    font-size: 0.8em;
    font-weight: bold;
    color: var(--accent-vital);
    border: 1px solid var(--accent-vital);
}

.no-bonus {
    color: var(--text-secondary);
    font-style: italic;
    font-size: 0.8em;
}

/* LOG DE COMBOS */
.log-combo {
    background: linear-gradient(135deg, var(--accent-arcane), var(--accent-essence));
    color: white;
    padding: 10px 15px;
    border-radius: 8px;
    margin: 8px 0;
    font-weight: bold;
    text-align: center;
    border: 2px solid gold;
}

.log-combo-bonus {
    color: var(--accent-vital);
    font-weight: bold;
    background: rgba(76, 175, 80, 0.1);
    padding: 2px 6px;
    border-radius: 4px;
    margin-left: 5px;
}
/* ESTILOS PARA HABILIDADES CONDICIONAIS */
.conditional-skills-container {
    background: var(--bg-secondary);
    padding: 15px;
    border-radius: 8px;
    margin: 15px 0;
    border: 1px solid var(--accent-essence);
}

.conditional-skills-container h4 {
    margin: 0 0 10px 0;
    color: var(--accent-essence);
    font-size: 1.1em;
}

.conditional-skills-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
    gap: 15px;
}

.conditional-skill-card {
    background: var(--bg-primary);
    border: 1px solid var(--bg-tertiary);
    border-radius: 8px;
    padding: 15px;
    transition: all 0.3s ease;
}

.conditional-skill-card:hover {
    border-color: var(--accent-essence);
    transform: translateY(-2px);
    box-shadow: 0 5px 15px rgba(233, 69, 96, 0.1);
}

.skill-condition-header {
    display: flex;
    align-items: center;
    gap: 10px;
    margin-bottom: 10px;
}

.skill-icon {
    font-size: 1.5em;
}

.skill-name {
    font-weight: bold;
    color: var(--text-primary);
    flex: 1;
}

.skill-level {
    background: var(--accent-arcane);
    color: white;
    padding: 2px 8px;
    border-radius: 10px;
    font-size: 0.7em;
    font-weight: bold;
}

.skill-description {
    color: var(--text-secondary);
    font-size: 0.9em;
    margin-bottom: 10px;
    line-height: 1.4;
}

.skill-conditions {
    margin-bottom: 10px;
}

.skill-conditions strong {
    display: block;
    margin-bottom: 5px;
    color: var(--text-primary);
    font-size: 0.8em;
}

.condition-badge {
    display: inline-block;
    background: var(--bg-tertiary);
    color: var(--text-secondary);
    padding: 3px 8px;
    border-radius: 12px;
    font-size: 0.7em;
    margin: 2px;
    border: 1px solid var(--text-secondary);
}

.skill-cost {
    display: flex;
    gap: 10px;
    margin-bottom: 10px;
    flex-wrap: wrap;
}

.mana-cost, .hp-cost {
    font-size: 0.8em;
    font-weight: bold;
    padding: 4px 8px;
    border-radius: 6px;
}

.mana-cost {
    background: rgba(58, 134, 255, 0.1);
    color: #3A86FF;
    border: 1px solid #3A86FF;
}

.hp-cost {
    background: rgba(239, 71, 111, 0.1);
    color: #EF476F;
    border: 1px solid #EF476F;
}

.skill-action {
    text-align: center;
}

.btn-conditional {
    display: inline-block;
    background: linear-gradient(135deg, var(--accent-essence), #FF6B35);
    color: white;
    padding: 8px 15px;
    border-radius: 6px;
    text-decoration: none;
    font-weight: bold;
    font-size: 0.9em;
    transition: all 0.3s ease;
}

.btn-conditional:hover {
    transform: scale(1.05);
    box-shadow: 0 5px 15px rgba(233, 69, 96, 0.3);
}

.no-conditional-skills {
    text-align: center;
    padding: 30px 20px;
    color: var(--text-secondary);
    grid-column: 1 / -1;
}

.no-conditional-skills p {
    margin: 5px 0;
}

/* LOG ALTERNATIVO */
.log-alternative {
    color: var(--accent-essence);
    font-weight: bold;
    background: rgba(233, 69, 96, 0.1);
    padding: 5px 10px;
    border-radius: 4px;
    display: inline-block;
    margin: 5px 0;
}
/* ESTILOS PARA ULTIMATE ABILITIES */
.ultimate-abilities-container {
    background: linear-gradient(135deg, var(--bg-secondary), #1a1a2e);
    padding: 20px;
    border-radius: 12px;
    margin: 20px 0;
    border: 2px solid var(--accent-essence);
    position: relative;
    overflow: hidden;
}

.ultimate-abilities-container::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    height: 3px;
    background: linear-gradient(90deg, var(--accent-essence), var(--accent-arcane), var(--accent-vital));
}

.ultimate-abilities-container h4 {
    margin: 0 0 15px 0;
    color: var(--accent-essence);
    font-size: 1.3em;
    text-align: center;
    text-shadow: 0 0 10px rgba(233, 69, 96, 0.5);
}

.ultimate-abilities-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(350px, 1fr));
    gap: 20px;
}

.ultimate-card {
    background: var(--bg-primary);
    border: 3px solid;
    border-radius: 12px;
    padding: 20px;
    transition: all 0.3s ease;
    position: relative;
    overflow: hidden;
}

.ultimate-card::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: linear-gradient(135deg, rgba(255,255,255,0.1), transparent);
    opacity: 0;
    transition: opacity 0.3s ease;
}

.ultimate-card:hover::before {
    opacity: 1;
}

.ultimate-card.available {
    border-color: var(--accent-vital);
    box-shadow: 0 5px 20px rgba(76, 175, 80, 0.2);
}

.ultimate-card.available:hover {
    transform: translateY(-5px) scale(1.02);
    box-shadow: 0 10px 30px rgba(76, 175, 80, 0.3);
}

.ultimate-card.unavailable {
    border-color: var(--bg-tertiary);
    opacity: 0.7;
    filter: grayscale(0.5);
}

.ultimate-header {
    display: flex;
    align-items: center;
    gap: 12px;
    margin-bottom: 12px;
    flex-wrap: wrap;
}

.ultimate-icon {
    font-size: 2em;
    filter: drop-shadow(0 0 5px currentColor);
}

.ultimate-name {
    font-weight: bold;
    color: var(--text-primary);
    font-size: 1.2em;
    flex: 1;
}

.ultimate-type {
    background: var(--bg-tertiary);
    color: var(--text-secondary);
    padding: 4px 8px;
    border-radius: 12px;
    font-size: 0.7em;
    font-weight: bold;
}

.ultimate-description {
    color: var(--text-secondary);
    font-size: 0.9em;
    margin-bottom: 15px;
    line-height: 1.4;
    font-style: italic;
}

.ultimate-effects {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 15px;
    padding: 10px;
    background: var(--bg-secondary);
    border-radius: 8px;
}

.effect-primary {
    font-weight: bold;
    color: var(--accent-vital);
    font-size: 0.9em;
}

.effect-target {
    background: var(--accent-arcane);
    color: white;
    padding: 4px 8px;
    border-radius: 10px;
    font-size: 0.7em;
    font-weight: bold;
}

.ultimate-costs {
    display: flex;
    gap: 15px;
    margin-bottom: 15px;
    flex-wrap: wrap;
}

.cost-mana, .cost-cooldown, .cooldown-active {
    display: flex;
    align-items: center;
    gap: 5px;
    padding: 6px 12px;
    border-radius: 15px;
    font-size: 0.8em;
    font-weight: bold;
}

.cost-mana {
    background: rgba(58, 134, 255, 0.1);
    color: #3A86FF;
    border: 1px solid #3A86FF;
}

.cost-cooldown {
    background: rgba(255, 214, 102, 0.1);
    color: #FFD166;
    border: 1px solid #FFD166;
}

.cooldown-active {
    background: rgba(239, 71, 111, 0.1);
    color: #EF476F;
    border: 1px solid #EF476F;
}

.ultimate-actions {
    text-align: center;
    margin-bottom: 10px;
}

.btn-ultimate {
    display: inline-block;
    background: linear-gradient(135deg, var(--accent-essence), #FF6B35) !important;
    color: white !important;
    padding: 12px 20px;
    border-radius: 8px;
    text-decoration: none;
    font-weight: bold;
    font-size: 1em;
    transition: all 0.3s ease;
    border: none;
    cursor: pointer;
    text-shadow: 0 1px 2px rgba(0,0,0,0.5);
    box-shadow: 0 4px 15px rgba(233, 69, 96, 0.4);
}

.btn-ultimate:hover {
    transform: scale(1.05);
    box-shadow: 0 6px 20px rgba(233, 69, 96, 0.6);
    filter: brightness(1.1);
}

.btn-ultimate-disabled {
    display: inline-block;
    background: var(--bg-tertiary) !important;
    color: var(--text-secondary) !important;
    padding: 12px 20px;
    border-radius: 8px;
    font-weight: bold;
    font-size: 0.9em;
    opacity: 0.6;
    cursor: not-allowed;
}

.ultimate-stats {
    text-align: center;
    color: var(--text-secondary);
    font-size: 0.8em;
}

.no-ultimates {
    text-align: center;
    padding: 40px 20px;
    color: var(--text-secondary);
    grid-column: 1 / -1;
}

.no-ultimates p {
    margin: 10px 0;
}

/* BUFFS DE ULTIMATE */
.ultimate-buffs-container {
    background: var(--bg-secondary);
    padding: 15px;
    border-radius: 8px;
    margin: 15px 0;
    border: 1px solid var(--accent-essence);
}

.ultimate-buffs-container h5 {
    margin: 0 0 10px 0;
    color: var(--accent-essence);
    font-size: 1em;
}

.ultimate-buffs-list {
    display: flex;
    flex-direction: column;
    gap: 8px;
}

.ultimate-buff-item {
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 10px 15px;
    background: var(--bg-primary);
    border: 2px solid;
    border-radius: 8px;
    font-size: 0.9em;
}

.buff-icon {
    font-size: 1.2em;
}

.buff-name {
    font-weight: bold;
    color: var(--text-primary);
    flex: 1;
}

.buff-duration {
    background: var(--bg-tertiary);
    color: var(--text-secondary);
    padding: 3px 8px;
    border-radius: 10px;
    font-size: 0.8em;
    font-weight: bold;
}

.buff-effect {
    color: var(--accent-vital);
    font-weight: bold;
    font-size: 0.8em;
}

.no-ultimate-buffs {
    text-align: center;
    color: var(--text-secondary);
    font-style: italic;
    padding: 10px;
}

/* LOG DE ULTIMATE */
.log-ultimate {
    background: linear-gradient(135deg, var(--accent-essence), var(--accent-arcane));
    color: white;
    padding: 15px 20px;
    border-radius: 10px;
    margin: 10px 0;
    font-weight: bold;
    text-align: center;
    border: 3px solid gold;
    text-shadow: 0 2px 4px rgba(0,0,0,0.5);
    box-shadow: 0 5px 20px rgba(233, 69, 96, 0.5);
    animation: pulse-ultimate 2s infinite;
}

@keyframes pulse-ultimate {
    0%, 100% { transform: scale(1); }
    50% { transform: scale(1.02); }
}

.log-ultimate-bonus {
    color: gold;
    font-weight: bold;
    background: rgba(255, 215, 0, 0.1);
    padding: 4px 8px;
    border-radius: 4px;
    margin-left: 5px;
    border: 1px solid gold;
}
</style>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Rolagem automática para o final do log
    const combatLog = document.querySelector('.combat-log');
    if (combatLog) {
        combatLog.scrollTop = combatLog.scrollHeight;
    }
    
    // Efeitos de hover nas habilidades
    const skillCards = document.querySelectorAll('.skill-card.available');
    skillCards.forEach(card => {
        card.addEventListener('mouseenter', function() {
            this.style.transform = 'translateY(-3px) scale(1.02)';
        });
        card.addEventListener('mouseleave', function() {
            this.style.transform = 'translateY(0) scale(1)';
        });
    });
});
</script>

<?php include 'footer.php'; ?>