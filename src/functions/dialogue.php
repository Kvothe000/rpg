<?php
// dialogue_system_functions.php - VERSÃO REFATORADA

/**
 * Busca os dados base de um NPC pelo ID.
 *
 * @param int $npc_id ID do NPC.
 * @param mysqli $conexao Objeto de conexão MySQLi.
 * @return array|null Dados do NPC ou null se não encontrado.
 */
function get_npc_data($npc_id, $conexao) {
    $sql = "SELECT * FROM npcs_base WHERE id = ?";
    $stmt = $conexao->prepare($sql);
    if (!$stmt) {
        error_log("Erro ao preparar get_npc_data: " . $conexao->error);
        return null;
    }
    $stmt->bind_param("i", $npc_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $data = $result->fetch_assoc();
    $stmt->close();
    return $data;
}

/**
 * Busca os dados de um jogador pelo ID.
 *
 * @param int $player_id ID do jogador.
 * @param mysqli $conexao Objeto de conexão MySQLi.
 * @return array|null Dados do jogador ou null se não encontrado.
 */
function get_player_data($player_id, $conexao) {
    $sql = "SELECT * FROM personagens WHERE id = ?";
    $stmt = $conexao->prepare($sql);
     if (!$stmt) {
        error_log("Erro ao preparar get_player_data: " . $conexao->error);
        return null;
    }
    $stmt->bind_param("i", $player_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $data = $result->fetch_assoc();
    $stmt->close();
    return $data;
}

/**
 * Calcula o nível de relacionamento textual com base na reputação numérica.
 *
 * @param int $reputacao Valor da reputação.
 * @return string Nível de relacionamento ('inimigo', 'hostil', 'neutro', 'aliado', 'idolo').
 */
function calcular_relacionamento($reputacao) {
    if ($reputacao <= -70) return 'inimigo';
    if ($reputacao <= -30) return 'hostil';
    if ($reputacao < 30) return 'neutro'; // <= 30 causava neutro até 30
    if ($reputacao < 70) return 'aliado'; // <= 70 causava aliado até 70
    return 'idolo';
}

/**
 * Busca a reputação de um jogador com uma facção específica.
 *
 * @param int $player_id ID do jogador.
 * @param string $faccao Nome da facção ('guilda', 'faccao_oculta', etc.).
 * @param mysqli $conexao Objeto de conexão MySQLi.
 * @return int Reputação (padrão 0 se não houver registro).
 */
function get_reputacao_faccao($player_id, $faccao, $conexao) {
    // Corrigido para buscar pela string da facção
    $sql = "SELECT reputacao FROM npc_reputacao WHERE player_id = ? AND faccao_id = ?";
    $stmt = $conexao->prepare($sql);
     if (!$stmt) {
        error_log("Erro ao preparar get_reputacao_faccao: " . $conexao->error);
        return 0;
    }
    $stmt->bind_param("is", $player_id, $faccao);
    $stmt->execute();
    $result = $stmt->get_result();
    $rep = $result->fetch_assoc();
    $stmt->close();
    return $rep ? (int)$rep['reputacao'] : 0;
}

/**
 * Altera a reputação de um jogador com uma facção e atualiza o relacionamento.
 *
 * @param int $player_id ID do jogador.
 * @param string $faccao Nome da facção.
 * @param int $valor Valor a ser adicionado (pode ser negativo).
 * @param mysqli $conexao Objeto de conexão MySQLi.
 */
function alterar_reputacao_faccao($player_id, $faccao, $valor, $conexao) {
    $valor = (int)$valor;
    if ($valor == 0) return; // Não faz nada se o valor for zero

    // Tenta inserir ou atualizar a reputação
    $sql = "INSERT INTO npc_reputacao (player_id, faccao_id, reputacao)
            VALUES (?, ?, ?)
            ON DUPLICATE KEY UPDATE reputacao = reputacao + VALUES(reputacao)";
    $stmt = $conexao->prepare($sql);
     if (!$stmt) {
        error_log("Erro ao preparar alterar_reputacao_faccao (INSERT/UPDATE): " . $conexao->error);
        return;
    }
    $stmt->bind_param("isi", $player_id, $faccao, $valor);
    $stmt->execute();
    $stmt->close();

    // Busca a nova reputação total
    $nova_rep = get_reputacao_faccao($player_id, $faccao, $conexao);
    $relacionamento = calcular_relacionamento($nova_rep);

    // Atualiza o campo relacionamento
    $sql_update = "UPDATE npc_reputacao SET relacionamento = ? WHERE player_id = ? AND faccao_id = ?";
    $stmt_update = $conexao->prepare($sql_update);
     if (!$stmt_update) {
        error_log("Erro ao preparar alterar_reputacao_faccao (UPDATE relacionamento): " . $conexao->error);
        return;
    }
    $stmt_update->bind_param("sis", $relacionamento, $player_id, $faccao);
    $stmt_update->execute();
    $stmt_update->close();
}


/**
 * Busca o diálogo atual (ou o primeiro) para um NPC.
 *
 * @param int $npc_id ID do NPC.
 * @param int|null $dialogo_id_especifico ID do diálogo a buscar (ou null para buscar o primeiro).
 * @param mysqli $conexao Objeto de conexão MySQLi.
 * @return array|null Dados do diálogo ou null se não encontrado.
 */
function get_npc_dialogo_atual($npc_id, $dialogo_id_especifico, $conexao) {
    if ($dialogo_id_especifico !== null) {
        $sql = "SELECT * FROM npc_dialogos WHERE id = ? AND npc_id = ?";
        $stmt = $conexao->prepare($sql);
        if (!$stmt) { error_log("Erro prepare get_npc_dialogo_atual (especifico): ".$conexao->error); return null; }
        $stmt->bind_param("ii", $dialogo_id_especifico, $npc_id);
    } else {
        $sql = "SELECT * FROM npc_dialogos WHERE npc_id = ? ORDER BY id ASC LIMIT 1";
        $stmt = $conexao->prepare($sql);
        if (!$stmt) { error_log("Erro prepare get_npc_dialogo_atual (primeiro): ".$conexao->error); return null; }
        $stmt->bind_param("i", $npc_id);
    }

    $stmt->execute();
    $result = $stmt->get_result();
    $dialogo = $result->fetch_assoc();
    $stmt->close();
    return $dialogo;
}

/**
 * Busca as opções VÁLIDAS do jogador que seguem um diálogo específico do NPC.
 * Verifica os requisitos de cada opção antes de retorná-la.
 *
 * @param array $dialogo_npc_atual Array contendo os dados do diálogo ATUAL do NPC.
 * @param mysqli $conexao Objeto de conexão MySQLi.
 * @return array Lista de opções válidas para o jogador.
 */
function get_opcoes_jogador($dialogo_npc_atual, $conexao) {
    $opcoes_validas = [];
    if (!isset($_SESSION['player_id'])) return $opcoes_validas; // Segurança
    $player_id = $_SESSION['player_id'];

    // Verifica se o diálogo atual do NPC existe, é do tipo 'npc' e aponta para uma próxima opção
    if (!$dialogo_npc_atual || $dialogo_npc_atual['tipo'] !== 'npc' || !isset($dialogo_npc_atual['proximo_dialogo_id'])) {
        return $opcoes_validas; // Retorna array vazio se não houver para onde ir
    }

    $npc_id = $dialogo_npc_atual['npc_id'];
    $primeira_opcao_id = $dialogo_npc_atual['proximo_dialogo_id'];

    // Busca todas as opções potenciais (sequenciais a partir da primeira, do tipo player)
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

    $id_esperado = $primeira_opcao_id; // Começa esperando o ID da primeira opção
    while($opcao = $result->fetch_assoc()) {
        // Verifica se o ID é o esperado (garante sequência) e se é do tipo player
        if ($opcao['id'] == $id_esperado && $opcao['tipo'] == 'player') {
            // Verifica os REQUISITOS da opção antes de adicioná-la
            if (verificar_requisitos_opcao($player_id, $opcao['requisitos'], $conexao)) {
                $opcoes_validas[] = $opcao;
            }
            $id_esperado++; // Espera o próximo ID sequencial
        } else {
            // Se a sequência quebrar ou o tipo for incorreto, para de buscar
            // Isso previne pegar opções de outro bloco de diálogo
            break;
        }
    }
    $stmt->close();

    return $opcoes_validas;
}

/**
 * Função auxiliar para verificar se o jogador cumpre os requisitos de uma opção de diálogo.
 *
 * @param int $player_id ID do jogador.
 * @param string|null $requisitos_json String JSON com os requisitos (ou null).
 * @param mysqli $conexao Objeto de conexão MySQLi.
 * @return bool True se os requisitos forem cumpridos (ou não houver), False caso contrário.
 */
function verificar_requisitos_opcao($player_id, $requisitos_json, $conexao) {
    if (empty($requisitos_json)) {
        return true; // Sem requisitos, sempre válido
    }

    $requisitos = json_decode($requisitos_json, true);
    // Se o JSON for inválido, considera que não há requisitos (ou logar erro)
    if (json_last_error() !== JSON_ERROR_NONE) {
         error_log("JSON de requisitos inválido: " . $requisitos_json);
         return true;
    }

    foreach ($requisitos as $tipo => $valor) {
        switch ($tipo) {
            case 'teste_atributo':
                // A opção deve ser exibida para o jogador clicar e o teste ocorrer.
                // A verificação real do sucesso/falha acontece em npc_interact.php
                break; // Continua para o próximo requisito, se houver

            case 'quest_completa':
                $quest_id_req = (int)$valor;
                if (!function_exists('is_quest_completa') || !is_quest_completa($player_id, $quest_id_req, $conexao)) {
                    return false; // Requisito NÃO cumprido
                }
                break;

            case 'quest_ativa':
                $quest_id_req = (int)$valor;
                $sql_check_ativa = "SELECT 1 FROM player_quests WHERE player_id = ? AND quest_id = ? AND status IN ('aceita', 'em_progresso') LIMIT 1";
                $stmt_check_ativa = $conexao->prepare($sql_check_ativa);
                 if (!$stmt_check_ativa) { error_log("Erro prepare quest_ativa: ".$conexao->error); return false;}
                $stmt_check_ativa->bind_param("ii", $player_id, $quest_id_req);
                $stmt_check_ativa->execute();
                $result_ativa = $stmt_check_ativa->get_result();
                $ativa = $result_ativa->num_rows > 0;
                $stmt_check_ativa->close();
                if (!$ativa) {
                    return false; // Requisito NÃO cumprido
                }
                break;

             case 'nivel_minimo':
                 $player_level = $_SESSION['player_level'] ?? 1; // Usar da sessão é mais rápido
                 if ($player_level < (int)$valor) {
                     return false; // Requisito NÃO cumprido
                 }
                 break;

             case 'reputacao_minima':
                  if (is_array($valor) && isset($valor['faccao']) && isset($valor['valor'])) {
                      $rep_atual = get_reputacao_faccao($player_id, $valor['faccao'], $conexao);
                      if ($rep_atual < (int)$valor['valor']) {
                          return false; // Requisito NÃO cumprido
                      }
                  }
                  break;

            // Adicione outros tipos de requisitos aqui (ex: classe, item no inventário)
            /*
            case 'classe':
                if (!isset($_SESSION['player_classe']) || $_SESSION['player_classe'] != $valor) {
                    return false;
                }
                break;
            */
        }
    }

    return true; // Se passou por todos os requisitos OU não havia requisitos bloqueantes
}


/**
 * Aplica as consequências definidas em um diálogo (NPC ou Player).
 * Usa $_SESSION para armazenar feedback que deve ser exibido após redirecionamentos.
 *
 * @param int $player_id ID do jogador.
 * @param string $trigger_principal Tipo da ação principal (ex: 'iniciar_quest').
 * @param string $valores_json String JSON contendo os valores para o trigger E/OU sub-triggers.
 * @param mysqli $conexao Objeto de conexão MySQLi.
 */
function aplicar_consequencia_dialogo($player_id, $trigger_principal, $valores_json, $conexao) {
    $valores_principais = json_decode($valores_json, true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        error_log("JSON de acao_valor inválido: " . $valores_json);
        return;
    }

    // Processa o trigger principal definido na coluna acao_trigger
    processar_trigger_unico($player_id, $trigger_principal, json_encode($valores_principais), $conexao);

    // Processa sub-triggers que possam estar DENTRO do JSON em acao_valor
    // (Exemplo: {"quest_id": 1, "mudar_reputacao": {"guilda": 5}})
    if (is_array($valores_principais)) {
        foreach ($valores_principais as $sub_trigger => $sub_valores) {
            // Verifica se não é o trigger principal já processado e se parece um trigger
            // (Assumimos que sub-triggers terão valores array ou objeto)
            if ($sub_trigger !== $trigger_principal && (is_array($sub_valores) || is_object($sub_valores))) {
                // Passa o sub_trigger como trigger e seus valores como JSON
                processar_trigger_unico($player_id, $sub_trigger, json_encode($sub_valores), $conexao);
            }
        }
    }
}

/**
 * Função auxiliar para processar um único trigger de consequência.
 *
 * @param int $player_id ID do jogador.
 * @param string $trigger Tipo da ação.
 * @param string $valores_json String JSON com os parâmetros da ação.
 * @param mysqli $conexao Objeto de conexão MySQLi.
 */
function processar_trigger_unico($player_id, $trigger, $valores_json, $conexao) {
    $valores = json_decode($valores_json, true);
     // Se o JSON for inválido OU os valores não forem um array após decode (ex: só {"quest_id": 1}),
     // mas o trigger precisar de um array (ex: mudar_reputacao), pode dar erro.
     // Tratamento básico:
     if (json_last_error() !== JSON_ERROR_NONE) {
          error_log("JSON inválido em processar_trigger_unico para trigger '$trigger': " . $valores_json);
          return;
     }
     // Garante que $valores seja um array para consistência, mesmo que o JSON original fosse simples (ex: '{"quest_id":1}')
     if (!is_array($valores) && $trigger != 'ganhar_ouro') { // ganhar_ouro pode receber só quantidade
         // Para triggers que esperam arrays (como mudar_reputacao), se $valores não for array, loga erro.
         // Para 'iniciar_quest' ou 'entregar_quest', o valor pode ser só o ID, então o $valores pode não ser array.
         if(in_array($trigger, ['mudar_reputacao'])) {
              error_log("Valores inválidos (não array) para trigger '$trigger': " . $valores_json);
              //return; // Pode decidir retornar ou tentar continuar se possível
         }
         // Para quest_id, o valor pode ser direto
         if(($trigger == 'iniciar_quest' || $trigger == 'entregar_quest') && isset($valores['quest_id'])) {
              // Ok, continua
         } elseif($trigger == 'ganhar_ouro' && isset($valores['quantidade'])){
             // Ok, continua
         }
         // Se não for um caso esperado, pode logar ou retornar
         // else { return; }

     }


    switch ($trigger) {
        case 'mudar_reputacao':
            // $valores deve ser um array como {"guilda": 5, "faccao_oculta": -10}
            if (is_array($valores)) {
                foreach ($valores as $faccao => $valor) {
                    if (is_string($faccao) && is_numeric($valor)) {
                        alterar_reputacao_faccao($player_id, $faccao, (int)$valor, $conexao);
                        $_SESSION['feedback_temporario'] = ($_SESSION['feedback_temporario'] ?? '') . "<div class='feedback feedback-info'>Reputação com ".ucfirst($faccao)." alterada em $valor.</div>";
                    }
                }
            } else {
                 error_log("Formato inválido para mudar_reputacao: esperado array, recebido: " . $valores_json);
            }
            break;

        case 'ganhar_ouro':
            $quantidade = isset($valores['quantidade']) ? (int)$valores['quantidade'] : 0;
            if ($quantidade != 0) {
                 $sql_ouro = "UPDATE personagens SET ouro = ouro + ? WHERE id = ?";
                 $stmt_ouro = $conexao->prepare($sql_ouro);
                 if($stmt_ouro){
                      $stmt_ouro->bind_param("ii", $quantidade, $player_id);
                      $stmt_ouro->execute();
                      $stmt_ouro->close();
                      $cor = $quantidade > 0 ? 'var(--status-gold)' : 'var(--status-hp)';
                      $sinal = $quantidade > 0 ? '+' : '';
                      $_SESSION['feedback_temporario'] = ($_SESSION['feedback_temporario'] ?? '') . "<div class='feedback feedback-info'>Ouro alterado em <span style='color:$cor'>{$sinal}{$quantidade}</span>.</div>";
                 } else {
                      error_log("Erro prepare ganhar_ouro: ".$conexao->error);
                 }
            }
            break;

        case 'iniciar_quest':
            $quest_id = isset($valores['quest_id']) ? (int)$valores['quest_id'] : 0;
            if ($quest_id > 0 && function_exists('atribuir_quest')) {
                if (atribuir_quest($player_id, $quest_id, $conexao)) {
                    $sql_q_titulo = "SELECT titulo FROM quests_base WHERE id = ?";
                    $stmt_q_titulo = $conexao->prepare($sql_q_titulo);
                    if($stmt_q_titulo){
                         $stmt_q_titulo->bind_param("i", $quest_id);
                         $stmt_q_titulo->execute();
                         $q_titulo = $stmt_q_titulo->get_result()->fetch_assoc()['titulo'] ?? "desconhecida";
                         $stmt_q_titulo->close();
                         // Usa feedback_quest para destaque especial
                         $_SESSION['feedback_quest'] = "<div class='feedback feedback-success quest-notification'>🎯 <strong>Nova Missão Desbloqueada!</strong><br>Missão: <em>" . htmlspecialchars($q_titulo) . "</em> foi adicionada ao seu diário!</div>";
                    }
                } else {
                     // Feedback se a quest não pôde ser atribuída (já tem, requisito não cumprido?)
                     $_SESSION['feedback_temporario'] = ($_SESSION['feedback_temporario'] ?? '') . "<div class='feedback feedback-warning'>Não foi possível iniciar a missão ID $quest_id (verifique pré-requisitos ou se já a possui).</div>";
                }
            }
            break;

        case 'entregar_quest':
            $quest_id_entregar = isset($valores['quest_id']) ? (int)$valores['quest_id'] : 0;
            if ($quest_id_entregar > 0 && function_exists('entregar_quest')) {
                $resultado_entrega = entregar_quest($player_id, $quest_id_entregar, $conexao);
                // Armazena a mensagem de resultado (sucesso/erro + recompensas + level up)
                if ($resultado_entrega['sucesso']) {
                     $_SESSION['feedback_geral'] = "<div class='feedback feedback-success'>{$resultado_entrega['mensagem']}</div>";
                     // Verifica level up (entregar_quest já faz isso, mas podemos adicionar msg aqui)
                     // $player_data_atualizado = get_player_data($player_id, $conexao);
                     // if (function_exists('verificar_level_up')) { ... }
                } else {
                     $_SESSION['feedback_geral'] = "<div class='feedback feedback-error'>{$resultado_entrega['mensagem']}</div>";
                }
            }
            break;

        // Adicione outros triggers aqui...
        // case 'dar_item': ...
        // case 'mudar_estado_npc': ...

        default:
             // Se o trigger não for reconhecido, pode ser um trigger dentro do JSON
             // que já foi tratado no loop anterior em aplicar_consequencia_dialogo.
             // Ou pode ser um erro. Podemos logar se quisermos.
             // error_log("Trigger de diálogo não reconhecido: " . $trigger);
             break;
    }
}


/**
 * Busca o histórico de escolhas de diálogo de um jogador com um NPC.
 * (Função mantida para possível uso futuro)
 *
 * @param int $player_id ID do jogador.
 * @param int $npc_id ID do NPC.
 * @param mysqli $conexao Objeto de conexão MySQLi.
 * @param int $limite Número máximo de entradas a retornar.
 * @return mysqli_result Conjunto de resultados.
 */
function get_escolhas_anteriores($player_id, $npc_id, $conexao, $limite = 5) {
    $sql = "SELECT pd.escolha_feita, pd.data_escolha
            FROM player_escolhas_dialogo pd
            JOIN npc_dialogos nd ON pd.dialogo_id = nd.id
            WHERE pd.player_id = ? AND nd.npc_id = ?
            ORDER BY pd.data_escolha DESC
            LIMIT ?";
    $stmt = $conexao->prepare($sql);
    if(!$stmt){ error_log("Erro prepare get_escolhas_anteriores: ".$conexao->error); return false; }
    $stmt->bind_param("iii", $player_id, $npc_id, $limite);
    $stmt->execute();
    $result = $stmt->get_result();
    $stmt->close(); // Fechar o statement aqui
    return $result;
}

?>