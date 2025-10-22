<?php
session_start();
include_once 'db_connect.php'; // ✅ Use include_once
include_once 'game_logic.php'; // ✅ Use include_once  
include_once 'daily_quests_functions.php';


if (!isset($_SESSION['player_id'])) {
    header('Location: login.php');
    exit;
}

$player_id = $_SESSION['player_id'];
$titulo_pagina = "Loja do Nexus";
$mensagem = "";

// Carrega dados do jogador
$sql_player = "SELECT * FROM personagens WHERE id = $player_id";
$player_data = $conexao->query($sql_player)->fetch_assoc();

// Processar compra
if (isset($_GET['acao']) && $_GET['acao'] === 'comprar') {
    $item_id = (int)$_GET['item_id'];
    
    // Busca dados do item
    $sql_item = "SELECT * FROM loja_itens WHERE id = $item_id";
    $item_data = $conexao->query($sql_item)->fetch_assoc();
    
    if ($item_data) {
        // Verifica se tem ouro suficiente
        if ($player_data['ouro'] >= $item_data['preco']) {
            // Verifica estoque
            if ($item_data['estoque'] == -1 || $item_data['estoque'] > 0) {
                
                // Processa compra
                $conexao->query("UPDATE personagens SET ouro = ouro - {$item_data['preco']} WHERE id = $player_id");
                
                // Atualiza estoque se não for ilimitado
                if ($item_data['estoque'] > 0) {
                    $conexao->query("UPDATE loja_itens SET estoque = estoque - 1 WHERE id = $item_id");
                }
                
                // ✅ ADICIONA AO INVENTÁRIO (CORRIGIDO)
                $sql_check_item = "SELECT id, quantidade FROM inventario WHERE id_personagem = ? AND id_item_base = ?";
                $stmt_check = $conexao->prepare($sql_check_item);
                $stmt_check->bind_param("ii", $player_id, $item_id);
                $stmt_check->execute();
                $item_existente = $stmt_check->get_result()->fetch_assoc();

                if ($item_existente) {
                    // Se já tem o item, aumenta a quantidade
                    $conexao->query("UPDATE inventario SET quantidade = quantidade + 1 WHERE id = {$item_existente['id']}");
                } else {
                    // Se não tem, insere novo
                    $sql_insert = "INSERT INTO inventario (id_personagem, id_item_base, quantidade, equipado) VALUES (?, ?, 1, 0)";
                    $stmt_insert = $conexao->prepare($sql_insert);
                    $stmt_insert->bind_param("ii", $player_id, $item_id);
                    $stmt_insert->execute();
                }
                
                // Atualiza missão de gastar ouro
                atualizar_progresso_missao($player_id, 'gastar_ouro', $item_data['preco'], $conexao);
                
                $mensagem = "<div class='feedback feedback-success'>✅ Compra realizada com sucesso! (-{$item_data['preco']}💰)</div>";
                
                // Recarrega dados do jogador
                $player_data = $conexao->query($sql_player)->fetch_assoc();
                
            } else {
                $mensagem = "<div class='feedback feedback-error'>❌ Item esgotado!</div>";
            }
        } else {
            $mensagem = "<div class='feedback feedback-error'>❌ Ouro insuficiente!</div>";
        }
    }
}

// Carrega itens da loja
$sql_itens = "SELECT * FROM loja_itens WHERE (estoque = -1 OR estoque > 0) ORDER BY preco ASC";
$result_itens = $conexao->query($sql_itens);

include 'header.php';
?>

