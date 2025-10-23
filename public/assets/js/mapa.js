// SISTEMA DE MAPA INTERATIVO ATUALIZADO
document.addEventListener('DOMContentLoaded', function() {
    // REGIÕES CLICÁVEIS (AGORA APENAS PARA FEEDBACK VISUAL)
    document.querySelectorAll('.map-region.descoberta').forEach(region => {
        region.addEventListener('click', function() {
            // Feedback visual apenas - a navegação agora é pelos botões específicos
            this.style.transform = 'scale(1.02)';
            setTimeout(() => {
                this.style.transform = 'scale(1)';
            }, 200);
        });
    });
    
    // TEMPORIZADOR DAS DUNGEONS (MANTIDO PARA COMPATIBILIDADE)
    document.querySelectorAll('.timer').forEach(timer => {
        const endTime = parseInt(timer.dataset.end);
        
        function updateTimer() {
            const now = Math.floor(Date.now() / 1000);
            const remaining = endTime - now;
            
            if (remaining <= 0) {
                timer.innerHTML = '⏰ Evento Expirado';
                return;
            }
            
            const minutes = Math.floor(remaining / 60);
            const seconds = remaining % 60;
            timer.querySelector('.time-remaining').textContent = 
                `${minutes.toString().padStart(2, '0')}:${seconds.toString().padStart(2, '0')}`;
        }
        
        updateTimer();
        setInterval(updateTimer, 1000);
    });
    
    // ENTRAR NA DUNGEON (MANTIDO PARA COMPATIBILIDADE)
    document.querySelectorAll('.btn-enter-dungeon').forEach(btn => {
        btn.addEventListener('click', function() {
            const dungeonData = JSON.parse(this.dataset.dungeon);
            if (confirm(`Ingressar na dungeon "${dungeonData.nome}"?`)) {
                // Redirecionar para sistema de combate com dados da dungeon
                window.location.href = `combate_dungeon.php?dungeon=${btoa(JSON.stringify(dungeonData))}`;
            }
        });
    });
});