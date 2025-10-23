<?php
session_start();
include_once 'db_connect.php'; // ✅ Use include_once
include_once 'game_logic.php'; // ✅ Use include_once  
include_once 'daily_quests_functions.php';

// ... (código de verificação de login e carregamento de dados) ...

$titulo_pagina = "Inventário do Caçador";
$pagina_atual = 'inventario';

// Verifica login
if (!isset($_SESSION['player_id'])) {
    header('Location: login.php');
    exit;
}

$player_id = $_SESSION['player_id'];
$mensagem_feedback = "";

// =============================================================================
// PROCESSAR AÇÕES DO INVENTÁRIO
// =============================================================================

// Lógica de Venda
if (isset($_GET['acao']) && $_GET['acao'] === 'vender') {
    $id_linha_inventario = (int)$_GET['item_id']; 
    
    $sql_item_venda = "SELECT i.quantidade, ib.nome, ib.valor_venda 
                       FROM inventario i
                       JOIN itens_base ib ON i.id_item_base = ib.id
                       WHERE i.id = $id_linha_inventario AND i.id_personagem = $player_id AND i.equipado = FALSE";
    
    $item = $conexao->query($sql_item_venda)->fetch_assoc();

    if ($item) {
        $ouro_ganho = $item['valor_venda'];
        $conexao->query("UPDATE personagens SET ouro = ouro + $ouro_ganho WHERE id = $player_id");
        
        if ($item['quantidade'] > 1) {
            $conexao->query("UPDATE inventario SET quantidade = quantidade - 1 WHERE id = $id_linha_inventario");
        } else {
            $conexao->query("DELETE FROM inventario WHERE id = $id_linha_inventario");
        }
        $mensagem_feedback = "<div class='feedback feedback-success'>Você vendeu 1x {$item['nome']} por <span class='gold-value'>{$ouro_ganho} Ouro</span>.</div>";
    } else {
        $mensagem_feedback = "<div class='feedback feedback-error'>Erro: Item não encontrado, não é seu, ou está equipado.</div>";
    }
}

// Lógica de Equipar
else if (isset($_GET['acao']) && $_GET['acao'] === 'equipar') {
    $id_linha_inventario = (int)$_GET['item_id'];
    
    $sql_item_equipar = "SELECT ib.nome, ib.slot 
                         FROM inventario i
                         JOIN itens_base ib ON i.id_item_base = ib.id
                         WHERE i.id = $id_linha_inventario AND i.id_personagem = $player_id AND i.equipado = FALSE";
    $item = $conexao->query($sql_item_equipar)->fetch_assoc();

    if ($item && $item['slot']) {
        $slot_para_equipar = $item['slot'];
        
        // Desequipa item no mesmo slot
        $sql_desequipar_antigo = "UPDATE inventario i
                                  JOIN itens_base ib ON i.id_item_base = ib.id
                                  SET i.equipado = FALSE
                                  WHERE i.id_personagem = $player_id 
                                  AND ib.slot = '{$slot_para_equipar}' 
                                  AND i.equipado = TRUE";
        $conexao->query($sql_desequipar_antigo);
        
        // Equipa o novo item
        $conexao->query("UPDATE inventario SET equipado = TRUE WHERE id = $id_linha_inventario");
        
        $mensagem_feedback = "<div class='feedback feedback-success'>Você equipou: <strong>{$item['nome']}</strong>.</div>";
        
    } else {
        $mensagem_feedback = "<div class='feedback feedback-error'>Este item não pode ser equipado.</div>";
    }
}

// Lógica de Desequipar
else if (isset($_GET['acao']) && $_GET['acao'] === 'desequipar') {
    $id_linha_inventario = (int)$_GET['item_id'];
    
    $sql_desequipar = "UPDATE inventario SET equipado = FALSE 
                       WHERE id = $id_linha_inventario AND id_personagem = $player_id AND equipado = TRUE";
    $conexao->query($sql_desequipar);
    
    $mensagem_feedback = "<div class='feedback feedback-warning'>Item desequipado.</div>";
}

