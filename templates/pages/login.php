
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $titulo_pagina; ?></title>
 
    <div class="login-container">
        <div class="portal-card">
            <div class="portal-header">
                <div class="portal-icon">🌌</div>
                <h1 class="portal-title">ARCANA DUALITY</h1>
                <p class="portal-subtitle">Entre no Mundo da Fenda</p>
            </div>

            <div class="form-tabs">
                <button class="tab-btn <?php echo $modo === 'login' ? 'active' : ''; ?>" data-tab="login">
                    🚪 Entrar
                </button>
                <button class="tab-btn <?php echo $modo === 'registro' ? 'active' : ''; ?>" data-tab="registro">
                    ✨ Novo Personagem
                </button>
            </div>

            <!-- FORMULÁRIO DE LOGIN -->
            <form method="POST" action="login.php" class="tab-content <?php echo $modo === 'login' ? 'active' : ''; ?>" id="login-tab">
                <input type="hidden" name="acao" value="login">
                
                <div class="form-group">
                    <label for="email" class="form-label">📧 Email</label>
                    <input type="email" id="email" name="email" class="form-input" placeholder="seu@email.com" required>
                </div>

                <div class="form-group">
                    <label for="senha" class="form-label">🔒 Senha</label>
                    <input type="password" id="senha" name="senha" class="form-input" placeholder="Sua senha secreta" required>
                </div>

                <button type="submit" class="btn-portal">
                    🚀 Acessar o Jogo
                </button>
            </form>

            <!-- FORMULÁRIO DE REGISTRO -->
            <form method="POST" action="login.php" class="tab-content <?php echo $modo === 'registro' ? 'active' : ''; ?>" id="registro-tab">
                <input type="hidden" name="acao" value="registro">
                
                <div class="form-group">
                    <label for="nome" class="form-label">👤 Nome do Personagem</label>
                    <input type="text" id="nome" name="nome" class="form-input" placeholder="Escolha um nome épico" required>
                </div>

                <div class="form-group">
                    <label for="email_registro" class="form-label">📧 Email</label>
                    <input type="email" id="email_registro" name="email" class="form-input" placeholder="seu@email.com" required>
                </div>

                <div class="form-group">
                    <label for="senha_registro" class="form-label">🔒 Senha</label>
                    <input type="password" id="senha_registro" name="senha" class="form-input" placeholder="Mínimo 6 caracteres" minlength="6" required>
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
                        <div class="class-option" data-class="Caçador">
                            <div class="class-icon">🏹</div>
                            <div class="class-name">Caçador</div>
                            <div class="class-desc">Precisão & Sobrevivência</div>
                        </div>
                    </div>
                    <input type="hidden" id="classe_base" name="classe_base" value="Guerreiro" required>
                </div>

                <button type="submit" class="btn-portal">
                    🌟 Criar Personagem
                </button>
            </form>

            <div class="portal-footer">
                <p class="footer-text">
                    Ao entrar, você concorda com os <a href="#" class="footer-link">Termos da Fenda</a>
                </p>
            </div>
        </div>
    </div>

