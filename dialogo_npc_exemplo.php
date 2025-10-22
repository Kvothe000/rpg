<?php
// Inclui as ferramentas de lógica (roll_d100 e teste_atributo)
include 'game_logic.php'; 
// (Em um jogo real, você incluiria também 'db_connect.php' e faria o login)

// Função para SIMULAR os dados do jogador e de sua Crença
function simular_dados_jogador() {
    return [
        'nome' => 'Ryu, o Arquiteto',
        'classe' => 'Ladino',
        // Valores que o jogador teria na tabela 'personagens'
        'stats' => ['str' => 12, 'dex' => 15, 'con' => 12, 'int_stat' => 10, 'wis' => 11, 'cha' => 14],
        // Simulação da Crença principal do jogador (Ex: Mestre do Engano)
        'crenca_ativa' => 'Eu serei o maior enganador da Guilda, custe o que custar.',
        'ouro' => 500 // Para a recompensa/penalidade
    ];
}

$jogador = simular_dados_jogador();
$npc_nome = "Comandante Hórus";
$dc_persuasao = 70; // Dificuldade da Ação

// -------------------------------------------------------------------------
// LÓGICA DE PROCESSAMENTO (QUANDO O JOGADOR CLICA EM UMA OPÇÃO)
// -------------------------------------------------------------------------

$mensagem_resultado = ""; // Variável para armazenar o feedback do jogo

if (isset($_GET['acao'])) {
    $acao = $_GET['acao'];
    
    // Teste de Carisma para Opção 1
    if ($acao === 'persuadir') {
        $atributo_usado = $jogador['stats']['cha'];
        $teste = teste_atributo($atributo_usado, $dc_persuasao);
        
        $mensagem_resultado .= "<h3>[TESTE DE CARISMA]</h3>";
        $mensagem_resultado .= "Roll d100: {$teste['roll']} + CHA ({$atributo_usado}) = Final: {$teste['final']} (DC {$dc_persuasao})<br>";
        
        if ($teste['resultado'] === 'sucesso' || $teste['resultado'] === 'critico') {
            $mensagem_resultado .= "<p style='color: green;'>**SUCESSO!** O Comandante parece convencido de sua inocência. Ele te dá 200 Ouro e um **+1 Ponto de Habilidade Bônus**.</p>";
            
            // LÓGICA DA CRENÇA (Reforçando a identidade do jogador)
            if (strpos($jogador['crenca_ativa'], 'enganador') !== false || strpos($jogador['crenca_ativa'], 'custe o que custar') !== false) {
                 $mensagem_resultado .= "<p style='color: yellow;'>[Sistema de Crença] Sua persuasão calculada reforça sua Crença de ser um enganador! **+50 XP Bônus!**</p>";
            }
            
        } elseif ($teste['resultado'] === 'falha_critica') {
            $mensagem_resultado .= "<p style='color: red;'>**FALHA CRÍTICA!** Suas palavras são desajeitadas. O Comandante fica furioso e você perde 50 Ouro em multa!</p>";
            // No jogo real: Penalidade: $conexao->query("UPDATE personagens SET ouro = ouro - 50 WHERE id = ...")
        } else {
            $mensagem_resultado .= "<p style='color: orange;'>**FALHA!** O Comandante não se convenceu, mas te deixa ir. Nenhuma recompensa obtida.</p>";
        }
    }
    
    // Teste de Força para Opção 2
    if ($acao === 'ameacar') {
        // ... (Aqui entraria a lógica de teste de STR com outra DC e resultados diferentes) ...
        $mensagem_resultado .= "<p>Você ameaçou o Comandante. Isso exigiria um teste de STR, mas não vamos arriscar a briga por enquanto!</p>";
    }
}

?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <title>Diálogo - Comandante Hórus</title>
    <style>
        /* Manter o estilo MUD-Style */
        body { font-family: monospace; background-color: #111; color: #0f0; margin: 20px; white-space: pre-wrap; }
        h2 { border-bottom: 1px solid #0f0; padding-bottom: 10px; }
        .npc-dialogue { border-left: 2px solid #00f; padding-left: 15px; margin-bottom: 20px; }
        .choices a { color: #0ff; text-decoration: none; display: block; margin-top: 10px; border: 1px solid #0ff; padding: 5px; }
        .choices a:hover { background-color: #0ff; color: #111; }
        .status-box { background: #333; padding: 10px; border: 1px solid #0f0; margin-bottom: 20px; }
    </style>
</head>
<body>

<div class="status-box">
    **[Status do Jogador]**<br>
    Nome: <?php echo $jogador['nome']; ?><br>
    Classe: <?php echo $jogador['classe']; ?><br>
    Carisma (CHA): <?php echo $jogador['stats']['cha']; ?><br>
    Crença: <?php echo $jogador['crenca_ativa']; ?>
</div>

<h2>ENCONTRO: <?php echo $npc_nome; ?></h2>

<?php echo $mensagem_resultado; // Exibe o resultado do teste após a ação ?>

<div class="npc-dialogue">
**<?php echo $npc_nome; ?>** (Comandante de Guarda):<br>
"Ryu, há rumores de que você esteve perto do Portal Duplo ontem. Houve um incidente, e artefatos sumiram. Sei que é um Ladino, mas... você pode me persuadir de sua inocência, ou terei que te levar para interrogatório. O que você me diz?"
</div>

<div class="choices">
    <h3>Escolhas de Ação (Baseadas em Atributos):</h3>
    <a href="?acao=persuadir">1. [Persuasão (CHA, DC <?php echo $dc_persuasao; ?>)] Tentar convencer o Comandante com sua lábia.</a>
    <a href="?acao=ameacar">2. [Intimidação (STR, DC 90)] Usar sua presença intimidadora para forçá-lo a recuar.</a>
    <a href="?acao=fugir">3. [Fugir (DEX)] Usar o comando `/fugir` e correr para fora da cidade.</a>
</div>

<br>
<p>Nível de Dificuldade (DC) para o Teste: <?php echo $dc_persuasao; ?></p>

</body>
</html>