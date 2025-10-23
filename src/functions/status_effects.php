<?php
// status_effects_functions.php

function aplicar_status_effect($combate_id, $alvo_id, $alvo_tipo, $status_id, $conexao, $intensidade = 1) {
    // Busca dados do status
    $sql_status = "SELECT * FROM status_effects_base WHERE id = ?";
    $stmt = $conexao->prepare($sql_status);
    $stmt->bind_param("i", $status_id);
    $stmt->execute();
    $status = $stmt->get_result()->fetch_assoc();
    
    if (!$status) return false;
    
    // Verifica se já tem o status
    $sql_check = "SELECT id, intensidade FROM combate_status_ativos 
                  WHERE combate_id = ? AND alvo_id = ? AND alvo_tipo = ? AND status_id = ?";
    $stmt = $conexao->prepare($sql_check);
    $stmt->bind_param("iisi", $combate_id, $alvo_id, $alvo_tipo, $status_id);
    $stmt->execute();
    $status_existente = $stmt->get_result()->fetch_assoc();
    
    if ($status_existente) {
        // Atualiza intensidade e reinicia duração
        $nova_intensidade = $status_existente['intensidade'] + $intensidade;
        $conexao->query("UPDATE combate_status_ativos SET 
                        duracao_restante = {$status['duracao_base']},
                        intensidade = $nova_intensidade
                        WHERE id = {$status_existente['id']}");
    } else {
        // Insere novo status
        $sql_insert = "INSERT INTO combate_status_ativos (combate_id, alvo_id, alvo_tipo, status_id, duracao_restante, intensidade) 
                       VALUES (?, ?, ?, ?, ?, ?)";
        $stmt = $conexao->prepare($sql_insert);
        $stmt->bind_param("iisiii", $combate_id, $alvo_id, $alvo_tipo, $status_id, $status['duracao_base'], $intensidade);
        $stmt->execute();
    }
    
    return $status;
}

function processar_status_effects_turno($combate_id, $conexao) {
    $efeitos_turno = [];
    
    // Busca todos os status ativos neste combate
    $sql_status = "SELECT csa.*, seb.* 
                   FROM combate_status_ativos csa
                   JOIN status_effects_base seb ON csa.status_id = seb.id
                   WHERE csa.combate_id = ?";
    $stmt = $conexao->prepare($sql_status);
    $stmt->bind_param("i", $combate_id);
    $stmt->execute();
    $status_ativos = $stmt->get_result();
    
    while ($status = $status_ativos->fetch_assoc()) {
        // Aplica efeito do status
        $efeito = aplicar_efeito_status($status, $conexao);
        if ($efeito) {
            $efeitos_turno[] = $efeito;
        }
        
        // Reduz duração
        $nova_duracao = $status['duracao_restante'] - 1;
        
        if ($nova_duracao <= 0) {
            // Remove status expirado
            $conexao->query("DELETE FROM combate_status_ativos WHERE id = {$status['id']}");
            $efeitos_turno[] = [
                'tipo' => 'remocao',
                'alvo_id' => $status['alvo_id'],
                'alvo_tipo' => $status['alvo_tipo'],
                'status_nome' => $status['nome'],
                'mensagem' => "{$status['icone']} {$status['nome']} expirou"
            ];
        } else {
            // Atualiza duração
            $conexao->query("UPDATE combate_status_ativos SET duracao_restante = $nova_duracao WHERE id = {$status['id']}");
        }
    }
    
    return $efeitos_turno;
}

function aplicar_efeito_status($status, $conexao) {
    $efeito = null;
    
    switch ($status['tipo']) {
        case 'dano':
            // Aplica dano por turno (Queimado, Envenenado)
            $dano_total = $status['valor_efeito'] * $status['intensidade'];
            
            if ($status['alvo_tipo'] == 'player') {
                $conexao->query("UPDATE personagens SET hp_atual = GREATEST(0, hp_atual - $dano_total) WHERE id = {$status['alvo_id']}");
            } else {
                // Para monstros - você precisaria de uma tabela de monstros_combate
                // $conexao->query("UPDATE monstros_combate SET hp_atual = GREATEST(0, hp_atual - $dano_total) WHERE id = {$status['alvo_id']}");
            }
            
            $efeito = [
                'tipo' => 'dano',
                'alvo_id' => $status['alvo_id'],
                'alvo_tipo' => $status['alvo_tipo'],
                'dano' => $dano_total,
                'status_nome' => $status['nome'],
                'mensagem' => "{$status['icone']} {$status['nome']} causou $dano_total de dano!"
            ];
            break;
            
        case 'buff':
        case 'debuff':
            // Buffs/Debuffs são aplicados nos cálculos de combate
            // Eles não causam efeito imediato, mas modificam atributos
            $efeito = [
                'tipo' => 'modificador',
                'alvo_id' => $status['alvo_id'],
                'alvo_tipo' => $status['alvo_tipo'],
                'atributo' => $status['atributo_afetado'],
                'modificador' => $status['modificador'] * $status['intensidade'],
                'status_nome' => $status['nome'],
                'mensagem' => "{$status['icone']} {$status['nome']} ativo"
            ];
            break;
            
        case 'controle':
            // Efeitos de controle (Atordoado, Congelado)
            if ($status['nome'] == 'Atordoado') {
                $efeito = [
                    'tipo' => 'controle',
                    'alvo_id' => $status['alvo_id'],
                    'alvo_tipo' => $status['alvo_tipo'],
                    'status_nome' => $status['nome'],
                    'perde_turno' => true,
                    'mensagem' => "{$status['icone']} {$status['nome']}! Perdeu o turno!"
                ];
            } elseif ($status['nome'] == 'Congelado') {
                $chance_perder_turno = $status['valor_efeito'];
                $perde_turno = (rand(1, 100) <= $chance_perder_turno);
                
                $efeito = [
                    'tipo' => 'controle',
                    'alvo_id' => $status['alvo_id'],
                    'alvo_tipo' => $status['alvo_tipo'],
                    'status_nome' => $status['nome'],
                    'perde_turno' => $perde_turno,
                    'mensagem' => $perde_turno ? "{$status['icone']} Congelado! Perdeu o turno!" : "{$status['icone']} Congelado, mas resistiu!"
                ];
            }
            break;
    }
    
    return $efeito;
}

function get_status_ativos($combate_id, $alvo_id, $alvo_tipo, $conexao) {
    $sql = "SELECT seb.*, csa.duracao_restante, csa.intensidade
            FROM combate_status_ativos csa
            JOIN status_effects_base seb ON csa.status_id = seb.id
            WHERE csa.combate_id = ? AND csa.alvo_id = ? AND csa.alvo_tipo = ?
            ORDER BY seb.tipo, seb.nome";
    
    $stmt = $conexao->prepare($sql);
    $stmt->bind_param("iis", $combate_id, $alvo_id, $alvo_tipo);
    $stmt->execute();
    
    return $stmt->get_result();
}

function remover_status($combate_id, $alvo_id, $alvo_tipo, $status_id, $conexao) {
    $conexao->query("DELETE FROM combate_status_ativos 
                    WHERE combate_id = $combate_id 
                    AND alvo_id = $alvo_id 
                    AND alvo_tipo = '$alvo_tipo'
                    AND status_id = $status_id");
    
    return $conexao->affected_rows > 0;
}

function get_modificadores_status($combate_id, $alvo_id, $alvo_tipo, $conexao) {
    $modificadores = [];
    
    $status_ativos = get_status_ativos($combate_id, $alvo_id, $alvo_tipo, $conexao);
    
    while ($status = $status_ativos->fetch_assoc()) {
        if ($status['modificador'] != 0 && $status['atributo_afetado'] != 'velocidade') {
            $atributo = $status['atributo_afetado'];
            $modificador = $status['modificador'] * $status['intensidade'];
            
            if (!isset($modificadores[$atributo])) {
                $modificadores[$atributo] = 0;
            }
            $modificadores[$atributo] += $modificador;
        }
    }
    
    return $modificadores;
}
?>