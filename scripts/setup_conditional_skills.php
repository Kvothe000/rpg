<?php
// setup_conditional_skills.php
include 'db_connect.php';

echo "<h2>Adicionando Habilidades Condicionais</h2>";

// Habilidades condicionais para inserir
$habilidades_condicionais = [
    // HABILIDADE DE EMERGÊNCIA (HP baixo)
    [
        'nome' => 'Último Recurso',
        'descricao' => 'Ataque desesperado quando a situação é crítica. Usa HP como custo.',
        'custo_mana' => 0,
        'dano_base' => 100,
        'multiplicador_atributo' => 2.0,
        'atributo_principal' => 'str',
        'classe_req' => 'Guerreiro',
        'custo_ph' => 3,
        'custo_ph_upgrade' => 2,
        'condicoes_uso' => '{"hp_minimo": 30, "recursos_alternativos": "hp_custo"}',
        'icone' => '💀'
    ],
    
    // HABILIDADE DE INÍCIO DE COMBATE
    [
        'nome' => 'Ataque Surpresa', 
        'descricao' => 'Golpe rápido apenas nos primeiros turnos do combate.',
        'custo_mana' => 20,
        'dano_base' => 40,
        'multiplicador_atributo' => 1.5,
        'atributo_principal' => 'dex',
        'classe_req' => 'Guerreiro',
        'custo_ph' => 2,
        'custo_ph_upgrade' => 1,
        'condicoes_uso' => '{"turno_maximo": 3}',
        'icone' => '⚡'
    ],
    
    // HABILIDADE DE FINALIZAÇÃO
    [
        'nome' => 'Golpe Finalizador',
        'descricao' => 'Dano massivo contra inimigos enfraquecidos.',
        'custo_mana' => 50,
        'dano_base' => 60,
        'multiplicador_atributo' => 2.5,
        'atributo_principal' => 'str',
        'classe_req' => 'Guerreiro',
        'custo_ph' => 4,
        'custo_ph_upgrade' => 3,
        'condicoes_uso' => '{"alvo_hp_minimo": 25}',
        'icone' => '🏁'
    ],
    
    // HABILIDADE DE FÚRIA (Mana baixa)
    [
        'nome' => 'Fúria Desesperada',
        'descricao' => 'Quando a mana acaba, a fúria toma conta.',
        'custo_mana' => 0,
        'dano_base' => 80,
        'multiplicador_atributo' => 1.8,
        'atributo_principal' => 'str', 
        'classe_req' => 'Guerreiro',
        'custo_ph' => 3,
        'custo_ph_upgrade' => 2,
        'condicoes_uso' => '{"mana_minimo": 15}',
        'icone' => '😠'
    ],
    
    // HABILIDADE DE MAGO (HP baixo)
    [
        'nome' => 'Explosão Arcana',
        'descricao' => 'Libera poder arcano acumulado em situações desesperadoras.',
        'custo_mana' => 80,
        'dano_base' => 120,
        'multiplicador_atributo' => 2.2,
        'atributo_principal' => 'int_stat',
        'classe_req' => 'Mago',
        'custo_ph' => 5,
        'custo_ph_upgrade' => 4,
        'condicoes_uso' => '{"hp_minimo": 40}',
        'icone' => '💥'
    ],
    
    // HABILIDADE DE SACERDOTE (Cura emergencial)
    [
        'nome' => 'Cura do Desespero',
        'descricao' => 'Cura massiva quando o aliado está à beira da morte.',
        'custo_mana' => 60,
        'dano_base' => 0, // Cura em vez de dano
        'multiplicador_atributo' => 1.5,
        'atributo_principal' => 'wis',
        'classe_req' => 'Sacerdote',
        'custo_ph' => 4,
        'custo_ph_upgrade' => 3,
        'condicoes_uso' => '{"hp_minimo": 20}',
        'icone' => '🙏'
    ]
];

// Primeiro, verificar se a coluna condicoes_uso existe
$sql_check_column = "SHOW COLUMNS FROM skills_base LIKE 'condicoes_uso'";
$result = $conexao->query($sql_check_column);

if ($result->num_rows == 0) {
    echo "📝 Adicionando coluna condicoes_uso...<br>";
    $sql_alter = "ALTER TABLE skills_base ADD COLUMN condicoes_uso JSON NULL AFTER aplica_status";
    if ($conexao->query($sql_alter)) {
        echo "✅ Coluna condicoes_uso adicionada!<br>";
    } else {
        echo "❌ Erro ao adicionar coluna: " . $conexao->error . "<br>";
    }
} else {
    echo "✅ Coluna condicoes_uso já existe!<br>";
}

// Inserir habilidades
foreach ($habilidades_condicionais as $habilidade) {
    $sql_check = "SELECT id FROM skills_base WHERE nome = ?";
    $stmt = $conexao->prepare($sql_check);
    $stmt->bind_param("s", $habilidade['nome']);
    $stmt->execute();
    
    if ($stmt->get_result()->num_rows == 0) {
        $sql = "INSERT INTO skills_base (nome, descricao, custo_mana, dano_base, multiplicador_atributo, atributo_principal, classe_req, custo_ph, custo_ph_upgrade, condicoes_uso) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
        $stmt = $conexao->prepare($sql);
        $stmt->bind_param("ssiidssiis", 
            $habilidade['nome'], 
            $habilidade['descricao'],
            $habilidade['custo_mana'],
            $habilidade['dano_base'],
            $habilidade['multiplicador_atributo'],
            $habilidade['atributo_principal'],
            $habilidade['classe_req'],
            $habilidade['custo_ph'],
            $habilidade['custo_ph_upgrade'],
            $habilidade['condicoes_uso']
        );
        
        if ($stmt->execute()) {
            echo "✅ Habilidade '{$habilidade['nome']}' adicionada!<br>";
        } else {
            echo "❌ Erro ao adicionar '{$habilidade['nome']}': " . $stmt->error . "<br>";
        }
    } else {
        echo "⏭️ Habilidade '{$habilidade['nome']}' já existe!<br>";
    }
}

echo "<br><strong>🎯 Habilidades Condicionais configuradas!</strong><br>";
echo "Total de habilidades: " . count($habilidades_condicionais) . "<br>";
echo "<br><a href='combate_portal.php'>Testar no Combate</a>";
?>