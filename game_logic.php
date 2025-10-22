<?php

// ==========================================================
// FUNÇÕES DE LÓGICA DE JOGO (game_logic.php)
// Este arquivo deve conter APENAS funções!
// ==========================================================

/**
 * Simula a rolagem de um dado de 100 faces (d100).
 * @return int Resultado da rolagem (entre 1 e 100).
 */
function roll_d100() {
    return mt_rand(1, 100);
}

/**
 * Realiza um teste de atributo: (Roll d100 + Modificador) vs. Dificuldade.
 * @param int $modificador O valor do atributo (ex: 15 de Carisma).
 * @param int $dc A Dificuldade da Classe (Difficulty Class) do teste (ex: 70).
 * @return array Contém o resultado do teste ('sucesso', 'falha', 'critico') e o roll do dado.
 */
function teste_atributo($modificador, $dc) {
    // Rola o dado de 100
    $roll = roll_d100();
    
    // Soma o modificador (o valor do atributo) ao resultado
    $resultado_final = $roll + $modificador;
    
    // 1. CHECAGEM DE CRÍTICOS (Regras D&D/Shonen)
    if ($roll >= 95) { 
        return ['resultado' => 'critico', 'roll' => $roll, 'final' => $resultado_final];
    }
    if ($roll <= 5) {
        return ['resultado' => 'falha_critica', 'roll' => $roll, 'final' => $resultado_final];
    }

    // 2. CHECAGEM PADRÃO
    if ($resultado_final >= $dc) {
        return ['resultado' => 'sucesso', 'roll' => $roll, 'final' => $resultado_final];
    } else {
        return ['resultado' => 'falha', 'roll' => $roll, 'final' => $resultado_final];
    }
}

/**
 * Calcula os atributos derivados (HP Máximo, Mana/Recurso Máximo).
 * (ATUALIZADO com fórmulas específicas por classe - Tarefa 13)
 *
 * @param array $stats_base Os atributos BASE do personagem (str, dex, con, int_stat, wis, cha).
 * @param int $level O nível atual do personagem.
 * @param string $classe_base A classe base ('Guerreiro', 'Mago', 'Ladino', 'Sacerdote').
 * @return array Contém 'hp_max' e 'recurso_max'.
 */
function calcular_stats_derivados($stats_base, $level, $classe_base) {
    // Pega os valores base para facilitar a leitura
    $str = isset($stats_base['str']) ? (int)$stats_base['str'] : 10;
    $dex = isset($stats_base['dex']) ? (int)$stats_base['dex'] : 10;
    $con = isset($stats_base['con']) ? (int)$stats_base['con'] : 10;
    $int_stat = isset($stats_base['int_stat']) ? (int)$stats_base['int_stat'] : 10;
    $wis = isset($stats_base['wis']) ? (int)$stats_base['wis'] : 10;

    $hp_max = 100; // Valor padrão
    $recurso_max = 50; // Valor padrão

    // --- CÁLCULO DE HP MÁXIMO (Depende da Classe) ---
    switch ($classe_base) {
        case 'Guerreiro':
            $hp_max = ($con * 12) + ($level * 60);
            break;
        case 'Ladino':
        case 'Caçador': // Adicionando Caçador com stats de Ladino por enquanto
            $hp_max = ($con * 10) + ($level * 50);
            break;
        case 'Mago':
            $hp_max = ($con * 8) + ($level * 40);
            break;
        case 'Sacerdote':
            $hp_max = ($con * 9) + ($level * 45);
            break;
    }

    // --- CÁLCULO DE MANA/RECURSO MÁXIMO (Depende da Classe) ---
    switch ($classe_base) {
        case 'Guerreiro': // Fúria
            $recurso_max = 50 + ($con * 5) + ($str * 2);
            break;
        case 'Ladino': // Energia
             $recurso_max = 100 + ($dex * 5);
             break;
        case 'Caçador': // Foco (similar a Energia, baseado em DEX/WIS?)
             $recurso_max = 90 + ($dex * 4) + ($wis * 2); // Exemplo
             break;
        case 'Mago': // Mana
            $recurso_max = 80 + ($int_stat * 10) + ($wis * 5);
            break;
        case 'Sacerdote': // Fé
            $recurso_max = 70 + ($wis * 10) + ($int_stat * 5);
            break;
    }

    // Garante valores mínimos
    $hp_max = max(10, $hp_max);
    $recurso_max = max(10, $recurso_max);

    return ['hp_max' => (int)$hp_max, 'recurso_max' => (int)$recurso_max];
}

