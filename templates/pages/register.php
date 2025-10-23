
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $titulo_pagina; ?></title>

     <div class="registro-container">
        <div class="portal-card">
            <div class="portal-header">
                <div class="portal-icon">🌌</div>
                <h1 class="portal-title">CRIAÇÃO DE PERSONAGEM</h1>
                <p class="portal-subtitle">Forje seu destino na Fenda</p>
            </div>

            <form method="POST" action="register.php">
                <div class="form-group">
                    <label for="nome" class="form-label">👤 Nome do Personagem</label>
                    <input type="text" id="nome" name="nome" class="form-input" placeholder="Escolha um nome épico" required>
                </div>

                <div class="form-group">
                    <label for="email" class="form-label">📧 Email</label>
                    <input type="email" id="email" name="email" class="form-input" placeholder="seu@email.com" required>
                </div>

                <div class="form-group">
                    <label for="senha" class="form-label">🔒 Senha</label>
                    <input type="password" id="senha" name="senha" class="form-input" placeholder="Mínimo 6 caracteres" minlength="6" required>
                </div>

                <div class="form-group">
                    <label class="form-label">⚔️ Escolha sua Classe</label>
                    <div class="class-selection" id="classSelection">
                        <div class="class-option" data-class="Guerreiro">
                            <div class="class-icon">🛡️</div>
                            <div class="class-name">Guerreiro</div>
                            <div class="class-desc">Força & Defesa</div>
                        </div>
                        <div class="class-option" data-class="Ladino">
                            <div class="class-icon">🗡️</div>
                            <div class="class-name">Ladino</div>
                            <div class="class-desc">Precisão & Furtividade</div>
                        </div>
                        <div class="class-option" data-class="Mago">
                            <div class="class-icon">🔮</div>
                            <div class="class-name">Mago</div>
                            <div class="class-desc">Poder Arcano</div>
                        </div>
                        <div class="class-option" data-class="Sacerdote">
                            <div class="class-icon">✨</div>
                            <div class="class-name">Sacerdote</div>
                            <div class="class-desc">Cura & Proteção</div>
                        </div>
                    </div>
                    <input type="hidden" id="classe_base" name="classe_base" value="Guerreiro" required>
                </div>

                <!-- Sistema de Alocação de Pontos -->
                <div class="stats-allocation">
                    <h3 class="stats-header">🎯 Alocação de Pontos de Atributo</h3>
                    <p style="text-align: center; color: var(--text-secondary); margin-bottom: 15px;">
                        Você tem <span id="pontosRestantes" style="color: var(--accent-vital); font-weight: bold;">12</span> pontos para distribuir.
                    </p>
                    
                    <div class="stats-grid">
                        <?php
                        $atributos = [
                            'str' => ['nome' => 'Força', 'icon' => '💪'],
                            'dex' => ['nome' => 'Destreza', 'icon' => '🎯'],
                            'con' => ['nome' => 'Constituição', 'icon' => '🛡️'],
                            'int_stat' => ['nome' => 'Inteligência', 'icon' => '🧠'],
                            'wis' => ['nome' => 'Sabedoria', 'icon' => '📚'],
                            'cha' => ['nome' => 'Carisma', 'icon' => '✨']
                        ];
                        
                        foreach ($atributos as $key => $atributo):
                        ?>
                        <div class="stat-item">
                            <div class="stat-info">
                                <span class="stat-name"><?php echo $atributo['icon']; ?> <?php echo $atributo['nome']; ?></span>
                                <span class="stat-base">Base: 10</span>
                            </div>
                            <div class="stat-controls">
                                <button type="button" class="btn-stat" onclick="alterarPonto('<?php echo $key; ?>', -1)" disabled>-</button>
                                <span class="stat-value" id="valor-<?php echo $key; ?>">0</span>
                                <button type="button" class="btn-stat" onclick="alterarPonto('<?php echo $key; ?>', 1)">+</button>
                            </div>
                            <input type="hidden" name="<?php echo $key; ?>_bonus" id="input-<?php echo $key; ?>" value="0">
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>

                <button type="submit" class="btn-portal" id="btnCriar">
                    🌟 Criar Personagem
                </button>
            </form>

            <div class="portal-footer">
                <p style="color: var(--text-secondary);">
                    Já tem um personagem? <a href="login.php" class="footer-link">Faça login aqui</a>
                </p>
            </div>
        </div>
    </div>