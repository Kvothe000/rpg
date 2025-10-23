<?php
// setup_ultimate_abilities.php
include 'db_connect.php';

echo "<h2>Configurando Sistema de Ultimate Abilities</h2>";

// Criar tabelas
$sql_tables = "
CREATE TABLE IF NOT EXISTS ultimate_abilities_base (
    id INT PRIMARY KEY AUTO_INCREMENT,
    nome VARCHAR(100) NOT NULL,
    descricao TEXT NOT NULL,
    custo_mana INT NOT NULL,
    cooldown_turnos INT NOT NULL,
    tipo ENUM('dano', 'cura', 'controle', 'suporte', 'transformacao') NOT NULL,
    alvo ENUM('unico', 'area', 'self', 'aliados') NOT NULL,
    efeito_principal JSON NOT NULL,
    efeito_secundario JSON NULL,
    condicoes_desbloqueio JSON NOT NULL,
    nivel_requerido INT DEFAULT 15,
    classe_req VARCHAR(50) NULL,
    icone VARCHAR(10) NOT NULL,
    cor_efeito VARCHAR(7) DEFAULT '#FFFFFF',
    data_criacao TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS player_ultimate_abilities (
    id INT PRIMARY KEY AUTO_INCREMENT,
    player_id INT NOT NULL,
    ultimate_id INT NOT NULL,
    desbloqueada BOOLEAN DEFAULT FALSE,
    cooldown_restante INT DEFAULT 0,
    usos_totais INT DEFAULT 0,
    data_desbloqueio TIMESTAMP NULL,
    data_ultimo_uso TIMESTAMP NULL,
    FOREIGN KEY (player_id) REFERENCES personagens(id),
    FOREIGN KEY (ultimate_id) REFERENCES ultimate_abilities_base(id),
    UNIQUE KEY unique_player_ultimate (player_id, ultimate_id)
);
";

if ($conexao->multi_query($sql_tables)) {
    echo "✅ Tabelas criadas/verificadas!<br>";
    while ($conexao->more_results() && $conexao->next_result()) {
        if ($result = $conexao->store_result()) $result->free();
    }
}

// Ultimate Abilities Base
$ultimate_abilities = [
    // GUERREIRO
    [
        'nome' => 'Fúria do Titã',
        'descricao' => 'Libera poder titânico, causando dano massivo e atordoando todos os inimigos',
        'custo_mana' => 100,
        'cooldown_turnos' => 8,
        'tipo' => 'dano',
        'alvo' => 'area',
        'efeito_principal' => '{"dano_base": 200, "multiplicador": 3.0, "dano_extra": 50, "duracao": 2}',
        'efeito_secundario' => '{"status_aplicado": 4, "chance": 80}',
        'condicoes_desbloqueio' => '{"nivel": 15, "classe": "Guerreiro", "habilidades_aprendidas": 10}',
        'nivel_requerido' => 15,
        'classe_req' => 'Guerreiro',
        'icone' => '👹',
        'cor_efeito' => '#FF6B35'
    ],
    [
        'nome' => 'Parede Indestrutível',
        'descricao' => 'Cria uma barreira impenetrável que absorve todo o dano por 2 turnos',
        'custo_mana' => 80,
        'cooldown_turnos' => 6,
        'tipo' => 'suporte',
        'alvo' => 'self',
        'efeito_principal' => '{"protecao": 100, "duracao": 2, "mitigacao_extra": 25}',
        'efeito_secundario' => '{"cura_turno": 15, "duracao_cura": 3}',
        'condicoes_desbloqueio' => '{"nivel": 12, "classe": "Guerreiro", "hp_maximo": 1500}',
        'nivel_requerido' => 12,
        'classe_req' => 'Guerreiro',
        'icone' => '🛡️',
        'cor_efeito' => '#3A86FF'
    ],

    // MAGO
    [
        'nome' => 'Tempestade Arcana',
        'descricao' => 'Invoca uma tempestade de energia arcana que devasta todos os inimigos',
        'custo_mana' => 150,
        'cooldown_turnos' => 10,
        'tipo' => 'dano',
        'alvo' => 'area',
        'efeito_principal' => '{"dano_base": 300, "multiplicador": 4.0, "ignora_defesa": true}',
        'efeito_secundario' => '{"status_aplicado": 1, "intensidade": 3, "duracao": 4}',
        'condicoes_desbloqueio' => '{"nivel": 18, "classe": "Mago", "mana_maximo": 800}',
        'nivel_requerido' => 18,
        'classe_req' => 'Mago',
        'icone' => '🌩️',
        'cor_efeito' => '#8A2BE2'
    ],
    [
        'nome' => 'Controle Temporal',
        'descricao' => 'Manipula o fluxo do tempo, concedendo um turno extra',
        'custo_mana' => 120,
        'cooldown_turnos' => 12,
        'tipo' => 'suporte',
        'alvo' => 'self',
        'efeito_principal' => '{"turno_extra": true, "reset_cooldown": true}',
        'efeito_secundario' => '{"buff_atributos": {"dex": 10, "int_stat": 10}, "duracao": 3}',
        'condicoes_desbloqueio' => '{"nivel": 20, "classe": "Mago", "inteligencia": 50}',
        'nivel_requerido' => 20,
        'classe_req' => 'Mago',
        'icone' => '⏰',
        'cor_efeito' => '#FFD166'
    ],

    // SACERDOTE
    [
        'nome' => 'Renascimento da Fênix',
        'descricao' => 'Ressuscita com HP e Mana completos quando morto',
        'custo_mana' => 200,
        'cooldown_turnos' => 15,
        'tipo' => 'cura',
        'alvo' => 'self',
        'efeito_principal' => '{"ressuscitar": true, "hp_restaurado": 100, "mana_restaurado": 100}',
        'efeito_secundario' => '{"buff_atributos": {"con": 15, "wis": 15}, "duracao": 5}',
        'condicoes_desbloqueio' => '{"nivel": 25, "classe": "Sacerdote", "mortes": 5}',
        'nivel_requerido' => 25,
        'classe_req' => 'Sacerdote',
        'icone' => '🔥',
        'cor_efeito' => '#EF476F'
    ],
    [
        'nome' => 'Chuva de Cura Celestial',
        'descricao' => 'Cura massiva para todos os aliados e remove efeitos negativos',
        'custo_mana' => 120,
        'cooldown_turnos' => 8,
        'tipo' => 'cura',
        'alvo' => 'aliados',
        'efeito_principal' => '{"cura_base": 150, "multiplicador": 2.5, "cura_extra": 50}',
        'efeito_secundario' => '{"remove_debuffs": true, "buff_regeneracao": 20}',
        'condicoes_desbloqueio' => '{"nivel": 16, "classe": "Sacerdote", "habilidades_cura": 5}',
        'nivel_requerido' => 16,
        'classe_req' => 'Sacerdote',
        'icone' => '🌧️',
        'cor_efeito' => '#38B000'
    ],

    // GERAL (para todas as classes)
    [
        'nome' => 'Explosão de Poder',
        'descricao' => 'Libera energia acumulada, dobrando o dano por 3 turnos',
        'custo_mana' => 90,
        'cooldown_turnos' => 7,
        'tipo' => 'transformacao',
        'alvo' => 'self',
        'efeito_principal' => '{"dano_dobrado": true, "duracao": 3, "velocidade_extra": 20}',
        'efeito_secundario' => '{"custo_hp_turno": 10, "buff_critico": 25}',
        'condicoes_desbloqueio' => '{"nivel": 10}',
        'nivel_requerido' => 10,
        'classe_req' => NULL,
        'icone' => '💥',
        'cor_efeito' => '#FF9E00'
    ]
];

foreach ($ultimate_abilities as $ultimate) {
    $sql_check = "SELECT id FROM ultimate_abilities_base WHERE nome = ?";
    $stmt = $conexao->prepare($sql_check);
    $stmt->bind_param("s", $ultimate['nome']);
    $stmt->execute();
    
    if ($stmt->get_result()->num_rows == 0) {
        $sql = "INSERT INTO ultimate_abilities_base (nome, descricao, custo_mana, cooldown_turnos, tipo, alvo, efeito_principal, efeito_secundario, condicoes_desbloqueio, nivel_requerido, classe_req, icone, cor_efeito) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
        $stmt = $conexao->prepare($sql);
        $stmt->bind_param("siiisssssiiss", 
            $ultimate['nome'], 
            $ultimate['descricao'],
            $ultimate['custo_mana'],
            $ultimate['cooldown_turnos'],
            $ultimate['tipo'],
            $ultimate['alvo'],
            $ultimate['efeito_principal'],
            $ultimate['efeito_secundario'],
            $ultimate['condicoes_desbloqueio'],
            $ultimate['nivel_requerido'],
            $ultimate['classe_req'],
            $ultimate['icone'],
            $ultimate['cor_efeito']
        );
        
        if ($stmt->execute()) {
            echo "✅ Ultimate '{$ultimate['nome']}' adicionada!<br>";
        } else {
            echo "❌ Erro ao adicionar '{$ultimate['nome']}': " . $stmt->error . "<br>";
        }
    } else {
        echo "⏭️ Ultimate '{$ultimate['nome']}' já existe!<br>";
    }
}

echo "<br><strong>🎯 Sistema de Ultimate Abilities configurado!</strong><br>";
echo "Total de ultimates: " . count($ultimate_abilities) . "<br>";
echo "<br><a href='personagem.php'>Verificar Desbloqueio</a>";
?>