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

// Em dialogue_system_functions.php

// Função ATUALIZADA para verificar requisitos antes de retornar opções
function get_opcoes_jogador($dialogo_npc_atual, $conexao) {
    $opcoes_validas = [];
    $player_id = $_SESSION['player_id']; // Precisamos do ID do jogador para verificar requisitos

    if (!$dialogo_npc_atual || !isset($dialogo_npc_atual['proximo_dialogo_id']) || $dialogo_npc_atual['tipo'] !== 'npc') {
        return $opcoes_validas;
    }

    $npc_id = $dialogo_npc_atual['npc_id'];
    $primeira_opcao_id = $dialogo_npc_atual['proximo_dialogo_id'];

    // Busca todas as opções potenciais (sequenciais do tipo player)
    $sql = "SELECT * FROM npc_dialogos
            WHERE npc_id = ? AND id >= ? AND tipo = 'player'
            ORDER BY id ASC";
    $stmt = $conexao->prepare($sql);
    if ($stmt === false) {
        error_log("Erro ao preparar a query em get_opcoes_jogador: " . $conexao->error);
        return $opcoes_validas;
    }
    $stmt->bind_param("ii", $npc_id, $primeira_opcao_id);
    $stmt->execute();
    $result = $stmt->get_result();

    $id_anterior = $primeira_opcao_id - 1;
    while($opcao = $result->fetch_assoc()) {
        if ($opcao['id'] == $id_anterior + 1 && $opcao['tipo'] == 'player') {
            // Verifica os REQUISITOS da opção antes de adicioná-la
            if (verificar_requisitos_opcao($player_id, $opcao['requisitos'], $conexao)) {
                $opcoes_validas[] = $opcao;
            }
            $id_anterior = $opcao['id'];
        } else {
            break; // Para se a sequência quebrar
        }
    }
    $stmt->close();

    return $opcoes_validas;
}


// --- NOVA FUNÇÃO AUXILIAR PARA VERIFICAR REQUISITOS DA OPÇÃO ---
function verificar_requisitos_opcao($player_id, $requisitos_json, $conexao) {
    if (empty($requisitos_json)) {
        return true; // Sem requisitos, sempre válido
    }

    $requisitos = json_decode($requisitos_json, true);
    if (!$requisitos) {
        return true; // JSON inválido ou vazio, considera válido por segurança
    }

    foreach ($requisitos as $tipo => $valor) {
        switch ($tipo) {
            case 'teste_atributo':
                // Testes de atributo são tratados na página npc_interact.php,
                // então a opção deve ser exibida. Retorna true.
                return true;
                break; // Redundante, mas para clareza

            case 'quest_completa':
                $quest_id_req = (int)$valor;
                if (!function_exists('is_quest_completa') || !is_quest_completa($player_id, $quest_id_req, $conexao)) {
                    return false; // Requisito não cumprido
                }
                break;

            case 'quest_ativa': // Exemplo: Opção só aparece se a quest X estiver ativa
                $quest_id_req = (int)$valor;
                $sql_check_ativa = "SELECT id FROM player_quests WHERE player_id = ? AND quest_id = ? AND status IN ('aceita', 'em_progresso')";
                $stmt_check_ativa = $conexao->prepare($sql_check_ativa);
                $stmt_check_ativa->bind_param("ii", $player_id, $quest_id_req);
                $stmt_check_ativa->execute();
                if ($stmt_check_ativa->get_result()->num_rows == 0) {
                     return false; // Requisito não cumprido
                }
                $stmt_check_ativa->close();
                break;

             case 'nivel_minimo': // Exemplo: Opção só aparece se tiver nível X
                 $player_level = $_SESSION['player_level'] ?? 1; // Pega da sessão se disponível
                 if ($player_level < (int)$valor) {
                     return false;
                 }
                 break;

             case 'reputacao_minima': // Exemplo: {"reputacao_minima": {"faccao": "guilda", "valor": 20}}
                  if (isset($valor['faccao']) && isset($valor['valor'])) {
                      $rep_atual = get_reputacao_faccao($player_id, $valor['faccao'], $conexao);
                      if ($rep_atual < (int)$valor['valor']) {
                          return false;
                      }
                  }
                  break;

            // Adicione outros tipos de requisitos aqui (item no inventário, classe específica, etc.)
        }
    }

    return true; // Se passou por todos os requisitos
}

// Restante das suas funções...
// get_npc_data, get_npc_dialogo_atual, etc.

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