// Lógica de Usar Item Consumível
else if (isset($_GET['acao']) && $_GET['acao'] === 'usar') {
    $id_linha_inventario = (int)$_GET['item_id'];

    $sql_item_usar = "SELECT
                            i.quantidade,
                            ib.nome, ib.tipo, ib.efeito_hp_cura, ib.efeito_mana_cura,
                            p.hp_atual, p.hp_max, p.mana_atual, p.mana_max
                       FROM inventario i
                       JOIN itens_base ib ON i.id_item_base = ib.id
                       JOIN personagens p ON i.id_personagem = p.id
                       WHERE i.id = $id_linha_inventario AND i.id_personagem = $player_id";

    $item = $conexao->query($sql_item_usar)->fetch_assoc();

    if ($item && in_array($item['tipo'], ['Consumivel', 'Poção', 'Consumivel_Eco'])) {

        // Verifica se é uma Poção de HP e se o HP já está cheio
        if ($item['efeito_hp_cura'] > 0 && $item['hp_atual'] >= $item['hp_max']) {
            $mensagem_feedback = "<div class='feedback feedback-warning'>Seu HP já está cheio.</div>";
        }
        // (Adicionar verificação para Mana cheia aqui no futuro, se criar poção de mana)
        else {
            // Aplica os Efeitos (Cura HP)
            $hp_curado = $item['efeito_hp_cura'];
            $hp_novo = min($item['hp_max'], $item['hp_atual'] + $hp_curado);

            // (Aplicar cura de Mana aqui no futuro)
            $mana_curada = 0; // $item['efeito_mana_cura'];
            $mana_nova = min($item['mana_max'], $item['mana_atual'] + $mana_curada);

            // Atualiza HP/Mana do Jogador no BD
            $conexao->query("UPDATE personagens SET hp_atual = {$hp_novo}, mana_atual = {$mana_nova} WHERE id = $player_id");

            // Consome o Item
            if ($item['quantidade'] > 1) {
                $conexao->query("UPDATE inventario SET quantidade = quantidade - 1 WHERE id = $id_linha_inventario");
            } else {
                $conexao->query("DELETE FROM inventario WHERE id = $id_linha_inventario");
            }

            $mensagem_feedback = "<div class='feedback feedback-success'>Você usou <strong>{$item['nome']}</strong> e recuperou <span class='hp-value'>{$hp_curado} HP</span>.</div>";

            // Recarrega os dados do jogador para exibição
            $player_data = $conexao->query("SELECT * FROM personagens WHERE id = $player_id")->fetch_assoc();
        }

    } else {
        $mensagem_feedback = "<div class='feedback feedback-error'>Não é possível usar este item.</div>";
    }
}

// =============================================================================
// CARREGAR DADOS PARA EXIBIÇÃO
// =============================================================================

// Carrega dados do jogador
$sql_player = "SELECT * FROM personagens WHERE id = $player_id";
$player_data = $conexao->query($sql_player)->fetch_assoc();

if (!$player_data) {
    session_destroy();
    header('Location: login.php');
    exit;
}

// Carrega os itens do inventário
$sql_inventario = "
    SELECT i.id, i.quantidade, i.equipado, 
           ib.nome, ib.peso, ib.valor_venda, ib.raridade, ib.tipo, ib.slot
    FROM inventario i
    JOIN itens_base ib ON i.id_item_base = ib.id
    WHERE i.id_personagem = $player_id
    ORDER BY i.equipado DESC, ib.tipo, ib.nome
";
$inventario_result = $conexao->query($sql_inventario);

// Calcula o limite de carga
$limite_carga = calcular_limite_carga($player_data['str'], $player_id, $conexao); 

// Calcula stats totais com equipamentos
$equip_bonus = carregar_stats_equipados($player_id, $conexao);

include 'header.php';
?>

