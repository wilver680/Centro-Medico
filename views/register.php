<?php use CentroMedico\Security; ?>
<section class="form-page">
    <div class="page-heading">
        <p class="eyebrow">Un paso para cuidar de ti</p>
        <h1>Crea tu cuenta</h1>
        <p>Los campos marcados con * son obligatorios.</p>
    </div>
    <form class="form-card form-wide" method="post" action="/registro" novalidate>
        <input type="hidden" name="_csrf" value="<?= Security::escape(Security::csrfToken()) ?>">
        <fieldset>
            <legend>Informacion personal</legend>
            <div class="form-grid">
                <?php
                $fields = [
                    'primer_nombre' => ['Primer nombre *', true], 'segundo_nombre' => ['Segundo nombre', false],
                    'tercer_nombre' => ['Tercer nombre', false], 'cuarto_nombre' => ['Cuarto nombre', false],
                    'primer_apellido' => ['Primer apellido *', true], 'segundo_apellido' => ['Segundo apellido', false],
                    'apellido_casada' => ['Apellido de casada', false]
                ];
                foreach ($fields as $name => [$label, $required]): ?>
                    <label><?= $label ?>
                        <input type="text" name="<?= $name ?>" maxlength="50" <?= $required ? 'required' : '' ?> value="<?= Security::escape($old[$name] ?? '') ?>">
                        <?php if (!empty($errors[$name])): ?><small class="field-error"><?= Security::escape($errors[$name]) ?></small><?php endif; ?>
                    </label>
                <?php endforeach; ?>
                <label>Telefono
                    <input type="tel" name="telefono" maxlength="20" autocomplete="tel" value="<?= Security::escape($old['telefono'] ?? '') ?>">
                    <?php if (!empty($errors['telefono'])): ?><small class="field-error"><?= Security::escape($errors['telefono']) ?></small><?php endif; ?>
                </label>
            </div>
        </fieldset>
        <fieldset>
            <legend>Datos de acceso</legend>
            <div class="form-grid">
                <label>Correo electronico *
                    <input type="email" name="correo" maxlength="150" required autocomplete="email" value="<?= Security::escape($old['correo'] ?? '') ?>">
                    <?php if (!empty($errors['correo'])): ?><small class="field-error"><?= Security::escape($errors['correo']) ?></small><?php endif; ?>
                </label>
                <label>Contrasena *
                    <input type="password" name="contrasena" minlength="8" required autocomplete="new-password">
                    <?php if (!empty($errors['contrasena'])): ?><small class="field-error"><?= Security::escape($errors['contrasena']) ?></small><?php endif; ?>
                </label>
                <label>Confirmar contrasena *
                    <input type="password" name="confirmar_contrasena" minlength="8" required autocomplete="new-password">
                    <?php if (!empty($errors['confirmar_contrasena'])): ?><small class="field-error"><?= Security::escape($errors['confirmar_contrasena']) ?></small><?php endif; ?>
                </label>
            </div>
        </fieldset>
        <button class="button button-primary" type="submit">Crear mi cuenta</button>
        <p class="form-footer">¿Ya tienes cuenta? <a href="/login">Inicia sesion</a></p>
    </form>
</section>
