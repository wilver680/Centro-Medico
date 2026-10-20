<?php

declare(strict_types=1);

namespace CentroMedico;

use DateTimeZone;
use PDO;
use PDOException;

final class AppointmentService
{
    private AppointmentRules $rules;

    public function __construct(private readonly PDO $db)
    {
        $this->rules = new AppointmentRules(new DateTimeZone(Config::get('APP_TIMEZONE', 'America/Guatemala')));
    }
 
    // los servicios que son true
    public function services(): array
    {
        return $this->db->query(
            'SELECT id_servicio, nombre, descripcion, url_imagen 
            FROM servicio 
            WHERE activo = True 
            ORDER BY nombre'
        )->fetchAll();
    }

    public function service(int $id): ?array
    {
        $query = $this->db->prepare(
            'SELECT id_servicio, nombre, descripcion, url_imagen 
            FROM servicio WHERE id_servicio = :id 
            AND activo = TRUE'
        );
        $query->execute(['id' => $id]);
        return $query->fetch() ?: null;
    }

    public function doctors(int $serviceId): array
    {
        $query = $this->db->prepare(
            "SELECT d.id_doctor,
                    CONCAT_WS(' ', d.primer_nombre, d.segundo_nombre, d.primer_apellido, d.segundo_apellido) AS nombre
             FROM doctor d
             JOIN doctor_servicio ds ON ds.id_doctor = d.id_doctor
             WHERE ds.id_servicio = :servicio AND d.activo = TRUE
             ORDER BY d.primer_nombre, d.primer_apellido"
        );
        $query->execute(['servicio' => $serviceId]);
        return $query->fetchAll();
    }

    public function doctorProvidesService(int $serviceId, int $doctorId): bool
    {
        $query = $this->db->prepare(
            'SELECT 1 FROM doctor_servicio ds
             JOIN doctor d ON d.id_doctor = ds.id_doctor AND d.activo = TRUE
             JOIN servicio s ON s.id_servicio = ds.id_servicio AND s.activo = TRUE
             WHERE ds.id_doctor = :doctor AND ds.id_servicio = :servicio'
        );
        $query->execute(['doctor' => $doctorId, 'servicio' => $serviceId]);

        return (bool) $query->fetchColumn();
    }

    public function availableSlots(int $serviceId, int $doctorId, int $userId, string $date): array
    {
        if (!$this->doctorProvidesService($serviceId, $doctorId)) {
            return [];
        }

        $allSlots = $this->rules->slots($date);
        if ($allSlots === []) {
            return [];
        }

        $query = $this->db->prepare(
            "SELECT TO_CHAR(hora, 'HH24:MI') AS hora
             FROM cita
             WHERE fecha = :fecha
               AND estado = 'confirmada'
               AND (id_doctor = :doctor OR id_usuario = :usuario)"
        );
        $query->execute([
            'fecha' => $date,
            'doctor' => $doctorId,
            'usuario' => $userId,
        ]);
        $occupied = array_column($query->fetchAll(), 'hora');

        return array_values(array_diff($allSlots, $occupied));
    }

