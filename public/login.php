<?php
// public/login.php

// 1. Inclui o bootstrap: Carrega DB, funções, sessão, etc.
// O caminho '../src/core/bootstrap.php' sobe um nível (de public para rpg)
// e entra em src/core/
require_once '../src/core/bootstrap.php';

// 2. Definições da Página
$titulo_pagina = "Portal de Acesso - Arcana Duality";
$pagina_atual = 'login'; // Para destacar a aba ativa no header (se aplicável)
$mensagem = "";          // Mensagens de feedback (sucesso/erro)
$mostrar_prologo = false; // Flag para controlar qual view carregar
$modo = 'login';         // Modo padrão do formulário ('login' ou 'registro')

// 3. Lógica de Redirecionamento Pós-Login (Captura do GET)
// Verifica se veio do despertar.php
if (isset($_GET['next']) && $_GET['next'] === 'kaelen') {
    $_SESSION['redirect_on_login'] = 'npc_interact.php?npc_id=10'; // Caminho relativo a public/
    // Força modo registro se acabou de ver o prólogo E não está logado
    if (isset($_SESSION['prologo_visto']) && !$is_logged_in) { // Usa $is_logged_in do bootstrap
        // Não definimos $modo aqui, deixamos o $_GET controlar ou o POST
    }
}
// Força modo registro se ?modo=registro estiver na URL (útil vindo do despertar.php)
if (isset($_GET['modo']) && $_GET['modo'] === 'registro') {
     $modo = 'registro';
}


// 4. Lógica do Prólogo
// Verifica se o usuário NÃO viu o prólogo E NÃO está logado
if (!isset($_SESSION['prologo_visto']) && !$is_logged_in) {
    $mostrar_prologo = true;
}

// 5. Redireciona se já estiver logado (para a cidade)
if ($is_logged_in && !$mostrar_prologo) { // Só redireciona se NÃO for mostrar o prólogo
    header('Location: cidade.php'); // Caminho relativo a public/
    exit;
}

