<?php
session_start();
// Includes essenciais no topo
include_once 'db_connect.php';
include_once 'game_logic.php'; // Para teste_atributo, is_quest_completa, entregar_quest
include_once 'dialogue_system_functions.php'; // Para get_npc_data, get_dialogo, get_opcoes, aplicar_consequencia, etc.

// --- 1. VERIFICAÇÕES INICIAIS E VARIÁVEIS GLOBAIS ---
if (!isset($_SESSION['player_id'])) {
    header('Location: login.php?prologo_visto=1'); // Redireciona se não logado
    exit;
}
if (!isset($_GET['npc_id'])) {
    header('Location: cidade.php'); // Redireciona se nenhum NPC foi especificado
    exit;
}

$player_id = (int)$_SESSION['player_id'];
$npc_id = (int)$_GET['npc_id'];
$dialogo_id_url = isset($_GET['dialogo_id']) ? (int)$_GET['dialogo_id'] : null;

// Variáveis para armazenar o estado final a ser exibido
$npc_data = null;
$dialogo_atual = null;
$opcoes_jogador = [];
$mensagem_feedback = ""; // Mensagens gerais e de teste/quest

// Carrega dados do jogador (necessário para testes e requisitos)
$player_data = get_player_data($player_id, $conexao);
if (!$player_data) {
    session_destroy(); header('Location: login.php'); exit; // Segurança
}

// --- 2. PROCESSAMENTO DE ESCOLHA (SE HOUVER POST) ---
$proximo_dialogo_id_apos_escolha = null; // Guarda o ID a ser carregado após processar POST

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['escolha_dialogo'])) {
    $dialogo_id_escolhido = (int)$_POST['dialogo_id'];

    // Busca dados da escolha feita
    $sql_escolha = "SELECT * FROM npc_dialogos WHERE id = ? AND npc_id = ? AND tipo = 'player'";
    $stmt_escolha = $conexao->prepare($sql_escolha);
    $stmt_escolha->bind_param("ii", $dialogo_id_escolhido, $npc_id);
    $stmt_escolha->execute();
    $escolha = $stmt_escolha->get_result()->fetch_assoc();
    $stmt_escolha->close();

    if ($escolha) {
        // Registrar escolha (antes de qualquer redirecionamento)
        $sql_registrar = "INSERT INTO player_escolhas_dialogo (player_id, dialogo_id, escolha_feita) VALUES (?, ?, ?)";
        $stmt_reg = $conexao->prepare($sql_registrar);
        $stmt_reg->bind_param("iis", $player_id, $dialogo_id_escolhido, $escolha['dialogo_texto']);
        $stmt_reg->execute();
        $stmt_reg->close();

        // Verificar teste de atributo
        $requisitos = isset($escolha['requisitos']) ? json_decode($escolha['requisitos'], true) : null;
        $proximo_dialogo_id_definido = $escolha['proximo_dialogo_id']; // ID padrão

        if ($requisitos && isset($requisitos['teste_atributo'])) {
            $atributo_teste = $requisitos['teste_atributo'];
            $dc_teste = (int)$requisitos['dc'];
            $atributo_jogador = $player_data[$atributo_teste] ?? 10;
            $modificador_final = $atributo_jogador; // Adicionar bônus/penalidade aqui se necessário

            $resultado_teste = teste_atributo($modificador_final, $dc_teste);

            // Adicionar feedback sobre o teste (será exibido na PRÓXIMA página)
            $_SESSION['feedback_temporario'] = "<div class='feedback feedback-test'>"; // Usando sessão para persistir após redirect
            $_SESSION['feedback_temporario'] .= "<strong>[TESTE DE " . strtoupper($atributo_teste) . "]</strong> (DC: {$dc_teste})<br>";
            $_SESSION['feedback_temporario'] .= "Resultado: {$resultado_teste['final']} vs {$dc_teste} ";

            // Determinar próximo diálogo baseado no resultado (Exemplo para Persuasão de Kaelen)
             if ($dialogo_id_escolhido == 109) { // ID da opção de Persuasão
                 if ($resultado_teste['resultado'] === 'sucesso' || $resultado_teste['resultado'] === 'critico') {
                    $proximo_dialogo_id_definido = 114; // ID da resposta de Sucesso
                    $_SESSION['feedback_temporario'] .= "<span style='color: var(--accent-vital);'>(SUCESSO!)</span>";
                 } else {
                    $proximo_dialogo_id_definido = 113; // ID da resposta de Falha
                    $_SESSION['feedback_temporario'] .= "<span style='color: var(--status-hp);'>(FALHA!)</span>";
                 }
            }
             // Adicionar 'else if' para outras escolhas com testes...
            $_SESSION['feedback_temporario'] .= "</div>";
        }

        // Aplicar consequências IMEDIATAS da escolha (se houver)
        if ($escolha['acao_trigger'] && $escolha['acao_valor'] && function_exists('aplicar_consequencia_dialogo')) {
            // A função aplicar_consequencia agora deve usar $_SESSION['feedback_geral'] para mensagens
            aplicar_consequencia_dialogo($player_id, $escolha['acao_trigger'], $escolha['acao_valor'], $conexao);
        }

        // Se a escolha leva a um próximo diálogo, redireciona
        if ($proximo_dialogo_id_definido !== null) {
            header("Location: npc_interact.php?npc_id=$npc_id&dialogo_id=" . $proximo_dialogo_id_definido);
            exit;
        } else {
            // Se a escolha termina o diálogo (proximo_dialogo_id é NULL)
            $_SESSION['feedback_temporario'] = ($_SESSION['feedback_temporario'] ?? '') . "<div class='feedback feedback-success'>💬 Diálogo concluído!</div>";
            // Redireciona de volta para a cidade ou para a própria página sem dialogo_id para mostrar o fim
            header("Location: cidade.php"); // Ou npc_interact.php?npc_id=$npc_id se preferir mostrar "Fim" aqui
            exit;
        }
    }
    // Se a escolha não foi encontrada (erro?), apenas recarrega a página atual
    header("Location: npc_interact.php?npc_id=$npc_id&dialogo_id=$dialogo_id_url");
    exit;
}

