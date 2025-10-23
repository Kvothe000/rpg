<?php
// src/core/bootstrap.php
error_reporting(E_ALL);
ini_set('display_errors', 1); // Em desenvolvimento; desativar em produção
date_default_timezone_set('America/Sao_Paulo');

if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// --- Definição de Caminhos ---
define('ROOT_PATH', dirname(__DIR__, 2));
define('SRC_PATH', ROOT_PATH . '/src');
define('PUBLIC_PATH', ROOT_PATH . '/public');
define('TEMPLATES_PATH', ROOT_PATH . '/templates');

// --- Conexão com Banco de Dados ---
require_once SRC_PATH . '/core/db_connect.php';

// --- Funções Essenciais de Lógica ---
require_once SRC_PATH . '/functions/game_logic.php'; // <<<=== ADICIONADO AQUI

// --- Carregamento Inicial de Dados do Jogador ---
$player_data = null;    // Dados base do personagem logado
$equip_bonus = null;    // Bônus somados dos equipamentos
$stats_totais = null;   // Stats base + bônus de equipamento
$limite_carga = null;   // Carga atual e máxima
$is_logged_in = false;  // Flag para verificar se o usuário está logado
$mensagem_corrupcao_sutil = ""; // Mensagem de corrupção

if (isset($_SESSION['player_id'])) {
    $player_id_session = (int)$_SESSION['player_id'];

    // Buscar dados base do jogador
    $sql_player_session = "SELECT *, corrupcao FROM personagens WHERE id = ?"; // Inclui corrupcao
    $stmt_player_session = $conexao->prepare($sql_player_session);

    if ($stmt_player_session) {
        $stmt_player_session->bind_param("i", $player_id_session);
        $stmt_player_session->execute();
        $player_data = $stmt_player_session->get_result()->fetch_assoc();
        $stmt_player_session->close();

        if ($player_data) {
            $is_logged_in = true;

            // Carrega bônus de equipamentos (função de game_logic.php)
            $equip_bonus = carregar_stats_equipados($player_id_session, $conexao);

            // Calcula os atributos totais (base + equipamento)
            $stats_totais = [
                'str' => ($player_data['str'] ?? 0) + ($equip_bonus['bonus_str'] ?? 0),
                'dex' => ($player_data['dex'] ?? 0) + ($equip_bonus['bonus_dex'] ?? 0),
                'con' => ($player_data['con'] ?? 0) + ($equip_bonus['bonus_con'] ?? 0),
                'int_stat' => ($player_data['int_stat'] ?? 0) + ($equip_bonus['bonus_int'] ?? 0),
                'wis' => ($player_data['wis'] ?? 0) + ($equip_bonus['bonus_wis'] ?? 0),
                'cha' => ($player_data['cha'] ?? 0) + ($equip_bonus['bonus_cha'] ?? 0)
            ];

            // Calcula o limite de carga (função de game_logic.php)
            $limite_carga = calcular_limite_carga($player_data['str'] ?? 10, $player_id_session, $conexao);

            // Lógica de Corrupção Sutil (do header.php original)
            $nivel_corrupcao = $player_data['corrupcao'] ?? 0;
            if ($nivel_corrupcao > 3) {
                $chance_efeito = min(5 + ($nivel_corrupcao * 2), 50);
                if (mt_rand(1, 100) <= $chance_efeito) {
                    $mensagens_sutis = [
                        "Você ouve um sussurro que some rapidamente...",
                        "Sua visão escurece por um breve instante.",
                        "Uma imagem fugaz de Elara cruza sua mente.", // <-- Usando Elara da história
                        "Um arrepio percorre sua espinha sem motivo aparente.",
                        "[Sistema?]: ...erro... fragmento instável...",
                        "A interface pisca em vermelho por um momento."
                    ];
                    $mensagem_corrupcao_sutil = $mensagens_sutis[array_rand($mensagens_sutis)];
                }
            }

        } else {
            // ID na sessão inválido, limpa sessão
            session_destroy();
            $is_logged_in = false; // Garante que a flag esteja correta
        }
    } else {
        // Erro grave na query do jogador
        error_log("Erro ao preparar query de jogador em bootstrap.php: " . $conexao->error);
        session_destroy(); // Desloga por segurança
        $is_logged_in = false;
    }
}

// --- Lógica de Logout ---
if (isset($_GET['logout'])) {
    session_destroy();
    header('Location: login.php'); // Redireciona para o login dentro de public/
    exit;
}

// --- Função Auxiliar para Templates ---
function render_template($template_name, $data = []) {
    extract($data);
    $template_file = TEMPLATES_PATH . '/' . $template_name . '.php';
    if (file_exists($template_file)) {
        include $template_file;
    } else {
        echo "<p><strong>Erro:</strong> Template não encontrado em: " . htmlspecialchars($template_file) . "</p>";
    }
}

// --- Inclusão das outras Funções (CARREGAMENTO SIMPLES - MELHORAR DEPOIS COM AUTOLOAD) ---
// Inclui todos os arquivos .php dentro de src/functions/, exceto game_logic.php que já foi incluído
$function_files = glob(SRC_PATH . '/functions/*.php');
foreach ($function_files as $file) {
    if (basename($file) !== 'game_logic.php') { // Evita incluir game_logic novamente
         require_once $file;
    }
}

// --- Inclusão de Classes ---
// Inclui todas as classes .php dentro de src/classes/
$class_files = glob(SRC_PATH . '/classes/*.php');
foreach ($class_files as $file) {
    require_once $file;
}

//echo ""; // Remover após teste inicial

?>