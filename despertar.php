<?php
session_start(); // <-- ADICIONAR ISSO NO TOPO
$_SESSION['prologo_visto'] = true; // <-- ADICIONAR ISSO AQUI

// include 'db_connect.php'; // Incluir se precisar interagir com BD aqui
// include 'header.php'; // Incluir se quiser a barra de status já aqui

// Simulação - O jogador ainda não existe formalmente no BD nesta fase
$escolha_feita = $_GET['escolha'] ?? null;
$dialogo_enfermeira = "";
$descricao_sistema = "";
$mostrar_opcoes = true; // Variável adicionada para controle
$link_prosseguir = null; // Variável adicionada para controle

if ($escolha_feita == 1) {
    $dialogo_enfermeira = '<p><strong style="color: #FFF;">ENFERMEIRA:</strong> "Você está na enfermaria da Guilda, Distrito Central. Houve um Incidente de Fenda no Distrito 7. Você foi encontrado nos escombros."</p>';
} elseif ($escolha_feita == 2) {
    // Adicionada variável para link e para esconder opções
    $dialogo_enfermeira = '<p><strong style="color: #FFF;">ENFERMEIRA:</strong> (Suspira) "Elara... Nome comum. Muitas perdas hoje. Mas ninguém sobrevive à Absorção direta. Sinto muito. O Comandante Kaelen quer vê-lo. Agora."</p>';
    $mostrar_opcoes = false;
    $link_prosseguir = 'npc_interact.php?npc_id=10';
} elseif ($escolha_feita == 3) {
    $dialogo_enfermeira = '<p><strong style="color: #FFF;">ENFERMEIRA:</strong> "Calma! Você ainda está fraco. Trauma de Fenda não é brincadeira. Descanse."</p>';
    // Poderia adicionar uma pequena penalidade ou impedir a ação
} elseif ($escolha_feita == 4) {
    $descricao_sistema = "<p><em>Você foca no texto flutuante. [SAÚDE: 34%]. Você fecha os olhos com força... o texto some. Abre... ele retorna. Parece real. Assustadoramente real.</em></p>";
    // Adicionada variável para link e para esconder opções
    $dialogo_enfermeira = '<p><strong style="color: #FFF;">ENFERMEIRA:</strong> (Te observando) "Distrito 7. Incidente de Fenda. Você foi o único sobrevivente... Sobre \'Elara\'... ninguém sobrevive à Absorção. O Comandante Kaelen quer vê-lo. Agora."</p>';
    $mostrar_opcoes = false;
    $link_prosseguir = 'npc_interact.php?npc_id=10';
}

?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>O Despertar - Arcana Duality</title>
    <link rel="stylesheet" href="style.css"> <style>
        /* Seus estilos aqui... */
         body { background-color: #1a1a1a; color: #ccc; display: flex; align-items: center; justify-content: center; min-height: 100vh; font-family: 'Courier New', monospace; }
        .despertar-container { max-width: 700px; padding: 30px; border: 1px solid #333; background-color: #2a2a2a; box-shadow: 0 0 15px rgba(80, 200, 120, 0.2); animation: fadeIn 1s ease-in; }
        .ambiente { color: #aaa; font-style: italic; margin-bottom: 20px; border-left: 3px solid #50C878; padding-left: 10px; }
        .ui-display { color: #00FFFF; border: 1px dashed #00FFFF; padding: 10px; margin-bottom: 20px; font-weight: bold; text-align: center; } /* Centralizado */
        .dialogo-npc { margin-top: 15px; margin-bottom: 25px; background: #333; padding: 15px; border-radius: 5px; border-left: 3px solid #FFF; }
        .opcoes { margin-top: 20px; }
        .opcoes p { font-weight: bold; margin-bottom: 15px; color: #FFF; text-align: center; } /* Centralizado */
        .opcoes a { display: block; margin-bottom: 10px; padding: 12px; background-color: #3e3e3e; color: #00FFFF; text-decoration: none; border-radius: 5px; transition: all 0.3s ease; border: 1px solid #444; text-align: left; } /* Alinhado à esquerda */
        .opcoes a:hover { background-color: #8A2BE2; color: white; border-color: #8A2BE2; transform: translateX(5px); }
        .prosseguir-link { display: inline-block; margin-top: 20px; color: #50C878; border: 1px solid #50C878; padding: 10px 15px; text-decoration: none; transition: all 0.3s ease; font-weight: bold; border-radius: 5px; } /* Arredondado */
        .prosseguir-link:hover { background-color: #50C878; color: #111; }
        .center-link { text-align: center; } /* Classe para centralizar o link */
        @keyframes fadeIn { from { opacity: 0; } to { opacity: 1; } }
    </style>
</head>
<body>
    <div class="despertar-container">
        <p class="ambiente">[Ambiente]: Você acorda com o cheiro forte de antisséptico. Uma luz fluorescente pisca ritmicamente acima de você. Você está em uma cama de enfermaria, austera e fria. Suas mãos tremem.</p>

        <div class="ui-display">
            [UI]: SAÚDE: 34% | MANA: 10% | CONDIÇÃO: ESGOTAMENTO DE ESSÊNCIA
        </div>

        <?php echo $descricao_sistema; // Mostra a descrição se a opção 4 foi escolhida ?>

        <div class="dialogo-npc">
            <?php if (empty($dialogo_enfermeira) && !$escolha_feita): // Primeira vez na cena ?>
                <p><strong style="color: #FFF;">ENFERMEIRA:</strong> (Anotando algo, sem olhar para você) "Ele acordou. Avise o Comandante Kaelen."</p>
            <?php else: ?>
                <?php echo $dialogo_enfermeira; // Mostra a resposta da enfermeira ?>
            <?php endif; ?>
        </div>

        <?php if ($mostrar_opcoes): // Mostra opções se a conversa não terminou ?>
            <div class="opcoes">
                <p>[Sistema]: O que você faz?</p>
                <a href="despertar.php?escolha=1">1. "Onde eu estou? O que aconteceu?"</a>
                <a href="despertar.php?escolha=2">2. "Elara! Onde está Elara? Ela sobreviveu?"</a>
                <a href="despertar.php?escolha=3">3. (Tentar se levantar)</a>
                <a href="despertar.php?escolha=4">4. (Ficar em silêncio e observar a UI)</a>
            </div>
        <?php elseif ($link_prosseguir): // Mostra o link para Kaelen se a conversa terminou ?>
            <div class="center-link"> <a href="<?php echo $link_prosseguir; ?>" class="prosseguir-link">Ir encontrar o Comandante Kaelen...</a>
            </div>
        <?php endif; ?>

    </div>
</body>
</html>