// --- 3. EXIBIR FEEDBACK DA SESSÃO (DE AÇÕES ANTERIORES) ---
if (isset($_SESSION['feedback_temporario'])) {
    $mensagem_feedback .= $_SESSION['feedback_temporario'];
    unset($_SESSION['feedback_temporario']);
}
if (isset($_SESSION['feedback_quest'])) {
    $mensagem_feedback .= $_SESSION['feedback_quest'];
    unset($_SESSION['feedback_quest']);
}
// Manter o feedback geral da entrega de quest
if (isset($_SESSION['feedback_geral'])) {
     $mensagem_feedback .= $_SESSION['feedback_geral'];
     unset($_SESSION['feedback_geral']);
}


// --- 4. DETERMINAR O ESTADO ATUAL DO DIÁLOGO PARA EXIBIÇÃO ---

$npc_data = get_npc_data($npc_id, $conexao); // Carrega dados do NPC para o cabeçalho
$quest_completa_tratada = false;

// Prioridade 1: Verificar se há uma quest completa relevante ao entrar (sem dialogo_id na URL)
if ($dialogo_id_url === null && function_exists('is_quest_completa')) {
    $quest_id_custo_poder = 1;
    if ($npc_id == 10 && is_quest_completa($player_id, $quest_id_custo_poder, $conexao)) {
        // Define o diálogo de espera pela entrega
        $dialogo_atual = [
            'id' => 120, 'npc_id' => 10,
            'dialogo_texto' => 'Sim, cadete? Concluiu a tarefa que lhe dei?',
            'tipo' => 'npc', 'proximo_dialogo_id' => 118
        ];
        // Busca a opção de entrega
        $sql_opcao_entrega = "SELECT * FROM npc_dialogos WHERE id = 118 AND npc_id = 10 AND tipo = 'player'";
        $stmt_entrega = $conexao->prepare($sql_opcao_entrega);
        if($stmt_entrega){
            $stmt_entrega->execute();
            $opcao_entrega = $stmt_entrega->get_result()->fetch_assoc();
            if ($opcao_entrega && verificar_requisitos_opcao($player_id, $opcao_entrega['requisitos'], $conexao)) { // Verifica requisitos da opção
                $opcoes_jogador[] = $opcao_entrega;
            }
            $stmt_entrega->close();
        }
        $quest_completa_tratada = true;
    }
    // Adicionar 'else if' para outras quests aqui...
}

// Prioridade 2: Carregar diálogo específico da URL ou o inicial se nenhuma condição especial foi tratada
if (!$quest_completa_tratada) {
    $dialogo_atual = get_npc_dialogo_atual($npc_id, $dialogo_id_url, $conexao); // Usa ID da URL ou busca o primeiro
    // Busca opções apenas se o diálogo atual for do NPC
    if ($dialogo_atual && $dialogo_atual['tipo'] == 'npc') {
        $opcoes_jogador = get_opcoes_jogador($dialogo_atual, $conexao); // Passa o array do diálogo
    }
}