<div class="container fade-in">
    <!-- CABEÇALHO -->
    <div class="section section-arcane text-center">
        <h1 style="color: var(--accent-arcane);">🏪 LOJA DO NEXUS</h1>
        <p style="color: var(--text-secondary);">
            Compre itens essenciais para sua jornada
        </p>
    </div>

    <?php echo $mensagem; ?>

    <!-- SALDO DO JOGADOR -->
    <div class="section section-vital">
        <div class="shop-balance">
            <div class="balance-card">
                <div class="balance-icon">💰</div>
                <div class="balance-info">
                    <div class="balance-label">Seu Ouro</div>
                    <div class="balance-value"><?php echo $player_data['ouro']; ?></div>
                </div>
            </div>
        </div>
    </div>

    <!-- CATÁLOGOS DE ITENS -->
    <div class="section section-arcane">
        <h2 class="section-header">🛍️ CATÁLOGO DE ITENS</h2>
        
        <?php if ($result_itens->num_rows > 0): ?>
        <div class="shop-grid">
            <?php while($item = $result_itens->fetch_assoc()): 
                $pode_comprar = $player_data['ouro'] >= $item['preco'];
            ?>
            <div class="shop-item <?php echo $pode_comprar ? 'affordable' : 'expensive'; ?>">
                <div class="item-header">
                    <h4><?php echo $item['nome']; ?></h4>
                    <span class="item-price"><?php echo $item['preco']; ?>💰</span>
                </div>
                
                <div class="item-description">
                    <?php echo $item['descricao']; ?>
                </div>
                
                <div class="item-type">
                    <span class="type-badge"><?php echo ucfirst($item['tipo']); ?></span>
                    <?php if ($item['estoque'] != -1): ?>
                        <span class="stock-badge">Estoque: <?php echo $item['estoque']; ?></span>
                    <?php endif; ?>
                </div>
                
                <div class="item-actions">
                    <?php if ($pode_comprar): ?>
                    <a href="?acao=comprar&item_id=<?php echo $item['id']; ?>" class="btn btn-success">
                        Comprar
                    </a>
                    <?php else: ?>
                    <span class="btn btn-disabled">
                        Ouro Insuficiente
                    </span>
                    <?php endif; ?>
                </div>
            </div>
            <?php endwhile; ?>
        </div>
        <?php else: ?>
        <div class="empty-state">
            <div class="empty-icon">🏪</div>
            <h3>Loja Vazia</h3>
            <p>Novos itens em breve!</p>
        </div>
        <?php endif; ?>
    </div>
</div>

<style>
.shop-balance {
    display: flex;
    justify-content: center;
    margin-bottom: 20px;
}

.balance-card {
    display: flex;
    align-items: center;
    gap: 15px;
    padding: 20px;
    background: var(--bg-primary);
    border: 2px solid var(--status-gold);
    border-radius: 12px;
    min-width: 200px;
}

.balance-icon {
    font-size: 2.5em;
}

.balance-label {
    color: var(--text-secondary);
    font-size: 0.9em;
    margin-bottom: 5px;
}

.balance-value {
    font-size: 1.8em;
    font-weight: bold;
    color: var(--status-gold);
}

.shop-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
    gap: 20px;
}

.shop-item {
    background: var(--bg-primary);
    border: 2px solid var(--bg-tertiary);
    border-radius: 12px;
    padding: 20px;
    transition: all 0.3s ease;
}

.shop-item:hover {
    transform: translateY(-5px);
    box-shadow: 0 10px 25px rgba(138, 43, 226, 0.1);
}

.shop-item.affordable {
    border-color: var(--accent-vital);
}

.shop-item.expensive {
    border-color: var(--status-hp);
    opacity: 0.7;
}

.item-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 15px;
}

.item-header h4 {
    margin: 0;
    color: var(--text-primary);
}

.item-price {
    background: var(--status-gold);
    color: var(--bg-primary);
    padding: 5px 10px;
    border-radius: 20px;
    font-weight: bold;
    font-size: 0.9em;
}

.item-description {
    color: var(--text-secondary);
    margin-bottom: 15px;
    line-height: 1.5;
}

.item-type {
    display: flex;
    gap: 10px;
    margin-bottom: 15px;
    flex-wrap: wrap;
}

.type-badge, .stock-badge {
    padding: 4px 8px;
    border-radius: 12px;
    font-size: 0.8em;
    font-weight: bold;
}

.type-badge {
    background: var(--accent-arcane);
    color: white;
}

.stock-badge {
    background: var(--bg-tertiary);
    color: var(--text-secondary);
}

.item-actions {
    text-align: center;
}

@media (max-width: 768px) {
    .shop-grid {
        grid-template-columns: 1fr;
    }
    
    .item-header {
        flex-direction: column;
        align-items: flex-start;
        gap: 10px;
    }
    
    .item-type {
        flex-direction: column;
        align-items: flex-start;
    }
}
</style>

<?php include 'footer.php'; ?>