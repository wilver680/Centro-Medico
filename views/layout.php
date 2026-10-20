<?php

use CentroMedico\Security;

$flashError = $_SESSION['flash_error'] ?? null;
$flashSuccess = $_SESSION['flash_success'] ?? null;
unset($_SESSION['flash_error'], $_SESSION['flash_success']);
?>
<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="description" content="Agenda citas medicas de forma rapida y segura.">
    <title><?= Security::escape($title ?? 'Centro Medico') ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700&family=Manrope:wght@600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="/assets/app.css">
    <script src="/assets/app.js" defer></script>
</head>
<body>
<header class="site-header">
    <a class="brand" href="/" aria-label="Inicio">
        <img class="brand-logo" src="/assets/logo%20centro%20medico.jpg" alt="">
        <span>Centro M&eacute;dico <span>Jireh</span></span>
    </a>
    <nav aria-label="Navegacion principal">
        <a href="/">Inicio</a>
        <a href="/#servicios">Servicios</a>
        <?php if (!empty($user)): ?>
            <a href="/mis-citas">Mis citas</a>
            <span class="user-name">Hola, <?= Security::escape($user['primer_nombre']) ?></span>
            <form action="/logout" method="post" class="inline-form">
                <input type="hidden" name="_csrf" value="<?= Security::escape(Security::csrfToken()) ?>">
                <button class="button button-ghost button-small" type="submit">Salir</button>
            </form>
        <?php else: ?>
            <a href="/login">Iniciar sesion</a>
            <a class="button button-primary button-small" href="/registro">Crear cuenta</a>
        <?php endif; ?>
    </nav>
</header>

<?php if ($flashError): ?>
    <div class="alert alert-error" role="alert"><?= Security::escape($flashError) ?></div>
<?php endif; ?>
<?php if ($flashSuccess): ?>
    <div class="alert alert-success" role="status"><?= Security::escape($flashSuccess) ?></div>
<?php endif; ?>

<main>
    <?php require __DIR__ . '/' . $view . '.php'; ?>
</main>

<footer>
    <div><strong>Centro M&eacute;dico Jireh</strong><br><span>Tu bienestar es nuestra prioridad, hoy y siempre.</span></div>
    <div><span>Lunes a viernes</span><br><strong>08:00 - 17:00</strong></div>
    <div><span>Agenda en linea</span><br><strong>Rapido y seguro</strong></div>
</footer>
</body>
</html>
