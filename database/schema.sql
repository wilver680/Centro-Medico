BEGIN;

CREATE TABLE usuario (
    id_usuario INTEGER GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    primer_nombre VARCHAR(50) NOT NULL CHECK (TRIM(primer_nombre) <> ''),
    segundo_nombre VARCHAR(50),
    tercer_nombre VARCHAR(50),
    cuarto_nombre VARCHAR(50),
    primer_apellido VARCHAR(50) NOT NULL CHECK (TRIM(primer_apellido) <> ''),
    segundo_apellido VARCHAR(50),
    apellido_casada VARCHAR(50),
    correo VARCHAR(150) NOT NULL CHECK (TRIM(correo) <> ''),
    contrasena_hash TEXT NOT NULL,
    telefono VARCHAR(20),
    activo BOOLEAN NOT NULL DEFAULT TRUE,
    creado_en TIMESTAMPTZ NOT NULL DEFAULT CURRENT_TIMESTAMP
);

CREATE UNIQUE INDEX uq_usuario_correo ON usuario (LOWER(TRIM(correo)));

CREATE TABLE servicio (
    id_servicio INTEGER GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    nombre VARCHAR(100) NOT NULL CHECK (TRIM(nombre) <> ''),
    descripcion TEXT NOT NULL CHECK (TRIM(descripcion) <> ''),
    url_imagen TEXT NOT NULL CHECK (TRIM(url_imagen) <> ''),
    activo BOOLEAN NOT NULL DEFAULT TRUE,
    creado_en TIMESTAMPTZ NOT NULL DEFAULT CURRENT_TIMESTAMP
);

CREATE UNIQUE INDEX uq_servicio_nombre ON servicio (LOWER(TRIM(nombre)));

CREATE TABLE doctor (
    id_doctor INTEGER GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    primer_nombre VARCHAR(50) NOT NULL CHECK (TRIM(primer_nombre) <> ''),
    segundo_nombre VARCHAR(50),
    tercer_nombre VARCHAR(50),
    cuarto_nombre VARCHAR(50),
    primer_apellido VARCHAR(50) NOT NULL CHECK (TRIM(primer_apellido) <> ''),
    segundo_apellido VARCHAR(50),
    apellido_casada VARCHAR(50),
    activo BOOLEAN NOT NULL DEFAULT TRUE,
    creado_en TIMESTAMPTZ NOT NULL DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE doctor_servicio (
    id_doctor INTEGER NOT NULL REFERENCES doctor(id_doctor),
    id_servicio INTEGER NOT NULL REFERENCES servicio(id_servicio),
    PRIMARY KEY (id_doctor, id_servicio)
);

CREATE TABLE cita (
    id_cita INTEGER GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    id_usuario INTEGER NOT NULL REFERENCES usuario(id_usuario),
    id_servicio INTEGER NOT NULL,
    id_doctor INTEGER NOT NULL,
    fecha DATE NOT NULL,
    hora TIME WITHOUT TIME ZONE NOT NULL,
    estado VARCHAR(20) NOT NULL DEFAULT 'confirmada'
        CHECK (estado IN ('confirmada', 'cancelada')),
    creado_en TIMESTAMPTZ NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_cita_doctor_servicio
        FOREIGN KEY (id_doctor, id_servicio)
        REFERENCES doctor_servicio(id_doctor, id_servicio),
    CONSTRAINT ck_cita_dia_laboral CHECK (EXTRACT(ISODOW FROM fecha) BETWEEN 1 AND 5),
    CONSTRAINT ck_cita_horario CHECK (hora >= TIME '08:00' AND hora < TIME '17:00'),
    CONSTRAINT ck_cita_intervalo CHECK (
        EXTRACT(MINUTE FROM hora) IN (0, 30)
        AND EXTRACT(SECOND FROM hora) = 0
    )
);

CREATE UNIQUE INDEX uq_cita_doctor_horario
    ON cita (id_doctor, fecha, hora)
    WHERE estado = 'confirmada';
CREATE INDEX ix_cita_usuario_fecha_hora ON cita (id_usuario, fecha, hora);

CREATE TABLE intento_login (
    clave CHAR(64) PRIMARY KEY,
    intentos SMALLINT NOT NULL DEFAULT 0,
    ventana_inicia TIMESTAMPTZ NOT NULL DEFAULT CURRENT_TIMESTAMP,
    bloqueado_hasta TIMESTAMPTZ,
    actualizado_en TIMESTAMPTZ NOT NULL DEFAULT CURRENT_TIMESTAMP
);

CREATE INDEX ix_intento_login_actualizado ON intento_login (actualizado_en);

COMMIT;
