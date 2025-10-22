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
            $mensagem = "<div class='feedback feedback-success'>💬 Diálogo concluído!</div>";
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
    </style>
</head>
<body>
    <?php include 'header.php'; ?>

    <div class="container fade-in">
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
                </div>

                <!-- OPÇÕES DE RESPOSTA -->
                <div class="dialogue-options">
                    <?php if (!empty($opcoes_jogador)): ?>
                        <?php foreach($opcoes_jogador as $opcao): ?>
                        <form method="POST" class="dialogue-option-form">
                            <input type="hidden" name="dialogo_id" value="<?php echo $opcao['id']; ?>">
                            <button type="submit" name="escolha_dialogo" class="dialogue-option">
                                💬 <?php echo $opcao['dialogo_texto']; ?>
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
    </div>

    <?php include 'footer.php'; ?>
</body>
</html>