// NO game_logic.php - função simples:
function ganhar_affinity($player_id, $eco_id, $quantidade, $conexao) {
    $sql = "UPDATE personagem_ecos 
            SET affinity_xp = affinity_xp + $quantidade 
            WHERE id_personagem = $player_id AND id = $eco_id";
    $conexao->query($sql);
    
    // Verificar level up
    $affinity_data = $conexao->query("SELECT affinity_xp, affinity_level FROM personagem_ecos WHERE id = $eco_id")->fetch_assoc();
    if ($affinity_data['affinity_xp'] >= ($affinity_data['affinity_level'] * 100)) {
        $conexao->query("UPDATE personagem_ecos SET affinity_level = affinity_level + 1, affinity_xp = 0 WHERE id = $eco_id");
        return true; // Level up!
    }
    return false;
}

/**
 * Busca e retorna as estatísticas de um Monstro do banco de dados.
 * (ATUALIZADO para usar a tabela monstros_base)
 * @param string $rank O Rank do Portal/Monstro (E, D, C, etc.)
 * @param object $conexao A conexão com o banco de dados.
 * @return array As estatísticas do monstro (ou null se não encontrado).
 */
function gerar_monstro($rank, $conexao) { // Adicionamos $conexao
    
    // Busca um monstro ALEATÓRIO daquele rank no BD
    $sql_monstro = "SELECT * FROM monstros_base WHERE rank_monstro = '{$rank}' ORDER BY RAND() LIMIT 1";
    $result = $conexao->query($sql_monstro);
    
    if ($result && $result->num_rows > 0) {
        $monstro_base = $result->fetch_assoc();
        
        // Monta o array para o combate (adicionando hp_atual e calculando ouro)
        return [
            'id_base' => $monstro_base['id'], // (NOVO) Guarda o ID do monstro
            'nome' => $monstro_base['nome'],
            'hp_max' => $monstro_base['hp_base'],
            'hp_atual' => $monstro_base['hp_base'], // Começa com HP cheio
            'str' => $monstro_base['str_base'],
            'dex' => $monstro_base['dex_base'],
            'con' => $monstro_base['con_base'],
            'dano_min' => $monstro_base['dano_min_base'], // (NOVO) Dano mínimo
            'dano_max' => $monstro_base['dano_max_base'], // (NOVO) Dano máximo
            'xp_recompensa' => $monstro_base['xp_recompensa'],
            'ouro_recompensa' => mt_rand($monstro_base['ouro_recompensa_min'], $monstro_base['ouro_recompensa_max']) // (NOVO) Ouro aleatório
        ];
    }
    
    return null; // Nenhum monstro encontrado para esse Rank
}

// Continuação do arquivo game_logic.php

/**
 * Calcula o dano final APÓS mitigação.
 * @param int $dano_bruto O dano inicial do ataque.
 * @param int $con_alvo O valor de CON do alvo (para mitigação passiva).
 * @param int $armadura_alvo O bônus de Armadura do alvo (de equipamentos).
 * @return int O dano real que o alvo recebe.
 */
function calcular_dano_mitigado($dano_bruto, $con_alvo, $armadura_alvo) {
    // 1. Mitigação passiva da CON (Ex: 1 ponto de CON mitiga 0.5 de dano)
    $reducao_con = floor($con_alvo / 2); // Usa floor para garantir números inteiros
    
    // 2. Mitigação da armadura (Ex: Armadura 10 é 10% de redução, mas vamos simplificar para armadura_alvo/100)
    $reducao_armadura = $armadura_alvo / 100;
    
    $dano_parcial = $dano_bruto - $reducao_con;
    
    // 3. Aplica a mitigação da armadura
    $dano_final = $dano_parcial * (1 - $reducao_armadura);

    // Garante que o dano mínimo seja 1 (a menos que seja bloqueado, mas simplificamos aqui)
    return max(1, round($dano_final));
}

// Continuação do arquivo game_logic.php

