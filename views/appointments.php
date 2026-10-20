<?php use CentroMedico\Security; ?>
<section class="list-page">
    <div class="page-heading">
        <p class="eyebrow">Tu agenda de salud</p>
        <h1>Mis citas</h1>
        <p>Aqui encontraras unicamente las reservas asociadas a tu cuenta.</p>
    </div>
    <?php if ($appointments === []): ?>
        <div class="empty-card">
            <div class="empty-icon">+</div>
            <h2>Aun no tienes citas</h2>
            <p>Explora nuestros servicios y encuentra el horario ideal para ti.</p>
            <a class="button button-primary" href="/#servicios">Ver servicios</a>
        </div>
    <?php else: ?>
        <div class="appointment-list">
            <?php foreach ($appointments as $appointment): ?>
                <?php
                $cancelled = $appointment['estado_visible'] === 'cancelada';
                $completed = $appointment['estado_visible'] === 'completada';
                ?>
                <article class="appointment-card<?= $cancelled ? ' appointment-cancelled' : '' ?>">
                    <div class="date-block">
                        <span><?= Security::escape(date('M', strtotime($appointment['fecha']))) ?></span>
                        <strong><?= Security::escape(date('d', strtotime($appointment['fecha']))) ?></strong>
                    </div>
                    <div class="appointment-info">
                        <span class="status<?= $cancelled ? ' status-cancelled' : ($completed ? ' status-completed' : '') ?>">
                            <?= $cancelled ? 'Cancelada' : ($completed ? 'Completada' : 'Confirmada') ?>
                        </span>
                        <h2><?= Security::escape($appointment['servicio']) ?></h2>
                        <p>Dr(a). <?= Security::escape($appointment['doctor']) ?></p>
                    </div>
                    <div class="appointment-time">
                        <small>Fecha y hora</small>
                        <strong><?= Security::escape(date('d/m/Y', strtotime($appointment['fecha']))) ?> · <?= Security::escape($appointment['hora']) ?></strong>
                        <a href="/citas/<?= (int) $appointment['id_cita'] ?>">Ver detalles →</a>
                        <?php if (!empty($appointment['puede_cancelar'])): ?>
                            <form action="/citas/<?= (int) $appointment['id_cita'] ?>/cancelar" method="post" class="cancel-form" data-confirm-cancel>
                                <input type="hidden" name="_csrf" value="<?= Security::escape(Security::csrfToken()) ?>">
                                <button type="submit" class="link-button">Cancelar cita</button>
                            </form>
                        <?php endif; ?>
                    </div>
                </article>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</section>

