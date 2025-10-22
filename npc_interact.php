<?php
session_start();
include_once 'db_connect.php';
// Incluir game_logic ANTES de dialogue_system_functions se dialogue_system precisar de teste_atributo
include_once 'game_logic.php';
include_once 'dialogue_system_functions.php'; // ✅ Sistema de diálogo

// --- VERIFICAÇÃO DE LOGIN E NPC ID ---
if (!isset($_SESSION['player_id']) || !isset($_GET['npc_id'])) {
    header('Location: cidade.php');
    exit;
}

$player_id = $_SESSION['player_id'];
$npc_id = (int)$_GET['npc_id'];
$mensagem = ""; // Para feedback geral
$mensagem_teste = ""; // Para feedback específico do teste de atributo

// --- CARREGAR DADOS DO JOGADOR --- (Necessário para os testes de atributo)
$player_data = get_player_data($player_id, $conexao);
if (!$player_data) {
    session_destroy();
    header('Location: login.php');
    exit;
}


// --- VERIFICAR ENTREGA DE QUEST ANTES DE CARREGAR DIÁLOGO ---
// (Código de verificação e entrega de quest - Mantido como estava)
$quest_id_custo_poder = 1;
// Ajuste: Verificar se a função is_quest_completa existe antes de chamar
if (function_exists('is_quest_completa') && $npc_id == 10 && is_quest_completa($player_id, $quest_id_custo_poder, $conexao)) {
    // Ajuste: Verificar se a função entregar_quest existe antes de chamar
    if (function_exists('entregar_quest')) {
        $resultado_entrega = entregar_quest($player_id, $quest_id_custo_poder, $conexao);
        if ($resultado_entrega['sucesso']) {
            $mensagem .= "<div class='feedback feedback-success'>{$resultado_entrega['mensagem']}</div>";
            // $dialogo_id_atual = 10; // ID do diálogo pós-missão (exemplo)
        } else {
            $mensagem .= "<div class='feedback feedback-error'>{$resultado_entrega['mensagem']}</div>";
        }
    }
}


// --- PROCESSAR ESCOLHA DE DIÁLOGO ---
$dialogo_id_apos_escolha = null; // Guarda o ID do próximo diálogo determinado pela escolha

