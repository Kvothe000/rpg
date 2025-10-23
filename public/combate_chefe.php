<?php
// combate_chefe.php - EXTENSÃO DO SEU COMBATE
session_start();
include_once 'db_connect.php';
include_once 'game_logic.php';

if (!isset($_SESSION['dungeon_atual'])) {
    header('Location: mapa.php');
    exit;
}

$dungeon = $_SESSION['dungeon_atual'];
$chefe = $dungeon['chefe'];

// ✅ USAR SEU SISTEMA DE COMBATE EXISTENTE, MAS COM DADOS DO CHEFE
if (!isset($_SESSION['combate_ativo'])) {
    $monstro_dados = [
        'id_base' => 999, // ID especial para chefes
        'nome' => $chefe['titulo'],
        'hp_max' => $chefe['vida'],
        'hp_atual' => $chefe['vida'],
        'str' => $chefe['dano'] / 2,
        'dex' => 15,
        'con' => 20,
        'dano_min' => $chefe['dano'] * 0.8,
        'dano_max' => $chefe['dano'] * 1.2,
        'ouro_recompensa' => $dungeon['recompensas']['ouro'] * 3,
        'xp_recompensa' => $dungeon['recompensas']['xp'] * 3,
        'fases' => $chefe['fases']
    ];
    
    // ✅ INICIALIZAR COMBATE COM CHEFE (usando seu sistema existente)
    $_SESSION['combate_ativo'] = [
        'monstro' => $monstro_dados,
        'turno_atual' => 1,
        'dado_escalada' => 0,
        'jogador_hp_atual' => $player_data_base['hp_atual'],
        'jogador_mana_atual' => $player_data_base['mana_atual'],
        'skills_aprendidas' => $skills_prontas,
        'eh_chefe' => true,
        'fase_chefe' => 1
    ];
}

// ✅ O RESTO DO SEU CÓDIGO DE COMBATE PERMANECE IGUAL!
// Apenas adicione verificações para "eh_chefe" onde necessário