// --- 5. APLICAR CONSEQUÊNCIAS DO DIÁLOGO ATUAL DO NPC (se houver) ---
// (Executa APÓS determinar $dialogo_atual)
if ($dialogo_atual && $dialogo_atual['tipo'] == 'npc' && $dialogo_atual['acao_trigger'] && $dialogo_atual['acao_valor'] && function_exists('aplicar_consequencia_dialogo')) {
    // Nota: Consequências aplicadas aqui podem afetar a exibição (ex: dar quest)
    // Se a consequência for 'entregar_quest', a mensagem será mostrada via $_SESSION na próxima carga
    aplicar_consequencia_dialogo($player_id, $dialogo_atual['acao_trigger'], $dialogo_atual['acao_valor'], $conexao);

     // Ex: Se o diálogo atual dá a quest, exibe feedback imediatamente
     if ($dialogo_atual['id'] == 113 || $dialogo_atual['id'] == 114) {
         if (isset($_SESSION['feedback_quest'])) { // Verifica se aplicar_consequencia definiu o feedback
              $mensagem_feedback .= $_SESSION['feedback_quest'];
              unset($_SESSION['feedback_quest']);
         }
     }
}

// --- 6. CARREGAR DADOS FINAIS PARA O TEMPLATE ---
$reputacao_guilda = get_reputacao_faccao($player_id, 'guilda', $conexao);
$reputacao_faccao = get_reputacao_faccao($player_id, 'faccao_oculta', $conexao);
$titulo_pagina = "Diálogo: " . ($npc_data['nome'] ?? 'NPC');

// --- 7. INCLUIR O HTML ---
include 'header.php'; // Inclui o header padrão
?>

<div class="container fade-in">
    <?php echo $mensagem_feedback; // Exibe todos os feedbacks acumulados ?>

    <div class="section section-vital npc-dialogue-header">
        <div class="npc-portrait">
            <div class="portrait-icon"><?php echo htmlspecialchars($npc_data['icone'] ?? '👤'); ?></div>
        </div>
        <div class="npc-info">
            <h2><?php echo htmlspecialchars($npc_data['nome'] ?? 'Desconhecido'); ?></h2>
            <p class="npc-description"><?php echo htmlspecialchars($npc_data['descricao'] ?? ''); ?></p>
            <div class="reputation-display">
                 <div class="reputation-item guild-rep">
                     <span class="faccao-badge faccao-guilda">🏛️ Guilda: <?php echo $reputacao_guilda; ?></span>
                     <span class="relationship-status relationship-<?php echo calcular_relacionamento($reputacao_guilda); ?>"><?php echo ucfirst(calcular_relacionamento($reputacao_guilda)); ?></span>
                 </div>
                 <div class="reputation-item faction-rep">
                     <span class="faccao-badge faccao-faccao_oculta">🌑 Facção: <?php echo $reputacao_faccao; ?></span>
                     <span class="relationship-status relationship-<?php echo calcular_relacionamento($reputacao_faccao); ?>"><?php echo ucfirst(calcular_relacionamento($reputacao_faccao)); ?></span>
                 </div>
            </div>
            </div>
    </div>

    <div class="section section-arcane dialogue-section">
        <?php if ($dialogo_atual): ?>
            <div class="dialogue-history">
                <div class="dialogue-message npc-message">
                    <div class="message-sender"><?php echo htmlspecialchars($npc_data['nome'] ?? 'NPC'); ?></div>
                    <div class="message-content"><?php echo nl2br(htmlspecialchars($dialogo_atual['dialogo_texto'])); ?></div>
                </div>
                <?php if (isset($dialogo_atual['id']) && ($dialogo_atual['id'] == 113 || $dialogo_atual['id'] == 114)): ?>
                 <div class="quest-hint">
                     <small>💡 <strong>Dica:</strong> Uma nova missão foi adicionada ao seu diário!</small>
                 </div>
                 <?php endif; ?>
            </div>

            <div class="dialogue-options">
                <?php if (!empty($opcoes_jogador)): ?>
                    <?php foreach($opcoes_jogador as $opcao): ?>
                    <?php
                        $req_opcao = isset($opcao['requisitos']) ? json_decode($opcao['requisitos'], true) : null;
                        $requer_teste = ($req_opcao && isset($req_opcao['teste_atributo']));
                        $is_entrega = ($req_opcao && isset($req_opcao['quest_completa'])); // Identifica opção de entrega
                    ?>
                    <form method="POST" action="npc_interact.php?npc_id=<?php echo $npc_id; ?><?php echo $dialogo_id_url ? '&dialogo_id='.$dialogo_id_url : ''; ?>" class="dialogue-option-form">
                        <input type="hidden" name="dialogo_id" value="<?php echo $opcao['id']; ?>">
                        <button type="submit" name="escolha_dialogo" class="btn btn-dialogue-option <?php echo $requer_teste ? 'requires-test' : ''; ?> <?php echo $is_entrega ? 'quest-turnin' : ''; ?>">
                            💬 <?php echo htmlspecialchars($opcao['dialogo_texto']); ?>
                            <?php if ($requer_teste): ?> <span class="test-indicator">[Teste]</span> <?php endif; ?>
                            <?php if ($is_entrega): ?> <span class="reward-indicator">💰 Entregar</span> <?php endif; ?>
                        </button>
                    </form>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="dialogue-end text-center">
                        <p>Fim da linha de diálogo atual.</p>
                        <a href="cidade.php" class="btn btn-primary">🏙️ Voltar para a Cidade</a>
                    </div>
                <?php endif; ?>
            </div>

        <?php else: ?>
            <div class="no-dialogue text-center">
                <p>Fim da conversa ou diálogo não encontrado.</p>
                <a href="cidade.php" class="btn btn-primary">🏙️ Voltar para a Cidade</a>
            </div>
        <?php endif; ?>
    </div>

    <div class="section section-vital text-center">
        <a href="quests.php" class="btn btn-success" style="display: inline-flex; align-items: center; gap: 8px;">
            📖 Ver Diário de Missões
        </a>
    </div>