if (isset($_POST['escolha_dialogo'])) {
    $dialogo_id_escolhido = (int)$_POST['dialogo_id'];

    // 1. Buscar dados da ESCOLHA feita pelo jogador
    $sql_escolha = "SELECT * FROM npc_dialogos WHERE id = ? AND tipo = 'player'";
    $stmt_escolha = $conexao->prepare($sql_escolha);
    $stmt_escolha->bind_param("i", $dialogo_id_escolhido);
    $stmt_escolha->execute();
    $escolha = $stmt_escolha->get_result()->fetch_assoc();

    if ($escolha) {
        // Registrar a escolha (movido para depois do teste, se houver)
        // $sql_registrar = "INSERT INTO player_escolhas_dialogo (player_id, dialogo_id, escolha_feita) VALUES (?, ?, ?)";
        // $stmt_reg = $conexao->prepare($sql_registrar);
        // $stmt_reg->bind_param("iis", $player_id, $dialogo_id_escolhido, $escolha['dialogo_texto']);
        // $stmt_reg->execute();


        // 2. Verificar se a escolha requer um TESTE DE ATRIBUTO
        $requisitos = isset($escolha['requisitos']) ? json_decode($escolha['requisitos'], true) : null;
        $proximo_dialogo_id_definido = $escolha['proximo_dialogo_id']; // ID padrão

        if ($requisitos && isset($requisitos['teste_atributo'])) {
            $atributo_teste = $requisitos['teste_atributo']; // Ex: 'cha'
            $dc_teste = $requisitos['dc']; // Ex: 15
            $atributo_jogador = $player_data[$atributo_teste] ?? 10; // Pega o valor do atributo do jogador

            // *** AJUSTE PARA BÔNUS/PENALIDADE *** (Exemplo: Trauma Recente)
            $bonus_penalidade = 0;
            // if ($atributo_teste == 'cha' && $alguma_condicao_de_trauma) {
            //     $bonus_penalidade = -1; // Exemplo de penalidade
            // }
            $modificador_final = $atributo_jogador + $bonus_penalidade;


            // 3. Realizar o teste
            $resultado_teste = teste_atributo($modificador_final, $dc_teste);

            // Adicionar feedback sobre o teste
            $mensagem_teste .= "<div class='feedback feedback-test'>";
            $mensagem_teste .= "<strong>[TESTE DE " . strtoupper($atributo_teste) . "]</strong> (DC: {$dc_teste})<br>";
            $mensagem_teste .= "Seu Atributo: {$atributo_jogador}";
            if ($bonus_penalidade != 0) {
                 $mensagem_teste .= ($bonus_penalidade > 0 ? " + " : " ") . $bonus_penalidade . " (Modificador)";
            }
             $mensagem_teste .= " = {$modificador_final}<br>";
             $mensagem_teste .= "Rolagem d100: {$resultado_teste['roll']} + Modificador ({$modificador_final}) = <strong>{$resultado_teste['final']}</strong><br>";


            // 4. Determinar o PRÓXIMO diálogo com base no resultado
            //    (Usando a convenção: ID_Falha = ID_Escolha, ID_Sucesso = ID_Escolha + X ou buscar no DB)
            //    No nosso SQL, definimos ID 107 (Falha) e 111 (Sucesso) para a escolha 106.
            if ($dialogo_id_escolhido == 106) { // Específico para a escolha de Persuasão
                 if ($resultado_teste['resultado'] === 'sucesso' || $resultado_teste['resultado'] === 'critico') {
                    $proximo_dialogo_id_definido = 111; // ID da resposta de Sucesso
                    $mensagem_teste .= "<span style='color: var(--accent-vital);'>Resultado: SUCESSO!</span>";
                 } else {
                    $proximo_dialogo_id_definido = 107; // ID da resposta de Falha (já era o padrão, mas confirmamos)
                    $mensagem_teste .= "<span style='color: var(--status-hp);'>Resultado: FALHA!</span>";
                 }
            }
            // Adicione 'else if' para outras escolhas com testes aqui...

            $mensagem_teste .= "</div>";

        } // Fim da verificação de teste de atributo

        // Registrar escolha AGORA que sabemos o resultado do teste
        $sql_registrar = "INSERT INTO player_escolhas_dialogo (player_id, dialogo_id, escolha_feita) VALUES (?, ?, ?)";
        $stmt_reg = $conexao->prepare($sql_registrar);
        $stmt_reg->bind_param("iis", $player_id, $dialogo_id_escolhido, $escolha['dialogo_texto']);
        $stmt_reg->execute();


        // 5. Aplicar consequências IMEDIATAS da escolha (se houver, independente do teste)
        if ($escolha['acao_trigger'] && $escolha['acao_valor']) {
             // Verificar se a função existe antes de chamar
             if (function_exists('aplicar_consequencia_dialogo')) {
                aplicar_consequencia_dialogo($player_id, $escolha['acao_trigger'], $escolha['acao_valor'], $conexao);
             }
        }

        // 6. Definir o ID do próximo diálogo a ser carregado
        $dialogo_id_apos_escolha = $proximo_dialogo_id_definido;

        // Redirecionar se houver um próximo diálogo definido
        if ($dialogo_id_apos_escolha) {
            header("Location: npc_interact.php?npc_id=$npc_id&dialogo_id=" . $dialogo_id_apos_escolha);
            exit;
        } else {
            // Se não há próximo ID, o diálogo terminou após esta escolha
            $mensagem .= "<div class='feedback feedback-success'>💬 Diálogo concluído!</div>";
            // O script continuará e carregará $dialogo_atual como null/false
        }

    } // Fim if ($escolha)
} // Fim if (isset($_POST['escolha_dialogo']))


// --- DETERMINAR DIÁLOGO ATUAL ---
// Se viemos de uma escolha que definiu o próximo ID, usamos ele. Senão, usamos o da URL ou buscamos o primeiro.
$dialogo_id_atual = $dialogo_id_apos_escolha ?? (isset($_GET['dialogo_id']) ? (int)$_GET['dialogo_id'] : null);


// --- CARREGAR DADOS PARA EXIBIÇÃO ---
$npc_data = get_npc_data($npc_id, $conexao);
$dialogo_atual = get_npc_dialogo_atual($npc_id, $dialogo_id_atual, $conexao);
// Carrega opções APENAS se o diálogo atual for do NPC (para evitar mostrar opções após escolha do jogador que terminou o diálogo)
$opcoes_jogador = ($dialogo_atual && $dialogo_atual['tipo'] == 'npc') ? get_opcoes_jogador($dialogo_atual['id'], $npc_id, $conexao) : [];