// 6. Processamento do Formulário (Login / Registro) - Somente se não for mostrar o prólogo
if (!$mostrar_prologo && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $_SESSION['prologo_visto'] = true; // Marca como visto ao tentar logar/registrar

    // --- Processar Login ---
    if (isset($_POST['acao']) && $_POST['acao'] === 'login') {
        $modo = 'login'; // Garante que a view correta seja mostrada em caso de erro
        $email = $conexao->real_escape_string($_POST['email']);
        $senha_bruta = $_POST['senha'];

        $sql = "SELECT id, nome, senha_hash, level, classe_base FROM personagens WHERE email = ?";
        $stmt = $conexao->prepare($sql);
        if ($stmt) {
            $stmt->bind_param("s", $email);
            $stmt->execute();
            $resultado = $stmt->get_result();
            $usuario = $resultado->fetch_assoc();
            $stmt->close();

            if ($usuario && password_verify($senha_bruta, $usuario['senha_hash'])) {
                // Login bem-sucedido
                $_SESSION['player_id'] = $usuario['id'];


                // Lógica de Redirecionamento
                if (isset($_SESSION['redirect_on_login'])) {
                    $redirect_url = $_SESSION['redirect_on_login'];
                    unset($_SESSION['redirect_on_login']);
                    header('Location: ' . $redirect_url); // O URL já está correto
                } else {
                    header('Location: cidade.php'); // Destino padrão
                }
                exit;
            } else {
                $mensagem = "<div class='feedback feedback-error'>❌ Credenciais inválidas.</div>";
            }
        } else {
             $mensagem = "<div class='feedback feedback-error'>❌ Erro ao preparar consulta de login.</div>";
             error_log("Erro prepare login: " . $conexao->error);
        }
    }

    // --- Processar Registro ---
    elseif (isset($_POST['acao']) && $_POST['acao'] === 'registro') {
        $modo = 'registro'; // Garante que a view correta seja mostrada em caso de erro
        $nome = $conexao->real_escape_string($_POST['nome']);
        $email = $conexao->real_escape_string($_POST['email']);
        $senha_bruta = $_POST['senha'];
        $classe_base = $conexao->real_escape_string($_POST['classe_base']); // Vem do input hidden

        // Verifica email existente
        $sql_check = "SELECT id FROM personagens WHERE email = ?";
        $stmt_check = $conexao->prepare($sql_check);
        if ($stmt_check) {
            $stmt_check->bind_param("s", $email);
            $stmt_check->execute();
            $result_check = $stmt_check->get_result();

            if ($result_check->num_rows > 0) {
                $mensagem = "<div class='feedback feedback-error'>❌ Este email já está em uso.</div>";
            } else {
                // Validações adicionais (senha forte, nome válido, etc.) podem ser adicionadas aqui

                $senha_hash = password_hash($senha_bruta, PASSWORD_DEFAULT);

                // Stats iniciais (poderiam vir de uma função em game_logic.php)
                $stats_iniciais = [
                    'Guerreiro' => ['str' => 15, 'dex' => 10, 'con' => 14, 'int_stat' => 8, 'wis' => 10, 'cha' => 8],
                    'Ladino' => ['str' => 10, 'dex' => 15, 'con' => 12, 'int_stat' => 10, 'wis' => 12, 'cha' => 10],
                    'Mago' => ['str' => 8, 'dex' => 12, 'con' => 10, 'int_stat' => 15, 'wis' => 14, 'cha' => 8],
                    'Sacerdote' => ['str' => 10, 'dex' => 10, 'con' => 12, 'int_stat' => 12, 'wis' => 15, 'cha' => 10],
                    'Caçador' => ['str' => 12, 'dex' => 14, 'con' => 12, 'int_stat' => 10, 'wis' => 12, 'cha' => 8]
                ];
                $stats = $stats_iniciais[$classe_base] ?? $stats_iniciais['Guerreiro']; // Default Guerreiro

                // Calcula HP/Mana usando a função
                $derivados = calcular_stats_derivados($stats, 1, $classe_base); // Função de game_logic.php
                $hp_max = $derivados['hp_max'];
                $mana_max = $derivados['recurso_max']; // Nome genérico do recurso

                // Insere no banco
                $sql_insert = "INSERT INTO personagens
                    (nome, email, senha_hash, classe_base, level, ouro, xp_atual, xp_proximo_level,
                     str, dex, con, int_stat, wis, cha, hp_max, hp_atual, mana_max, mana_atual,
                     pontos_atributo_disponiveis, pontos_habilidade_disponiveis, fama_rank, corrupcao)
                    VALUES (?, ?, ?, ?, 1, 100, 0, 100, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 5, 2, 'Novato', 0)"; // Adiciona corrupcao = 0

                $stmt_insert = $conexao->prepare($sql_insert);
                if ($stmt_insert) {
                    // Tipos: sss s ii ii ii ii iiii ii s i
                    $stmt_insert->bind_param("ssssiiiiiiiiiiiiis",
                        $nome, $email, $senha_hash, $classe_base,
                        $stats['str'], $stats['dex'], $stats['con'], $stats['int_stat'],
                        $stats['wis'], $stats['cha'], $hp_max, $hp_max, $mana_max, $mana_max
                    );

                    if ($stmt_insert->execute()) {
                        $mensagem = "<div class='feedback feedback-success'>✨ Personagem criado! Faça login.</div>";
                        $modo = 'login'; // Muda para a aba de login após sucesso
                    } else {
                        $mensagem = "<div class='feedback feedback-error'>❌ Erro ao criar: " . $stmt_insert->error . "</div>";
                    }
                    $stmt_insert->close();
                } else {
                    $mensagem = "<div class='feedback feedback-error'>❌ Erro ao preparar inserção de personagem.</div>";
                    error_log("Erro prepare insert personagem: " . $conexao->error);
                }
            }
            $stmt_check->close();
        } else {
             $mensagem = "<div class='feedback feedback-error'>❌ Erro ao preparar verificação de email.</div>";
             error_log("Erro prepare check email: " . $conexao->error);
        }
    }
} // Fim do if ($_SERVER['REQUEST_METHOD'] === 'POST')


// 7. Prepara Dados para o Template
$template_data = [
    'titulo_pagina' => $titulo_pagina,
    'pagina_atual' => $pagina_atual,
    'mensagem' => $mensagem,
    'modo' => $modo, // Passa o modo ('login' ou 'registro') para a view
    // Outras variáveis globais como $player_data, $is_logged_in já estão no escopo do bootstrap
    // Mas podemos passá-las explicitamente se preferirmos clareza:
    'player_data' => $player_data,
    'is_logged_in' => $is_logged_in,
];

// 8. Renderiza a View Apropriada
if ($mostrar_prologo) {
    // Renderiza diretamente o template do prólogo (ele não usa o layout principal)
    render_template('pages/prologo', $template_data); // Passa dados se necessário
} else {
    // Renderiza o layout principal, que incluirá o template da página de login/registro
    render_template('layouts/main', array_merge($template_data, ['pagina_conteudo' => 'login']));
    // Passamos 'pagina_conteudo' => 'login' para que main.php saiba qual arquivo de /pages/ incluir.
}

?>