-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Tempo de geração: 22/10/2025 às 05:40
-- Versão do servidor: 10.4.32-MariaDB
-- Versão do PHP: 8.2.12

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Banco de dados: `rpg_mud_db`
--

-- --------------------------------------------------------

--
-- Estrutura para tabela `achievements_base`
--

CREATE TABLE `achievements_base` (
  `id` int(11) NOT NULL,
  `titulo` varchar(100) NOT NULL,
  `descricao` text NOT NULL,
  `categoria` enum('combate','progresso','ecos','colecionavel','social') NOT NULL,
  `tipo_objetivo` enum('nivel_personagem','monstros_derrotados','ecos_recrutados','ouro_coletado','missoes_completas','habilidades_aprendidas') NOT NULL,
  `objetivo` int(11) NOT NULL,
  `recompensa_ouro` int(11) NOT NULL,
  `recompensa_xp` int(11) NOT NULL,
  `recompensa_item` int(11) DEFAULT NULL,
  `icone` varchar(10) NOT NULL,
  `raridade` enum('comum','raro','epico','lendario') DEFAULT 'comum'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Despejando dados para a tabela `achievements_base`
--

INSERT INTO `achievements_base` (`id`, `titulo`, `descricao`, `categoria`, `tipo_objetivo`, `objetivo`, `recompensa_ouro`, `recompensa_xp`, `recompensa_item`, `icone`, `raridade`) VALUES
(1, 'Mestre dos Combos', 'Complete 25 combos diferentes', 'progresso', '', 25, 5000, 10000, NULL, '🎯', 'epico'),
(2, 'Combo Iniciante', 'Complete seu primeiro combo', 'progresso', '', 1, 500, 1000, NULL, '⚡', 'comum');

-- --------------------------------------------------------

--
-- Estrutura para tabela `combate_status_ativos`
--

CREATE TABLE `combate_status_ativos` (
  `id` int(11) NOT NULL,
  `combate_id` int(11) NOT NULL,
  `alvo_id` int(11) NOT NULL,
  `alvo_tipo` enum('player','monstro') NOT NULL,
  `status_id` int(11) NOT NULL,
  `duracao_restante` int(11) NOT NULL,
  `intensidade` int(11) DEFAULT 1,
  `data_aplicacao` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Estrutura para tabela `combos_base`
--

CREATE TABLE `combos_base` (
  `id` int(11) NOT NULL,
  `nome` varchar(100) NOT NULL,
  `descricao` text NOT NULL,
  `sequencia` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL CHECK (json_valid(`sequencia`)),
  `classe_req` varchar(50) DEFAULT NULL,
  `nivel_requerido` int(11) DEFAULT 1,
  `efeito_combo` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL CHECK (json_valid(`efeito_combo`)),
  `duracao_combo` int(11) DEFAULT 3,
  `icone` varchar(10) NOT NULL,
  `data_criacao` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Despejando dados para a tabela `combos_base`
--

INSERT INTO `combos_base` (`id`, `nome`, `descricao`, `sequencia`, `classe_req`, `nivel_requerido`, `efeito_combo`, `duracao_combo`, `icone`, `data_criacao`) VALUES
(1, 'Combo de Fúria', 'Golpe Duplo → Ataque Giratório → Fúria Berserk', '[\"Golpe Duplo\", \"Ataque Giratório\", \"Fúria Berserk\"]', 'Guerreiro', 5, '{\"tipo\": \"dano_extra\", \"valor\": 75, \"duracao\": 2, \"status_aplicado\": 5}', 3, '💥', '2025-10-22 01:00:15'),
(2, 'Combo Defensivo', 'Postura Defensiva → Contra-Ataque → Investida Implacável', '[\"Postura Defensiva\", \"Contra-Ataque\", \"Investida Implacável\"]', 'Guerreiro', 8, '{\"tipo\": \"buff_defesa\", \"valor\": 15, \"duracao\": 3, \"mitigacao_extra\": 10}', 3, '🛡️', '2025-10-22 01:00:15'),
(3, 'Combo Arcano', 'Missil Mágico → Explosão Arcana → Tempestade de Mana', '[\"Missil Mágico\", \"Explosão Arcana\", \"Tempestade de Mana\"]', 'Mago', 6, '{\"tipo\": \"dano_magico_extra\", \"valor\": 50, \"duracao\": 2, \"status_aplicado\": 1}', 3, '🔮', '2025-10-22 01:00:15'),
(4, 'Combo Elemental', 'Toque Chocante → Rajada de Gelo → Labareda', '[\"Toque Chocante\", \"Rajada de Gelo\", \"Labareda\"]', 'Mago', 10, '{\"tipo\": \"multi_status\", \"status\": [1, 3, 6], \"dano_extra\": 100}', 3, '⚡', '2025-10-22 01:00:15'),
(5, 'Combo Rápido', 'Ataque Rápido → Golpe Preciso → Finalizador', '[\"Ataque Rápido\", \"Golpe Preciso\", \"Finalizador\"]', NULL, 3, '{\"tipo\": \"critico_extra\", \"valor\": 25, \"duracao\": 1}', 3, '🎯', '2025-10-22 01:00:15');

-- --------------------------------------------------------

--
-- Estrutura para tabela `conquistas`
--

CREATE TABLE `conquistas` (
  `id` int(11) NOT NULL,
  `nome` varchar(100) NOT NULL,
  `descricao` text DEFAULT NULL,
  `tipo` enum('exploracao','combate','coleta','social') NOT NULL,
  `dificuldade` enum('facil','medio','dificil','epico') DEFAULT 'facil',
  `recompensa_ouro` int(11) DEFAULT 0,
  `recompensa_xp` int(11) DEFAULT 0,
  `item_recompensa` varchar(100) DEFAULT NULL,
  `icone` varchar(10) DEFAULT '?'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Despejando dados para a tabela `conquistas`
--

INSERT INTO `conquistas` (`id`, `nome`, `descricao`, `tipo`, `dificuldade`, `recompensa_ouro`, `recompensa_xp`, `item_recompensa`, `icone`) VALUES
(1, 'Explorador Inicial', 'Descubra sua primeira região', 'exploracao', 'facil', 500, 100, NULL, '🗺️'),
(2, 'Caçador de Slimes', 'Derrote 10 Slimes', 'combate', 'facil', 300, 50, NULL, '🌀'),
(3, 'Colecionador', 'Obtenha 10 itens diferentes', 'coleta', 'medio', 1000, 200, NULL, '🎒'),
(4, 'Mestre das Dungeons', 'Complete 5 dungeons diferentes', 'combate', 'dificil', 5000, 1000, NULL, '⚔️'),
(5, 'Lenda Viva', 'Alcance o nível 50', 'exploracao', 'epico', 10000, 5000, NULL, '👑'),
(6, 'Socializador', 'Interaja com 5 NPCs diferentes', 'social', 'facil', 200, 50, NULL, '💬');

-- --------------------------------------------------------

--
-- Estrutura para tabela `daily_quests_base`
--

CREATE TABLE `daily_quests_base` (
  `id` int(11) NOT NULL,
  `titulo` varchar(100) NOT NULL,
  `descricao` text NOT NULL,
  `tipo` enum('combate','ecos','economia','progresso') NOT NULL,
  `objetivo` int(11) NOT NULL,
  `tipo_objetivo` enum('matar_monstros','completar_missoes','gastar_ouro','evoluir_affinity','usar_habilidades') NOT NULL,
  `rank_requerido` enum('E','D','C','B','A','S') DEFAULT 'E',
  `recompensa_ouro` int(11) NOT NULL,
  `recompensa_xp` int(11) NOT NULL,
  `recompensa_itens` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`recompensa_itens`)),
  `data_criacao` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Despejando dados para a tabela `daily_quests_base`
--

INSERT INTO `daily_quests_base` (`id`, `titulo`, `descricao`, `tipo`, `objetivo`, `tipo_objetivo`, `rank_requerido`, `recompensa_ouro`, `recompensa_xp`, `recompensa_itens`, `data_criacao`) VALUES
(1, 'Caçador Inicial', 'Derrote 10 monstros de qualquer rank', 'combate', 10, 'matar_monstros', 'E', 500, 1000, NULL, '2025-10-21 22:34:11'),
(2, 'Mestre do Campo de Batalha', 'Derrote 25 monstros Rank C ou superior', 'combate', 25, 'matar_monstros', 'C', 1500, 3000, NULL, '2025-10-21 22:34:11'),
(3, 'Lazarento de Almas', 'Complete 3 missões com seus Ecos', 'ecos', 3, 'completar_missoes', 'D', 800, 1500, NULL, '2025-10-21 22:34:11'),
(4, 'Vínculo Profundo', 'Aumente o nível de afinidade de qualquer Eco', 'ecos', 1, 'evoluir_affinity', 'C', 1200, 2000, NULL, '2025-10-21 22:34:11'),
(5, 'Mercador Astuto', 'Gaste 2000 de ouro', 'economia', 2000, 'gastar_ouro', 'D', 1000, 1500, NULL, '2025-10-21 22:34:11'),
(6, 'Aprendiz Zeloso', 'Use habilidades 15 vezes em combate', 'progresso', 15, 'usar_habilidades', 'D', 600, 1200, NULL, '2025-10-21 22:34:11');

-- --------------------------------------------------------

--
-- Estrutura para tabela `ecos_base`
--

CREATE TABLE `ecos_base` (
  `id` int(11) NOT NULL,
  `nome` varchar(50) NOT NULL,
  `rank_eco` varchar(5) NOT NULL COMMENT 'E, D, C, B, A, S',
  `tipo_eco` varchar(20) NOT NULL COMMENT 'Besta, Sombra, Humanoide',
  `descricao` text DEFAULT NULL,
  `bonus_ouro_hora` int(11) DEFAULT 10,
  `chance_material_raro` float DEFAULT 0.1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Despejando dados para a tabela `ecos_base`
--

INSERT INTO `ecos_base` (`id`, `nome`, `rank_eco`, `tipo_eco`, `descricao`, `bonus_ouro_hora`, `chance_material_raro`) VALUES
(1, 'Goblin Coletor', 'E', 'Humanoide', 'Fraco em combate, mas ótimo em achar moedas perdidas.', 25, 0.01),
(2, 'Lobo das Sombras Filhote', 'D', 'Besta', 'Um caçador nato. Traz restos de materiais de valor.', 10, 0.05);

-- --------------------------------------------------------

--
-- Estrutura para tabela `eco_habilidades_base`
--

CREATE TABLE `eco_habilidades_base` (
  `id` int(11) NOT NULL,
  `id_eco_base` int(11) DEFAULT NULL,
  `nivel_affinity_requerido` int(11) DEFAULT NULL,
  `nome_habilidade` varchar(100) DEFAULT NULL,
  `tipo_habilidade` enum('passiva','ativa') DEFAULT NULL,
  `descricao` text DEFAULT NULL,
  `efeito_bonus` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`efeito_bonus`))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Despejando dados para a tabela `eco_habilidades_base`
--

INSERT INTO `eco_habilidades_base` (`id`, `id_eco_base`, `nivel_affinity_requerido`, `nome_habilidade`, `tipo_habilidade`, `descricao`, `efeito_bonus`) VALUES
(1, 1, 2, 'Pilhagem Fantasma', 'passiva', 'Aumenta ouro de missões em 10%', '{\"bonus_ouro\": 0.1}'),
(2, 1, 4, 'Furtividade Espectral', 'passiva', 'Aumenta chance de loot raro em 15%', '{\"bonus_loot\": 0.15}'),
(3, 1, 6, 'Assalto Dimensional', 'ativa', 'Missões produzem 2x ouro por 4h', '{\"multiplicador_ouro\": 2.0, \"duracao\": 4}'),
(4, 2, 2, 'Faro da Fenda', 'passiva', 'Aumenta XP de missões em 20%', '{\"bonus_xp\": 0.2}'),
(5, 2, 5, 'Uivo da Lua', 'passiva', 'Reduz tempo de missão em 25%', '{\"reducao_tempo\": 0.25}'),
(6, 2, 8, 'Alcateia Espectral', 'ativa', 'Todos os Ecos ganham +50% eficiência por 2h', '{\"bonus_geral\": 0.5, \"duracao\": 2}');

-- --------------------------------------------------------

--
-- Estrutura para tabela `inventario`
--

CREATE TABLE `inventario` (
  `id` int(11) NOT NULL,
  `id_personagem` int(11) NOT NULL,
  `id_item_base` int(11) NOT NULL,
  `quantidade` int(11) DEFAULT 1,
  `equipado` tinyint(1) DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Despejando dados para a tabela `inventario`
--

INSERT INTO `inventario` (`id`, `id_personagem`, `id_item_base`, `quantidade`, `equipado`) VALUES
(2, 1, 2, 75, 0),
(12, 1, 1, 6, 1),
(13, 1, 3, 1, 1),
(20, 1, 6, 4, 0),
(21, 3, 1, 1, 1),
(22, 3, 2, 55, 0),
(23, 3, 1, 1, 0),
(24, 3, 1, 1, 0),
(25, 3, 1, 1, 0),
(26, 3, 1, 1, 0),
(27, 3, 1, 1, 0),
(28, 3, 1, 1, 0),
(29, 3, 1, 1, 0),
(30, 3, 1, 1, 0),
(31, 3, 1, 1, 0),
(32, 3, 1, 1, 0),
(34, 1, 8, 1, 0);

-- --------------------------------------------------------

--
-- Estrutura para tabela `itens_base`
--

CREATE TABLE `itens_base` (
  `id` int(11) NOT NULL,
  `nome` varchar(50) NOT NULL,
  `descricao` text DEFAULT NULL,
  `raridade` varchar(20) NOT NULL,
  `tipo` varchar(20) NOT NULL,
  `slot` varchar(20) DEFAULT NULL,
  `peso` float DEFAULT 0.1,
  `valor_venda` int(11) DEFAULT 1,
  `bonus_str` int(11) DEFAULT 0,
  `bonus_con` int(11) DEFAULT 0,
  `bonus_dex` int(11) DEFAULT 0,
  `bonus_int` int(11) DEFAULT 0,
  `dano_min` int(11) DEFAULT 0,
  `dano_max` int(11) DEFAULT 0,
  `mitigacao` int(11) DEFAULT 0,
  `efeito_hp_cura` int(11) DEFAULT 0,
  `efeito_mana_cura` int(11) DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Despejando dados para a tabela `itens_base`
--

INSERT INTO `itens_base` (`id`, `nome`, `descricao`, `raridade`, `tipo`, `slot`, `peso`, `valor_venda`, `bonus_str`, `bonus_con`, `bonus_dex`, `bonus_int`, `dano_min`, `dano_max`, `mitigacao`, `efeito_hp_cura`, `efeito_mana_cura`) VALUES
(1, 'Faca Enfermada', 'Uma faca velha e enferrujada.', 'Comum', 'Arma', 'Arma_1', 0.5, 5, 0, 0, 0, 0, 5, 8, 0, 0, 0),
(2, 'Fragmento de Slime', 'Resto gelatinoso do Slime. Útil para alquimia.', 'Comum', 'Material', 'Arma_1', 0.2, 2, 0, 0, 0, 0, 0, 0, 0, 0, 0),
(3, 'Peitoral de Couro', 'Armadura simples, mas resistente.', 'Incomum', 'Armadura', 'Peitoral', 5, 50, 1, 0, 0, 0, 0, 0, 3, 0, 0),
(4, 'Poção de HP Pequena', 'Cura uma pequena quantidade de HP.', 'Incomum', 'Consumivel', NULL, 0.1, 10, 0, 0, 0, 0, 0, 0, 0, 50, 0),
(5, 'Núcleo de Eco Fraco', 'Um cristal pulsante. Parece conter a essência de um aliado. Use-o na sua base para tentar um recrutamento.', 'Raro', 'Consumivel_Eco', NULL, 0.1, 100, 0, 0, 0, 0, 0, 0, 0, 0, 0),
(6, 'Orelha de Goblin', 'Prova da derrota de um Goblin. Pode ser útil.', 'Comum', 'Material', NULL, 0.1, 8, 0, 0, 0, 0, 0, 0, 0, 0, 0),
(7, 'Espada Longa Gasta', 'Uma espada padrão, um pouco desgastada mas confiável.', 'Incomum', 'Arma', 'Arma_1', 3, 60, 1, 0, 0, 0, 18, 28, 0, 0, 0),
(8, 'Placas Leves Rústicas', 'Fragmentos de armadura de metal unidos. Oferece boa proteção.', 'Incomum', 'Armadura', 'Peitoral', 8, 100, 0, 1, 0, 0, 0, 0, 5, 0, 0),
(9, 'Fragmento Ósseo', 'Um pedaço de osso magicamente carregado, deixado por um morto-vivo.', 'Comum', 'Material', NULL, 0.2, 12, 0, 0, 0, 0, 0, 0, 0, 0, 0);

-- --------------------------------------------------------

--
-- Estrutura para tabela `loja_itens`
--

CREATE TABLE `loja_itens` (
  `id` int(11) NOT NULL,
  `nome` varchar(100) NOT NULL,
  `descricao` text NOT NULL,
  `tipo` enum('consumivel','equipamento','material','especial') NOT NULL,
  `preco` int(11) NOT NULL,
  `efeito` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`efeito`)),
  `estoque` int(11) DEFAULT -1,
  `rank_requerido` enum('E','D','C','B','A','S') DEFAULT 'E',
  `data_criacao` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Despejando dados para a tabela `loja_itens`
--

INSERT INTO `loja_itens` (`id`, `nome`, `descricao`, `tipo`, `preco`, `efeito`, `estoque`, `rank_requerido`, `data_criacao`) VALUES
(1, 'Poção de Cura Pequena', 'Restaura 50 pontos de vida', 'consumivel', 100, '{\"tipo\": \"cura\", \"valor\": 50}', -1, 'E', '2025-10-21 22:44:34'),
(2, 'Poção de Mana', 'Restaura 30 pontos de mana/fúria', 'consumivel', 150, '{\"tipo\": \"mana\", \"valor\": 30}', -1, 'E', '2025-10-21 22:44:34'),
(3, 'Núcleo de Eco Comum', 'Chance de recrutar um Eco Rank E-D', 'especial', 500, '{\"tipo\": \"nucleo_eco\", \"rank\": \"E\"}', 5, 'E', '2025-10-21 22:44:34'),
(4, 'Elixir de Força', '+2 de Força por 1 hora', 'consumivel', 300, '{\"tipo\": \"buff\", \"atributo\": \"str\", \"valor\": 2, \"duracao\": 3600}', 3, 'E', '2025-10-21 22:44:34'),
(5, 'Poção de Cura Média', 'Restaura 100 pontos de vida', 'consumivel', 200, '{\"tipo\": \"cura\", \"valor\": 100}', -1, 'E', '2025-10-21 22:44:34'),
(6, 'Núcleo de Eco Raro', 'Chance de recrutar um Eco Rank C-B', 'especial', 1000, '{\"tipo\": \"nucleo_eco\", \"rank\": \"C\"}', 2, 'E', '2025-10-21 22:44:34');

-- --------------------------------------------------------

--
-- Estrutura para tabela `monstros_base`
--

CREATE TABLE `monstros_base` (
  `id` int(11) NOT NULL,
  `nome` varchar(50) NOT NULL,
  `rank_monstro` varchar(5) NOT NULL COMMENT 'E, D, C, B, A, S',
  `hp_base` int(11) NOT NULL,
  `str_base` int(11) NOT NULL,
  `dex_base` int(11) NOT NULL,
  `con_base` int(11) NOT NULL,
  `dano_min_base` int(11) DEFAULT 5,
  `dano_max_base` int(11) DEFAULT 10,
  `xp_recompensa` int(11) DEFAULT 50,
  `ouro_recompensa_min` int(11) DEFAULT 1,
  `ouro_recompensa_max` int(11) DEFAULT 10
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Despejando dados para a tabela `monstros_base`
--

INSERT INTO `monstros_base` (`id`, `nome`, `rank_monstro`, `hp_base`, `str_base`, `dex_base`, `con_base`, `dano_min_base`, `dano_max_base`, `xp_recompensa`, `ouro_recompensa_min`, `ouro_recompensa_max`) VALUES
(1, 'Slime de Mana Fraco', 'E', 80, 5, 10, 10, 8, 12, 100, 5, 15),
(2, 'Goblin Batedor', 'D', 150, 12, 14, 12, 15, 25, 250, 20, 50),
(3, 'Esqueleto Guerreiro', 'C', 250, 15, 12, 18, 20, 30, 400, 40, 80);

-- --------------------------------------------------------

--
-- Estrutura para tabela `npcs_base`
--

CREATE TABLE `npcs_base` (
  `id` int(11) NOT NULL,
  `nome` varchar(100) NOT NULL,
  `descricao` text DEFAULT NULL,
  `icone` varchar(10) DEFAULT '?',
  `faccao` enum('guilda','faccao_oculta','neutro') DEFAULT 'neutro',
  `localizacao` varchar(50) DEFAULT 'cidade_central',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Despejando dados para a tabela `npcs_base`
--

INSERT INTO `npcs_base` (`id`, `nome`, `descricao`, `icone`, `faccao`, `localizacao`, `created_at`) VALUES
(5, 'Comandante Hórus', 'Líder da Guilda do Conselho, busca estabilidade acima de tudo', '👑', 'guilda', 'cidade_central', '2025-10-22 02:08:57'),
(6, 'Sombra da Facção', 'Misterioso representante da facção oculta, oferece poder rápido', '🌑', 'faccao_oculta', 'cidade_central', '2025-10-22 02:08:57'),
(7, 'Arcanista Elara', 'Pesquisadora que estuda a Fenda Arcana', '🔮', 'neutro', 'cidade_central', '2025-10-22 02:08:57'),
(8, 'Mestre Kaito', 'Veterano que treina novos Caçadores', '🥋', 'guilda', 'cidade_central', '2025-10-22 02:08:57'),
(9, 'Mercador Jin', 'Comerciante de itens raros e informações', '💰', 'neutro', 'cidade_central', '2025-10-22 02:08:57');

-- --------------------------------------------------------

--
-- Estrutura para tabela `npc_dialogos`
--

CREATE TABLE `npc_dialogos` (
  `id` int(11) NOT NULL,
  `npc_id` int(11) NOT NULL,
  `dialogo_texto` text NOT NULL,
  `tipo` enum('pergunta','resposta','narrativa') NOT NULL,
  `requisitos` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`requisitos`)),
  `acao_trigger` varchar(50) DEFAULT NULL,
  `acao_valor` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`acao_valor`)),
  `proximo_dialogo_id` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Despejando dados para a tabela `npc_dialogos`
--

INSERT INTO `npc_dialogos` (`id`, `npc_id`, `dialogo_texto`, `tipo`, `requisitos`, `acao_trigger`, `acao_valor`, `proximo_dialogo_id`) VALUES
(1, 5, 'Comandante Hórus: Vejo potencial em você. A Guilda precisa de aliados fortes.', '', NULL, NULL, NULL, 2),
(2, 5, 'Hórus: Encontramos refugiados presos ou cristais valiosos. O que escolhe?', '', NULL, NULL, NULL, NULL),
(3, 5, 'Salvar os refugiados.', '', NULL, 'mudar_reputacao', '{\"guilda\": 15}', 4),
(4, 5, 'Coletar os cristais.', '', NULL, 'mudar_reputacao', '{\"guilda\": -10}', 5),
(5, 5, 'Hórus: Nobre escolha! A Guilda recompensa sua compaixão.', '', NULL, 'ganhar_ouro', '{\"quantidade\": 500}', NULL),
(6, 5, 'Hórus: Recursos estratégicos... que sejam úteis para a causa.', '', NULL, 'ganhar_ouro', '{\"quantidade\": 300}', NULL);

-- --------------------------------------------------------

--
-- Estrutura para tabela `npc_reputacao`
--

CREATE TABLE `npc_reputacao` (
  `id` int(11) NOT NULL,
  `player_id` int(11) NOT NULL,
  `npc_id` int(11) NOT NULL,
  `faccao_id` int(11) DEFAULT NULL,
  `reputacao` int(11) DEFAULT 0,
  `relacionamento` enum('inimigo','hostil','neutro','aliado','idolo') DEFAULT 'neutro',
  `data_ultima_interacao` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Estrutura para tabela `personagem_ecos`
--

CREATE TABLE `personagem_ecos` (
  `id` int(11) NOT NULL,
  `id_personagem` int(11) NOT NULL,
  `id_eco_base` int(11) NOT NULL,
  `nome_personalizado` varchar(50) DEFAULT NULL,
  `status_eco` varchar(20) NOT NULL DEFAULT 'Descansando' COMMENT 'Descansando, Em Missao',
  `tempo_retorno_missao` datetime DEFAULT NULL COMMENT 'A hora exata que a missão termina',
  `affinity_level` int(11) DEFAULT 1,
  `affinity_xp` int(11) DEFAULT 0,
  `habilidades_desbloqueadas` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`habilidades_desbloqueadas`))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Despejando dados para a tabela `personagem_ecos`
--

INSERT INTO `personagem_ecos` (`id`, `id_personagem`, `id_eco_base`, `nome_personalizado`, `status_eco`, `tempo_retorno_missao`, `affinity_level`, `affinity_xp`, `habilidades_desbloqueadas`) VALUES
(1, 1, 1, NULL, 'Em Missao', '2025-10-21 23:13:16', 1, 0, NULL);

-- --------------------------------------------------------

--
-- Estrutura para tabela `personagem_skills`
--

CREATE TABLE `personagem_skills` (
  `id` int(11) NOT NULL,
  `id_personagem` int(11) NOT NULL,
  `id_skill_base` int(11) NOT NULL,
  `skill_level` int(11) DEFAULT 1,
  `cooldown_restante` int(11) DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Despejando dados para a tabela `personagem_skills`
--

INSERT INTO `personagem_skills` (`id`, `id_personagem`, `id_skill_base`, `skill_level`, `cooldown_restante`) VALUES
(1, 1, 1, 2, 0),
(2, 3, 2, 3, 0);

-- --------------------------------------------------------

--
-- Estrutura para tabela `personagens`
--

CREATE TABLE `personagens` (
  `id` int(11) NOT NULL,
  `nome` varchar(50) NOT NULL,
  `email` varchar(100) NOT NULL,
  `senha_hash` varchar(255) NOT NULL,
  `level` int(11) DEFAULT 1,
  `xp_atual` int(11) DEFAULT 0,
  `xp_proximo_level` int(11) DEFAULT 1000,
  `classe_base` varchar(20) NOT NULL,
  `subclasse` varchar(30) DEFAULT NULL,
  `fama_rank` varchar(10) DEFAULT 'E',
  `str` int(11) DEFAULT 10,
  `dex` int(11) DEFAULT 10,
  `con` int(11) DEFAULT 10,
  `int_stat` int(11) DEFAULT 10,
  `wis` int(11) DEFAULT 10,
  `cha` int(11) DEFAULT 10,
  `hp_atual` int(11) DEFAULT 100,
  `hp_max` int(11) DEFAULT 100,
  `mana_atual` int(11) DEFAULT 50,
  `mana_max` int(11) DEFAULT 50,
  `ouro` int(11) DEFAULT 0,
  `pontos_atributo_disponiveis` int(11) DEFAULT 3,
  `pontos_habilidade_disponiveis` int(11) DEFAULT 0,
  `votos_ativos` text DEFAULT NULL,
  `data_criacao` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Despejando dados para a tabela `personagens`
--

INSERT INTO `personagens` (`id`, `nome`, `email`, `senha_hash`, `level`, `xp_atual`, `xp_proximo_level`, `classe_base`, `subclasse`, `fama_rank`, `str`, `dex`, `con`, `int_stat`, `wis`, `cha`, `hp_atual`, `hp_max`, `mana_atual`, `mana_max`, `ouro`, `pontos_atributo_disponiveis`, `pontos_habilidade_disponiveis`, `votos_ativos`, `data_criacao`) VALUES
(1, 'Kvothe', 'azirmatheus@gmail.com', '$2y$10$IOorHbf5j1Y.R/JOh5TRJuKh5argggdO5SiWT47VwVIjdR7Vzxrj.', 12, 2300, 12000, 'Guerreiro', 'Mestre de Armas', 'E', 31, 16, 30, 10, 10, 10, 57, 1092, 5, 267, 1, 0, 1, NULL, '2025-10-21 12:08:55'),
(3, 'Kvothe2', 'matheus_azir@hotmail.com', '$2y$10$XudUx9B.nPYhKrvMiWAHUOMytswfG4EA.W1Q2NcI0PHbIecKgOVna', 2, 100, 2000, 'Mago', NULL, 'E', 10, 15, 14, 19, 10, 10, 1, 192, 75, 320, 90, 0, 1, NULL, '2025-10-21 14:17:24');

-- --------------------------------------------------------

--
-- Estrutura para tabela `player_achievements`
--

CREATE TABLE `player_achievements` (
  `id` int(11) NOT NULL,
  `player_id` int(11) NOT NULL,
  `achievement_id` int(11) NOT NULL,
  `progresso_atual` int(11) DEFAULT 0,
  `desbloqueada` tinyint(1) DEFAULT 0,
  `data_desbloqueio` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Estrutura para tabela `player_combo_progress`
--

CREATE TABLE `player_combo_progress` (
  `id` int(11) NOT NULL,
  `player_id` int(11) NOT NULL,
  `combo_id` int(11) NOT NULL,
  `sequencia_atual` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT '[]' CHECK (json_valid(`sequencia_atual`)),
  `turno_inicio` int(11) NOT NULL,
  `expira_em_turno` int(11) NOT NULL,
  `ativo` tinyint(1) DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Estrutura para tabela `player_conquistas`
--

CREATE TABLE `player_conquistas` (
  `id` int(11) NOT NULL,
  `player_id` int(11) NOT NULL,
  `conquista_id` int(11) NOT NULL,
  `data_conquista` timestamp NOT NULL DEFAULT current_timestamp(),
  `progresso_atual` int(11) DEFAULT 0,
  `completada` tinyint(1) DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Estrutura para tabela `player_daily_quests`
--

CREATE TABLE `player_daily_quests` (
  `id` int(11) NOT NULL,
  `player_id` int(11) NOT NULL,
  `quest_id` int(11) NOT NULL,
  `progresso_atual` int(11) DEFAULT 0,
  `completada` tinyint(1) DEFAULT 0,
  `data_ativacao` date NOT NULL,
  `data_completada` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Estrutura para tabela `player_dungeons_ativas`
--

CREATE TABLE `player_dungeons_ativas` (
  `id` int(11) NOT NULL,
  `player_id` int(11) NOT NULL,
  `dungeon_id` varchar(100) NOT NULL,
  `dungeon_data` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL CHECK (json_valid(`dungeon_data`)),
  `progresso` int(11) DEFAULT 0,
  `tempo_inicio` timestamp NOT NULL DEFAULT current_timestamp(),
  `tempo_limite` timestamp NULL DEFAULT NULL,
  `completada` tinyint(1) DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Estrutura para tabela `player_dungeon_history`
--

CREATE TABLE `player_dungeon_history` (
  `id` int(11) NOT NULL,
  `player_id` int(11) NOT NULL,
  `dungeon_nome` varchar(100) NOT NULL,
  `dificuldade` varchar(20) NOT NULL,
  `sucesso` tinyint(1) DEFAULT 0,
  `recompensa_ouro` int(11) DEFAULT 0,
  `recompensa_xp` int(11) DEFAULT 0,
  `itens_obtidos` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`itens_obtidos`)),
  `tempo_conclusao` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Estrutura para tabela `player_escolhas_dialogo`
--

CREATE TABLE `player_escolhas_dialogo` (
  `id` int(11) NOT NULL,
  `player_id` int(11) NOT NULL,
  `dialogo_id` int(11) NOT NULL,
  `escolha_feita` text NOT NULL,
  `data_escolha` timestamp NOT NULL DEFAULT current_timestamp(),
  `consequencia_ativa` tinyint(1) DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Estrutura para tabela `player_exploracao`
--

CREATE TABLE `player_exploracao` (
  `id` int(11) NOT NULL,
  `player_id` int(11) NOT NULL,
  `regiao` varchar(50) NOT NULL,
  `data_descoberta` timestamp NOT NULL DEFAULT current_timestamp(),
  `progresso` int(11) DEFAULT 0,
  `conquistada` tinyint(1) DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Despejando dados para a tabela `player_exploracao`
--

INSERT INTO `player_exploracao` (`id`, `player_id`, `regiao`, `data_descoberta`, `progresso`, `conquistada`) VALUES
(1, 1, 'floresta_corrompida', '2025-10-22 03:12:06', 100, 1),
(2, 3, 'floresta_corrompida', '2025-10-22 03:12:06', 100, 1);

-- --------------------------------------------------------

--
-- Estrutura para tabela `player_ultimate_abilities`
--

CREATE TABLE `player_ultimate_abilities` (
  `id` int(11) NOT NULL,
  `player_id` int(11) NOT NULL,
  `ultimate_id` int(11) NOT NULL,
  `desbloqueada` tinyint(1) DEFAULT 0,
  `cooldown_restante` int(11) DEFAULT 0,
  `usos_totais` int(11) DEFAULT 0,
  `data_desbloqueio` timestamp NULL DEFAULT NULL,
  `data_ultimo_uso` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Despejando dados para a tabela `player_ultimate_abilities`
--

INSERT INTO `player_ultimate_abilities` (`id`, `player_id`, `ultimate_id`, `desbloqueada`, `cooldown_restante`, `usos_totais`, `data_desbloqueio`, `data_ultimo_uso`) VALUES
(1, 1, 7, 1, 0, 0, NULL, NULL);

-- --------------------------------------------------------

--
-- Estrutura para tabela `skills_base`
--

CREATE TABLE `skills_base` (
  `id` int(11) NOT NULL,
  `nome` varchar(50) NOT NULL,
  `descricao` text DEFAULT NULL,
  `classe_req` varchar(50) DEFAULT NULL,
  `custo_ph` int(11) DEFAULT 1,
  `custo_ph_upgrade` int(11) DEFAULT 2,
  `aplica_status` int(11) DEFAULT NULL,
  `condicoes_uso` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`condicoes_uso`)),
  `custo_mana` int(11) DEFAULT 0,
  `dano_base` int(11) DEFAULT 0,
  `dano_base_por_level` int(11) DEFAULT 5,
  `multiplicador_atributo` float DEFAULT 1,
  `multiplicador_por_level` float DEFAULT 0.1,
  `atributo_principal` varchar(10) DEFAULT 'str',
  `cooldown_turnos` int(11) DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Despejando dados para a tabela `skills_base`
--

INSERT INTO `skills_base` (`id`, `nome`, `descricao`, `classe_req`, `custo_ph`, `custo_ph_upgrade`, `aplica_status`, `condicoes_uso`, `custo_mana`, `dano_base`, `dano_base_por_level`, `multiplicador_atributo`, `multiplicador_por_level`, `atributo_principal`, `cooldown_turnos`) VALUES
(1, 'Golpe Poderoso', 'Um ataque focado que usa Fúria para causar dano massivo baseado em STR.', 'Guerreiro', 1, 2, NULL, NULL, 10, 20, 10, 1.5, 0.2, 'str', 3),
(2, 'Seta de Fogo', 'Um projétil mágico básico que causa dano baseado em INT.', 'Mago', 1, 2, NULL, NULL, 15, 25, 5, 1.2, 0.1, 'int_stat', 2),
(3, 'Último Recurso', 'Ataque desesperado quando a situação é crítica. Usa HP como custo.', 'Guerreiro', 3, 2, NULL, '{\"hp_minimo\": 30, \"recursos_alternativos\": \"hp_custo\"}', 0, 100, 5, 2, 0.1, 'str', 0),
(4, 'Ataque Surpresa', 'Golpe rápido apenas nos primeiros turnos do combate.', 'Guerreiro', 2, 1, NULL, '{\"turno_maximo\": 3}', 20, 40, 5, 1.5, 0.1, 'dex', 0),
(5, 'Golpe Finalizador', 'Dano massivo contra inimigos enfraquecidos.', 'Guerreiro', 4, 3, NULL, '{\"alvo_hp_minimo\": 25}', 50, 60, 5, 2.5, 0.1, 'str', 0),
(6, 'Fúria Desesperada', 'Quando a mana acaba, a fúria toma conta.', 'Guerreiro', 3, 2, NULL, '{\"mana_minimo\": 15}', 0, 80, 5, 1.8, 0.1, 'str', 0),
(7, 'Explosão Arcana', 'Libera poder arcano acumulado em situações desesperadoras.', 'Mago', 5, 4, NULL, '{\"hp_minimo\": 40}', 80, 120, 5, 2.2, 0.1, 'int_stat', 0),
(8, 'Cura do Desespero', 'Cura massiva quando o aliado está à beira da morte.', 'Sacerdote', 4, 3, NULL, '{\"hp_minimo\": 20}', 60, 0, 5, 1.5, 0.1, 'wis', 0);

-- --------------------------------------------------------

--
-- Estrutura para tabela `status_effects_base`
--

CREATE TABLE `status_effects_base` (
  `id` int(11) NOT NULL,
  `nome` varchar(50) NOT NULL,
  `descricao` text NOT NULL,
  `tipo` enum('dano','controle','buff','debuff') NOT NULL,
  `duracao_base` int(11) NOT NULL,
  `valor_efeito` int(11) DEFAULT NULL,
  `atributo_afetado` enum('hp','mana','str','dex','con','int_stat','wis','cha','velocidade') DEFAULT NULL,
  `modificador` int(11) DEFAULT NULL,
  `icone` varchar(10) NOT NULL,
  `cor` varchar(7) DEFAULT '#FFFFFF'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Estrutura para tabela `subclasses_base`
--

CREATE TABLE `subclasses_base` (
  `id` int(11) NOT NULL,
  `nome` varchar(50) NOT NULL,
  `descricao` text NOT NULL,
  `classe_base_req` varchar(50) NOT NULL COMMENT 'Ex: Guerreiro, Mago'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Despejando dados para a tabela `subclasses_base`
--

INSERT INTO `subclasses_base` (`id`, `nome`, `descricao`, `classe_base_req`) VALUES
(1, 'Mestre de Armas', 'Foco em dano explosivo e crítico (DPS Burst).', 'Guerreiro'),
(2, 'Cavaleiro da Rocha', 'Foco em defesa absoluta e provocação (Tank).', 'Guerreiro'),
(3, 'Sombra Veloz', 'Foco em furtividade e dano massivo em um único alvo (Assassin).', 'Ladino'),
(4, 'Mestre do Engano', 'Foco em debuffs sociais e controle (Bard/Illusionist).', 'Ladino'),
(5, 'Arquimago Elemental', 'Dano massivo focado em elementos (Fogo/Gelo/Vento).', 'Mago'),
(6, 'Convocador Arcano', 'Foco em invocação de Ecos/Sombras (Summoner).', 'Mago'),
(7, 'Curandeiro Devoto', 'Magias de cura pura e proteção (Healer).', 'Sacerdote'),
(8, 'Votado à Sombra', 'Suporte via debuffs, maldições e sacrifícios (Warlock/Voto).', 'Sacerdote');

-- --------------------------------------------------------

--
-- Estrutura para tabela `ultimate_abilities_base`
--

CREATE TABLE `ultimate_abilities_base` (
  `id` int(11) NOT NULL,
  `nome` varchar(100) NOT NULL,
  `descricao` text NOT NULL,
  `custo_mana` int(11) NOT NULL,
  `cooldown_turnos` int(11) NOT NULL,
  `tipo` enum('dano','cura','controle','suporte','transformacao') NOT NULL,
  `alvo` enum('unico','area','self','aliados') NOT NULL,
  `efeito_principal` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL CHECK (json_valid(`efeito_principal`)),
  `efeito_secundario` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`efeito_secundario`)),
  `condicoes_desbloqueio` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL CHECK (json_valid(`condicoes_desbloqueio`)),
  `nivel_requerido` int(11) DEFAULT 15,
  `classe_req` varchar(50) DEFAULT NULL,
  `icone` varchar(10) NOT NULL,
  `cor_efeito` varchar(7) DEFAULT '#FFFFFF',
  `data_criacao` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Despejando dados para a tabela `ultimate_abilities_base`
--

INSERT INTO `ultimate_abilities_base` (`id`, `nome`, `descricao`, `custo_mana`, `cooldown_turnos`, `tipo`, `alvo`, `efeito_principal`, `efeito_secundario`, `condicoes_desbloqueio`, `nivel_requerido`, `classe_req`, `icone`, `cor_efeito`, `data_criacao`) VALUES
(1, 'Fúria do Titã', '0', 100, 8, 'dano', 'area', '{\"dano_base\": 200, \"multiplicador\": 3.0, \"dano_extra\": 50, \"duracao\": 2}', '{\"status_aplicado\": 4, \"chance\": 80}', '{\"nivel\": 15, \"classe\": \"Guerreiro\", \"habilidades_aprendidas\": 10}', 15, '0', '👹', '#FF6B35', '2025-10-22 01:26:51'),
(2, 'Parede Indestrutível', '0', 80, 6, 'suporte', 'self', '{\"protecao\": 100, \"duracao\": 2, \"mitigacao_extra\": 25}', '{\"cura_turno\": 15, \"duracao_cura\": 3}', '{\"nivel\": 12, \"classe\": \"Guerreiro\", \"hp_maximo\": 1500}', 12, '0', '🛡️', '#3A86FF', '2025-10-22 01:26:51'),
(3, 'Tempestade Arcana', '0', 150, 10, 'dano', 'area', '{\"dano_base\": 300, \"multiplicador\": 4.0, \"ignora_defesa\": true}', '{\"status_aplicado\": 1, \"intensidade\": 3, \"duracao\": 4}', '{\"nivel\": 18, \"classe\": \"Mago\", \"mana_maximo\": 800}', 18, '0', '🌩️', '#8A2BE2', '2025-10-22 01:26:51'),
(4, 'Controle Temporal', '0', 120, 12, 'suporte', 'self', '{\"turno_extra\": true, \"reset_cooldown\": true}', '{\"buff_atributos\": {\"dex\": 10, \"int_stat\": 10}, \"duracao\": 3}', '{\"nivel\": 20, \"classe\": \"Mago\", \"inteligencia\": 50}', 20, '0', '⏰', '#FFD166', '2025-10-22 01:26:51'),
(5, 'Renascimento da Fênix', '0', 200, 15, 'cura', 'self', '{\"ressuscitar\": true, \"hp_restaurado\": 100, \"mana_restaurado\": 100}', '{\"buff_atributos\": {\"con\": 15, \"wis\": 15}, \"duracao\": 5}', '{\"nivel\": 25, \"classe\": \"Sacerdote\", \"mortes\": 5}', 25, '0', '🔥', '#EF476F', '2025-10-22 01:26:51'),
(6, 'Chuva de Cura Celestial', '0', 120, 8, 'cura', 'aliados', '{\"cura_base\": 150, \"multiplicador\": 2.5, \"cura_extra\": 50}', '{\"remove_debuffs\": true, \"buff_regeneracao\": 20}', '{\"nivel\": 16, \"classe\": \"Sacerdote\", \"habilidades_cura\": 5}', 16, '0', '🌧️', '#38B000', '2025-10-22 01:26:51'),
(7, 'Explosão de Poder', '0', 90, 7, 'transformacao', 'self', '{\"dano_dobrado\": true, \"duracao\": 3, \"velocidade_extra\": 20}', '{\"custo_hp_turno\": 10, \"buff_critico\": 25}', '{\"nivel\": 10}', 10, NULL, '💥', '#FF9E00', '2025-10-22 01:26:51');

--
-- Índices para tabelas despejadas
--

--
-- Índices de tabela `achievements_base`
--
ALTER TABLE `achievements_base`
  ADD PRIMARY KEY (`id`);

--
-- Índices de tabela `combate_status_ativos`
--
ALTER TABLE `combate_status_ativos`
  ADD PRIMARY KEY (`id`),
  ADD KEY `status_id` (`status_id`);

--
-- Índices de tabela `combos_base`
--
ALTER TABLE `combos_base`
  ADD PRIMARY KEY (`id`);

--
-- Índices de tabela `conquistas`
--
ALTER TABLE `conquistas`
  ADD PRIMARY KEY (`id`);

--
-- Índices de tabela `daily_quests_base`
--
ALTER TABLE `daily_quests_base`
  ADD PRIMARY KEY (`id`);

--
-- Índices de tabela `ecos_base`
--
ALTER TABLE `ecos_base`
  ADD PRIMARY KEY (`id`);

--
-- Índices de tabela `eco_habilidades_base`
--
ALTER TABLE `eco_habilidades_base`
  ADD PRIMARY KEY (`id`),
  ADD KEY `id_eco_base` (`id_eco_base`);

--
-- Índices de tabela `inventario`
--
ALTER TABLE `inventario`
  ADD PRIMARY KEY (`id`),
  ADD KEY `id_personagem` (`id_personagem`),
  ADD KEY `id_item_base` (`id_item_base`);

--
-- Índices de tabela `itens_base`
--
ALTER TABLE `itens_base`
  ADD PRIMARY KEY (`id`);

--
-- Índices de tabela `loja_itens`
--
ALTER TABLE `loja_itens`
  ADD PRIMARY KEY (`id`);

--
-- Índices de tabela `monstros_base`
--
ALTER TABLE `monstros_base`
  ADD PRIMARY KEY (`id`);

--
-- Índices de tabela `npcs_base`
--
ALTER TABLE `npcs_base`
  ADD PRIMARY KEY (`id`);

--
-- Índices de tabela `npc_dialogos`
--
ALTER TABLE `npc_dialogos`
  ADD PRIMARY KEY (`id`),
  ADD KEY `npc_id` (`npc_id`);

--
-- Índices de tabela `npc_reputacao`
--
ALTER TABLE `npc_reputacao`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_player_npc` (`player_id`,`npc_id`),
  ADD KEY `npc_id` (`npc_id`);

--
-- Índices de tabela `personagem_ecos`
--
ALTER TABLE `personagem_ecos`
  ADD PRIMARY KEY (`id`),
  ADD KEY `id_personagem` (`id_personagem`),
  ADD KEY `id_eco_base` (`id_eco_base`);

--
-- Índices de tabela `personagem_skills`
--
ALTER TABLE `personagem_skills`
  ADD PRIMARY KEY (`id`),
  ADD KEY `id_personagem` (`id_personagem`),
  ADD KEY `id_skill_base` (`id_skill_base`);

--
-- Índices de tabela `personagens`
--
ALTER TABLE `personagens`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `nome` (`nome`),
  ADD UNIQUE KEY `email` (`email`);

--
-- Índices de tabela `player_achievements`
--
ALTER TABLE `player_achievements`
  ADD PRIMARY KEY (`id`),
  ADD KEY `player_id` (`player_id`),
  ADD KEY `achievement_id` (`achievement_id`);

--
-- Índices de tabela `player_combo_progress`
--
ALTER TABLE `player_combo_progress`
  ADD PRIMARY KEY (`id`),
  ADD KEY `player_id` (`player_id`),
  ADD KEY `combo_id` (`combo_id`);

--
-- Índices de tabela `player_conquistas`
--
ALTER TABLE `player_conquistas`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_player_conquista` (`player_id`,`conquista_id`),
  ADD KEY `conquista_id` (`conquista_id`);

--
-- Índices de tabela `player_daily_quests`
--
ALTER TABLE `player_daily_quests`
  ADD PRIMARY KEY (`id`),
  ADD KEY `player_id` (`player_id`),
  ADD KEY `quest_id` (`quest_id`);

--
-- Índices de tabela `player_dungeons_ativas`
--
ALTER TABLE `player_dungeons_ativas`
  ADD PRIMARY KEY (`id`),
  ADD KEY `player_id` (`player_id`);

--
-- Índices de tabela `player_dungeon_history`
--
ALTER TABLE `player_dungeon_history`
  ADD PRIMARY KEY (`id`),
  ADD KEY `player_id` (`player_id`);

--
-- Índices de tabela `player_escolhas_dialogo`
--
ALTER TABLE `player_escolhas_dialogo`
  ADD PRIMARY KEY (`id`),
  ADD KEY `dialogo_id` (`dialogo_id`);

--
-- Índices de tabela `player_exploracao`
--
ALTER TABLE `player_exploracao`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_player_regiao` (`player_id`,`regiao`);

--
-- Índices de tabela `player_ultimate_abilities`
--
ALTER TABLE `player_ultimate_abilities`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_player_ultimate` (`player_id`,`ultimate_id`),
  ADD KEY `ultimate_id` (`ultimate_id`);

--
-- Índices de tabela `skills_base`
--
ALTER TABLE `skills_base`
  ADD PRIMARY KEY (`id`),
  ADD KEY `aplica_status` (`aplica_status`);

--
-- Índices de tabela `status_effects_base`
--
ALTER TABLE `status_effects_base`
  ADD PRIMARY KEY (`id`);

--
-- Índices de tabela `subclasses_base`
--
ALTER TABLE `subclasses_base`
  ADD PRIMARY KEY (`id`);

--
-- Índices de tabela `ultimate_abilities_base`
--
ALTER TABLE `ultimate_abilities_base`
  ADD PRIMARY KEY (`id`);

--
-- AUTO_INCREMENT para tabelas despejadas
--

--
-- AUTO_INCREMENT de tabela `achievements_base`
--
ALTER TABLE `achievements_base`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT de tabela `combate_status_ativos`
--
ALTER TABLE `combate_status_ativos`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de tabela `combos_base`
--
ALTER TABLE `combos_base`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT de tabela `conquistas`
--
ALTER TABLE `conquistas`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT de tabela `daily_quests_base`
--
ALTER TABLE `daily_quests_base`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT de tabela `ecos_base`
--
ALTER TABLE `ecos_base`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT de tabela `eco_habilidades_base`
--
ALTER TABLE `eco_habilidades_base`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- AUTO_INCREMENT de tabela `inventario`
--
ALTER TABLE `inventario`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=36;

--
-- AUTO_INCREMENT de tabela `itens_base`
--
ALTER TABLE `itens_base`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=10;

--
-- AUTO_INCREMENT de tabela `loja_itens`
--
ALTER TABLE `loja_itens`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT de tabela `monstros_base`
--
ALTER TABLE `monstros_base`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT de tabela `npcs_base`
--
ALTER TABLE `npcs_base`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=10;

--
-- AUTO_INCREMENT de tabela `npc_dialogos`
--
ALTER TABLE `npc_dialogos`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT de tabela `npc_reputacao`
--
ALTER TABLE `npc_reputacao`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de tabela `personagem_ecos`
--
ALTER TABLE `personagem_ecos`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT de tabela `personagem_skills`
--
ALTER TABLE `personagem_skills`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT de tabela `personagens`
--
ALTER TABLE `personagens`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT de tabela `player_achievements`
--
ALTER TABLE `player_achievements`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de tabela `player_combo_progress`
--
ALTER TABLE `player_combo_progress`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de tabela `player_conquistas`
--
ALTER TABLE `player_conquistas`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de tabela `player_daily_quests`
--
ALTER TABLE `player_daily_quests`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de tabela `player_dungeons_ativas`
--
ALTER TABLE `player_dungeons_ativas`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de tabela `player_dungeon_history`
--
ALTER TABLE `player_dungeon_history`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de tabela `player_escolhas_dialogo`
--
ALTER TABLE `player_escolhas_dialogo`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- AUTO_INCREMENT de tabela `player_exploracao`
--
ALTER TABLE `player_exploracao`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT de tabela `player_ultimate_abilities`
--
ALTER TABLE `player_ultimate_abilities`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT de tabela `skills_base`
--
ALTER TABLE `skills_base`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- AUTO_INCREMENT de tabela `status_effects_base`
--
ALTER TABLE `status_effects_base`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de tabela `subclasses_base`
--
ALTER TABLE `subclasses_base`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- AUTO_INCREMENT de tabela `ultimate_abilities_base`
--
ALTER TABLE `ultimate_abilities_base`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- Restrições para tabelas despejadas
--

--
-- Restrições para tabelas `combate_status_ativos`
--
ALTER TABLE `combate_status_ativos`
  ADD CONSTRAINT `combate_status_ativos_ibfk_1` FOREIGN KEY (`status_id`) REFERENCES `status_effects_base` (`id`);

--
-- Restrições para tabelas `eco_habilidades_base`
--
ALTER TABLE `eco_habilidades_base`
  ADD CONSTRAINT `eco_habilidades_base_ibfk_1` FOREIGN KEY (`id_eco_base`) REFERENCES `ecos_base` (`id`);

--
-- Restrições para tabelas `inventario`
--
ALTER TABLE `inventario`
  ADD CONSTRAINT `inventario_ibfk_1` FOREIGN KEY (`id_personagem`) REFERENCES `personagens` (`id`),
  ADD CONSTRAINT `inventario_ibfk_2` FOREIGN KEY (`id_item_base`) REFERENCES `itens_base` (`id`);

--
-- Restrições para tabelas `npc_dialogos`
--
ALTER TABLE `npc_dialogos`
  ADD CONSTRAINT `npc_dialogos_ibfk_1` FOREIGN KEY (`npc_id`) REFERENCES `npcs_base` (`id`) ON DELETE CASCADE;

--
-- Restrições para tabelas `npc_reputacao`
--
ALTER TABLE `npc_reputacao`
  ADD CONSTRAINT `npc_reputacao_ibfk_1` FOREIGN KEY (`npc_id`) REFERENCES `npcs_base` (`id`) ON DELETE CASCADE;

--
-- Restrições para tabelas `personagem_ecos`
--
ALTER TABLE `personagem_ecos`
  ADD CONSTRAINT `personagem_ecos_ibfk_1` FOREIGN KEY (`id_personagem`) REFERENCES `personagens` (`id`),
  ADD CONSTRAINT `personagem_ecos_ibfk_2` FOREIGN KEY (`id_eco_base`) REFERENCES `ecos_base` (`id`);

--
-- Restrições para tabelas `personagem_skills`
--
ALTER TABLE `personagem_skills`
  ADD CONSTRAINT `personagem_skills_ibfk_1` FOREIGN KEY (`id_personagem`) REFERENCES `personagens` (`id`),
  ADD CONSTRAINT `personagem_skills_ibfk_2` FOREIGN KEY (`id_skill_base`) REFERENCES `skills_base` (`id`);

--
-- Restrições para tabelas `player_achievements`
--
ALTER TABLE `player_achievements`
  ADD CONSTRAINT `player_achievements_ibfk_1` FOREIGN KEY (`player_id`) REFERENCES `personagens` (`id`),
  ADD CONSTRAINT `player_achievements_ibfk_2` FOREIGN KEY (`achievement_id`) REFERENCES `achievements_base` (`id`);

--
-- Restrições para tabelas `player_combo_progress`
--
ALTER TABLE `player_combo_progress`
  ADD CONSTRAINT `player_combo_progress_ibfk_1` FOREIGN KEY (`player_id`) REFERENCES `personagens` (`id`),
  ADD CONSTRAINT `player_combo_progress_ibfk_2` FOREIGN KEY (`combo_id`) REFERENCES `combos_base` (`id`);

--
-- Restrições para tabelas `player_conquistas`
--
ALTER TABLE `player_conquistas`
  ADD CONSTRAINT `player_conquistas_ibfk_1` FOREIGN KEY (`player_id`) REFERENCES `personagens` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `player_conquistas_ibfk_2` FOREIGN KEY (`conquista_id`) REFERENCES `conquistas` (`id`);

--
-- Restrições para tabelas `player_daily_quests`
--
ALTER TABLE `player_daily_quests`
  ADD CONSTRAINT `player_daily_quests_ibfk_1` FOREIGN KEY (`player_id`) REFERENCES `personagens` (`id`),
  ADD CONSTRAINT `player_daily_quests_ibfk_2` FOREIGN KEY (`quest_id`) REFERENCES `daily_quests_base` (`id`);

--
-- Restrições para tabelas `player_dungeons_ativas`
--
ALTER TABLE `player_dungeons_ativas`
  ADD CONSTRAINT `player_dungeons_ativas_ibfk_1` FOREIGN KEY (`player_id`) REFERENCES `personagens` (`id`) ON DELETE CASCADE;

--
-- Restrições para tabelas `player_dungeon_history`
--
ALTER TABLE `player_dungeon_history`
  ADD CONSTRAINT `player_dungeon_history_ibfk_1` FOREIGN KEY (`player_id`) REFERENCES `personagens` (`id`) ON DELETE CASCADE;

--
-- Restrições para tabelas `player_escolhas_dialogo`
--
ALTER TABLE `player_escolhas_dialogo`
  ADD CONSTRAINT `player_escolhas_dialogo_ibfk_1` FOREIGN KEY (`dialogo_id`) REFERENCES `npc_dialogos` (`id`) ON DELETE CASCADE;

--
-- Restrições para tabelas `player_exploracao`
--
ALTER TABLE `player_exploracao`
  ADD CONSTRAINT `player_exploracao_ibfk_1` FOREIGN KEY (`player_id`) REFERENCES `personagens` (`id`) ON DELETE CASCADE;

--
-- Restrições para tabelas `player_ultimate_abilities`
--
ALTER TABLE `player_ultimate_abilities`
  ADD CONSTRAINT `player_ultimate_abilities_ibfk_1` FOREIGN KEY (`player_id`) REFERENCES `personagens` (`id`),
  ADD CONSTRAINT `player_ultimate_abilities_ibfk_2` FOREIGN KEY (`ultimate_id`) REFERENCES `ultimate_abilities_base` (`id`);

--
-- Restrições para tabelas `skills_base`
--
ALTER TABLE `skills_base`
  ADD CONSTRAINT `skills_base_ibfk_1` FOREIGN KEY (`aplica_status`) REFERENCES `status_effects_base` (`id`);
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
