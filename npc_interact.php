<?php
session_start();
include_once 'db_connect.php';
include_once 'dialogue_system_functions.php';

if (!isset($_SESSION['player_id']) || !isset($_GET['npc_id'])) {
    header('Location: cidade.php');
    exit;
}

$player_id = $_SESSION['player_id'];
$npc_id = (int)$_GET['npc_id'];
$mensagem = "";

// ---> NOVO: VERIFICAR ENTREGA DE QUEST ANTES DE CARREGAR DIÁLOGO <---
$quest_id_custo_poder = 1;
if ($npc_id == 5 && is_quest_completa($player_id, $quest_id_custo_poder, $conexao)) {
    $resultado_entrega = entregar_quest($player_id, $quest_id_custo_poder, $conexao);
    if ($resultado_entrega['sucesso']) {
        $mensagem .= "<div class='feedback feedback-success'>{$resultado_entrega['mensagem']}</div>";
        // Após entregar, você pode querer direcionar para um diálogo específico de Kaelen
        // Ex: $dialogo_id_atual = 10; // ID do diálogo de Kaelen pós-missão
    } else {
        $mensagem .= "<div class='feedback feedback-error'>{$resultado_entrega['mensagem']}</div>";
    }
}
// ------------------------------------------------------------------

// Processar escolha de diálogo
if (isset($_POST['escolha_dialogo'])) {
    $dialogo_id = (int)$_POST['dialogo_id'];
    $resultado = processar_escolha_dialogo($player_id, $dialogo_id, $conexao);
    
    if ($resultado) {
        if ($resultado['proximo_dialogo_id']) {
            // Redirecionar para o próximo diálogo
            header("Location: npc_interact.php?npc_id=$npc_id&dialogo_id=" . $resultado['proximo_dialogo_id']);
            exit;
        } else {
            $mensagem .= "<div class='feedback feedback-success'>💬 Diálogo concluído!</div>";
        }
    }
}

// Determinar diálogo atual
$dialogo_id_atual = isset($_GET['dialogo_id']) ? (int)$_GET['dialogo_id'] : null;

// Carregar dados
$npc_data = get_npc_data($npc_id, $conexao);
$dialogo_atual = get_npc_dialogo_atual($npc_id, $dialogo_id_atual, $conexao);
$opcoes_jogador = $dialogo_atual ? get_opcoes_jogador($dialogo_atual['id'], $npc_id, $conexao) : [];

// Aplicar consequências se for um diálogo NPC final
if ($dialogo_atual && $dialogo_atual['tipo'] == 'npc' && $dialogo_atual['acao_trigger'] && !$dialogo_id_atual) {
    aplicar_consequencia_dialogo($player_id, $dialogo_atual['acao_trigger'], $dialogo_atual['acao_valor'], $conexao);
}

// ✅ SISTEMA DE MISSÕES - VERIFICAR SE O DIÁLOGO ATUAL DEVE ATRIBUIR UMA MISSÃO
if ($dialogo_atual) {
    // Exemplo: Se o diálogo atual é o ID 6 (onde Kaelen dá a quest)
    if ($dialogo_atual['id'] == 6) { // Ou o ID correto do diálogo onde Kaelen dá a quest
        $quest_id_custo_poder = 1; // ID da quest que inserimos no passo 1
        
        // Verificar se a missão já foi atribuída para evitar duplicação
        $sql_check_quest = "SELECT id FROM personagem_quests WHERE id_personagem = ? AND id_quest_base = ? AND status != 'completa'";
        $stmt_check = $conexao->prepare($sql_check_quest);
        $stmt_check->bind_param("ii", $player_id, $quest_id_custo_poder);
        $stmt_check->execute();
        $result_check = $stmt_check->get_result();
        
        if ($result_check->num_rows == 0) {
            // Atribuir a missão apenas se ainda não foi atribuída
            if (atribuir_quest($player_id, $quest_id_custo_poder, $conexao)) {
                // Adicionar mensagem de feedback na sessão
                $_SESSION['feedback_quest'] = "<div class='feedback feedback-success'>🎯 <strong>Nova Missão Desbloqueada!</strong><br>Missão: <em>O Custo do Poder</em> foi adicionada ao seu diário!</div>";
            }
        }
    }
    
    // ✅ ADICIONAR MAIS CONDIÇÕES PARA OUTRAS MISSÕES AQUI
    // Exemplo para futuras missões:
    /*
    if ($dialogo_atual['id'] == 15) { // Outro diálogo que dá missão
        $outra_quest_id = 2;
        if (atribuir_quest($player_id, $outra_quest_id, $conexao)) {
            $_SESSION['feedback_quest'] = "<div class='feedback feedback-success'>🎯 Nova Missão: [Nome da Missão] adicionada!</div>";
        }
    }
    */
}

// ✅ EXIBIR MENSAGENS DE MISSÃO DA SESSÃO
if (isset($_SESSION['feedback_quest'])) {
    $mensagem .= $_SESSION['feedback_quest'];
    unset($_SESSION['feedback_quest']);
}

