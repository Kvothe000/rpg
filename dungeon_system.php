<?php
// dungeon_system.php - VERSÃO COMPLETA E CORRIGIDA
class DungeonSystem {
    private $conexao;
    
    public function __construct($conexao) {
        $this->conexao = $conexao;
    }
    
    // ✅ MÉTODO get_player_data IMPLEMENTADO
    private function get_player_data($player_id) {
        $sql = "SELECT * FROM personagens WHERE id = ?";
        $stmt = $this->conexao->prepare($sql);
        $stmt->bind_param("i", $player_id);
        $stmt->execute();
        $result = $stmt->get_result();
        return $result->fetch_assoc();
    }
    
    // ✅ MÉTODO get_dungeon_base IMPLEMENTADO
    private function get_dungeon_base($dificuldade) {
        $dungeons_base = [
            'facil' => [
                'nome' => 'Caverna dos Iniciantes',
                'nivel_base' => 5,
                'dificuldade' => 'facil'
            ],
            'medio' => [
                'nome' => 'Ruínas Esquecidas', 
                'nivel_base' => 15,
                'dificuldade' => 'medio'
            ],
            'dificil' => [
                'nome' => 'Abismo Profundo',
                'nivel_base' => 25,
                'dificuldade' => 'dificil'
            ]
        ];
        
        return $dungeons_base[$dificuldade] ?? $dungeons_base['medio'];
    }
    
    // GERA DUNGEON DINÂMICA BASEADA NO JOGADOR
    public function gerar_dungeon_dinamica($player_id, $dificuldade) {
        $player_data = $this->get_player_data($player_id);
        
        if (!$player_data) {
            // ✅ FALLBACK SE PLAYER NÃO EXISTIR
            return $this->gerar_dungeon_fallback($dificuldade);
        }
        
        $dungeon_base = $this->get_dungeon_base($dificuldade);
        
        // MODIFICADORES ALEATÓRIOS
        $modificadores = $this->gerar_modificadores();
        $eventos_especiais = $this->gerar_eventos_especiais();
        
        return [
            'id' => uniqid(),
            'nome' => $dungeon_base['nome'] . ' ' . $modificadores['sufixo'],
            'dificuldade' => $dificuldade,
            'nivel_recomendado' => $dungeon_base['nivel_base'] + $modificadores['nivel_ajuste'],
            'monstros' => $this->gerar_encounters($dungeon_base, $modificadores, $player_data['level']),
            'chefe' => $this->gerar_chefe($dungeon_base, $modificadores),
            'modificadores' => $modificadores,
            'eventos' => $eventos_especiais,
            'recompensas' => $this->calcular_recompensas($dungeon_base, $modificadores, $player_data),
            'tempo_limite' => time() + (30 * 60), // 30 minutos
            'exploracao' => 0
        ];
    }
    
    // ✅ MÉTODO FALLBACK PARA CASOS DE ERRO
    private function gerar_dungeon_fallback($dificuldade) {
        $dungeon_base = $this->get_dungeon_base($dificuldade);
        $modificadores = $this->gerar_modificadores();
        
        return [
            'id' => uniqid(),
            'nome' => $dungeon_base['nome'] . ' ' . $modificadores['sufixo'],
            'dificuldade' => $dificuldade,
            'nivel_recomendado' => 10,
            'monstros' => $this->gerar_encounters($dungeon_base, $modificadores, 10),
            'chefe' => $this->gerar_chefe($dungeon_base, $modificadores),
            'modificadores' => $modificadores,
            'eventos' => [],
            'recompensas' => ['ouro' => 500, 'xp' => 300, 'itens' => []],
            'tempo_limite' => time() + (30 * 60),
            'exploracao' => 0
        ];
    }
    
