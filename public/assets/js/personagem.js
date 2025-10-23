document.addEventListener('DOMContentLoaded', function() {
    // Sistema de Abas
    const tabButtons = document.querySelectorAll('.tab-btn');
    const tabContents = document.querySelectorAll('.tab-content');
    
    tabButtons.forEach(button => {
        button.addEventListener('click', function() {
            const targetTab = this.getAttribute('data-tab');
            
            // Atualiza botões ativos
            tabButtons.forEach(btn => btn.classList.remove('active'));
            this.classList.add('active');
            
            // Mostra conteúdo da aba
            tabContents.forEach(content => {
                content.classList.remove('active');
                if (content.id === targetTab + '-tab') {
                    content.classList.add('active');
                }
            });
        });
    });
    
    // Efeitos visuais para cards
    const attributeCards = document.querySelectorAll('.attribute-card');
    attributeCards.forEach(card => {
        card.addEventListener('mouseenter', function() {
            this.style.transform = 'translateY(-5px)';
            this.style.boxShadow = '0 10px 25px rgba(138, 43, 226, 0.2)';
        });
        
        card.addEventListener('mouseleave', function() {
            this.style.transform = 'translateY(-2px)';
            this.style.boxShadow = 'none';
        });
    });
    
    // Animação para botões de upgrade
    const upgradeButtons = document.querySelectorAll('.btn-attribute-upgrade, .btn-skill-learn, .btn-skill-upgrade');
    upgradeButtons.forEach(button => {
        button.addEventListener('mouseenter', function() {
            this.style.transform = 'scale(1.05)';
        });
        
        button.addEventListener('mouseleave', function() {
            this.style.transform = 'scale(1)';
        });
    });
    
    // Destaque para atributos com bônus
    const positiveBonuses = document.querySelectorAll('.breakdown-value.positive');
    positiveBonuses.forEach(bonus => {
        bonus.style.animation = 'pulse 2s ease-in-out infinite';
    });
});