<div class="container fade-in">
    <!-- CABEÇALHO -->
    <div class="section section-arcane text-center">
        <h1>🎒 INVENTÁRIO</h1>
        <p class="subtitle">Gerenciamento de Equipamentos e Itens</p>
    </div>

    <?php echo $mensagem_feedback; ?>

    <!-- STATUS DO PERSONAGEM -->
    <div class="section section-vital">
        <h2 class="section-header vital">👤 STATUS</h2>
        <div class="character-stats">
            <div class="stat-row">
                <div class="stat-item">
                    <span class="stat-label">Caçador</span>
                    <span class="stat-value"><?php echo htmlspecialchars($player_data['nome']); ?></span>
                </div>
                <div class="stat-item">
                    <span class="stat-label">Nível</span>
                    <span class="stat-value"><?php echo $player_data['level']; ?></span>
                </div>
                <div class="stat-item">
                    <span class="stat-label">Classe</span>
                    <span class="stat-value"><?php echo $player_data['classe_base']; ?></span>
                </div>
            </div>
            
            <div class="stat-row">
                <div class="stat-item">
                    <span class="stat-label">Ouro</span>
                    <span class="stat-value gold-value"><?php echo number_format($player_data['ouro']); ?></span>
                </div>
                <div class="stat-item">
                    <span class="stat-label">Carga</span>
                    <span class="stat-value <?php echo ($limite_carga['peso_atual'] > $limite_carga['max_carga']) ? 'error' : 'success'; ?>">
                        <?php echo number_format($limite_carga['peso_atual'], 1); ?>/<?php echo $limite_carga['max_carga']; ?>kg
                    </span>
                </div>
                <div class="stat-item">
                    <span class="stat-label">Força</span>
                    <span class="stat-value"><?php echo $player_data['str'] + $equip_bonus['bonus_str']; ?></span>
                </div>
            </div>
        </div>
    </div>

    <!-- EQUIPAMENTOS ATUAIS -->
    <div class="section section-arcane">
        <h2 class="section-header">⚔️ EQUIPAMENTOS</h2>
        <div class="equipment-grid">
            <?php
            $slots = [
                'Arma_1' => ['name' => 'Arma Principal', 'icon' => '⚔️'],
                'Arma_2' => ['name' => 'Arma Secundária', 'icon' => '🗡️'],
                'Peitoral' => ['name' => 'Armadura', 'icon' => '🛡️'],
                'Helmo' => ['name' => 'Elmo', 'icon' => '👑'],
                'Luvas' => ['name' => 'Luvas', 'icon' => '🧤'],
                'Botas' => ['name' => 'Botas', 'icon' => '👢']
            ];
            
            // Busca itens equipados
            $sql_equipados = "SELECT ib.slot, ib.nome, i.id 
                              FROM inventario i
                              JOIN itens_base ib ON i.id_item_base = ib.id
                              WHERE i.id_personagem = $player_id AND i.equipado = TRUE";
            $result_equipados = $conexao->query($sql_equipados);
            $equipados = [];
            while ($row = $result_equipados->fetch_assoc()) {
                $equipados[$row['slot']] = $row;
            }
            
            foreach ($slots as $slot => $info): 
                $item = $equipados[$slot] ?? null;
            ?>
                <div class="equipment-slot">
                    <div class="slot-icon"><?php echo $info['icon']; ?></div>
                    <div class="slot-info">
                        <div class="slot-name"><?php echo $info['name']; ?></div>
                        <div class="item-name <?php echo $item ? 'rarity-rare' : 'empty'; ?>">
                            <?php echo $item ? htmlspecialchars($item['nome']) : 'Vazio'; ?>
                        </div>
                    </div>
                    <?php if ($item): ?>
                        <a href="?acao=desequipar&item_id=<?php echo $item['id']; ?>" class="btn btn-small btn-warning">Desequipar</a>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        </div>
    </div>

    <!-- LISTA DE ITENS -->
    <div class="section section-vital">
        <h2 class="section-header vital">📦 ITENS</h2>
        
        <div class="inventory-actions">
            <div class="search-box">
                <input type="text" placeholder="🔍 Buscar item..." class="form-input">
            </div>
            <div class="filter-buttons">
                <button class="btn btn-small active">Todos</button>
                <button class="btn btn-small">Equipamentos</button>
                <button class="btn btn-small">Consumíveis</button>
                <button class="btn btn-small">Materiais</button>
            </div>
        </div>

        <div class="table-responsive">
            <table class="table">
                <thead>
                    <tr>
                        <th>Item</th>
                        <th>Tipo</th>
                        <th>Quantidade</th>
                        <th>Peso</th>
                        <th>Valor</th>
                        <th>Ações</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($inventario_result->num_rows > 0): ?>
                        <?php while($item = $inventario_result->fetch_assoc()): ?>
                            <?php
                                $peso_total_item = $item['peso'] * $item['quantidade'];
                                $raridade_class = 'rarity-' . strtolower($item['raridade']);
                            ?>
                            <tr class="inventory-item <?php echo $item['equipado'] ? 'equipped' : ''; ?>">
                                <td>
                                    <div class="item-info">
                                        <span class="item-name <?php echo $raridade_class; ?>">
                                            <?php echo htmlspecialchars($item['nome']); ?>
                                            <?php if ($item['equipado']): ?>
                                                <span class="equipped-badge">[E]</span>
                                            <?php endif; ?>
                                        </span>
                                    </div>
                                </td>
                                <td><?php echo $item['tipo']; ?></td>
                                <td>
                                    <span class="quantity"><?php echo $item['quantidade']; ?></span>
                                </td>
                                <td><?php echo number_format($peso_total_item, 1); ?>kg</td>
                                <td class="gold-value"><?php echo $item['valor_venda']; ?></td>
                                <td>
                                    <div class="item-actions">
                                        <?php if ($item['equipado']): ?>
                                            <a href="?acao=desequipar&item_id=<?php echo $item['id']; ?>" class="btn-action btn-unequip" title="Desequipar">🚫</a>
                                        <?php else: ?>
                                            <?php if (in_array($item['tipo'], ['Arma', 'Armadura'])): ?>
                                                <a href="?acao=equipar&item_id=<?php echo $item['id']; ?>" class="btn-action btn-equip" title="Equipar">⚔️</a>
                                            <?php endif; ?>
                                            
                                            <?php if (in_array($item['tipo'], ['Consumivel', 'Poção'])): ?>
                                                <a href="?acao=usar&item_id=<?php echo $item['id']; ?>" class="btn-action btn-use" title="Usar">❤️</a>
                                            <?php endif; ?>
                                            
                                            <a href="?acao=vender&item_id=<?php echo $item['id']; ?>" class="btn-action btn-sell" title="Vender">💰</a>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="6" class="text-center">
                                <div class="empty-inventory">
                                    <div class="empty-icon">🎒</div>
                                    <h3>Inventário Vazio</h3>
                                    <p>Comece explorando portais para coletar itens!</p>
                                    <a href="mapa.php" class="btn btn-primary">Explorar Portais</a>
                                </div>
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- ESTATÍSTICAS DE EQUIPAMENTO -->
    <div class="section section-arcane">
        <h2 class="section-header">📊 BÔNUS DE EQUIPAMENTO</h2>
        <div class="bonus-grid">
            <div class="bonus-item">
                <span class="bonus-label">Força</span>
                <span class="bonus-value">+<?php echo $equip_bonus['bonus_str']; ?></span>
            </div>
            <div class="bonus-item">
                <span class="bonus-label">Destreza</span>
                <span class="bonus-value">+<?php echo $equip_bonus['bonus_dex']; ?></span>
            </div>
            <div class="bonus-item">
                <span class="bonus-label">Constituição</span>
                <span class="bonus-value">+<?php echo $equip_bonus['bonus_con']; ?></span>
            </div>
            <div class="bonus-item">
                <span class="bonus-label">Inteligência</span>
                <span class="bonus-value">+<?php echo $equip_bonus['bonus_int']; ?></span>
            </div>
            <div class="bonus-item">
                <span class="bonus-label">Dano</span>
                <span class="bonus-value"><?php echo $equip_bonus['dano_min_total']; ?>-<?php echo $equip_bonus['dano_max_total']; ?></span>
            </div>
            <div class="bonus-item">
                <span class="bonus-label">Mitigação</span>
                <span class="bonus-value">+<?php echo $equip_bonus['mitigacao_total']; ?></span>
            </div>
        </div>
    </div>
