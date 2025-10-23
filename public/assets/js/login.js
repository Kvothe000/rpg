
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

            // Sistema de Seleção de Classe
            const classOptions = document.querySelectorAll('.class-option');
            const classInput = document.getElementById('classe_base');
            
            classOptions.forEach(option => {
                option.addEventListener('click', function() {
                    const selectedClass = this.getAttribute('data-class');
                    
                    // Atualiza seleção visual
                    classOptions.forEach(opt => opt.classList.remove('selected'));
                    this.classList.add('selected');
                    
                    // Atualiza input hidden
                    classInput.value = selectedClass;
                });
            });

            // Seleciona Guerreiro por padrão
            const defaultClass = document.querySelector('.class-option[data-class="Guerreiro"]');
            if (defaultClass) defaultClass.click();

            // Efeitos visuais para inputs
            const inputs = document.querySelectorAll('.form-input');
            inputs.forEach(input => {
                input.addEventListener('focus', function() {
                    this.style.transform = 'translateY(-2px)';
                });
                
                input.addEventListener('blur', function() {
                    this.style.transform = 'translateY(0)';
                });
            });
        });
        