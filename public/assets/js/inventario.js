document.addEventListener('DOMContentLoaded', function() {
    // Filtros de inventário
    const filterButtons = document.querySelectorAll('.filter-buttons .btn');
    const inventoryItems = document.querySelectorAll('.inventory-item');
    
    filterButtons.forEach(button => {
        button.addEventListener('click', function() {
            // Remove active class de todos os botões
            filterButtons.forEach(btn => btn.classList.remove('active'));
            // Adiciona active no botão clicado
            this.classList.add('active');
            
            const filter = this.textContent.toLowerCase();
            
            inventoryItems.forEach(item => {
                if (filter === 'todos') {
                    item.style.display = '';
                } else {
                    const itemType = item.querySelector('td:nth-child(2)').textContent.toLowerCase();
                    if (itemType.includes(filter)) {
                        item.style.display = '';
                    } else {
                        item.style.display = 'none';
                    }
                }
            });
        });
    });
    
    // Busca em tempo real
    const searchInput = document.querySelector('.search-box input');
    searchInput.addEventListener('input', function() {
        const searchTerm = this.value.toLowerCase();
        
        inventoryItems.forEach(item => {
            const itemName = item.querySelector('.item-name').textContent.toLowerCase();
            if (itemName.includes(searchTerm)) {
                item.style.display = '';
            } else {
                item.style.display = 'none';
            }
        });
    });
});