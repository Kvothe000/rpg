

<div class="container fade-in">
    <!-- CABEÇALHO -->
    <div class="section section-arcane text-center">
        <h1 style="color: var(--accent-arcane); text-shadow: 0 0 20px var(--accent-arcane-glow);">
            ⚡ PERFIL DO CAÇADOR
        </h1>
        <p style="color: var(--text-secondary);">
            Sua jornada, seu poder, seu destino
        </p>
    </div>

    <!-- ALERTA DE DESPERTAR -->
    <?php if ($result_subclasses_disponiveis && $result_subclasses_disponiveis->num_rows > 0): ?>
    <div class="section section-awakening">
        <div class="awakening-alert">
            <div class="awakening-icon">🌌</div>
            <div class="awakening-content">
                <h3>O DESPERTAR AGUARDA</h3>
                <p>Seu poder atingiu o ápice. Escolha seu caminho - esta decisão moldará seu destino para sempre.</p>
            </div>
        </div>
        
        <div class="subclass-grid">
            <?php while($sub = $result_subclasses_disponiveis->fetch_assoc()): ?>
            <div class="subclass-card">
                <div class="subclass-header">
                    <h4><?php echo $sub['nome']; ?></h4>
                    <span class="subclass-badge">Nível 10</span>
                </div>
                <div class="subclass-description">
                    <?php echo $sub['descricao']; ?>
                </div>
                <div class="subclass-actions">
                    <a href="?acao=escolher_subclasse&subclasse_id=<?php echo $sub['id']; ?>" class="btn btn-awakening">
                        🌟 Escolher Caminho
                    </a>
                </div>
            </div>
            <?php endwhile; ?>
        </div>
    </div>
    <?php endif; ?>

    <!-- RESUMO DO PERSONAGEM -->
    <div class="grid-2-col">
        <div class="section section-vital">
            <h2 class="section-header vital">👤 STATUS PRINCIPAL</h2>
            <div class="character-main">
                <div class="char-identity">
                    <div class="char-avatar">
                        <div class="avatar-icon">⚔️</div>
                    </div>
                    <div class="char-details">
                        <h3><?php echo htmlspecialchars($player_data['nome']); ?></h3>
                        <div class="char-tags">
                            <span class="char-tag level">Nível <?php echo $player_data['level']; ?></span>
                            <span class="char-tag class"><?php echo $player_data['classe_base']; ?></span>
                            <?php if ($player_data['subclasse']): ?>
                            <span class="char-tag subclass"><?php echo $player_data['subclasse']; ?></span>
                            <?php endif; ?>
                            <span class="char-tag rank"><?php echo $player_data['fama_rank']; ?></span>
                        </div>
                    </div>
                </div>
                
                <div class="char-progress">
                    <div class="progress-item">
                        <div class="progress-label">
                            <span>Experiência</span>
                            <span><?php echo $player_data['xp_atual']; ?>/<?php echo $player_data['xp_proximo_level']; ?></span>
                        </div>
                        <div class="progress-bar">
                            <div class="progress-fill xp-fill" style="width: <?php echo ($player_data['xp_atual'] / $player_data['xp_proximo_level']) * 100; ?>%"></div>
                        </div>
                    </div>
                </div>

                <div class="char-resources-grid">
                    <div class="resource-card">
                        <div class="resource-icon">❤️</div>
                        <div class="resource-info">
                            <div class="resource-value"><?php echo $player_data['hp_atual']; ?>/<?php echo $player_data['hp_max']; ?></div>
                            <div class="resource-label">Vitalidade</div>
                        </div>
                        <div class="resource-bar">
                            <div class="bar-fill hp-fill" style="width: <?php echo ($player_data['hp_atual'] / $player_data['hp_max']) * 100; ?>%"></div>
                        </div>
                    </div>
                    
                    <div class="resource-card">
                        <div class="resource-icon">🔷</div>
                        <div class="resource-info">
                            <div class="resource-value"><?php echo $player_data['mana_atual']; ?>/<?php echo $player_data['mana_max']; ?></div>
                            <div class="resource-label">
                                <?php echo ($player_data['classe_base'] === 'Mago' || $player_data['classe_base'] === 'Sacerdote') ? 'Mana' : 'Fúria'; ?>
                            </div>
                        </div>
                        <div class="resource-bar">
                            <div class="bar-fill mana-fill" style="width: <?php echo ($player_data['mana_atual'] / $player_data['mana_max']) * 100; ?>%"></div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- PONTOS DISPONÍVEIS -->
        <div class="section section-arcane">
            <h2 class="section-header">🎯 PONTOS DE PROGRESSO</h2>
            <div class="points-grid">
                <div class="point-card">
                    <div class="point-icon">⭐</div>
                    <div class="point-content">
                        <div class="point-value"><?php echo $player_data['pontos_atributo_disponiveis']; ?></div>
                        <div class="point-label">Pontos de Atributo</div>
                        <div class="point-description">Use para aumentar atributos base</div>
                    </div>
                </div>
                
                <div class="point-card">
                    <div class="point-icon">🎯</div>
                    <div class="point-content">
                        <div class="point-value"><?php echo $player_data['pontos_habilidade_disponiveis']; ?></div>
                        <div class="point-label">Pontos de Habilidade</div>
                        <div class="point-description">Aprenda e melhore skills</div>
                    </div>
                </div>
            </div>
            <div class="section section-arcane">
    <h2 class="section-header">👻 VÍNCULOS COM ECOS</h2>
    <div class="ecos-affinity-grid">
        <?php
        // QUERY CORRIGIDA - pegar nome da tabela ecos_base
        $sql_ecos_affinity = "SELECT eb.nome, pe.affinity_level, pe.affinity_xp, 
                             eb.rank_eco, eb.tipo_eco,
                             (pe.affinity_level * 100) as xp_necessario
                              FROM personagem_ecos pe
                              JOIN ecos_base eb ON pe.id_eco_base = eb.id
                              WHERE pe.id_personagem = $player_id
                              ORDER BY pe.affinity_level DESC, pe.affinity_xp DESC";
        $result_ecos_affinity = $conexao->query($sql_ecos_affinity);
        
        if ($result_ecos_affinity && $result_ecos_affinity->num_rows > 0): 
            while($eco = $result_ecos_affinity->fetch_assoc()):
                $percentual_xp = min(100, ($eco['affinity_xp'] / $eco['xp_necessario']) * 100);
        ?>
        <div class="affinity-card">
            <div class="affinity-header">
                <h4><?php echo htmlspecialchars($eco['nome']); ?></h4>
                <span class="eco-rank <?php echo strtolower($eco['rank_eco']); ?>"><?php echo $eco['rank_eco']; ?></span>
            </div>
            <div class="affinity-type"><?php echo $eco['tipo_eco']; ?></div>
            <div class="affinity-level">Vínculo Nv. <?php echo $eco['affinity_level']; ?></div>
            <div class="affinity-bar">
                <div class="affinity-fill" style="width: <?php echo $percentual_xp; ?>%"></div>
            </div>
            <div class="affinity-xp"><?php echo $eco['affinity_xp']; ?>/<?php echo $eco['xp_necessario']; ?> XP</div>
        </div>
        <?php endwhile; ?>
        <?php else: ?>
        <div class="empty-state-small">
            <p>Nenhum Eco recrutado ainda</p>
            <a href="ecos.php" class="btn btn-small">Recrutar Ecos</a>
        </div>
        <?php endif; ?>
    </div>