    public function book(int $userId, int $serviceId, int $doctorId, string $date, string $time): array
    {
        $errors = $this->rules->validate($date, $time);
        if ($errors !== []) {
            return ['ok' => false, 'message' => implode(' ', $errors)];
        }

        try {
            $this->db->beginTransaction();

            // Serializa las reservas del mismo paciente para evitar que dos
            // solicitudes simultaneas superen la validacion de disponibilidad.
            $userLock = $this->db->prepare(
                'SELECT 1 FROM usuario
                 WHERE id_usuario = :usuario AND activo = TRUE
                 FOR UPDATE'
            );
            $userLock->execute(['usuario' => $userId]);
            if (!$userLock->fetchColumn()) {
                $this->db->rollBack();
                return ['ok' => false, 'message' => 'La cuenta no esta disponible para reservar citas.'];
            }

            $patientConflict = $this->db->prepare(
                "SELECT 1 FROM cita
                 WHERE id_usuario = :usuario AND fecha = :fecha AND hora = :hora
                   AND estado = 'confirmada'
                 LIMIT 1"
            );
            $patientConflict->execute([
                'usuario' => $userId,
                'fecha' => $date,
                'hora' => $time,
            ]);
            if ($patientConflict->fetchColumn()) {
                $this->db->rollBack();
                return ['ok' => false, 'message' => 'Ya tienes una cita reservada en esa fecha y hora.'];
            }

            $assignment = $this->db->prepare(
                'SELECT 1 FROM doctor_servicio ds
                 JOIN doctor d ON d.id_doctor = ds.id_doctor AND d.activo = TRUE
                 JOIN servicio s ON s.id_servicio = ds.id_servicio AND s.activo = TRUE
                 WHERE ds.id_doctor = :doctor AND ds.id_servicio = :servicio
                 FOR SHARE'
            );
            $assignment->execute(['doctor' => $doctorId, 'servicio' => $serviceId]);
            if (!$assignment->fetchColumn()) {
                $this->db->rollBack();
                return ['ok' => false, 'message' => 'El doctor no atiende el servicio seleccionado.'];
            }

            $insert = $this->db->prepare(
                'INSERT INTO cita (id_usuario, id_servicio, id_doctor, fecha, hora)
                 VALUES (:usuario, :servicio, :doctor, :fecha, :hora)
                 RETURNING id_cita'
            );
            $insert->execute([
                'usuario' => $userId,
                'servicio' => $serviceId,
                'doctor' => $doctorId,
                'fecha' => $date,
                'hora' => $time,
            ]);
            $appointmentId = (int) $insert->fetchColumn();
            $this->db->commit();

            return ['ok' => true, 'id' => $appointmentId];
        } catch (PDOException $exception) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            if ($exception->getCode() === '23505') {
                return ['ok' => false, 'message' => 'Ese horario acaba de ser reservado. Seleccione otro.'];
            }
            if ($exception->getCode() === '23514') {
                return ['ok' => false, 'message' => 'La fecha u hora no cumple el horario de atencion.'];
            }
            throw $exception;
        }
    }

    public function appointment(int $appointmentId, int $userId): ?array
    {
        $query = $this->db->prepare($this->detailsSql() . ' WHERE c.id_cita = :id AND c.id_usuario = :usuario');
        $query->execute(['id' => $appointmentId, 'usuario' => $userId]);
        return $query->fetch() ?: null;
    }

    public function userAppointments(int $userId): array
    {
        $query = $this->db->prepare(
            $this->detailsSql()
            . " WHERE c.id_usuario = :usuario
                ORDER BY
                    CASE
                        WHEN c.estado = 'confirmada' AND (c.fecha + c.hora) > CURRENT_TIMESTAMP THEN 0
                        WHEN c.estado = 'confirmada' THEN 1
                        ELSE 2
                    END,
                    CASE WHEN (c.fecha + c.hora) > CURRENT_TIMESTAMP THEN c.fecha END,
                    c.fecha DESC,
                    c.hora"
        );
        $query->execute(['usuario' => $userId]);
        return $query->fetchAll();
    }

    public function cancel(int $appointmentId, int $userId): bool
    {
        $query = $this->db->prepare(
            "UPDATE cita
             SET estado = 'cancelada'
             WHERE id_cita = :id
               AND id_usuario = :usuario
               AND estado = 'confirmada'
               AND (fecha + hora) > CURRENT_TIMESTAMP
             RETURNING id_cita"
        );
        $query->execute(['id' => $appointmentId, 'usuario' => $userId]);

        return (bool) $query->fetchColumn();
    }

    private function detailsSql(): string
    {
        return "SELECT c.id_cita, c.fecha, TO_CHAR(c.hora, 'HH24:MI') AS hora, c.estado,
                       CASE
                           WHEN c.estado = 'cancelada' THEN 'cancelada'
                           WHEN (c.fecha + c.hora) <= CURRENT_TIMESTAMP THEN 'completada'
                           ELSE 'confirmada'
                       END AS estado_visible,
                       CASE WHEN c.estado = 'confirmada' AND (c.fecha + c.hora) > CURRENT_TIMESTAMP
                            THEN 1 ELSE 0 END AS puede_cancelar,
                       s.nombre AS servicio,
                       CONCAT_WS(' ', d.primer_nombre, d.segundo_nombre, d.primer_apellido, d.segundo_apellido) AS doctor
                FROM cita c
                JOIN servicio s ON s.id_servicio = c.id_servicio
                JOIN doctor d ON d.id_doctor = c.id_doctor";
    }
}
