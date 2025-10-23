document.addEventListener('DOMContentLoaded', function() {
    // Filtros
    const filterBtns = document.querySelectorAll('.filter-btn');
    const achievementCards = document.querySelectorAll('.achievement-card');
    
    filterBtns.forEach(btn => {
        btn.addEventListener('click', function() {
            const filter = this.getAttribute('data-filter');
            
            // Atualizar botões ativos
            filterBtns.forEach(b => b.classList.remove('active'));
            this.classList.add('active');
            
            // Filtrar cards
            achievementCards.forEach(card => {
                const status = card.getAttribute('data-status');
                
                if (filter === 'all' || filter === status) {
                    card.style.display = 'grid';
                } else {
                    card.style.display = 'none';
                }
            });
        });
    });
});