</div>
            <!-- ESTATÍSTICAS DE COMBATE -->
            <div class="combat-stats">
                <h4>⚔️ Estatísticas de Combate</h4>
                <div class="combat-grid">
                    <div class="combat-stat">
                        <span class="combat-label">Dano Físico</span>
                        <span class="combat-value"><?php echo $equip_bonus['dano_min_total']; ?>-<?php echo $equip_bonus['dano_max_total']; ?></span>
                    </div>
                    <div class="combat-stat">
                        <span class="combat-label">Mitigação</span>
                        <span class="combat-value">+<?php echo $equip_bonus['mitigacao_total']; ?></span>
                    </div>
                    <div class="combat-stat">
                        <span class="combat-label">Chance Crítico</span>
                        <span class="combat-value"><?php echo number_format(($stats_totais['dex'] * 0.5), 1); ?>%</span>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- ATRIBUTOS -->
    <div class="section section-vital">
        <h2 class="section-header vital">📊 ATRIBUTOS</h2>
        
        <div class="attributes-grid">
            <?php
            $atributos_config = [
                'str' => ['nome' => 'Força', 'icon' => '💪', 'cor' => 'var(--status-hp)', 'desc' => 'Aumenta dano físico e carga'],
                'dex' => ['nome' => 'Destreza', 'icon' => '🎯', 'cor' => 'var(--accent-vital)', 'desc' => 'Aumenta chance de crítico e esquiva'],
                'con' => ['nome' => 'Constituição', 'icon' => '🛡️', 'cor' => 'var(--status-gold)', 'desc' => 'Aumenta HP e resistências'],
                'int_stat' => ['nome' => 'Inteligência', 'icon' => '🧠', 'cor' => 'var(--status-mana)', 'desc' => 'Aumenta dano mágico e mana'],
                'wis' => ['nome' => 'Sabedoria', 'icon' => '📚', 'cor' => 'var(--accent-arcane)', 'desc' => 'Aumenta cura e percepção'],
                'cha' => ['nome' => 'Carisma', 'icon' => '✨', 'cor' => 'var(--accent-essence)', 'desc' => 'Melhora interações e preços']
            ];
            
            $tem_pontos_pa = $player_data['pontos_atributo_disponiveis'] > 0;
            
            foreach ($atributos_config as $atributo => $config): 
                $valor_base = $player_data[$atributo];
                $bonus_equip = $equip_bonus['bonus_' . $atributo] ?? 0;
                $valor_total = $stats_totais[$atributo];
            ?>
            <div class="attribute-card">
                <div class="attribute-header" style="border-left-color: <?php echo $config['cor']; ?>">
                    <div class="attribute-icon"><?php echo $config['icon']; ?></div>
                    <div class="attribute-name"><?php echo $config['nome']; ?></div>
                    <div class="attribute-total" style="color: <?php echo $config['cor']; ?>">
                        <?php echo $valor_total; ?>
                    </div>
                </div>
                
                <div class="attribute-breakdown">
                    <div class="breakdown-item">
                        <span class="breakdown-label">Base</span>
                        <span class="breakdown-value"><?php echo $valor_base; ?></span>
                    </div>
                    <div class="breakdown-item">
                        <span class="breakdown-label">Equipamento</span>
                        <span class="breakdown-value <?php echo $bonus_equip > 0 ? 'positive' : ''; ?>">
                            +<?php echo $bonus_equip; ?>
                        </span>
                    </div>
                </div>
                
                <div class="attribute-description">
                    <?php echo $config['desc']; ?>
                </div>
                
                <div class="attribute-actions">
                    <?php if ($tem_pontos_pa): ?>
                    <a href="?acao=gastar_pa&att=<?php echo $atributo; ?>" class="btn-attribute-upgrade">
                        <span class="upgrade-icon">⬆️</span>
                        <span class="upgrade-text">Melhorar</span>
                    </a>
                    <?php else: ?>
                    <span class="btn-attribute-disabled">
                        <span class="upgrade-icon">⏸️</span>
                        <span class="upgrade-text">Sem PA</span>
                    </span>
                    <?php endif; ?>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>

    <!-- HABILIDADES -->
    <div class="section section-arcane">
        <div class="skills-header">
            <h2 class="section-header">🎯 HABILIDADES</h2>
            <div class="skills-tabs">
                <button class="tab-btn active" data-tab="learn">Aprender Habilidades</button>
                <button class="tab-btn" data-tab="known">Grimório</button>
            </div>
        </div>

        <!-- ABA: APRENDER HABILIDADES -->
        <div class="tab-content active" id="learn-tab">
            <?php if ($result_skills_disponiveis->num_rows > 0): ?>
            <div class="skills-grid">
                <?php while($skill = $result_skills_disponiveis->fetch_assoc()): 
                    $pode_aprender = $player_data['pontos_habilidade_disponiveis'] >= $skill['custo_ph'];
                ?>
                <div class="skill-card <?php echo $pode_aprender ? 'available' : 'locked'; ?>">
                    <div class="skill-icon">⚡</div>
                    <div class="skill-info">
                        <h4><?php echo $skill['nome']; ?></h4>
                        <p class="skill-description"><?php echo $skill['descricao']; ?></p>
                        <div class="skill-cost">
                            <span class="cost-label">Custo:</span>
                            <span class="cost-value <?php echo $pode_aprender ? 'affordable' : 'expensive'; ?>">
                                <?php echo $skill['custo_ph']; ?> PH
                            </span>
                        </div>
                    </div>
                    <div class="skill-actions">
                        <?php if ($pode_aprender): ?>
                        <a href="?acao=aprender_skill&skill_id=<?php echo $skill['id']; ?>" class="btn-skill-learn">
                            Aprender
                        </a>
                        <?php else: ?>
                        <span class="btn-skill-locked">
                            PH Insuficiente
                        </span>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endwhile; ?>
            </div>
            <?php else: ?>
            <div class="empty-state">
                <div class="empty-icon">📚</div>
                <h3>Nenhuma Habilidade Disponível</h3>
                <p>Você já aprendeu todas as habilidades disponíveis para sua classe atual.</p>
            </div>
            <?php endif; ?>
        </div>

        <!-- ABA: GRIMÓRIO -->
        <div class="tab-content" id="known-tab">
            <?php if ($result_skills_aprendidas->num_rows > 0): ?>
            <div class="grimorio-grid">
                <?php while($skill = $result_skills_aprendidas->fetch_assoc()): 
                    $pode_melhorar = $player_data['pontos_habilidade_disponiveis'] >= $skill['custo_ph_upgrade'];
                ?>
                <div class="grimorio-card">
                    <div class="skill-level">
                        <span class="level-badge">Nv. <?php echo $skill['skill_level']; ?></span>
                    </div>
                    <div class="skill-content">
                        <h4><?php echo $skill['nome']; ?></h4>
                        <p class="skill-description"><?php echo $skill['descricao']; ?></p>
                        <div class="upgrade-info">
                            <span class="upgrade-cost <?php echo $pode_melhorar ? 'affordable' : 'expensive'; ?>">
                                Próximo nível: <?php echo $skill['custo_ph_upgrade']; ?> PH
                            </span>
                        </div>
                    </div>
                    <div class="skill-actions">
                        <?php if ($pode_melhorar): ?>
                        <a href="?acao=upar_skill&skill_id=<?php echo $skill['id_personagem_skill']; ?>" class="btn-skill-upgrade">
                            Melhorar
                        </a>
                        <?php else: ?>
                        <span class="btn-upgrade-locked">
                            Aguardando PH
                        </span>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endwhile; ?>
            </div>
            <?php else: ?>
            <div class="empty-state">
                <div class="empty-icon">🎯</div>
                <h3>Grimório Vazio</h3>
                <p>Aprenda habilidades na aba "Aprender Habilidades" para preencher seu grimório.</p>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>