/**
 * Calcula a capacidade máxima de carga do jogador (baseado em STR).
 * E o peso total atual.
 * * NOTA: No jogo real, a $conexao seria usada para somar o peso de todos os itens no inventário.
 * Por enquanto, vamos simular a busca.
 * @param int $str_personagem O valor do atributo Força (STR).
 * @param int $id_personagem O ID do jogador (para buscar no BD).
 * @param object $conexao A conexão ativa com o banco de dados.
 * @return array Contém 'max_carga' e 'peso_atual'.
 */
function calcular_limite_carga($str_personagem, $id_personagem, $conexao) {
    // FÓRMULA: Capacidade de Carga (Peso) = 10 + (STR * 5)
    $max_carga = 10 + ($str_personagem * 5);
    
    // Calcula o peso atual do inventário do jogador (Requer JOIN entre inventario e itens_base)
    $sql_peso_atual = "SELECT SUM(ib.peso * i.quantidade) AS peso_total 
                       FROM inventario i
                       JOIN itens_base ib ON i.id_item_base = ib.id
                       WHERE i.id_personagem = $id_personagem";
                       
    $resultado = $conexao->query($sql_peso_atual);
    $row = $resultado->fetch_assoc();
    $peso_atual = (float)$row['peso_total'];

    return [
        'max_carga' => $max_carga,
        'peso_atual' => $peso_atual
    ];
}

// Continuação do arquivo game_logic.php

/**
 * Tenta adicionar um item ao inventário, verificando o limite de carga (STR).
 * @param int $id_personagem ID do jogador.
 * @param array $stats_personagem Stats do jogador (precisamos do STR).
 * @param object $conexao Conexão com o banco de dados.
 * @param int $id_item_drop ID do item base que foi dropado.
 * @param int $quantidade Quantidade do item.
 * @return string Mensagem de feedback (sucesso ou falha por excesso de peso).
 */
function processar_auto_loot($id_personagem, $stats_personagem, $conexao, $id_item_drop, $quantidade = 1) {
    
    // 1. Obtém as informações do item dropado (COM A CORREÇÃO - INCLUINDO 'tipo')
    $sql_item = "SELECT nome, peso, valor_venda, tipo FROM itens_base WHERE id = $id_item_drop";
    $item = $conexao->query($sql_item)->fetch_assoc();

    if (!$item) {
        return "Erro: Item dropado (ID: $id_item_drop) não encontrado no catálogo.";
    }

    // 2. Checa o limite de carga do jogador
    $limite_carga = calcular_limite_carga($stats_personagem['str'], $id_personagem, $conexao);
    $peso_item = $item['peso'] * $quantidade;
    
    // Peso projetado após coletar este item
    $peso_projetado = $limite_carga['peso_atual'] + $peso_item;

    if ($peso_projetado > $limite_carga['max_carga']) {
        
        // 3. FALHA NO LOOT: Excesso de Peso (Auto-Venda)
        $ouro_ganho = $item['valor_venda'] * $quantidade;
        
        // Atualiza o Ouro do jogador
        $conexao->query("UPDATE personagens SET ouro = ouro + $ouro_ganho WHERE id = $id_personagem");
        
        return "<span style='color: #FF8C00;'>[CARGA EXCEDIDA!]</span> O item '{$item['nome']}' era muito pesado e foi vendido automaticamente por {$ouro_ganho} Ouro.";
    } else {
        
        // 4. SUCESSO NO LOOT: Adiciona ao Inventário
        // Verifica se o jogador já tem este item (para empilhar)
        $sql_checar = "SELECT id, quantidade FROM inventario WHERE id_personagem = $id_personagem AND id_item_base = $id_item_drop AND equipado = FALSE";
        $existe = $conexao->query($sql_checar)->fetch_assoc();
        
        // Agora esta verificação funciona, pois temos $item['tipo']
        if ($existe && $item['tipo'] !== 'Arma' && $item['tipo'] !== 'Armadura') {
            // Se já existe e é empilhável (não é equipamento)
            $conexao->query("UPDATE inventario SET quantidade = quantidade + $quantidade WHERE id = {$existe['id']}");
        } else {
            // Insere um novo slot no inventário
            $conexao->query("INSERT INTO inventario (id_personagem, id_item_base, quantidade) VALUES ($id_personagem, $id_item_drop, $quantidade)");
        }

        return "<span style='color: #00FFFF;'>[LOOT SUCESSO!]</span> Você coletou **{$quantidade}x {$item['nome']}**. (Peso: +".($peso_item).")";
    }
}

