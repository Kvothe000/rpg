<?php
// dialogue_system_functions.php - VERSÃO COMPLETA E TESTADA

function get_npc_data($npc_id, $conexao) {
    $sql = "SELECT * FROM npcs_base WHERE id = ?";
    $stmt = $conexao->prepare($sql);
    $stmt->bind_param("i", $npc_id);
    $stmt->execute();
    return $stmt->get_result()->fetch_assoc();
}

function get_player_data($player_id, $conexao) {
    $sql = "SELECT * FROM personagens WHERE id = ?";
    $stmt = $conexao->prepare($sql);
    $stmt->bind_param("i", $player_id);
    $stmt->execute();
    return $stmt->get_result()->fetch_assoc();
}

function calcular_relacionamento($reputacao) {
    if ($reputacao <= -70) return 'inimigo';
    if ($reputacao <= -30) return 'hostil';
    if ($reputacao <= 30) return 'neutro';
    if ($reputacao <= 70) return 'aliado';
    return 'idolo';
}

function get_reputacao_faccao($player_id, $faccao, $conexao) {
    $sql = "SELECT reputacao FROM npc_reputacao WHERE player_id = ? AND faccao_id = ?";
    $stmt = $conexao->prepare($sql);
    $stmt->bind_param("is", $player_id, $faccao);
    $stmt->execute();
    $result = $stmt->get_result();
    
    return $result->num_rows > 0 ? $result->fetch_assoc()['reputacao'] : 0;
}

function get_npc_dialogo_atual($npc_id, $dialogo_id_atual, $conexao) {
    if ($dialogo_id_atual) {
        // Buscar diálogo específico
        $sql = "SELECT * FROM npc_dialogos WHERE id = ? AND npc_id = ?";
        $stmt = $conexao->prepare($sql);
        $stmt->bind_param("ii", $dialogo_id_atual, $npc_id);
    } else {
        // Buscar PRIMEIRO diálogo do NPC
        $sql = "SELECT * FROM npc_dialogos WHERE npc_id = ? ORDER BY id LIMIT 1";
        $stmt = $conexao->prepare($sql);
        $stmt->bind_param("i", $npc_id);
    }
    
    $stmt->execute();
    return $stmt->get_result()->fetch_assoc();
}

function get_opcoes_jogador($dialogo_id, $npc_id, $conexao) {
    // Buscar todas as opções do jogador que partem deste diálogo
    $sql = "SELECT * FROM npc_dialogos WHERE proximo_dialogo_id = ? AND npc_id = ? AND tipo = 'player' ORDER BY id";
    $stmt = $conexao->prepare($sql);
    $stmt->bind_param("ii", $dialogo_id, $npc_id);
    $stmt->execute();
    
    $opcoes = [];
    $result = $stmt->get_result();
    while($opcao = $result->fetch_assoc()) {
        $opcoes[] = $opcao;
    }
    
    return $opcoes;
}

function processar_escolha_dialogo($player_id, $dialogo_id, $conexao) {
    // Buscar a escolha do jogador
    $sql = "SELECT * FROM npc_dialogos WHERE id = ? AND tipo = 'player'";
    $stmt = $conexao->prepare($sql);
    $stmt->bind_param("i", $dialogo_id);
    $stmt->execute();
    $escolha = $stmt->get_result()->fetch_assoc();
    
    if (!$escolha) return false;
    
    // Registrar escolha
    $sql_registrar = "INSERT INTO player_escolhas_dialogo (player_id, dialogo_id, escolha_feita) VALUES (?, ?, ?)";
    $stmt = $conexao->prepare($sql_registrar);
    $stmt->bind_param("iis", $player_id, $dialogo_id, $escolha['dialogo_texto']);
    $stmt->execute();
    
    // Aplicar consequências imediatas da escolha
    if ($escolha['acao_trigger'] && $escolha['acao_valor']) {
        aplicar_consequencia_dialogo($player_id, $escolha['acao_trigger'], $escolha['acao_valor'], $conexao);
    }
    
    return $escolha;
}

function aplicar_consequencia_dialogo($player_id, $trigger, $valores_json, $conexao) {
    $valores = json_decode($valores_json, true);
    if (!$valores) return;
    
    switch ($trigger) {
        case 'mudar_reputacao':
            foreach ($valores as $faccao => $valor) {
                alterar_reputacao_faccao($player_id, $faccao, $valor, $conexao);
            }
            break;
            
        case 'ganhar_ouro':
            $quantidade = $valores['quantidade'] ?? 0;
            $conexao->query("UPDATE personagens SET ouro = ouro + $quantidade WHERE id = $player_id");
            break;
            
        case 'iniciar_quest':
            $quest_id = $valores['quest_id'] ?? 0;
            // Verificar se a tabela existe
            $table_check = $conexao->query("SHOW TABLES LIKE 'player_quests'");
            if ($table_check->num_rows > 0 && $quest_id > 0) {
                $sql_quest = "INSERT IGNORE INTO player_quests (player_id, quest_id, progresso) VALUES (?, ?, 0)";
                $stmt = $conexao->prepare($sql_quest);
                $stmt->bind_param("ii", $player_id, $quest_id);
                $stmt->execute();
            }
            break;
            
        case 'desbloquear_habilidade':
            $skill_id = $valores['skill_id'] ?? 0;
            $sql_skill = "INSERT IGNORE INTO personagem_skills (id_personagem, id_skill_base, skill_level) VALUES (?, ?, 1)";
            $stmt = $conexao->prepare($sql_skill);
            $stmt->bind_param("ii", $player_id, $skill_id);
            $stmt->execute();
            break;
    }
}

function alterar_reputacao_faccao($player_id, $faccao, $valor, $conexao) {
    $npc_id = ($faccao == 'guilda') ? 1 : 2;
    
    $sql = "INSERT INTO npc_reputacao (player_id, npc_id, faccao_id, reputacao) 
            VALUES (?, ?, ?, ?) 
            ON DUPLICATE KEY UPDATE reputacao = reputacao + VALUES(reputacao)";
    
    $stmt = $conexao->prepare($sql);
    $stmt->bind_param("iisi", $player_id, $npc_id, $faccao, $valor);
    $stmt->execute();
    
    // Atualizar nível de relacionamento
    $nova_rep = get_reputacao_faccao($player_id, $faccao, $conexao);
    $relacionamento = calcular_relacionamento($nova_rep);
    
    $sql_update = "UPDATE npc_reputacao SET relacionamento = ? WHERE player_id = ? AND faccao_id = ?";
    $stmt = $conexao->prepare($sql_update);
    $stmt->bind_param("sis", $relacionamento, $player_id, $faccao);
    $stmt->execute();
}

function get_escolhas_anteriores($player_id, $npc_id, $conexao, $limite = 5) {
    $sql = "SELECT pd.*, nd.dialogo_texto 
            FROM player_escolhas_dialogo pd
            JOIN npc_dialogos nd ON pd.dialogo_id = nd.id
            WHERE pd.player_id = ? AND nd.npc_id = ?
            ORDER BY pd.data_escolha DESC 
            LIMIT ?";
    
    $stmt = $conexao->prepare($sql);
    $stmt->bind_param("iii", $player_id, $npc_id, $limite);
    $stmt->execute();
    
    return $stmt->get_result();
}
?>