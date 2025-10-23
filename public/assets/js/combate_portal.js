document.addEventListener('DOMContentLoaded', function() {
    // Rolagem automática para o final do log
    const combatLog = document.querySelector('.combat-log');
    if (combatLog) {
        combatLog.scrollTop = combatLog.scrollHeight;
    }
    
    // Efeitos de hover nas habilidades
    const skillCards = document.querySelectorAll('.skill-card.available');
    skillCards.forEach(card => {
        card.addEventListener('mouseenter', function() {
            this.style.transform = 'translateY(-3px) scale(1.02)';
        });
        card.addEventListener('mouseleave', function() {
            this.style.transform = 'translateY(0) scale(1)';
        });
    });
});