</div>

<style>
    /* ... (Seus estilos para .npc-dialogue-header, .dialogue-history, .btn-dialogue-option, etc.) ... */
     .npc-dialogue-header { display: flex; align-items: center; gap: 20px; }
    .npc-portrait { width: 80px; height: 80px; background: var(--bg-primary); border: 2px solid var(--accent-arcane); border-radius: 50%; display: flex; align-items: center; justify-content: center; }
    .portrait-icon { font-size: 2.5em; }
    .npc-info h2 { color: var(--accent-vital); margin-bottom: 10px; }
    .npc-description { color: var(--text-secondary); margin-bottom: 15px; }
    .reputation-display { display: flex; gap: 15px; margin-top: 10px; flex-wrap: wrap; }
    .reputation-item { background: var(--bg-primary); padding: 5px 10px; border-radius: 6px; font-size: 0.9em; display: flex; gap: 8px; align-items: center; border: 1px solid var(--bg-tertiary); }
    .dialogue-section {}
    .dialogue-history { background: var(--bg-primary); padding: 20px; border-radius: 8px; margin-bottom: 20px; border: 1px solid var(--bg-tertiary); min-height: 100px; }
    .dialogue-message { margin-bottom: 15px; }
    .message-sender { font-weight: bold; color: var(--accent-vital); margin-bottom: 5px; }
    .message-content { color: var(--text-primary); line-height: 1.5; }
    .quest-hint { margin-top: 10px; padding: 8px; background: rgba(255, 215, 0, 0.1); border-left: 3px solid var(--status-gold); color: var(--status-gold); border-radius: 4px; }
    .dialogue-options { display: flex; flex-direction: column; gap: 10px; }
    .btn-dialogue-option { display: block; width: 100%; text-align: left; padding: 12px 18px; background: var(--bg-secondary); color: var(--text-primary); border: 1px solid var(--bg-tertiary); border-radius: 6px; cursor: pointer; transition: all 0.3s ease; font-family: inherit; font-size: 1em; }
    .btn-dialogue-option:hover { background: var(--accent-arcane); color: white; border-color: var(--accent-arcane); transform: translateX(3px); }
    .test-indicator { color: var(--status-gold); font-size: 0.8em; font-style: italic; margin-left: 5px; }
    .reward-indicator { float: right; color: var(--accent-vital); font-size: 0.9em; font-weight: bold; } /* Ajustado */
    .btn-dialogue-option.quest-turnin { border-left: 4px solid var(--accent-vital); } /* Destaque para entrega */
    .quest-complete-indicator { background: linear-gradient(135deg, rgba(76, 175, 80, 0.1), rgba(76, 175, 80, 0.2)); border: 2px solid #4CAF50; padding: 10px 15px; border-radius: 8px; margin-top: 15px; font-weight: bold; color: #4CAF50; animation: pulse-quest 2s infinite; }
    .feedback-test { background: rgba(255, 215, 0, 0.1); border-color: var(--status-gold); color: var(--status-gold); margin-top: 15px; padding: 10px 15px; border-left: 4px solid var(--status-gold); }
    .text-center { text-align: center; }
    @keyframes pulse-quest { 0%, 100% { transform: scale(1); } 50% { transform: scale(1.02); } }
</style>

<?php include 'footer.php'; ?>