// --- APLICAR CONSEQUÊNCIAS DE DIÁLOGO NPC ---
// Aplicar consequências se for um diálogo NPC (seja o inicial ou um após escolha)
if ($dialogo_atual && $dialogo_atual['tipo'] == 'npc' && $dialogo_atual['acao_trigger'] && $dialogo_atual['acao_valor']) {
     // Verificar se a função existe antes de chamar
     if (function_exists('aplicar_consequencia_dialogo')) {
        aplicar_consequencia_dialogo($player_id, $dialogo_atual['acao_trigger'], $dialogo_atual['acao_valor'], $conexao);
     }
}


// --- LÓGICA DE ATRIBUIÇÃO DE QUEST NO DIÁLOGO ---
// (Código de atribuição de quest - Mantido como estava)
if ($dialogo_atual) {
    if ($dialogo_atual['id'] == 107 || $dialogo_atual['id'] == 111) { // IDs das respostas de Kaelen que dão a quest
        $quest_id_custo_poder = 1;
        // Ajuste: Verificar se a função atribuir_quest existe antes de chamar
        if (function_exists('atribuir_quest')) {
            // Verificar se já tem ou completou antes de atribuir
            $sql_check_quest_atrib = "SELECT id FROM player_quests WHERE player_id = ? AND quest_id = ?";
            $stmt_check_atrib = $conexao->prepare($sql_check_quest_atrib);
            $stmt_check_atrib->bind_param("ii", $player_id, $quest_id_custo_poder);
            $stmt_check_atrib->execute();
            if ($stmt_check_atrib->get_result()->num_rows == 0) {
                if (atribuir_quest($player_id, $quest_id_custo_poder, $conexao)) {
                     $_SESSION['feedback_quest'] = "<div class='feedback feedback-success quest-notification'>🎯 <strong>Nova Missão Desbloqueada!</strong><br>Missão: <em>O Custo do Poder</em> foi adicionada ao seu diário!</div>";
                }
            }
             $stmt_check_atrib->close();
        }
    }
}


// --- EXIBIR MENSAGENS DE FEEDBACK (QUEST E TESTE) ---
if (isset($_SESSION['feedback_quest'])) {
    $mensagem .= $_SESSION['feedback_quest'];
    unset($_SESSION['feedback_quest']);
}
// Adiciona a mensagem do teste de atributo (se houver)
$mensagem .= $mensagem_teste;


// --- CARREGAR REPUTAÇÃO ---
$reputacao_guilda = get_reputacao_faccao($player_id, 'guilda', $conexao);
$reputacao_faccao = get_reputacao_faccao($player_id, 'faccao_oculta', $conexao);


// --- INCLUIR HEADER E HTML ---
include 'header.php'; // Inclui o header aqui para ter $player_data disponível
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Diálogo com <?php echo htmlspecialchars($npc_data['nome'] ?? 'NPC'); ?> | RPG MUD</title>
    <style>
        /* Estilos específicos mantidos como antes... */
        .npc-dialogue-header { /* ... */ }
        .npc-portrait { /* ... */ }
        /* ... (restante dos estilos) ... */

        /* Novo estilo para feedback de teste */
        .feedback-test {
            background: rgba(255, 215, 0, 0.1); /* Amarelo claro */
            border-color: #FFD700;
            color: #FFD700; /* Dourado */
            margin-top: 15px;
            padding: 10px 15px;
            border-left: 4px solid #FFD700;
        }

        /* Estilo para opção com requisito */
        .dialogue-option[data-requires-test="true"]::after {
            content: ' [Teste de Atributo]';
            font-size: 0.8em;
            color: #FFD700; /* Dourado */
            margin-left: 5px;
            font-style: italic;
        }

    </style>
