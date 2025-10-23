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
        document.querySelector('.class-option[data-class="Guerreiro"]').click();

        // Sistema de Alocação de Pontos
        let pontosRestantes = 12;
        const pontosPorAtributo = {
            str: 0,
            dex: 0,
            con: 0,
            int_stat: 0,
            wis: 0,
            cha: 0
        };

        function alterarPonto(atributo, valor) {
            const novoValor = pontosPorAtributo[atributo] + valor;
            
            // Verifica se pode adicionar ou remover
            if (valor > 0 && pontosRestantes <= 0) return;
            if (valor < 0 && novoValor < 0) return;
            if (novoValor > 5) return;
            
            // Atualiza pontos
            pontosPorAtributo[atributo] = novoValor;
            pontosRestantes -= valor;
            
            // Atualiza interface
            document.getElementById(`valor-${atributo}`).textContent = novoValor;
            document.getElementById(`input-${atributo}`).value = novoValor;
            document.getElementById('pontosRestantes').textContent = pontosRestantes;
            
            // Atualiza botões
            atualizarBotoes();
        }

        function atualizarBotoes() {
            // Atualiza todos os botões de + e -
            for (const atributo in pontosPorAtributo) {
                const valorAtual = pontosPorAtributo[atributo];
                const btnMenos = document.querySelector(`button[onclick="alterarPonto('${atributo}', -1)"]`);
                const btnMais = document.querySelector(`button[onclick="alterarPonto('${atributo}', 1)"]`);
                
                btnMenos.disabled = valorAtual <= 0;
                btnMais.disabled = pontosRestantes <= 0 || valorAtual >= 5;
            }
            
            // Atualiza botão de criar personagem
            const btnCriar = document.getElementById('btnCriar');
            btnCriar.disabled = pontosRestantes !== 0;
            if (pontosRestantes !== 0) {
                btnCriar.title = `Você ainda tem ${pontosRestantes} pontos para gastar.`;
            } else {
                btnCriar.title = '';
            }
        }

        // Inicializa os botões
        atualizarBotoes();