/**
 * Verifica se o jogador tem XP suficiente para subir de nível e processa o level up.
 *
 * @param int $player_id O ID do jogador.
 * @param array $player_data Os dados atuais do jogador (array vindo do BD).
 * @param object $conexao A conexão com o banco de dados.
 * @return string Mensagem de feedback (Ex: "VOCÊ SUBIU DE NÍVEL!").
 */
function verificar_level_up($player_id, $player_data, $conexao) {
    
    $mensagem_level_up = ""; // String para retornar o feedback
    
    // Pega o XP atual e o XP necessário
    $xp_atual = $player_data['xp_atual'];
    $xp_necessario = $player_data['xp_proximo_level'];

    // Usamos 'while' em vez de 'if' para o caso do jogador ganhar XP para 2 níveis de uma vez
    while ($xp_atual >= $xp_necessario) {
        
        // 1. O JOGADOR SUBIU DE NÍVEL
        $novo_level = $player_data['level'] + 1;
        
        // 2. Calcula o XP "restante"
        $xp_atual = $xp_atual - $xp_necessario; 
        
        // 3. Define o XP para o próximo nível (Ex: Fórmula simples: Nível * 1000)
        // (No futuro, podemos usar uma tabela de XP, mas uma fórmula é mais fácil agora)
        $novo_xp_necessario = $novo_level * 1000; 
        
        // 4. Define as Recompensas (Conforme nosso GDD)
        $pa_ganho = 3; // 3 Pontos de Atributo (PA)
        $ph_ganho = 1; // 1 Ponto de Habilidade (PH) (a cada 2 níveis)
        
        if ($novo_level % 2 != 0) { // Se o nível for ímpar
            $ph_ganho = 0; // Não ganha PH em nível ímpar (só em par)
        }
        
        // 5. Atualiza o Banco de Dados com os novos valores E RECALCULA HP/MANA MAX
        $stats_base_atuais = [ /* ... pega stats base ... */ ];
        $novos_derivados = calcular_stats_derivados($stats_base_atuais, $novo_level, $player_data['classe_base']);
        $novo_hp_max = $novos_derivados['hp_max'];
        $novo_recurso_max = $novos_derivados['recurso_max'];

        $sql_level_up = "UPDATE personagens SET
                            level = $novo_level,
                            xp_atual = $xp_atual,
                            xp_proximo_level = $novo_xp_necessario,
                            hp_max = $novo_hp_max,             -- CONFIRME SE ESTÁ AQUI
                            mana_max = $novo_recurso_max,       -- CONFIRME SE ESTÁ AQUI
                            pontos_atributo_disponiveis = pontos_atributo_disponiveis + $pa_ganho,
                            pontos_habilidade_disponiveis = pontos_habilidade_disponiveis + $ph_ganho
                         WHERE id = $player_id";
        $conexao->query($sql_level_up);

        // Opcional: Curar no level up
        $conexao->query("UPDATE personagens SET hp_atual = $novo_hp_max, mana_atual = $novo_recurso_max WHERE id = $player_id");


        // 6. Prepara a mensagem de feedback
        $mensagem_level_up .= "<h3 style='color: #00FF00;'>LEVEL UP! Você alcançou o Nível {$novo_level}!</h3>";
        $mensagem_level_up .= "<p style='color: yellow;'>Você ganhou +{$pa_ganho} Pontos de Atributo (PA)!</p>";
        if ($ph_ganho > 0) {
            $mensagem_level_up .= "<p style='color: yellow;'>Você ganhou +{$ph_ganho} Ponto de Habilidade (PH)!</p>";
        }
        
        // 7. Atualiza os dados locais para o 'while' continuar checando
        $player_data['level'] = $novo_level;
        $player_data['xp_atual'] = $xp_atual;
        $player_data['xp_proximo_level'] = $novo_xp_necessario;
    }
    
    return $mensagem_level_up;
}

