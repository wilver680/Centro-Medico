BEGIN;

ALTER TABLE cita
    ADD COLUMN IF NOT EXISTS estado VARCHAR(20) NOT NULL DEFAULT 'confirmada';

ALTER TABLE cita DROP CONSTRAINT IF EXISTS uq_cita_doctor_horario;
ALTER TABLE cita DROP CONSTRAINT IF EXISTS uq_cita_servicio_horario;
DROP INDEX IF EXISTS uq_cita_doctor_horario;
DROP INDEX IF EXISTS uq_cita_servicio_horario;

ALTER TABLE cita DROP CONSTRAINT IF EXISTS ck_cita_estado;
ALTER TABLE cita ADD CONSTRAINT ck_cita_estado
    CHECK (estado IN ('confirmada', 'cancelada'));
ALTER TABLE cita DROP CONSTRAINT IF EXISTS ck_cita_dia_laboral;
ALTER TABLE cita ADD CONSTRAINT ck_cita_dia_laboral
    CHECK (EXTRACT(ISODOW FROM fecha) BETWEEN 1 AND 5);
ALTER TABLE cita DROP CONSTRAINT IF EXISTS ck_cita_horario;
ALTER TABLE cita ADD CONSTRAINT ck_cita_horario
    CHECK (hora >= TIME '08:00' AND hora < TIME '17:00');
ALTER TABLE cita DROP CONSTRAINT IF EXISTS ck_cita_intervalo;
ALTER TABLE cita ADD CONSTRAINT ck_cita_intervalo
    CHECK (EXTRACT(MINUTE FROM hora) IN (0, 30) AND EXTRACT(SECOND FROM hora) = 0);

CREATE UNIQUE INDEX uq_cita_doctor_horario
    ON cita (id_doctor, fecha, hora)
    WHERE estado = 'confirmada';
CREATE TABLE IF NOT EXISTS intento_login (
    clave CHAR(64) PRIMARY KEY,
    intentos SMALLINT NOT NULL DEFAULT 0,
    ventana_inicia TIMESTAMPTZ NOT NULL DEFAULT CURRENT_TIMESTAMP,
    bloqueado_hasta TIMESTAMPTZ,
    actualizado_en TIMESTAMPTZ NOT NULL DEFAULT CURRENT_TIMESTAMP
);

CREATE INDEX IF NOT EXISTS ix_intento_login_actualizado
    ON intento_login (actualizado_en);

COMMIT;
