<?php
// ----------------------------------------------------
// 1. CONFIGURAÇÃO DO BANCO DE DADOS (XAMPP PADRÃO)
// ----------------------------------------------------

// Verificar se as constantes já não foram definidas
if (!defined('DB_NAME')) {
    define('DB_NAME', 'rpg_mud_db'); 
}

if (!defined('DB_HOST')) {
    define('DB_HOST', 'localhost');
}

if (!defined('DB_USER')) {
    define('DB_USER', 'root');
}

if (!defined('DB_PASS')) {
    define('DB_PASS', '');
}

// ----------------------------------------------------
// 2. TENTATIVA DE CONEXÃO
// ----------------------------------------------------

// Verificar se a conexão já não existe
if (!isset($conexao)) {
    $conexao = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);

    // 3. VERIFICAÇÃO DE ERROS
    if ($conexao->connect_error) {
        // Se a conexão falhar, interrompe o script e exibe o erro
        die('Erro de Conexão com o Banco de Dados (' . $conexao->connect_errno . ') ' 
            . $conexao->connect_error);
    }

    // 4. CONFIGURAÇÃO DE SEGURANÇA E CODIFICAÇÃO
    // Define o charset para UTF-8 (essencial para acentuação e caracteres especiais)
    $conexao->set_charset('utf8mb4');
}

// Agora, a variável $conexao contém o objeto de conexão, pronto para ser usado
// para fazer consultas SQL em qualquer script que inclua este arquivo.

// Exemplo: $resultado = $conexao->query("SELECT * FROM personagens WHERE id = 1");
?>