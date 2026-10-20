<?php use CentroMedico\Security; ?>
<section class="auth-shell">
    <div class="auth-message">
        <p class="eyebrow">Bienvenido de vuelta</p>
        <h1>Tu salud sigue <em>en buenas manos.</em></h1>
        <p>Ingresa para reservar y consultar tus citas medicas.</p>
    </div>
    <div class="form-card">
        <h2>Iniciar sesion</h2>
        <p>Accede con tu correo y contrasena.</p>
        <?php if (!empty($error)): ?><div class="alert alert-error" role="alert"><?= Security::escape($error) ?></div><?php endif; ?>
        <form method="post" action="/login">
            <input type="hidden" name="_csrf" value="<?= Security::escape(Security::csrfToken()) ?>">
            <label>Correo electronico
                <input type="email" name="correo" required autocomplete="email" maxlength="150" placeholder="nombre@correo.com">
            </label>
            <label>Contrasena
                <input type="password" name="contrasena" required autocomplete="current-password" placeholder="Tu contrasena">
            </label>
            <button class="button button-primary button-block" type="submit">Ingresar</button>
        </form>
        <p class="form-footer">¿Aun no tienes cuenta? <a href="/registro">Crear cuenta</a></p>
    </div>
</section>