</head>
<body>
    <div class="container fade-in">
        <?php echo $mensagem; ?>

        <div class="section section-vital">
            <div class="npc-dialogue-header">
                <div class="npc-portrait">
                    <div class="portrait-icon"><?php echo htmlspecialchars($npc_data['icone'] ?? '👤'); ?></div>
                </div>
                <div class="npc-info">
                    <h2><?php echo htmlspecialchars($npc_data['nome'] ?? 'Desconhecido'); ?></h2>
                    <p class="npc-description"><?php echo htmlspecialchars($npc_data['descricao'] ?? ''); ?></p>

                    <div class="reputation-display">
                        <div class="reputation-item guild-rep">
                            🏛️ Guilda: <?php echo $reputacao_guilda; ?> (<?php echo calcular_relacionamento($reputacao_guilda); ?>)
                        </div>
                        <div class="reputation-item faction-rep">
                            🌑 Facção: <?php echo $reputacao_faccao; ?> (<?php echo calcular_relacionamento($reputacao_faccao); ?>)
                        </div>
                    </div>

                    <?php if (function_exists('is_quest_completa') && $npc_id == 10 && is_quest_completa($player_id, $quest_id_custo_poder, $conexao)): ?>
                    <div class="quest-complete-indicator">
                        ✅ <strong>Missão Pronta para Entrega!</strong><br>
                        <small>Você completou "O Custo do Poder". Fale com Kaelen para receber sua recompensa!</small>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <div class="section section-arcane">
            <div class="dialogue-container">
                <?php if ($dialogo_atual): ?>

                <div class="dialogue-history">
                    <div class="dialogue-message npc-message">
                        <div class="message-sender"><?php echo htmlspecialchars($npc_data['nome'] ?? 'NPC'); ?></div>
                        <div class="message-content"><?php echo nl2br(htmlspecialchars($dialogo_atual['dialogo_texto'])); // Use nl2br para quebras de linha ?></div>
                    </div>

                    <?php if ($dialogo_atual['id'] == 107 || $dialogo_atual['id'] == 111): // IDs que dão a quest ?>
                    <div class="quest-hint" style="margin-top: 15px; padding: 10px; background: rgba(255, 215, 0, 0.1); border-radius: 5px; border-left: 3px solid #FFD700;">
                        <small>💡 <strong>Dica:</strong> Uma nova missão foi adicionada ao seu diário!</small>
                    </div>
                    <?php endif; ?>

                    <?php if (function_exists('is_quest_completa') && $npc_id == 10 && is_quest_completa($player_id, $quest_id_custo_poder, $conexao)): ?>
                    <div class="quest-complete-indicator" style="margin-top: 15px;">
                        🎉 <strong>Parabéns!</strong> Você completou a missão "O Custo do Poder".
                        Escolha uma opção de diálogo para receber sua recompensa!
                    </div>
                    <?php endif; ?>
                </div>

                <div class="dialogue-options">
                    <?php if (!empty($opcoes_jogador)): ?>
                        <?php foreach($opcoes_jogador as $opcao): ?>
                        <?php
                            // Verifica se esta opção requer teste para adicionar indicador visual
                            $req_opcao = isset($opcao['requisitos']) ? json_decode($opcao['requisitos'], true) : null;
                            $requer_teste = ($req_opcao && isset($req_opcao['teste_atributo']));
                        ?>
                        <form method="POST" class="dialogue-option-form">
                            <input type="hidden" name="dialogo_id" value="<?php echo $opcao['id']; ?>">
                            <button type="submit" name="escolha_dialogo" class="dialogue-option"
                                    data-requires-test="<?php echo $requer_teste ? 'true' : 'false'; ?>">
                                💬 <?php echo htmlspecialchars($opcao['dialogo_texto']); ?>

                                <?php if (function_exists('is_quest_completa') && $npc_id == 10 && is_quest_completa($player_id, $quest_id_custo_poder, $conexao) && $opcao['id'] == 10): // Ajuste o ID da opção se necessário ?>
                                <span style="float: right; color: #4CAF50; font-size: 0.9em;">💰 Recompensa</span>
                                <?php endif; ?>
                            </button>
                        </form>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div class="dialogue-end">
                            <p>O diálogo chegou ao fim.</p>
                            <a href="cidade.php" class="back-button">🏙️ Voltar para a Cidade</a>
                        </div>
                    <?php endif; ?>
                </div>

                <?php else: ?>
                <div class="no-dialogue">
                    <p>Fim da conversa ou diálogo não encontrado.</p>
                    <a href="cidade.php" class="back-button">🏙️ Voltar para a Cidade</a>
                </div>
                <?php endif; ?>
            </div>
        </div>

        <div class="section section-vital text-center">
            <a href="quests.php" class="btn btn-primary" style="display: inline-flex; align-items: center; gap: 8px;">
                📖 Ver Diário de Missões
            </a>
        </div>
    </div>

    <?php include 'footer.php'; ?>

    <script>
    // Script de animação de feedback de quest mantido como estava...
    document.addEventListener('DOMContentLoaded', function() {
        const questFeedback = document.querySelector('.feedback-success.quest-notification');
        if (questFeedback) {
            // ... (lógica de animação e remoção) ...
        }
        const questCompleteIndicators = document.querySelectorAll('.quest-complete-indicator');
        questCompleteIndicators.forEach(indicator => {
            indicator.style.animation = 'pulse-quest 2s infinite';
        });
    });
    </script>
</body>
</html>