    private function gerar_modificadores() {
        $prefixos = ['Abandonada', 'Amaldiçoada', 'Sagrada', 'Corrompida', 'Cristalina', 'Sombria'];
        $sufixos = ['da Perdição', 'do Pesadelo', 'da Esperança', 'do Vazio', 'da Eternidade'];
        $efeitos = [
            'chuva_arcana' => ['xp_bonus' => 0.2, 'dano_extra' => 'magico', 'cor' => '#8A2BE2', 'descricao' => '+20% XP e dano mágico'],
            'escuridao_total' => ['dano_reduzido' => 0.15, 'furtividade_bonus' => 0.3, 'cor' => '#2F4F4F', 'descricao' => '-15% dano recebido'],
            'fenda_instavel' => ['loot_bonus' => 0.25, 'dano_aleatorio' => true, 'cor' => '#FF4444', 'descricao' => '+25% loot e dano aleatório'],
            'mana_corrompida' => ['mana_cost' => 1.5, 'poder_bonus' => 0.4, 'cor' => '#32CD32', 'descricao' => '+40% poder, +50% custo de mana']
        ];
        
        $efeito_escolhido = array_rand($efeitos);
        
        return [
            'prefixo' => $prefixos[array_rand($prefixos)],
            'sufixo' => $sufixos[array_rand($sufixos)],
            'efeito' => $efeito_escolhido,
            'nivel_ajuste' => rand(-2, 3),
            'dados_efeito' => $efeitos[$efeito_escolhido]
        ];
    }
    
    private function gerar_eventos_especiais() {
        $eventos = [
            'Sala do Tesouro' => ['ouro_extra' => 200, 'chance_item_raro' => 0.3],
            'Poção de Cura' => ['cura' => 50, 'mensagem' => 'Você encontrou uma poção de cura!'],
            'Armadilha' => ['dano' => 25, 'mensagem' => 'Cuidado! Uma armadilha!'],
            'Altar Antigo' => ['buffs' => ['dano' => 1.2, 'defesa' => 1.1], 'mensagem' => 'O altar te abençoou com poder!']
        ];
        
        $evento_escolhido = array_rand($eventos);
        return [$evento_escolhido => $eventos[$evento_escolhido]];
    }
    
    private function gerar_encounters($dungeon_base, $modificadores, $player_level) {
        $encounters = [];
        $num_encounters = rand(3, 6);
        
        $monstros_base = [
            'facil' => ['Slime', 'Goblin Fraco', 'Rato Gigante', 'Aranha Pequena'],
            'medio' => ['Goblin Guerreiro', 'Esqueleto', 'Orc', 'Lobisomem'],
            'dificil' => ['Troll', 'Ogro', 'Necromante', 'Dragão Jovem']
        ];
        
        for ($i = 0; $i < $num_encounters; $i++) {
            $monstro_tipo = $monstros_base[$dungeon_base['dificuldade']][array_rand($monstros_base[$dungeon_base['dificuldade']])];
            
            $nivel_monstro = max(1, $player_level + $modificadores['nivel_ajuste'] + rand(-1, 2));
            $vida_base = rand(80, 120) * ($dungeon_base['dificuldade'] == 'dificil' ? 1.5 : 1);
            $dano_base = rand(15, 25) * ($dungeon_base['dificuldade'] == 'dificil' ? 1.3 : 1);
            
            $encounters[] = [
                'id' => $i + 1,
                'tipo' => $monstro_tipo,
                'nivel' => $nivel_monstro,
                'vida' => $vida_base,
                'hp_max' => $vida_base,
                'hp_atual' => $vida_base,
                'dano' => $dano_base,
                'dano_min' => $dano_base * 0.8,
                'dano_max' => $dano_base * 1.2,
                'str' => rand(8, 15),
                'dex' => rand(8, 15),
                'con' => rand(8, 15),
                'ouro_recompensa' => rand(30, 80) * ($nivel_monstro / 5),
                'xp_recompensa' => rand(20, 50) * ($nivel_monstro / 5),
                'loot' => $this->gerar_loot_monstro($monstro_tipo, $dungeon_base['dificuldade']),
                'habilidades' => $this->gerar_habilidades_monstro($monstro_tipo)
            ];
        }
        
        return $encounters;
    }
    
    private function gerar_habilidades_monstro($tipo_monstro) {
        $habilidades = [
            'Slime' => ['Ácido Corrosivo'],
            'Goblin' => ['Ataque Furtivo', 'Grito de Guerra'],
            'Esqueleto' => ['Ossada Afiada', 'Grito Aterrorizante'],
            'Dragão' => ['Sopro de Fogo', 'Garras Afiadas', 'Presa Venenosa']
        ];
        
        return $habilidades[$tipo_monstro] ?? ['Ataque Básico'];
    }
    
    private function gerar_loot_monstro($tipo_monstro, $dificuldade) {
        $loot_tables = [
            'Slime' => ['Gosma de Slime', 'Cristal Pequeno'],
            'Goblin' => ['Dente de Goblin', 'Moedas Velhas'],
            'Esqueleto' => ['Osso Antigo', 'Fragmento de Alma'],
            'Dragão' => ['Escama de Dragão', 'Presa Afiada', 'Cristal de Dragão']
        ];
        
        return $loot_tables[$tipo_monstro] ?? ['Item Comum'];
    }
    