</div>

<style>
/* ESTILOS ESPECÍFICOS DO INVENTÁRIO */
.character-stats {
    background: var(--bg-primary);
    padding: 20px;
    border-radius: 8px;
    border: 1px solid var(--bg-tertiary);
}

.stat-row {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 15px;
    margin-bottom: 15px;
}

.stat-row:last-child {
    margin-bottom: 0;
}

.stat-item {
    text-align: center;
    padding: 10px;
    background: var(--bg-secondary);
    border-radius: 6px;
}

.stat-label {
    display: block;
    color: var(--text-secondary);
    font-size: 0.8em;
    margin-bottom: 5px;
}

.stat-value {
    display: block;
    font-weight: bold;
    font-size: 1.1em;
}

.equipment-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
    gap: 15px;
}

.equipment-slot {
    display: flex;
    align-items: center;
    gap: 15px;
    padding: 15px;
    background: var(--bg-primary);
    border: 2px solid var(--bg-tertiary);
    border-radius: 8px;
    transition: all 0.3s ease;
}

.equipment-slot:hover {
    border-color: var(--accent-arcane);
}

.slot-icon {
    font-size: 2em;
    width: 40px;
    text-align: center;
}

.slot-info {
    flex: 1;
}

.slot-name {
    color: var(--text-secondary);
    font-size: 0.9em;
    margin-bottom: 5px;
}

.item-name.empty {
    color: var(--text-secondary);
    font-style: italic;
}

