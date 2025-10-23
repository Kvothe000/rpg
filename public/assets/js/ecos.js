document.addEventListener('DOMContentLoaded', function() {
    // Atualização em tempo real dos temporizadores
    function updateTimers() {
        const timeElements = document.querySelectorAll('.status-time');
        timeElements.forEach(element => {
            const text = element.textContent;
            const match = text.match(/(\d+)h (\d+)m (\d+)s/);
            if (match) {
                let hours = parseInt(match[1]);
                let minutes = parseInt(match[2]);
                let seconds = parseInt(match[3]);
                
                // Decrementa o tempo
                seconds--;
                if (seconds < 0) {
                    seconds = 59;
                    minutes--;
                    if (minutes < 0) {
                        minutes = 59;
                        hours--;
                        if (hours < 0) {
                            // Tempo esgotado - recarrega a página
                            location.reload();
                            return;
                        }
                    }
                }
                
                // Atualiza o display
                element.textContent = `(${hours.toString().padStart(2, '0')}h ${minutes.toString().padStart(2, '0')}m ${seconds.toString().padStart(2, '0')}s)`;
            }
        });
    }
    
    // Atualiza a cada segundo
    setInterval(updateTimers, 1000);
    
    // Efeitos de hover nos cards de Eco
    const ecoCards = document.querySelectorAll('.eco-card');
    ecoCards.forEach(card => {
        card.addEventListener('mouseenter', function() {
            this.style.transform = 'translateY(-5px)';
        });
        card.addEventListener('mouseleave', function() {
            this.style.transform = 'translateY(0)';
        });
    });
});