    private function gerar_chefe($dungeon_base, $modificadores) {
        $chefes = [
            'facil' => ['Rei Goblin', 'Líder dos Slimes', 'Aranha Rainha'],
            'medio' => ['Lich Menor', 'Troll da Montanha', 'Necromante Sombrio'],
            'dificil' => ['Dragão Ancião', 'Lich Supremo', 'Deus Menor Corrompido']
        ];
        
        $chefe_base = $chefes[$dungeon_base['dificuldade']][array_rand($chefes[$dungeon_base['dificuldade']])];
        
        $vida_base = rand(500, 800) * ($dungeon_base['dificuldade'] == 'dificil' ? 2 : 1);
        $dano_base = rand(40, 60) * ($dungeon_base['dificuldade'] == 'dificil' ? 1.5 : 1);
        
        return [
            'nome' => $chefe_base,
            'titulo' => $modificadores['prefixo'] . ' ' . $chefe_base,
            'vida' => $vida_base,
            'hp_max' => $vida_base,
            'dano' => $dano_base,
            'fases' => rand(2, 3),
            'loot_especial' => $this->gerar_loot_chefe($dungeon_base['dificuldade']),
            'habilidades_chefe' => $this->gerar_habilidades_chefe($chefe_base)
        ];
    }
    
    private function gerar_habilidades_chefe($chefe_base) {
        $habilidades_chefe = [
            'Rei Goblin' => ['Grito de Guerra', 'Chamado dos Goblins', 'Fúria do Rei'],
            'Dragão Ancião' => ['Sopro Infernal', 'Asas da Morte', 'Fúria do Dragão'],
            'Lich Supremo' => ['Nevoa da Morte', 'Invocação de Mortos-Vivos', 'Drenar Alma']
        ];
        
        return $habilidades_chefe[$chefe_base] ?? ['Ataque Poderoso'];
    }
    
    private function gerar_loot_chefe($dificuldade) {
        $loot_tables = [
            'facil' => ['Cristal de Eco Fraco', 'Essência de Mana', 'Pele de Goblin'],
            'medio' => ['Cristal de Eco Médio', 'Essência Arcana', 'Coração de Troll'],
            'dificil' => ['Cristal de Eco Puro', 'Fragmento de Alma', 'Escama de Dragão', 'ARTEFATO RARO']
        ];
        
        $loot = [];
        $num_itens = rand(2, 4);
        
        for ($i = 0; $i < $num_itens; $i++) {
            $item = $loot_tables[$dificuldade][array_rand($loot_tables[$dificuldade])];
            $loot[] = [
                'item' => $item,
                'raridade' => $this->calcular_raridade($dificuldade),
                'quantidade' => rand(1, 3)
            ];
        }
        
        return $loot;
    }
    
    private function calcular_raridade($dificuldade) {
        $raridades = [
            'facil' => ['Comum' => 70, 'Incomum' => 30],
            'medio' => ['Comum' => 50, 'Incomum' => 40, 'Raro' => 10],
            'dificil' => ['Incomum' => 40, 'Raro' => 35, 'Épico' => 20, 'Lendário' => 5]
        ];
        
        $roll = rand(1, 100);
        $acumulado = 0;
        
        foreach ($raridades[$dificuldade] as $raridade => $chance) {
            $acumulado += $chance;
            if ($roll <= $acumulado) {
                return $raridade;
            }
        }
        
        return 'Comum';
    }
    
    // ✅ MÉTODO calcular_recompensas IMPLEMENTADO
    private function calcular_recompensas($dungeon_base, $modificadores, $player_data) {
        $base_ouro = rand(300, 600) * (1 + ($player_data['level'] / 20));
        $base_xp = rand(200, 400) * (1 + ($player_data['level'] / 15));
        
        // Bônus do modificador
        if (isset($modificadores['dados_efeito']['xp_bonus'])) {
            $base_xp *= (1 + $modificadores['dados_efeito']['xp_bonus']);
        }
        
        if (isset($modificadores['dados_efeito']['loot_bonus'])) {
            $base_ouro *= (1 + $modificadores['dados_efeito']['loot_bonus']);
        }
        
        return [
            'ouro' => (int)$base_ouro,
            'xp' => (int)$base_xp,
            'itens' => $this->gerar_loot_chefe($dungeon_base['dificuldade'])
        ];
    }
}
?>