/**
 * Busca todos os itens EQUIPADOS do jogador e soma seus bônus totais.
 *
 * @param int $player_id O ID do jogador.
 * @param object $conexao A conexão com o banco de dados.
 * @return array Um array contendo a soma de todos os bônus (ex: 'bonus_str', 'dano_total', 'mitigacao_total').
 */
function carregar_stats_equipados($player_id, $conexao) {
    
    // 1. Prepara o array de bônus (começando com tudo zerado)
    $bonus_totais = [
        'bonus_str' => 0,
        'bonus_dex' => 0,
        'bonus_con' => 0,
        'bonus_int' => 0,
        'bonus_wis' => 0,
        'bonus_cha' => 0,
        'dano_min_total' => 0,
        'dano_max_total' => 0,
        'mitigacao_total' => 0 // Mitigação de armadura
    ];

    // 2. Query SQL que busca e SOMA os bônus de todos os itens equipados
    // (JOIN entre inventario e itens_base, filtrando por id_personagem e equipado=TRUE)
    $sql_bonus = "SELECT 
                        SUM(ib.bonus_str) AS total_str,
                        SUM(ib.bonus_con) AS total_con,
                        SUM(ib.bonus_dex) AS total_dex,
                        SUM(ib.bonus_int) AS total_int,
                        SUM(ib.dano_min) AS total_dano_min,
                        SUM(ib.dano_max) AS total_dano_max,
                        SUM(ib.mitigacao) AS total_mitigacao
                  FROM inventario i
                  JOIN itens_base ib ON i.id_item_base = ib.id
                  WHERE i.id_personagem = $player_id AND i.equipado = TRUE";
                  
    $resultado = $conexao->query($sql_bonus);
    
    if ($resultado && $resultado->num_rows > 0) {
        $soma_bonus = $resultado->fetch_assoc();
        
        // 3. Atualiza o array de bônus com os valores somados do BD
        // (Usamos (int) para garantir que valores NULOS se tornem 0)
        $bonus_totais['bonus_str'] = (int)$soma_bonus['total_str'];
        $bonus_totais['bonus_con'] = (int)$soma_bonus['total_con'];
        $bonus_totais['bonus_dex'] = (int)$soma_bonus['total_dex'];
        $bonus_totais['bonus_int'] = (int)$soma_bonus['total_int'];
        $bonus_totais['dano_min_total'] = (int)$soma_bonus['total_dano_min'];
        $bonus_totais['dano_max_total'] = (int)$soma_bonus['total_dano_max'];
        $bonus_totais['mitigacao_total'] = (int)$soma_bonus['total_mitigacao'];
    }

    // 4. Retorna o array completo com todos os bônus
    return $bonus_totais;
}
function calcular_dano_com_status($dano_base, $combate_id, $atacante_id, $atacante_tipo, $conexao) {
    // Busca modificadores de status do atacante
    $modificadores = get_modificadores_status($combate_id, $atacante_id, $atacante_tipo, $conexao);
    
    $dano_final = $dano_base;
    
    // Aplica modificadores de força (para dano físico)
    if (isset($modificadores['str'])) {
        $dano_final += $modificadores['str'];
    }
    
    // Aplica modificadores de inteligência (para dano mágico)
    if (isset($modificadores['int_stat'])) {
        $dano_final += $modificadores['int_stat'];
    }
    
    return max(1, $dano_final); // Dano mínimo de 1
}
function verificar_resistencia_status($alvo_id, $status_id, $conexao) {
    // Baseado nos atributos do alvo - você pode customizar
    $resistencia_base = 20; // 20% base
    
    // Busca atributos do alvo (exemplo para jogador)
    $sql_atributos = "SELECT con, wis FROM personagens WHERE id = ?";
    $stmt = $conexao->prepare($sql_atributos);
    $stmt->bind_param("i", $alvo_id);
    $stmt->execute();
    $atributos = $stmt->get_result()->fetch_assoc();
    
    if ($atributos) {
        // Constituição aumenta resistência a dano/controle
        $resistencia_base += ($atributos['con'] * 2);
        // Sabedoria aumenta resistência a efeitos mágicos
        $resistencia_base += ($atributos['wis'] * 1);
    }
    
    return min(80, $resistencia_base); // Máximo 80% de resistência
}

?>
