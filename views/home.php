<?php

use CentroMedico\Security;
?>

<section class="home-carousel" aria-label="Presentacion del centro medico" data-carousel>
    <article class="home-slide is-active" data-slide aria-hidden="false">
        <img src="/assets/MEDICO.webp" alt="Medico atendiendo a un paciente">
        <div class="slide-overlay"></div>
        <div class="slide-content">
            <p class="eyebrow">Centro M&eacute;dico Jireh</p>
            <h1>Atenci&oacute;n m&eacute;dica integral</h1>
            <p>Comprometidos con tu salud y la de tu familia.</p>
            <a class="button button-light" href="#servicios">Conocer servicios</a>
        </div>
    </article>

    <article class="home-slide" data-slide aria-hidden="true">
        <img src="/assets/tecnologiaavanzada.png" alt="Tecnologia medica avanzada">
        <div class="slide-overlay"></div>
        <div class="slide-content">
            <p class="eyebrow">Diagn&oacute;stico confiable</p>
            <h2>Tecnolog&iacute;a avanzada</h2>
            <p>Herramientas modernas para una atenci&oacute;n precisa y oportuna.</p>
            <a class="button button-light" href="#servicios">Ver especialidades</a>
        </div>
    </article>

    <article class="home-slide" data-slide aria-hidden="true">
        <img src="/assets/especialistas.jpg" alt="Equipo de medicos especialistas">
        <div class="slide-overlay"></div>
        <div class="slide-content">
            <p class="eyebrow">Cuidado profesional</p>
            <h2>Especialistas certificados</h2>
            <p>Un equipo humano, cercano y altamente capacitado.</p>
            <a class="button button-light" href="#servicios">Agenda tu cita</a>
        </div>
    </article>

    <div class="carousel-dots" aria-label="Seleccionar diapositiva">
        <button class="is-active" type="button" data-slide-dot="0" aria-label="Mostrar diapositiva 1" aria-current="true"></button>
        <button type="button" data-slide-dot="1" aria-label="Mostrar diapositiva 2"></button>
        <button type="button" data-slide-dot="2" aria-label="Mostrar diapositiva 3"></button>
        <button class="carousel-toggle" type="button" data-carousel-toggle>Pausar</button>
    </div>
</section>

<section class="appointment-cta">
    <div>
        <strong>&iquest;Necesitas atenci&oacute;n m&eacute;dica?</strong>
        <span>Reserva tu cita de forma r&aacute;pida y segura.</span>
    </div>
    <a class="button button-primary" href="#servicios">Agendar cita</a>
</section>

<section class="services-showcase" id="servicios">
    <div class="showcase-heading">
        <p class="eyebrow">Nuestros servicios</p>
        <h2>Atenci&oacute;n para cada etapa de tu vida</h2>
        <p>Elige la especialidad que necesitas y consulta los horarios disponibles.</p>
    </div>

    <?php foreach ($services as $index => $service): ?>
        <article class="service-feature reveal-on-scroll">
            <div class="feature-copy">
                <span class="feature-number"><?= str_pad((string) ($index + 1), 2, '0', STR_PAD_LEFT) ?></span>
                <h3><?= Security::escape($service['nombre']) ?></h3>
                <p><?= Security::escape($service['descripcion']) ?></p>
                <a class="button button-primary" href="/reservar/<?= (int) $service['id_servicio'] ?>">Agendar cita</a>
            </div>
            <div class="feature-image">
                <img src="<?= Security::escape($service['url_imagen'] ?: '/assets/MEDICO.webp') ?>" alt="<?= Security::escape($service['nombre']) ?>">
            </div>
        </article>
    <?php endforeach; ?>
</section>
