<?php
// templates/partials/feedback.php

// A variável $mensagem é passada pelo 'render_template' a partir do arquivo em public/
// (via 'extract($data)' na função render_template)

// Verifica se a variável $mensagem existe E não está vazia.
if (!empty($mensagem)): ?>
    <div class="feedback-container">
        <?php echo $mensagem; // A $mensagem já deve vir formatada com as classes (e.g., feedback-error) ?>
    </div>
<?php endif; ?>