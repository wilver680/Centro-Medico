<?php

declare(strict_types=1);

namespace Tests\Unit;


use CentroMedico\AppointmentRules; //para las reglas de las citas
use DateTimeImmutable;
use DateTimeZone;
use PHPUnit\Framework\TestCase;


final class ReglasCitaTest extends TestCase
{

private AppointmentRules $reglasCita;

protected function setUp(): void
{
    $zonaHoraria = new DateTimeZone('America/Guatemala');

    $this->reglasCita = new AppointmentRules($zonaHoraria);
}

//agendar citas 
public function testAceptaCitaFuturaEnHorarioValido(): void
{
//fecha y hora actual 
$fechaHoraActual = new DateTimeImmutable(
    '2026-10-16 06:00:00',
    new DateTimeZone('America/Guatemala')
);


//para que hora se valida la fecha de la cita 
$erroresEncontrados = $this->reglasCita->validate(
    '2026-10-26',
    '08:00',
    $fechaHoraActual
);

    $this->assertSame([], $erroresEncontrados);
}


//agendar cita en horario pasado
public function testRechazaCitaEnHoraPasada(): void
{
    //fehca y hora actual
    $fechaHoraActual = new DateTimeImmutable(
        '2026-10-16 10:40:00',
        new DateTimeZone('America/Guatemala')
    );

//para que hora se valida la fecha de la cita 
    $erroresEncontrados = $this->reglasCita->validate(
        '2026-10-16',
        '10:00',
        $fechaHoraActual
    );

    $this->assertContains(
        'No se pueden reservar fechas u horas pasadas.',
        $erroresEncontrados
    );
}

//agendar cita en fin de semana
public function testRechazaCitaEnFinDeSemana(): void
{
    //fecha y hora actual
    $fechaHoraActual = new DateTimeImmutable(
        '2026-10-16 06:00:00',
        new DateTimeZone('America/Guatemala')
    );

    //para que hora se valida la fecha de la cita
    $erroresEncontrados = $this->reglasCita->validate(
        '2026-10-24',
        '09:00',
        $fechaHoraActual
    );

    $this->assertContains(
        'El centro medico atiende de lunes a viernes.',
        $erroresEncontrados
    );
}

}