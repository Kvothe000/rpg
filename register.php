<?php
session_start();
include 'db_connect.php';

$titulo_pagina = "Criação de Personagem - Arcana Duality";
$pagina_atual = 'registro';

// Verifica se o jogador já está logado
if (isset($_SESSION['player_id'])) {
    header('Location: inventario.php');
    exit;
}

// Função para calcular stats derivados
function calcular_stats_derivados($stats, $level, $classe_base) {
    $con = $stats['con'];
    $int_stat = $stats['int_stat'];
    $wis = $stats['wis'];

    $hp_max = ($con * 10) + ($level * 50);

    $recurso_max = 0;
    if ($classe_base === 'Mago' || $classe_base === 'Sacerdote') {
        $recurso_max = ($int_stat + $wis) * 15;
    } else {
        $recurso_max = $con * 10 + $stats['str'] * 5; 
    }
    
    return ['hp_max' => $hp_max, 'recurso_max' => $recurso_max];
}

$mensagem = "";

// Verifica se o formulário foi submetido
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    
    // 1. Coleta e Sanitiza os dados
    $nome = $conexao->real_escape_string($_POST['nome']);
    $email = $conexao->real_escape_string($_POST['email']);
    $senha_bruta = $_POST['senha'];
    $classe_base = $conexao->real_escape_string($_POST['classe_base']);

    // 2. Coleta Pontos Bônus e Calcula Stats Finais (Base 10 + Bônus)
    $stats_bonus = [
        'str' => (int)$_POST['str_bonus'],
        'dex' => (int)$_POST['dex_bonus'],
        'con' => (int)$_POST['con_bonus'],
        'int_stat' => (int)$_POST['int_stat_bonus'],
        'wis' => (int)$_POST['wis_bonus'],
        'cha' => (int)$_POST['cha_bonus']
    ];
    
    // Calcula o total de pontos gastos
    $pontos_gastos = array_sum($stats_bonus);
    
    // Validação principal: 12 pontos
    if ($pontos_gastos !== 12) {
        $mensagem = "<div class='feedback feedback-error'>❌ ERRO: Você deve gastar exatamente 12 Pontos Bônus. Gastou: $pontos_gastos.</div>";
    } else {
        
        // 3. Calcula Stats Finais e Derivados
        $stats_finais = [
            'str' => 10 + $stats_bonus['str'],
            'dex' => 10 + $stats_bonus['dex'],
            'con' => 10 + $stats_bonus['con'],
            'int_stat' => 10 + $stats_bonus['int_stat'],
            'wis' => 10 + $stats_bonus['wis'],
            'cha' => 10 + $stats_bonus['cha']
        ];
        
        // Calcula HP Max e Mana Max usando a função
        $derivados = calcular_stats_derivados($stats_finais, 1, $classe_base);
        
        $hp_max = $derivados['hp_max'];
        $mana_max = $derivados['recurso_max'];
        
        // 4. Segurança: Cria o Hash da Senha
        $senha_hash = password_hash($senha_bruta, PASSWORD_DEFAULT);
        
        // 5. Query SQL para inserção
        $sql = "INSERT INTO personagens (
                    nome, email, senha_hash, classe_base, 
                    str, dex, con, int_stat, wis, cha, 
                    hp_atual, hp_max, mana_atual, mana_max,
                    level, ouro, xp_atual, xp_proximo_level,
                    pontos_atributo_disponiveis, pontos_habilidade_disponiveis, fama_rank
                ) VALUES (
                    '{$nome}', '{$email}', '{$senha_hash}', '{$classe_base}', 
                    {$stats_finais['str']}, {$stats_finais['dex']}, {$stats_finais['con']}, 
                    {$stats_finais['int_stat']}, {$stats_finais['wis']}, {$stats_finais['cha']}, 
                    {$hp_max}, {$hp_max}, {$mana_max}, {$mana_max},
                    1, 100, 0, 100, 5, 2, 'Novato'
                )";
        
        if ($conexao->query($sql) === TRUE) {
            $mensagem = "<div class='feedback feedback-success'>✨ Personagem <strong>{$nome}</strong> criado com sucesso! Faça login para começar sua jornada.</div>";
        } else {
            $mensagem = "<div class='feedback feedback-error'>❌ ERRO na Criação de Personagem: " . $conexao->error . "</div>";
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
        /* ESTILOS ESPECÍFICOS DO REGISTRO COM ALOCAÇÃO */
        .registro-container {
            min-height: 100vh;
            background: linear-gradient(135deg, var(--bg-primary) 0%, #1a0a2a 100%);
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }

        .portal-card {
            background: var(--bg-secondary);
            border: 3px solid var(--accent-arcane);
            border-radius: 15px;
            padding: 40px;
            width: 100%;
            max-width: 600px;
            box-shadow: 0 20px 40px rgba(138, 43, 226, 0.3);
            position: relative;
            overflow: hidden;
            z-index: 1; /* Garante que o cartão esteja acima do pseudo-elemento */
        }

        .portal-card::before {
            content: '';
            position: absolute;
            top: -50%;
            left: -50%;
            width: 200%;
            height: 200%;
            background: linear-gradient(45deg, transparent, rgba(138, 43, 226, 0.1), transparent);
            animation: portal-glow 6s linear infinite;
            z-index: -1; /* Coloca o pseudo-elemento atrás do conteúdo */
        }

        @keyframes portal-glow {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }

        .portal-header {
            text-align: center;
            margin-bottom: 30px;
        }

        .portal-icon {
            font-size: 4em;
            margin-bottom: 15px;
            animation: float 3s ease-in-out infinite;
        }

        @keyframes float {
            0%, 100% { transform: translateY(0px); }
            50% { transform: translateY(-10px); }
        }

        .portal-title {
            color: var(--accent-arcane);
            font-size: 2.5em;
            margin-bottom: 10px;
            text-shadow: 0 0 20px var(--accent-arcane-glow);
        }

        .portal-subtitle {
            color: var(--text-secondary);
            font-size: 1.1em;
        }

        .form-group {
            margin-bottom: 20px;
            position: relative; /* Para garantir que os elementos internos fiquem acima */
            z-index: 2; /* Acima do pseudo-elemento */
        }

        .form-label {
            display: block;
            margin-bottom: 8px;
            color: var(--accent-vital);
            font-weight: bold;
            text-transform: uppercase;
            font-size: 0.9em;
        }

        .form-input {
            width: 100%;
            padding: 12px 15px;
            background: var(--bg-primary);
            border: 2px solid var(--accent-arcane);
            border-radius: 8px;
            color: var(--text-primary);
            font-family: inherit;
            font-size: 1em;
            transition: all 0.3s ease;
        }

        .form-input:focus {
            outline: none;
            border-color: var(--accent-arcane-glow);
            box-shadow: 0 0 15px rgba(138, 43, 226, 0.5);
        }

        .class-selection {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(120px, 1fr));
            gap: 10px;
            margin-bottom: 20px;
        }

        .class-option {
            padding: 15px 10px;
            background: var(--bg-primary);
            border: 2px solid var(--bg-tertiary);
            border-radius: 8px;
            text-align: center;
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .class-option:hover {
            border-color: var(--accent-arcane);
            transform: translateY(-2px);
        }

        .class-option.selected {
            border-color: var(--accent-vital);
            background: rgba(80, 200, 120, 0.1);
            box-shadow: 0 5px 15px rgba(80, 200, 120, 0.3);
        }

        .class-icon {
            font-size: 2em;
            margin-bottom: 8px;
        }

        .class-name {
            font-weight: bold;
            color: var(--text-primary);
            margin-bottom: 5px;
        }

        .class-desc {
            font-size: 0.8em;
            color: var(--text-secondary);
        }

        .stats-allocation {
            background: var(--bg-primary);
            padding: 20px;
            border-radius: 8px;
            border: 1px solid var(--bg-tertiary);
            margin-bottom: 20px;
        }

        .stats-header {
            text-align: center;
            margin-bottom: 15px;
            color: var(--accent-vital);
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 10px;
        }

        .stat-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 10px;
            background: var(--bg-secondary);
            border-radius: 6px;
        }

        .stat-info {
            display: flex;
            flex-direction: column;
        }

        .stat-name {
            font-weight: bold;
            color: var(--text-primary);
        }

        .stat-base {
            font-size: 0.8em;
            color: var(--text-secondary);
        }

        .stat-controls {
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .stat-value {
            min-width: 30px;
            text-align: center;
            font-weight: bold;
            color: var(--accent-vital);
        }

        .btn-stat {
            width: 30px;
            height: 30px;
            background: var(--accent-arcane);
            color: white;
            border: none;
            border-radius: 50%;
            cursor: pointer;
            font-weight: bold;
            transition: all 0.2s ease;
        }

        .btn-stat:hover {
            background: var(--accent-arcane-glow);
            transform: scale(1.1);
        }

        .btn-stat:disabled {
            background: var(--bg-tertiary);
            color: var(--text-secondary);
            cursor: not-allowed;
            transform: none;
        }

        .points-remaining {
            text-align: center;
            margin-top: 15px;
            font-weight: bold;
            color: var(--accent-vital);
            font-size: 1.1em;
        }

        .btn-portal {
            width: 100%;
            padding: 15px;
            background: linear-gradient(135deg, var(--accent-arcane), var(--accent-essence));
            color: white;
            border: none;
            border-radius: 8px;
            font-size: 1.1em;
            font-weight: bold;
            text-transform: uppercase;
            cursor: pointer;
            transition: all 0.3s ease;
            margin-top: 10px;
        }

        .btn-portal:hover {
            transform: translateY(-3px);
            box-shadow: 0 10px 25px rgba(138, 43, 226, 0.5);
        }

        .portal-footer {
            text-align: center;
            margin-top: 25px;
            padding-top: 20px;
            border-top: 1px solid var(--bg-tertiary);
        }

        .footer-link {
            color: var(--accent-vital);
            text-decoration: none;
        }

        .footer-link:hover {
            text-decoration: underline;
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

            .stats-grid {
                grid-template-columns: 1fr;
            }
        }

        @media (max-width: 480px) {
            .class-selection {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <div class="registro-container">
        <div class="portal-card">
            <div class="portal-header">
                <div class="portal-icon">🌌</div>
                <h1 class="portal-title">CRIAÇÃO DE PERSONAGEM</h1>
                <p class="portal-subtitle">Forje seu destino na Fenda</p>
            </div>

            <?php echo $mensagem; ?>

            <form method="POST" action="register.php">
                <div class="form-group">
                    <label for="nome" class="form-label">👤 Nome do Personagem</label>
                    <input type="text" id="nome" name="nome" class="form-input" placeholder="Escolha um nome épico" required>
                </div>

                <div class="form-group">
                    <label for="email" class="form-label">📧 Email</label>
                    <input type="email" id="email" name="email" class="form-input" placeholder="seu@email.com" required>
                </div>

                <div class="form-group">
                    <label for="senha" class="form-label">🔒 Senha</label>
                    <input type="password" id="senha" name="senha" class="form-input" placeholder="Mínimo 6 caracteres" minlength="6" required>
                </div>

                <div class="form-group">
                    <label class="form-label">⚔️ Escolha sua Classe</label>
                    <div class="class-selection" id="classSelection">
                        <div class="class-option" data-class="Guerreiro">
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
                    </div>
                    <input type="hidden" id="classe_base" name="classe_base" value="Guerreiro" required>
                </div>

                <!-- Sistema de Alocação de Pontos -->
                <div class="stats-allocation">
                    <h3 class="stats-header">🎯 Alocação de Pontos de Atributo</h3>
                    <p style="text-align: center; color: var(--text-secondary); margin-bottom: 15px;">
                        Você tem <span id="pontosRestantes" style="color: var(--accent-vital); font-weight: bold;">12</span> pontos para distribuir.
                    </p>
                    
                    <div class="stats-grid">
                        <?php
                        $atributos = [
                            'str' => ['nome' => 'Força', 'icon' => '💪'],
                            'dex' => ['nome' => 'Destreza', 'icon' => '🎯'],
                            'con' => ['nome' => 'Constituição', 'icon' => '🛡️'],
                            'int_stat' => ['nome' => 'Inteligência', 'icon' => '🧠'],
                            'wis' => ['nome' => 'Sabedoria', 'icon' => '📚'],
                            'cha' => ['nome' => 'Carisma', 'icon' => '✨']
                        ];
                        
                        foreach ($atributos as $key => $atributo):
                        ?>
                        <div class="stat-item">
                            <div class="stat-info">
                                <span class="stat-name"><?php echo $atributo['icon']; ?> <?php echo $atributo['nome']; ?></span>
                                <span class="stat-base">Base: 10</span>
                            </div>
                            <div class="stat-controls">
                                <button type="button" class="btn-stat" onclick="alterarPonto('<?php echo $key; ?>', -1)" disabled>-</button>
                                <span class="stat-value" id="valor-<?php echo $key; ?>">0</span>
                                <button type="button" class="btn-stat" onclick="alterarPonto('<?php echo $key; ?>', 1)">+</button>
                            </div>
                            <input type="hidden" name="<?php echo $key; ?>_bonus" id="input-<?php echo $key; ?>" value="0">
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>

                <button type="submit" class="btn-portal" id="btnCriar">
                    🌟 Criar Personagem
                </button>
            </form>

            <div class="portal-footer">
                <p style="color: var(--text-secondary);">
                    Já tem um personagem? <a href="login.php" class="footer-link">Faça login aqui</a>
                </p>
            </div>
        </div>
    </div>

    <script>
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

        // Sistema de Alocação de Pontos
        let pontosRestantes = 12;
        const pontosPorAtributo = {
            str: 0,
            dex: 0,
            con: 0,
            int_stat: 0,
            wis: 0,
            cha: 0
        };

        function alterarPonto(atributo, valor) {
            const novoValor = pontosPorAtributo[atributo] + valor;
            
            // Verifica se pode adicionar ou remover
            if (valor > 0 && pontosRestantes <= 0) return;
            if (valor < 0 && novoValor < 0) return;
            if (novoValor > 5) return;
            
            // Atualiza pontos
            pontosPorAtributo[atributo] = novoValor;
            pontosRestantes -= valor;
            
            // Atualiza interface
            document.getElementById(`valor-${atributo}`).textContent = novoValor;
            document.getElementById(`input-${atributo}`).value = novoValor;
            document.getElementById('pontosRestantes').textContent = pontosRestantes;
            
            // Atualiza botões
            atualizarBotoes();
        }

        function atualizarBotoes() {
            // Atualiza todos os botões de + e -
            for (const atributo in pontosPorAtributo) {
                const valorAtual = pontosPorAtributo[atributo];
                const btnMenos = document.querySelector(`button[onclick="alterarPonto('${atributo}', -1)"]`);
                const btnMais = document.querySelector(`button[onclick="alterarPonto('${atributo}', 1)"]`);
                
                btnMenos.disabled = valorAtual <= 0;
                btnMais.disabled = pontosRestantes <= 0 || valorAtual >= 5;
            }
            
            // Atualiza botão de criar personagem
            const btnCriar = document.getElementById('btnCriar');
            btnCriar.disabled = pontosRestantes !== 0;
            if (pontosRestantes !== 0) {
                btnCriar.title = `Você ainda tem ${pontosRestantes} pontos para gastar.`;
            } else {
                btnCriar.title = '';
            }
        }

        // Inicializa os botões
        atualizarBotoes();
    </script>
</body>
</html>