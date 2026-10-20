<?php

declare(strict_types=1);

namespace CentroMedico;

use PDO;

final class Auth
{
    private const DUMMY_PASSWORD_HASH = '$2y$12$6czbS4WBw.x7a/qz0XLhgeuq2qP6zHK/f9qXOGgD7PdLZ1xVrRf/y';
    private const LOGIN_MAX_ATTEMPTS = 8;
    private const LOGIN_WINDOW_MINUTES = 15;

    private int $retryAfter = 0;

    public function __construct(private readonly PDO $db)
    {
    }

    public function user(): ?array
    {
        if (empty($_SESSION['user_id'])) {
            return null;
        }

        $query = $this->db->prepare(
            'SELECT id_usuario, primer_nombre, primer_apellido, correo
             FROM usuario WHERE id_usuario = :id AND activo = TRUE'
        );
        $query->execute(['id' => (int) $_SESSION['user_id']]);
        $user = $query->fetch();

        return $user ?: null;
    }

    public function login(string $email, string $password, string $clientIp): bool
    {
        $email = trim($email);
        $attemptKey = hash('sha256', strtolower($email) . '|' . $clientIp);
        $this->retryAfter = $this->blockedSeconds($attemptKey);
        if ($this->retryAfter > 0) {
            return false;
        }

        $user = false;
        if ($email !== '' && mb_strlen($email) <= 150 && strlen($password) <= 72 && !str_contains($password, "\0")) {
            $query = $this->db->prepare(
                'SELECT id_usuario, contrasena_hash FROM usuario
                 WHERE LOWER(TRIM(correo)) = LOWER(TRIM(:correo)) AND activo = TRUE'
            );
            $query->execute(['correo' => $email]);
            $user = $query->fetch();
        }

        $passwordForVerification = str_contains($password, "\0") ? 'invalid-password' : $password;
        $passwordIsValid = password_verify(
            $passwordForVerification,
            $user ? $user['contrasena_hash'] : self::DUMMY_PASSWORD_HASH
        );
        if (!$user || !$passwordIsValid) {
            $this->retryAfter = $this->recordFailedLogin($attemptKey);
            return false;
        }

        $clearAttempts = $this->db->prepare('DELETE FROM intento_login WHERE clave = :clave');
        $clearAttempts->execute(['clave' => $attemptKey]);
        if (password_needs_rehash($user['contrasena_hash'], PASSWORD_DEFAULT)) {
            $rehash = $this->db->prepare(
                'UPDATE usuario SET contrasena_hash = :hash WHERE id_usuario = :id'
            );
            $rehash->execute([
                'hash' => password_hash($password, PASSWORD_DEFAULT),
                'id' => (int) $user['id_usuario'],
            ]);
        }
        session_regenerate_id(true);
        $_SESSION['user_id'] = (int) $user['id_usuario'];
        return true;
    }

    public function retryAfter(): int
    {
        return $this->retryAfter;
    }

    public function register(array $data): array
    {
        $errors = $this->validateRegistration($data);
        if ($errors !== []) {
            return $errors;
        }

        $query = $this->db->prepare(
            'INSERT INTO usuario
             (primer_nombre, segundo_nombre, tercer_nombre, cuarto_nombre,
              primer_apellido, segundo_apellido, apellido_casada, correo,
              contrasena_hash, telefono)
             VALUES
             (:primer_nombre, :segundo_nombre, :tercer_nombre, :cuarto_nombre,
              :primer_apellido, :segundo_apellido, :apellido_casada, :correo,
              :contrasena_hash, :telefono)
             RETURNING id_usuario'
        );

        try {
            $query->execute([
                'primer_nombre' => trim($data['primer_nombre']),
                'segundo_nombre' => self::nullable($data['segundo_nombre'] ?? null),
                'tercer_nombre' => self::nullable($data['tercer_nombre'] ?? null),
                'cuarto_nombre' => self::nullable($data['cuarto_nombre'] ?? null),
                'primer_apellido' => trim($data['primer_apellido']),
                'segundo_apellido' => self::nullable($data['segundo_apellido'] ?? null),
                'apellido_casada' => self::nullable($data['apellido_casada'] ?? null),
                'correo' => strtolower(trim($data['correo'])),
                'contrasena_hash' => password_hash($data['contrasena'], PASSWORD_DEFAULT),
                'telefono' => self::nullable($data['telefono'] ?? null),
            ]);
            $_SESSION['user_id'] = (int) $query->fetchColumn();
            session_regenerate_id(true);
            return [];
        } catch (\PDOException $exception) {
            if ($exception->getCode() === '23505') {
                return ['correo' => 'Ya existe una cuenta con este correo.'];
            }
            throw $exception;
        }
    }

