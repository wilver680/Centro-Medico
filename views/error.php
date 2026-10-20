<?php use CentroMedico\Security; ?>
<section class="empty-card error-page">
    <div class="empty-icon">!</div>
    <h1><?= Security::escape($title) ?></h1>
    <p><?= Security::escape($message) ?></p>
    <a class="button button-primary" href="/">Volver al inicio</a>
</section>