.inventory-actions {
    display: flex;
    gap: 15px;
    margin-bottom: 20px;
    flex-wrap: wrap;
}

.search-box {
    flex: 1;
    min-width: 200px;
}

.filter-buttons {
    display: flex;
    gap: 5px;
}

.filter-buttons .btn {
    padding: 8px 12px;
    font-size: 0.85em;
}

.filter-buttons .btn.active {
    background: var(--accent-vital);
    color: var(--bg-primary);
}

.table-responsive {
    overflow-x: auto;
}

.inventory-item:hover {
    background: rgba(138, 43, 226, 0.1) !important;
}

.inventory-item.equipped {
    background: rgba(80, 200, 120, 0.1);
}

.item-info {
    display: flex;
    align-items: center;
    gap: 10px;
}

.equipped-badge {
    background: var(--accent-vital);
    color: var(--bg-primary);
    padding: 2px 6px;
    border-radius: 10px;
    font-size: 0.7em;
    font-weight: bold;
}

.quantity {
    font-weight: bold;
    color: var(--accent-vital);
}

.item-actions {
    display: flex;
    gap: 5px;
}

.btn-action {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 32px;
    height: 32px;
    border: 1px solid;
    border-radius: 4px;
    text-decoration: none;
    font-size: 0.8em;
    transition: all 0.2s ease;
}

.btn-equip {
    border-color: var(--accent-vital);
    color: var(--accent-vital);
}

.btn-equip:hover {
    background: var(--accent-vital);
    color: var(--bg-primary);
}

.btn-unequip {
    border-color: var(--status-hp);
    color: var(--status-hp);
}

.btn-unequip:hover {
    background: var(--status-hp);
    color: white;
}

.btn-use {
    border-color: var(--status-mana);
    color: var(--status-mana);
}

.btn-use:hover {
    background: var(--status-mana);
    color: white;
}

.btn-sell {
    border-color: var(--status-gold);
    color: var(--status-gold);
}

.btn-sell:hover {
    background: var(--status-gold);
    color: var(--bg-primary);
}

.empty-inventory {
    text-align: center;
    padding: 40px 20px;
}

.empty-icon {
    font-size: 4em;
    margin-bottom: 20px;
    opacity: 0.5;
}

.empty-inventory h3 {
    color: var(--text-secondary);
    margin-bottom: 10px;
}

.empty-inventory p {
    color: var(--text-secondary);
    margin-bottom: 20px;
}

.bonus-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
    gap: 15px;
}

.bonus-item {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 12px;
    background: var(--bg-primary);
    border-radius: 6px;
    border: 1px solid var(--bg-tertiary);
}

.bonus-label {
    color: var(--text-secondary);
    font-size: 0.9em;
}

.bonus-value {
    font-weight: bold;
    color: var(--accent-vital);
}

@media (max-width: 768px) {
    .stat-row {
        grid-template-columns: 1fr;
    }
    
    .inventory-actions {
        flex-direction: column;
    }
    
    .filter-buttons {
        justify-content: center;
    }
    
    .equipment-grid {
        grid-template-columns: 1fr;
    }
    
    .bonus-grid {
        grid-template-columns: repeat(2, 1fr);
    }
}
</style>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Filtros de inventário
    const filterButtons = document.querySelectorAll('.filter-buttons .btn');
    const inventoryItems = document.querySelectorAll('.inventory-item');
    
    filterButtons.forEach(button => {
        button.addEventListener('click', function() {
            // Remove active class de todos os botões
            filterButtons.forEach(btn => btn.classList.remove('active'));
            // Adiciona active no botão clicado
            this.classList.add('active');
            
            const filter = this.textContent.toLowerCase();
            
            inventoryItems.forEach(item => {
                if (filter === 'todos') {
                    item.style.display = '';
                } else {
                    const itemType = item.querySelector('td:nth-child(2)').textContent.toLowerCase();
                    if (itemType.includes(filter)) {
                        item.style.display = '';
                    } else {
                        item.style.display = 'none';
                    }
                }
            });
        });
    });
    
    // Busca em tempo real
    const searchInput = document.querySelector('.search-box input');
    searchInput.addEventListener('input', function() {
        const searchTerm = this.value.toLowerCase();
        
        inventoryItems.forEach(item => {
            const itemName = item.querySelector('.item-name').textContent.toLowerCase();
            if (itemName.includes(searchTerm)) {
                item.style.display = '';
            } else {
                item.style.display = 'none';
            }
        });
    });
});
</script>

<?php include 'footer.php'; ?>