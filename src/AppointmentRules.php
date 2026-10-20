<?php

declare(strict_types=1);

namespace CentroMedico;

use DateTimeImmutable;
use DateTimeZone;

final class AppointmentRules
{
    public const OPEN_TIME = '08:00';
    public const CLOSE_TIME = '17:00';
    public const SLOT_MINUTES = 30;

    public function __construct(private readonly DateTimeZone $timezone)
    {
    }


    //horarios validos para agendar citas  
    public function validate(string $date, string $time, ?DateTimeImmutable $now = null): array
    {
        $errors = [];
       // $variablePruebaSinUso = 'demostracion-sonarqube';
        $appointment = DateTimeImmutable::createFromFormat('!Y-m-d H:i', $date . ' ' . $time, $this->timezone);
        $formatErrors = DateTimeImmutable::getLastErrors();

        if (!$appointment || ($formatErrors !== false && ($formatErrors['warning_count'] > 0 || $formatErrors['error_count'] > 0))
            || $appointment->format('Y-m-d H:i') !== $date . ' ' . $time) {
            return ['La fecha u hora no tiene un formato valido.'];
        }

        $now ??= new DateTimeImmutable('now', $this->timezone);
        if ($appointment <= $now) {
            $errors[] = 'No se pueden reservar fechas u horas pasadas.';
        }
        if ((int) $appointment->format('N') > 5) {
            $errors[] = 'El centro medico atiende de lunes a viernes.';
        }
        if ($time < self::OPEN_TIME || $time >= self::CLOSE_TIME) {
            $errors[] = 'El horario de atencion es de 08:00 a 17:00.';
        }
        if ((int) $appointment->format('i') % self::SLOT_MINUTES !== 0) {
            $errors[] = 'Las citas se asignan en intervalos de 30 minutos.';
        }

        return $errors;
    }

    public function slots(string $date, ?DateTimeImmutable $now = null): array
    {
        $day = DateTimeImmutable::createFromFormat('!Y-m-d', $date, $this->timezone);
        $formatErrors = DateTimeImmutable::getLastErrors();
        if (!$day || ($formatErrors !== false && ($formatErrors['warning_count'] > 0 || $formatErrors['error_count'] > 0))
            || $day->format('Y-m-d') !== $date || (int) $day->format('N') > 5) {
            return [];
        }

        [$openHour, $openMinute] = array_map('intval', explode(':', self::OPEN_TIME));
        [$closeHour, $closeMinute] = array_map('intval', explode(':', self::CLOSE_TIME));
        $start = $day->setTime($openHour, $openMinute);
        $end = $day->setTime($closeHour, $closeMinute);
        $now ??= new DateTimeImmutable('now', $this->timezone);
        $slots = [];

        for ($slot = $start; $slot < $end; $slot = $slot->modify('+' . self::SLOT_MINUTES . ' minutes')) {
            if ($slot > $now) {
                $slots[] = $slot->format('H:i');
            }
        }
        return $slots;
    }
}
