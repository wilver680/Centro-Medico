<?php use CentroMedico\Security; ?>
<section class="booking-page" data-booking data-service-id="<?= (int) $service['id_servicio'] ?>">
    <div class="page-heading">
        <a class="back-link" href="/">← Volver a servicios</a>
        <p class="eyebrow">Reserva en linea</p>
        <h1><?= Security::escape($service['nombre']) ?></h1>
        <p><?= Security::escape($service['descripcion']) ?></p>
    </div>
    <div class="booking-layout">
        <form class="form-card" method="post" action="/citas" data-booking-form>
            <input type="hidden" name="_csrf" value="<?= Security::escape(Security::csrfToken()) ?>">
            <input type="hidden" name="id_servicio" value="<?= (int) $service['id_servicio'] ?>">
            <div class="step"><span>1</span><div><strong>Selecciona un doctor</strong><small>Especialistas disponibles para este servicio</small></div></div>
            <label>Doctor
                <select name="id_doctor" required data-doctor>
                    <option value="">Selecciona un especialista</option>
                    <?php foreach ($doctors as $doctor): ?>
                        <option value="<?= (int) $doctor['id_doctor'] ?>">Dr(a). <?= Security::escape($doctor['nombre']) ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
            <div class="step"><span>2</span><div><strong>Elige la fecha</strong><small>Atendemos de lunes a viernes</small></div></div>
            <label>Fecha
                <input type="date" name="fecha" required data-date min="<?= date('Y-m-d') ?>">
            </label>
            <div class="step"><span>3</span><div><strong>Elige un horario</strong><small>Horarios disponibles de 08:00 a 17:00</small></div></div>
            <div class="slots" data-slots><p class="empty-state">Selecciona doctor y fecha para ver los horarios.</p></div>
            <input type="hidden" name="hora" required data-time>
            <button class="button button-primary button-block" type="submit" data-submit disabled>Confirmar cita</button>
        </form>
        <aside class="booking-aside">
            <p class="eyebrow">Antes de reservar</p>
            <h2>Todo listo para tu visita</h2>
            <ul>
                <li><span>01</span>Llega 10 minutos antes de tu cita.</li>
                <li><span>02</span>Trae un documento de identificacion.</li>
                <li><span>03</span>Recibiras los datos al confirmar.</li>
            </ul>
        </aside>
    </div>
</section>