// Função ATUALIZADA para incluir 'entregar_quest'
function aplicar_consequencia_dialogo($player_id, $trigger, $valores_json, $conexao) {
    $valores = json_decode($valores_json, true);
    if (!$valores) return;

    // Usar um loop se houver múltiplos triggers no JSON (como fizemos no Sucesso da Persuasão)
    // Se for apenas uma string, tratamos como antes
    if (is_string($trigger)) {
        $triggers_a_processar = [$trigger => $valores];
    } elseif (is_array($trigger)) { // Assume que $trigger é um array associativo { 'trigger1': {valores1}, 'trigger2': {valores2} }
         // Ajuste necessário se o formato do JSON for diferente, ex: { "iniciar_quest": {"quest_id": 1}, "mudar_reputacao": {"guilda": 5} }
         // Neste caso, $trigger seria 'iniciar_quest' e $valores seria {"quest_id": 1}
         // A estrutura que usamos no INSERT foi: 'acao_trigger'='iniciar_quest', 'acao_valor'='{"quest_id": 1, "mudar_reputacao": {"guilda": 5}}'
         // Vamos adaptar para lidar com o JSON em acao_valor contendo múltiplos triggers
         $triggers_a_processar = $valores; // Assume que os valores são os triggers
         $trigger_principal_ignorado = $trigger; // Ignora o trigger principal se os valores contêm os triggers
    } else {
        $triggers_a_processar = [];
    }


    // Processa cada trigger encontrado
    //foreach ($triggers_a_processar as $trigger_atual => $valores_atuais) {
     // Simplificando por agora - assume trigger único ou múltiplos dentro de acao_valor
     $trigger_atual = $trigger; // Usa o trigger principal passado
     $valores_atuais = $valores; // Usa os valores principais

     // Processa o trigger principal
     processar_trigger_unico($player_id, $trigger_atual, json_encode($valores_atuais), $conexao);

     // Se os valores também contêm triggers (como no caso do sucesso da persuasão)
     if (is_array($valores_atuais)) {
         foreach ($valores_atuais as $sub_trigger => $sub_valores) {
              // Verifica se não é o trigger principal já processado e se parece um trigger
              if ($sub_trigger !== $trigger_atual && is_array($sub_valores)) {
                   processar_trigger_unico($player_id, $sub_trigger, json_encode($sub_valores), $conexao);
              }
         }
     }
    //}
}
// Função auxiliar para processar um único trigger (refatorado de aplicar_consequencia_dialogo)
function processar_trigger_unico($player_id, $trigger, $valores_json, $conexao) {
     $valores = json_decode($valores_json, true);
     if (!$valores) return;

    switch ($trigger) {
        case 'mudar_reputacao':
            // Permitir mudar reputação para múltiplas facções de uma vez
            if (isset($valores['guilda'])) {
                 alterar_reputacao_faccao($player_id, 'guilda', (int)$valores['guilda'], $conexao);
            }
            if (isset($valores['faccao_oculta'])) {
                 alterar_reputacao_faccao($player_id, 'faccao_oculta', (int)$valores['faccao_oculta'], $conexao);
            }
             // Adicione outras facções se necessário
            break;

        case 'ganhar_ouro':
            $quantidade = isset($valores['quantidade']) ? (int)$valores['quantidade'] : 0;
            if ($quantidade != 0) { // Evita query desnecessária
                 $sql_ouro = "UPDATE personagens SET ouro = ouro + ? WHERE id = ?";
                 $stmt_ouro = $conexao->prepare($sql_ouro);
                 $stmt_ouro->bind_param("ii", $quantidade, $player_id);
                 $stmt_ouro->execute();
            }
            break;

        case 'iniciar_quest':
            $quest_id = isset($valores['quest_id']) ? (int)$valores['quest_id'] : 0;
            if ($quest_id > 0 && function_exists('atribuir_quest')) {
                // Adicionar feedback na sessão para ser exibido na próxima página
                if (atribuir_quest($player_id, $quest_id, $conexao)) {
                    // Buscar título da quest para a mensagem
                    $sql_q_titulo = "SELECT titulo FROM quests_base WHERE id = ?";
                    $stmt_q_titulo = $conexao->prepare($sql_q_titulo);
                    $stmt_q_titulo->bind_param("i", $quest_id);
                    $stmt_q_titulo->execute();
                    $q_titulo = $stmt_q_titulo->get_result()->fetch_assoc()['titulo'] ?? "desconhecida";
                    $_SESSION['feedback_quest'] = "<div class='feedback feedback-success quest-notification'>🎯 <strong>Nova Missão Desbloqueada!</strong><br>Missão: <em>" . htmlspecialchars($q_titulo) . "</em> foi adicionada ao seu diário!</div>";
                }
            }
            break;

        // --- NOVO CASE PARA ENTREGAR QUEST ---
        case 'entregar_quest':
            $quest_id_entregar = isset($valores['quest_id']) ? (int)$valores['quest_id'] : 0;
            if ($quest_id_entregar > 0 && function_exists('entregar_quest')) {
                // A função entregar_quest já retorna a mensagem de sucesso/erro
                $resultado_entrega = entregar_quest($player_id, $quest_id_entregar, $conexao);
                // Armazenar a mensagem na sessão para exibir na próxima página
                if ($resultado_entrega['sucesso']) {
                     $_SESSION['feedback_geral'] = "<div class='feedback feedback-success'>{$resultado_entrega['mensagem']}</div>";
                     // Opcional: Verificar level up aqui também, pois entregar_quest pode dar XP
                     $player_data_atualizado = get_player_data($player_id, $conexao); // Usar a função get_player_data
                     if (function_exists('verificar_level_up')) {
                         $msg_lvl_up = verificar_level_up($player_id, $player_data_atualizado, $conexao);
                         if (!empty($msg_lvl_up)) {
                              $_SESSION['feedback_geral'] .= $msg_lvl_up; // Adiciona mensagem de level up
                         }
                     }
                } else {
                     $_SESSION['feedback_geral'] = "<div class='feedback feedback-error'>{$resultado_entrega['mensagem']}</div>";
                }
            }
            break;

        case 'desbloquear_habilidade': // Mantido como estava
            // ...
            break;

        // Adicione outros cases conforme necessário
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