// Carregar reputação
$reputacao_guilda = get_reputacao_faccao($player_id, 'guilda', $conexao);
$reputacao_faccao = get_reputacao_faccao($player_id, 'faccao_oculta', $conexao);
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Diálogo com <?php echo $npc_data['nome']; ?> | RPG MUD</title>
    <link rel="stylesheet" href="styles.css">
    <style>
        .npc-dialogue-header {
            display: flex;
            align-items: center;
            gap: 20px;
            padding: 20px;
            background: var(--bg-secondary);
            border-radius: 12px;
            margin-bottom: 20px;
        }

        .npc-portrait {
            width: 80px;
            height: 80px;
            background: var(--bg-primary);
            border: 2px solid var(--accent-arcane);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .portrait-icon {
            font-size: 2.5em;
        }

        .npc-info h2 {
            margin: 0 0 10px 0;
            color: var(--accent-vital);
        }

        .reputation-display {
            display: flex;
            gap: 20px;
            margin-top: 10px;
        }

        .reputation-item {
            padding: 5px 12px;
            background: var(--bg-primary);
            border-radius: 8px;
            font-size: 0.9em;
        }

        .guild-rep { border-left: 3px solid var(--accent-vital); }
        .faction-rep { border-left: 3px solid var(--accent-arcane); }

        .dialogue-container {
            background: var(--bg-primary);
            border: 1px solid var(--bg-tertiary);
            border-radius: 12px;
            overflow: hidden;
            margin-bottom: 20px;
        }

        .dialogue-history {
            padding: 25px;
            border-bottom: 1px solid var(--bg-tertiary);
            min-height: 120px;
        }

        .dialogue-message {
            margin-bottom: 15px;
        }

        .message-sender {
            font-weight: bold;
            color: var(--accent-arcane);
            margin-bottom: 8px;
            font-size: 1.1em;
        }

        .message-content {
            color: var(--text-primary);
            line-height: 1.6;
            font-size: 1.05em;
        }

        .dialogue-options {
            padding: 20px;
            display: flex;
            flex-direction: column;
            gap: 12px;
        }

        .dialogue-option-form {
            margin: 0;
        }

        .dialogue-option {
            display: block;
            width: 100%;
            padding: 16px 20px;
            background: var(--bg-secondary);
            border: 1px solid var(--bg-tertiary);
            border-radius: 8px;
            color: var(--text-primary);
            text-align: left;
            cursor: pointer;
            transition: all 0.3s ease;
            font-size: 1em;
        }

        .dialogue-option:hover {
            background: var(--accent-arcane);
            color: white;
            border-color: var(--accent-arcane);
            transform: translateX(5px);
        }

        .dialogue-end, .no-dialogue {
            text-align: center;
            padding: 40px 20px;
            color: var(--text-secondary);
        }

        .back-button {
            display: inline-block;
            padding: 10px 20px;
            background: var(--bg-secondary);
            color: var(--text-primary);
            text-decoration: none;
            border-radius: 8px;
            margin-top: 20px;
            transition: all 0.3s ease;
        }

        .back-button:hover {
            background: var(--accent-arcane);
            color: white;
        }

        /* ✅ NOVOS ESTILOS PARA FEEDBACK DE MISSÕES */
        .feedback {
            padding: 15px;
            border-radius: 8px;
            margin: 15px 0;
            border: 2px solid;
            font-weight: bold;
        }

        .feedback-success {
            background: rgba(76, 175, 80, 0.1);
            border-color: #4CAF50;
            color: #4CAF50;
        }

        .feedback-error {
            background: rgba(239, 71, 111, 0.1);
            border-color: #EF476F;
            color: #EF476F;
        }

        .feedback-quest {
            background: linear-gradient(135deg, rgba(255, 215, 0, 0.1), rgba(255, 165, 0, 0.1));
            border-color: #FFD700;
            color: #FF8C00;
            border-left: 5px solid #FFD700;
            padding: 20px;
            margin: 20px 0;
        }

        .quest-notification {
            animation: pulse-quest 2s infinite;
        }

        .quest-complete-indicator {
            background: linear-gradient(135deg, rgba(76, 175, 80, 0.1), rgba(76, 175, 80, 0.2));
            border: 2px solid #4CAF50;
            padding: 10px 15px;
            border-radius: 8px;
            margin: 10px 0;
            font-weight: bold;
            color: #4CAF50;
        }

        @keyframes pulse-quest {
            0%, 100% { transform: scale(1); }
            50% { transform: scale(1.02); }
        }
    </style>
</head>
<body>
    <?php include 'header.php'; ?>

    <div class="container fade-in">
        <!-- ✅ MENSAGENS DE FEEDBACK (DIÁLOGO E MISSÕES) -->
        <?php echo $mensagem; ?>

        <!-- CABEÇALHO DO NPC -->
        <div class="section section-vital">
            <div class="npc-dialogue-header">
                <div class="npc-portrait">
                    <div class="portrait-icon"><?php echo $npc_data['icone'] ?? '👤'; ?></div>
                </div>
                <div class="npc-info">
                    <h2><?php echo $npc_data['nome']; ?></h2>
                    <p class="npc-description"><?php echo $npc_data['descricao']; ?></p>
                    
                    <div class="reputation-display">
                        <div class="reputation-item guild-rep">
                            🏛️ Guilda: <?php echo $reputacao_guilda; ?>
                        </div>
                        <div class="reputation-item faction-rep">
                            🌑 Facção: <?php echo $reputacao_faccao; ?>
                        </div>
                    </div>

                    <!-- ✅ INDICADOR DE QUEST COMPLETA PARA ENTREGA -->
                    <?php if ($npc_id == 5 && is_quest_completa($player_id, $quest_id_custo_poder, $conexao)): ?>
                    <div class="quest-complete-indicator">
                        ✅ <strong>Missão Pronta para Entrega!</strong><br>
                        <small>Você completou "O Custo do Poder". Fale com Kaelen para receber sua recompensa!</small>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- ÁREA DE DIÁLOGO -->
        <div class="section section-arcane">
            <div class="dialogue-container">
                <?php if ($dialogo_atual): ?>
                
                <!-- HISTÓRICO DO DIÁLOGO -->
                <div class="dialogue-history">
                    <div class="dialogue-message npc-message">
                        <div class="message-sender"><?php echo $npc_data['nome']; ?></div>
                        <div class="message-content"><?php echo $dialogo_atual['dialogo_texto']; ?></div>
                    </div>
                    
                    <!-- ✅ INDICADOR VISUAL QUANDO UMA MISSÃO É ATRIBUÍDA -->
                    <?php if ($dialogo_atual['id'] == 6): ?>
                    <div class="quest-hint" style="margin-top: 15px; padding: 10px; background: rgba(255, 215, 0, 0.1); border-radius: 5px; border-left: 3px solid #FFD700;">
                        <small>💡 <strong>Dica:</strong> Esta conversa pode levar a uma nova missão!</small>
                    </div>
                    <?php endif; ?>

                    <!-- ✅ INDICADOR DE QUEST COMPLETA NO DIÁLOGO -->
                    <?php if ($npc_id == 5 && is_quest_completa($player_id, $quest_id_custo_poder, $conexao)): ?>
                    <div class="quest-complete-indicator" style="margin-top: 15px;">
                        🎉 <strong>Parabéns!</strong> Você completou a missão "O Custo do Poder". 
                        Escolha uma opção de diálogo para receber sua recompensa!
                    </div>
                    <?php endif; ?>
                </div>

                <!-- OPÇÕES DE RESPOSTA -->
                <div class="dialogue-options">
                    <?php if (!empty($opcoes_jogador)): ?>
                        <?php foreach($opcoes_jogador as $opcao): ?>
                        <form method="POST" class="dialogue-option-form">
                            <input type="hidden" name="dialogo_id" value="<?php echo $opcao['id']; ?>">
                            <button type="submit" name="escolha_dialogo" class="dialogue-option">
                                💬 <?php echo $opcao['dialogo_texto']; ?>
                                
                                <!-- ✅ INDICADOR DE MISSÃO NAS OPÇÕES RELEVANTES -->
                                <?php if ($opcao['id'] == 6): ?>
                                <span style="float: right; color: #FFD700; font-size: 0.9em;">🎯 Missão</span>
                                <?php endif; ?>

                                <!-- ✅ INDICADOR DE ENTREGA DE QUEST -->
                                <?php if ($npc_id == 5 && is_quest_completa($player_id, $quest_id_custo_poder, $conexao) && $opcao['id'] == 10): ?>
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
                    <p>Este NPC não tem diálogos disponíveis.</p>
                    <a href="cidade.php" class="back-button">🏙️ Voltar para a Cidade</a>
                </div>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- ✅ LINK PARA O DIÁRIO DE MISSÕES -->
        <div class="section section-vital text-center">
            <a href="quests.php" class="btn btn-primary" style="display: inline-flex; align-items: center; gap: 8px;">
                📖 Ver Diário de Missões
            </a>
        </div>
    </div>

    <?php include 'footer.php'; ?>
    
    <script>
    // ✅ ANIMAÇÃO PARA NOVAS MISSÕES
    document.addEventListener('DOMContentLoaded', function() {
        const questFeedback = document.querySelector('.feedback-success');
        if (questFeedback && questFeedback.textContent.includes('Missão')) {
            questFeedback.classList.add('quest-notification');
            
            // Auto-remover após 5 segundos
            setTimeout(() => {
                questFeedback.style.opacity = '0';
                questFeedback.style.transition = 'opacity 1s ease';
                setTimeout(() => {
                    if (questFeedback.parentNode) {
                        questFeedback.parentNode.removeChild(questFeedback);
                    }
                }, 1000);
            }, 5000);
        }

        // ✅ DESTACAR OPÇÕES DE ENTREGA DE QUEST
        const questCompleteIndicators = document.querySelectorAll('.quest-complete-indicator');
        questCompleteIndicators.forEach(indicator => {
            indicator.style.animation = 'pulse-quest 2s infinite';
        });
    });
    </script>
</body>
</html>