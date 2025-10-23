<?php
// templates/layouts/main.php
// Variáveis como $titulo_pagina, $pagina_atual, $player_data, $is_logged_in, $pagina_conteudo
// são extraídas pelo render_template() chamado no arquivo public/
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo isset($titulo_pagina) ? htmlspecialchars($titulo_pagina) : 'Arcana Duality'; ?></title>
    <link rel="stylesheet" href="assets/css/style.css"> <?php // Caminho relativo à pasta public ?>
    <?php
        // Futuramente: incluir CSS específico da página aqui
    ?>
</head>
<body>
    <?php if ($is_logged_in ?? false): // Verifica se está logado para mostrar header ?>
        <?php include TEMPLATES_PATH . '/partials/header.php'; // Incluirá o header (ainda não criado) ?>
    <?php endif; ?>

    <main class="container">
        <?php include TEMPLATES_PATH . '/partials/feedback.php'; // Inclui exibição de feedback ?>

        <?php
        // Inclui o CONTEÚDO específico da página atual
        $page_template_file = TEMPLATES_PATH . '/pages/' . ($pagina_conteudo ?? 'default') . '.php';
        if (isset($pagina_conteudo) && file_exists($page_template_file)) {
            include $page_template_file;
        } elseif ($pagina_atual ?? null) { // Fallback para usar $pagina_atual se $pagina_conteudo não foi passado
             $page_template_file = TEMPLATES_PATH . '/pages/' . $pagina_atual . '.php';
             if (file_exists($page_template_file)) {
                 include $page_template_file;
             } else {
                echo "<p>Erro: Template da página '$pagina_atual' não encontrado.</p>";
             }
        } else {
            echo "<p>Erro: Nenhuma página de conteúdo especificada.</p>";
        }
        ?>
    </main>

    <?php include TEMPLATES_PATH . '/partials/footer.php'; // Incluirá o footer (ainda não criado) ?>

    <?php
        // Futuramente: incluir JS específico da página aqui
        // Futuramente: incluir mensagem de corrupção aqui
    ?>
</body>
</html>