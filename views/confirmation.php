<?php
use CentroMedico\Security;

$cancelled = $appointment['estado'] === 'cancelada';
?>
<section class="confirmation-page">
    <div class="confirmation-mark<?= $cancelled ? ' confirmation-cancelled' : '' ?>"><?= $cancelled ? '×' : '✓' ?></div>
    <p class="eyebrow"><?= $cancelled ? 'Cita cancelada' : 'Reserva completada' ?></p>
    <h1><?= $cancelled ? 'Esta cita fue cancelada' : 'Tu cita esta confirmada' ?></h1>
    <p><?= $cancelled ? 'El horario vuelve a estar disponible.' : 'Guarda estos datos para tu visita.' ?></p>
    <div class="confirmation-card">
        <div><small>Servicio</small><strong><?= Security::escape($appointment['servicio']) ?></strong></div>
        <div><small>Doctor</small><strong>Dr(a). <?= Security::escape($appointment['doctor']) ?></strong></div>
        <div><small>Fecha</small><strong><?= Security::escape(date('d/m/Y', strtotime($appointment['fecha']))) ?></strong></div>
        <div><small>Hora</small><strong><?= Security::escape($appointment['hora']) ?></strong></div>
        <div class="confirmation-code"><small>Numero de cita</small><strong>#<?= str_pad((string) $appointment['id_cita'], 6, '0', STR_PAD_LEFT) ?></strong></div>
    </div>
    <?php if (!empty($appointment['puede_cancelar'])): ?>
        <form action="/citas/<?= (int) $appointment['id_cita'] ?>/cancelar" method="post" class="confirmation-actions" data-confirm-cancel>
            <input type="hidden" name="_csrf" value="<?= Security::escape(Security::csrfToken()) ?>">
            <button class="button button-danger" type="submit">Cancelar cita</button>
        </form>
    <?php endif; ?>
    <div class="confirmation-actions">
        <a class="button button-primary" href="/mis-citas">Ver mis citas</a>
        <a class="button button-ghost" href="/">Volver al inicio</a>
    </div>
</section>

