<?php
session_start();
include 'db_connect.php';

$titulo_pagina = "Portal de Acesso - Arcana Duality";
$pagina_atual = 'login';

// =============================================================================
// VERIFICAÇÃO DE SESSÃO EXISTENTE
// =============================================================================
if (isset($_SESSION['player_id'])) {
    header('Location: inventario.php');
    exit;
}

// =============================================================================
// PROCESSAMENTO DO LOGIN
// =============================================================================
$mensagem = "";
$modo = $_GET['modo'] ?? 'login'; // 'login' ou 'registro'

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['acao']) && $_POST['acao'] === 'login') {
        // PROCESSAR LOGIN
        $email = $conexao->real_escape_string($_POST['email']);
        $senha_bruta = $_POST['senha'];

        $sql = "SELECT id, nome, senha_hash, level, classe_base FROM personagens WHERE email = '{$email}'";
        $resultado = $conexao->query($sql);
        $usuario = $resultado->fetch_assoc();

        if ($usuario && password_verify($senha_bruta, $usuario['senha_hash'])) {
            $_SESSION['player_id'] = $usuario['id'];
            $_SESSION['player_nome'] = $usuario['nome'];
            $_SESSION['player_level'] = $usuario['level'];
            $_SESSION['player_classe'] = $usuario['classe_base'];
            
            header('Location: inventario.php');
            exit;
        } else {
            $mensagem = "<div class='feedback feedback-error'>❌ Credenciais inválidas. Tente novamente.</div>";
            $modo = 'login';
        }
    } 
    
    elseif (isset($_POST['acao']) && $_POST['acao'] === 'registro') {
        // PROCESSAR REGISTRO
        $nome = $conexao->real_escape_string($_POST['nome']);
        $email = $conexao->real_escape_string($_POST['email']);
        $senha_bruta = $_POST['senha'];
        $classe_base = $conexao->real_escape_string($_POST['classe_base']);

        // Verifica se email já existe
        $sql_check = "SELECT id FROM personagens WHERE email = '{$email}'";
        $result_check = $conexao->query($sql_check);
        
        if ($result_check->num_rows > 0) {
            $mensagem = "<div class='feedback feedback-error'>❌ Este email já está em uso.</div>";
            $modo = 'registro';
        } else {
            // Hash da senha
            $senha_hash = password_hash($senha_bruta, PASSWORD_DEFAULT);
            
            // Stats iniciais baseados na classe
            $stats_iniciais = [
                'Guerreiro' => ['str' => 15, 'dex' => 10, 'con' => 14, 'int_stat' => 8, 'wis' => 10, 'cha' => 8],
                'Ladino' => ['str' => 10, 'dex' => 15, 'con' => 12, 'int_stat' => 10, 'wis' => 12, 'cha' => 10],
                'Mago' => ['str' => 8, 'dex' => 12, 'con' => 10, 'int_stat' => 15, 'wis' => 14, 'cha' => 8],
                'Sacerdote' => ['str' => 10, 'dex' => 10, 'con' => 12, 'int_stat' => 12, 'wis' => 15, 'cha' => 10],
                'Caçador' => ['str' => 12, 'dex' => 14, 'con' => 12, 'int_stat' => 10, 'wis' => 12, 'cha' => 8]
            ];
            
            $stats = $stats_iniciais[$classe_base] ?? $stats_iniciais['Guerreiro'];
            
            // Insere novo personagem
            $sql_insert = "INSERT INTO personagens 
                (nome, email, senha_hash, classe_base, level, ouro, xp_atual, xp_proximo_level,
                 str, dex, con, int_stat, wis, cha, hp_max, hp_atual, mana_max, mana_atual,
                 pontos_atributo_disponiveis, pontos_habilidade_disponiveis, fama_rank)
                VALUES (
                    '{$nome}', '{$email}', '{$senha_hash}', '{$classe_base}', 1, 100, 0, 100,
                    {$stats['str']}, {$stats['dex']}, {$stats['con']}, {$stats['int_stat']}, 
                    {$stats['wis']}, {$stats['cha']}, 100, 100, 50, 50,
                    5, 2, 'Novato'
                )";
            
            if ($conexao->query($sql_insert)) {
                $mensagem = "<div class='feedback feedback-success'>✨ Personagem criado com sucesso! Faça login para começar sua jornada.</div>";
                $modo = 'login';
            } else {
                $mensagem = "<div class='feedback feedback-error'>❌ Erro ao criar personagem. Tente novamente.</div>";
                $modo = 'registro';
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $titulo_pagina; ?></title>
    <style>
        /* CORREÇÃO CRÍTICA - PERMITIR CLICKS */
        .login-container {
            min-height: 100vh;
            background: linear-gradient(135deg, #1a1a1a 0%, #1a0a2a 100%);
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
            font-family: 'Courier New', monospace;
        }

        .portal-card {
            background: #2a2a2a;
            border: 3px solid #8A2BE2;
            border-radius: 15px;
            padding: 40px;
            width: 100%;
            max-width: 500px;
            box-shadow: 0 20px 40px rgba(138, 43, 226, 0.3);
            position: relative;
        }

        /* REMOVER O ::before PROBLEMÁTICO E USAR BACKGROUND NORMAL */
        .portal-card {
            background: linear-gradient(135deg, #2a2a2a 0%, #2a1a3a 100%);
        }

        .portal-header {
            text-align: center;
            margin-bottom: 30px;
            position: relative;
            z-index: 10;
        }

        .portal-icon {
            font-size: 4em;
            margin-bottom: 15px;
        }

        .portal-title {
            color: #8A2BE2;
            font-size: 2.5em;
            margin-bottom: 10px;
            text-shadow: 0 0 20px rgba(138, 43, 226, 0.5);
        }

        .portal-subtitle {
            color: #aaaaaa;
            font-size: 1.1em;
        }

        .form-tabs {
            display: flex;
            margin-bottom: 30px;
            background: #333333;
            border-radius: 10px;
            padding: 5px;
            position: relative;
            z-index: 10;
        }

        .tab-btn {
            flex: 1;
            padding: 12px 20px;
            background: transparent;
            border: none;
            border-radius: 8px;
            color: #aaaaaa;
            font-weight: bold;
            cursor: pointer;
            transition: all 0.3s ease;
            text-transform: uppercase;
            font-size: 0.9em;
            position: relative;
            z-index: 11;
        }

        .tab-btn.active {
            background: #8A2BE2;
            color: white;
            box-shadow: 0 4px 12px rgba(138, 43, 226, 0.4);
        }

        .tab-content {
            display: none;
            position: relative;
            z-index: 10;
        }

        .tab-content.active {
            display: block;
        }

        .form-group {
            margin-bottom: 20px;
            position: relative;
            z-index: 10;
        }

        .form-label {
            display: block;
            margin-bottom: 8px;
            color: #50C878;
            font-weight: bold;
            text-transform: uppercase;
            font-size: 0.9em;
        }

        .form-input {
            width: 100%;
            padding: 12px 15px;
            background: #1a1a1a;
            border: 2px solid #8A2BE2;
            border-radius: 8px;
            color: #cccccc;
            font-family: inherit;
            font-size: 1em;
            transition: all 0.3s ease;
            position: relative;
            z-index: 11;
        }

        .form-input:focus {
            outline: none;
            border-color: #FF00FF;
            box-shadow: 0 0 15px rgba(138, 43, 226, 0.5);
        }

        .class-selection {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(120px, 1fr));
            gap: 10px;
            margin-bottom: 20px;
            position: relative;
            z-index: 10;
        }

        .class-option {
            padding: 15px 10px;
            background: #1a1a1a;
            border: 2px solid #333333;
            border-radius: 8px;
            text-align: center;
            cursor: pointer;
            transition: all 0.3s ease;
            position: relative;
            z-index: 11;
        }

        .class-option:hover {
            border-color: #8A2BE2;
            transform: translateY(-2px);
        }

        .class-option.selected {
            border-color: #50C878;
            background: rgba(80, 200, 120, 0.1);
            box-shadow: 0 5px 15px rgba(80, 200, 120, 0.3);
        }

        .class-icon {
            font-size: 2em;
            margin-bottom: 8px;
        }

        .class-name {
            font-weight: bold;
            color: #cccccc;
            margin-bottom: 5px;
        }

        .class-desc {
            font-size: 0.8em;
            color: #aaaaaa;
        }

        .btn-portal {
            width: 100%;
            padding: 15px;
            background: linear-gradient(135deg, #8A2BE2, #FF00FF);
            color: white;
            border: none;
            border-radius: 8px;
            font-size: 1.1em;
            font-weight: bold;
            text-transform: uppercase;
            cursor: pointer;
            transition: all 0.3s ease;
            margin-top: 10px;
            position: relative;
            z-index: 11;
        }

        .btn-portal:hover {
            transform: translateY(-3px);
            box-shadow: 0 10px 25px rgba(138, 43, 226, 0.5);
        }

        .portal-footer {
            text-align: center;
            margin-top: 25px;
            padding-top: 20px;
            border-top: 1px solid #333333;
            position: relative;
            z-index: 10;
        }

        .footer-text {
            color: #aaaaaa;
            font-size: 0.9em;
        }

        .footer-link {
            color: #50C878;
            text-decoration: none;
        }

        .footer-link:hover {
            text-decoration: underline;
        }

        .feedback {
            padding: 15px;
            margin: 15px 0;
            border-radius: 6px;
            border-left: 5px solid;
            background: #333333;
            position: relative;
            z-index: 10;
        }

        .feedback-success {
            border-left-color: #50C878;
            background: linear-gradient(135deg, #333333 0%, rgba(80, 200, 120, 0.1) 100%);
            color: #50C878;
        }

        .feedback-error {
            border-left-color: #FF4444;
            background: linear-gradient(135deg, #333333 0%, rgba(255, 68, 68, 0.1) 100%);
            color: #FF4444;
        }

        @media (max-width: 768px) {
            .portal-card {
                padding: 30px 20px;
            }

            .portal-title {
                font-size: 2em;
            }

            .class-selection {
                grid-template-columns: repeat(2, 1fr);
            }
        }

        @media (max-width: 480px) {
            .class-selection {
                grid-template-columns: 1fr;
            }

            .form-tabs {
                flex-direction: column;
            }

            .tab-btn {
                margin-bottom: 5px;
            }
        }
    </style>
</head>
<body>
    <div class="login-container">
        <div class="portal-card">
            <div class="portal-header">
                <div class="portal-icon">🌌</div>
                <h1 class="portal-title">ARCANA DUALITY</h1>
                <p class="portal-subtitle">Entre no Mundo da Fenda</p>
            </div>

            <?php echo $mensagem; ?>

            <div class="form-tabs">
                <button class="tab-btn <?php echo $modo === 'login' ? 'active' : ''; ?>" data-tab="login">
                    🚪 Entrar
                </button>
                <button class="tab-btn <?php echo $modo === 'registro' ? 'active' : ''; ?>" data-tab="registro">
                    ✨ Novo Personagem
                </button>
            </div>

            <!-- FORMULÁRIO DE LOGIN -->
            <form method="POST" action="login.php?modo=login" class="tab-content <?php echo $modo === 'login' ? 'active' : ''; ?>" id="login-tab">
                <input type="hidden" name="acao" value="login">
                
                <div class="form-group">
                    <label for="email" class="form-label">📧 Email</label>
                    <input type="email" id="email" name="email" class="form-input" placeholder="seu@email.com" required>
                </div>

                <div class="form-group">
                    <label for="senha" class="form-label">🔒 Senha</label>
                    <input type="password" id="senha" name="senha" class="form-input" placeholder="Sua senha secreta" required>
                </div>

                <button type="submit" class="btn-portal">
                    🚀 Acessar o Jogo
                </button>
            </form>

            <!-- FORMULÁRIO DE REGISTRO -->
            <form method="POST" action="login.php?modo=registro" class="tab-content <?php echo $modo === 'registro' ? 'active' : ''; ?>" id="registro-tab">
                <input type="hidden" name="acao" value="registro">
                
                <div class="form-group">
                    <label for="nome" class="form-label">👤 Nome do Personagem</label>
                    <input type="text" id="nome" name="nome" class="form-input" placeholder="Escolha um nome épico" required>
                </div>

                <div class="form-group">
                    <label for="email_registro" class="form-label">📧 Email</label>
                    <input type="email" id="email_registro" name="email" class="form-input" placeholder="seu@email.com" required>
                </div>

                <div class="form-group">
                    <label for="senha_registro" class="form-label">🔒 Senha</label>
                    <input type="password" id="senha_registro" name="senha" class="form-input" placeholder="Mínimo 6 caracteres" minlength="6" required>
                </div>

                <div class="form-group">
                    <label class="form-label">⚔️ Escolha sua Classe</label>
                    <div class="class-selection" id="classSelection">
                        <div class="class-option selected" data-class="Guerreiro">
                            <div class="class-icon">🛡️</div>
                            <div class="class-name">Guerreiro</div>
                            <div class="class-desc">Força & Defesa</div>
                        </div>
                        <div class="class-option" data-class="Ladino">
                            <div class="class-icon">🗡️</div>
                            <div class="class-name">Ladino</div>
                            <div class="class-desc">Precisão & Furtividade</div>
                        </div>
                        <div class="class-option" data-class="Mago">
                            <div class="class-icon">🔮</div>
                            <div class="class-name">Mago</div>
                            <div class="class-desc">Poder Arcano</div>
                        </div>
                        <div class="class-option" data-class="Sacerdote">
                            <div class="class-icon">✨</div>
                            <div class="class-name">Sacerdote</div>
                            <div class="class-desc">Cura & Proteção</div>
                        </div>
                        <div class="class-option" data-class="Caçador">
                            <div class="class-icon">🏹</div>
                            <div class="class-name">Caçador</div>
                            <div class="class-desc">Precisão & Sobrevivência</div>
                        </div>
                    </div>
                    <input type="hidden" id="classe_base" name="classe_base" value="Guerreiro" required>
                </div>

                <button type="submit" class="btn-portal">
                    🌟 Criar Personagem
                </button>
            </form>

            <div class="portal-footer">
                <p class="footer-text">
                    Ao entrar, você concorda com os <a href="#" class="footer-link">Termos da Fenda</a>
                </p>
            </div>
        </div>
    </div>

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

            // Sistema de Seleção de Classe
            const classOptions = document.querySelectorAll('.class-option');
            const classInput = document.getElementById('classe_base');
            
            classOptions.forEach(option => {
                option.addEventListener('click', function() {
                    const selectedClass = this.getAttribute('data-class');
                    
                    // Atualiza seleção visual
                    classOptions.forEach(opt => opt.classList.remove('selected'));
                    this.classList.add('selected');
                    
                    // Atualiza input hidden
                    classInput.value = selectedClass;
                });
            });

            // Seleciona Guerreiro por padrão
            document.querySelector('.class-option[data-class="Guerreiro"]').click();

            // Efeitos visuais para inputs
            const inputs = document.querySelectorAll('.form-input');
            inputs.forEach(input => {
                input.addEventListener('focus', function() {
                    this.style.transform = 'translateY(-2px)';
                });
                
                input.addEventListener('blur', function() {
                    this.style.transform = 'translateY(0)';
                });
            });
        });
    </script>
</body>
</html>