    public function logout(): void
    {
        $_SESSION = [];
        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', [
                'expires' => time() - 42000,
                'path' => $params['path'],
                'domain' => $params['domain'],
                'secure' => $params['secure'],
                'httponly' => $params['httponly'],
                'samesite' => $params['samesite'] ?? 'Lax',
            ]);
        }
        session_destroy();
    }

    private function blockedSeconds(string $attemptKey): int
    {
        $query = $this->db->prepare(
            'SELECT CEIL(EXTRACT(EPOCH FROM bloqueado_hasta - CURRENT_TIMESTAMP))::INTEGER
             FROM intento_login
             WHERE clave = :clave AND bloqueado_hasta > CURRENT_TIMESTAMP'
        );
        $query->execute(['clave' => $attemptKey]);

        return max(0, (int) ($query->fetchColumn() ?: 0));
    }

    private function recordFailedLogin(string $attemptKey): int
    {
        $query = $this->db->prepare(
            "WITH parametros AS (SELECT make_interval(mins => :window_minutes) AS ventana)
             INSERT INTO intento_login (clave, intentos, ventana_inicia, bloqueado_hasta, actualizado_en)
             VALUES (:clave, 1, CURRENT_TIMESTAMP, NULL, CURRENT_TIMESTAMP)
             ON CONFLICT (clave) DO UPDATE SET
                 intentos = CASE
                     WHEN intento_login.ventana_inicia < CURRENT_TIMESTAMP - (SELECT ventana FROM parametros) THEN 1
                     ELSE intento_login.intentos + 1
                 END,
                 ventana_inicia = CASE
                     WHEN intento_login.ventana_inicia < CURRENT_TIMESTAMP - (SELECT ventana FROM parametros) THEN CURRENT_TIMESTAMP
                     ELSE intento_login.ventana_inicia
                 END,
                 bloqueado_hasta = CASE
                     WHEN intento_login.ventana_inicia >= CURRENT_TIMESTAMP - (SELECT ventana FROM parametros)
                          AND intento_login.intentos + 1 >= :max_attempts
                     THEN CURRENT_TIMESTAMP + (SELECT ventana FROM parametros)
                     ELSE NULL
                 END,
                 actualizado_en = CURRENT_TIMESTAMP
             RETURNING COALESCE(
                 CEIL(EXTRACT(EPOCH FROM bloqueado_hasta - CURRENT_TIMESTAMP))::INTEGER,
                 0
             )"
        );
        $query->execute([
            'clave' => $attemptKey,
            'max_attempts' => self::LOGIN_MAX_ATTEMPTS,
            'window_minutes' => self::LOGIN_WINDOW_MINUTES,
        ]);
        $retryAfter = max(0, (int) $query->fetchColumn());

        if (random_int(1, 100) === 1) {
            $this->db->exec(
                "DELETE FROM intento_login
                 WHERE actualizado_en < CURRENT_TIMESTAMP - INTERVAL '1 day'"
            );
        }

        return $retryAfter;
    }

    private function validateRegistration(array $data): array
    {
        $errors = [];
        if (trim($data['primer_nombre'] ?? '') === '') {
            $errors['primer_nombre'] = 'El primer nombre es obligatorio.';
        }
        if (trim($data['primer_apellido'] ?? '') === '') {
            $errors['primer_apellido'] = 'El primer apellido es obligatorio.';
        }
        if (!filter_var(trim($data['correo'] ?? ''), FILTER_VALIDATE_EMAIL)) {
            $errors['correo'] = 'Ingrese un correo valido.';
        } elseif (mb_strlen(trim($data['correo'])) > 150) {
            $errors['correo'] = 'El correo no puede superar 150 caracteres.';
        }
        if (strlen($data['contrasena'] ?? '') < 8) {
            $errors['contrasena'] = 'La contrasena debe tener al menos 8 caracteres.';
        } elseif (strlen($data['contrasena']) > 72 || str_contains($data['contrasena'], "\0")) {
            $errors['contrasena'] = 'La contrasena no puede superar 72 bytes ni contener caracteres nulos.';
        }
        if (($data['contrasena'] ?? '') !== ($data['confirmar_contrasena'] ?? '')) {
            $errors['confirmar_contrasena'] = 'Las contrasenas no coinciden.';
        }
        if (($data['telefono'] ?? '') !== '' && !preg_match('/^[0-9+() -]{7,20}$/', $data['telefono'])) {
            $errors['telefono'] = 'Ingrese un telefono valido.';
        }

        foreach (['primer_nombre', 'segundo_nombre', 'tercer_nombre', 'cuarto_nombre', 'primer_apellido', 'segundo_apellido', 'apellido_casada'] as $field) {
            if (mb_strlen(trim($data[$field] ?? '')) > 50) {
                $errors[$field] = 'Este campo no puede superar 50 caracteres.';
            }
        }
        return $errors;
    }

    private static function nullable(?string $value): ?string
    {
        $trimmed = trim((string) $value);
        return $trimmed === '' ? null : $trimmed;
    }
}
