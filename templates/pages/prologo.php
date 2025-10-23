<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Prólogo - Arcana Duality</title>
    <style>
        body { 
            font-family: monospace; 
            background-color: #000; 
            color: #ccc; 
            margin: 0; 
            padding: 0; 
            display: flex; 
            align-items: center; 
            justify-content: center; 
            min-height: 100vh;
            overflow: hidden;
        }
        .prologo-container { 
            max-width: 800px; 
            padding: 40px; 
            text-align: left; 
            line-height: 1.6; 
            border: 1px solid #555; 
            background-color: #111; 
            box-shadow: 0 0 20px rgba(138, 43, 226, 0.3);
            max-height: 90vh;
            overflow-y: auto;
        }
        .prologo-container p { 
            margin: 0 0 1em 0; 
        }
        .highlight { 
            color: #FF00FF;
            font-weight: bold; 
        }
        .system-message { 
            color: #00FFFF;
            border: 1px dashed #00FFFF; 
            padding: 10px; 
            margin-top: 15px; 
        }
        .continue-link { 
            display: block; 
            margin-top: 30px; 
            text-align: center; 
            color: #50C878; 
            text-decoration: none; 
            font-weight: bold; 
            border: 1px solid #50C878; 
            padding: 15px; 
            transition: all 0.3s ease;
            border-radius: 8px;
        }
        .continue-link:hover { 
            background-color: #50C878; 
            color: #111; 
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(80, 200, 120, 0.3);
        }
        @keyframes fadeIn { 
            from { opacity: 0; } 
            to { opacity: 1; } 
        }
        .prologo-container { 
            animation: fadeIn 2s ease-in; 
        }
        .character-name {
            color: #FFF;
            font-weight: bold;
        }
        .alert {
            color: #FF4444;
        }
    </style>
</head>
<body>
    <div class="prologo-container">
        <p class="alert">...Alarme da Cidade Central: [NÍVEL DE AMEAÇA: CÁRMINA]. Fenda de Nível 4 detectada no Distrito 7. EVACUAR. [cite_start]EVACUAR. [cite: 58]</p>
        <p><em>(O som de vidro se quebrando, mas em escala cósmica, ecoa em sua mente.)</em></p>
        [cite_start]<p><strong class="character-name">ELARA:</strong> "Corre! Pela minha esquerda! O abrigo é a duas quadras!" [cite: 58]</p>
        <p>Você corre. O asfalto racha à sua frente. [cite_start]Uma fissura negra, pulsando com energia <span style="color: #8A2BE2;">púrpura</span>, rasga o concreto. [cite: 59]</p>
        [cite_start]<p><strong class="character-name">ELARA:</strong> "Não... não aqui..." [cite: 59]</p>
        <p>Garras feitas de pura sombra irrompem da Fenda. Elas ignoram você. [cite_start]Elas vão direto para Elara. [cite: 60]</p>
        [cite_start]<p><strong class="character-name alert">ELARA:</strong> "NÃO! ME SOLTA! CORRE!" [cite: 60]</p>
        <p>Você tenta puxá-la. Suas mãos agarram o nada. As garras a envolvem. [cite_start]Os olhos dela encontram os seus, um terror silencioso que grita seu nome. [cite: 61]</p>
        <p>Ela é puxada para dentro do rasgo. A Fenda se fecha. [cite_start]O silêncio é ensurdecedor. [cite: 61]</p>
        <p>Sua mente se estilhaça. [cite_start]O mundo se dissolve em dor e um zumbido agudo... [cite: 62]</p>
        <p>...</p>
        <p>...</p>
        <div class="system-message">
            <p class="highlight">[ BEM-VINDO, RECEPTÁCULO. [cite_start]] [cite: 62]</p>
            <p class="highlight">[ SISTEMA ATIVADO. [cite_start]] [cite: 62]</p>
            <p class="highlight">[ SINCRONIZANDO FRAGMENTO... ERRO. [cite_start]] [cite: 63]</p>
            <p class="highlight">[...SINCRONIZAÇÃO FORÇADA. [cite_start]] [cite: 63]</p>
            [cite_start]<p>[ SAÚDE: 1% ] [cite: 63]</p>
            [cite_start]<p>[ CONDIÇÃO: TRAUMA DE FENDA (SEVERO) ] [cite: 63]</p>
        </div>
        [cite_start]<p>...Você desmaia. [cite: 63]</p>

        <a href="despertar.php" class="continue-link">🌌 Continuar para o Despertar...</a>
    </div>
</body>
</html>

