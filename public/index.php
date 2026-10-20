<?php

declare(strict_types=1);

use CentroMedico\AppointmentService;
use CentroMedico\Auth;
use CentroMedico\Config;
use CentroMedico\Database;
use CentroMedico\Security;

$autoload = dirname(__DIR__) . '/vendor/autoload.php';
if (is_file($autoload)) {
    require $autoload;
} else {
    spl_autoload_register(static function (string $class): void {
        $prefix = 'CentroMedico\\';
        if (str_starts_with($class, $prefix)) {
            $file = dirname(__DIR__) . '/src/' . substr($class, strlen($prefix)) . '.php';
            if (is_file($file)) {
                require $file;
            }
        }
    });
}

Config::load(dirname(__DIR__) . '/.env');

$environment = strtolower(Config::get('APP_ENV', 'production'));
$debugEnabled = $environment !== 'production'
    && filter_var(Config::get('APP_DEBUG', 'false'), FILTER_VALIDATE_BOOL);
ini_set('display_errors', $debugEnabled ? '1' : '0');
ini_set('display_startup_errors', $debugEnabled ? '1' : '0');
ini_set('log_errors', '1');
ini_set('session.use_strict_mode', '1');
ini_set('session.use_only_cookies', '1');
ini_set('session.cookie_httponly', '1');
ini_set('session.cookie_samesite', 'Lax');
$httpsEnabled = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
    || parse_url(Config::get('APP_URL'), PHP_URL_SCHEME) === 'https';
if ($httpsEnabled) {
    ini_set('session.cookie_secure', '1');
}
session_name('centro_medico_session');
session_start();

$idleSeconds = max(300, (int) Config::get('SESSION_IDLE_MINUTES', '30') * 60);
if (isset($_SESSION['last_activity']) && time() - (int) $_SESSION['last_activity'] > $idleSeconds) {
    $_SESSION = [];
    session_regenerate_id(true);
}
$_SESSION['last_activity'] = time();

header_remove('X-Powered-By');
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header('Referrer-Policy: strict-origin-when-cross-origin');
header('Permissions-Policy: camera=(), microphone=(), geolocation=()');
header("Content-Security-Policy: default-src 'self'; base-uri 'none'; form-action 'self'; object-src 'none'; style-src 'self' https://fonts.googleapis.com; font-src https://fonts.gstatic.com; script-src 'self'; img-src 'self' data:");
if ($httpsEnabled) {
    header('Strict-Transport-Security: max-age=31536000; includeSubDomains');
}

$route = trim(parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH), '/');
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

try {
    $db = Database::connection();
    $auth = new Auth($db);
    $appointments = new AppointmentService($db);
    $user = $auth->user();
} catch (Throwable $exception) {
    error_log($exception->__toString());
    http_response_code(503);
    header('Content-Type: text/plain; charset=utf-8');
    echo 'El servicio no esta disponible temporalmente.';
    exit;
}

function redirect(string $path): never
{
    if (!str_starts_with($path, '/') || str_starts_with($path, '//')) {
        $path = '/';
    }
    header('Location: ' . $path, true, 303);
    exit;
}

function render(string $view, array $data = []): void
{
    extract($data, EXTR_SKIP);
    require dirname(__DIR__) . '/views/layout.php';
}

function requireUser(?array $user): array
{
    if ($user === null) {
        $_SESSION['intended_url'] = $_SERVER['REQUEST_URI'] ?? '/';
        redirect('/login');
    }
    return $user;
}

function jsonResponse(array $data, int $status = 200): never
{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store');
    echo json_encode($data, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);
    exit;
}

function requireApiUser(?array $user): array
{
    if ($user === null) {
        jsonResponse(['error' => 'Autenticacion requerida.'], 401);
    }

    return $user;
}

function requestString(array $source, string $key): string
{
    $value = $source[$key] ?? '';
    return is_string($value) ? $value : '';
}

function allowedMethods(string $route): array
{
    return match (true) {
        $route === '' => ['GET'],
        $route === 'login', $route === 'registro' => ['GET', 'POST'],
        $route === 'logout', $route === 'citas' => ['POST'],
        $route === 'mis-citas' => ['GET'],
        $route === 'api/csrf', $route === 'api/servicios', $route === 'api/disponibilidad' => ['GET'],
        preg_match('#^api/servicios/\d+/doctores$#', $route) === 1 => ['GET'],
        preg_match('#^reservar/\d+$#', $route) === 1 => ['GET'],
        preg_match('#^citas/\d+$#', $route) === 1 => ['GET'],
        preg_match('#^citas/\d+/cancelar$#', $route) === 1 => ['POST'],
        default => [],
    };
}

function requiresCsrf(string $route, string $method): bool
{
    if ($method !== 'POST') {
        return false;
    }

    return in_array($route, ['login', 'registro', 'logout', 'citas'], true)
        || preg_match('#^citas/\d+/cancelar$#', $route) === 1;
}

try {
    $csrfToken = $_POST['_csrf'] ?? ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? null);
    if (requiresCsrf($route, $method) && !Security::verifyCsrf($csrfToken)) {
        http_response_code(419);
        render('error', [
            'title' => 'Solicitud expirada',
            'message' => 'Actualice la pagina e intente nuevamente.',
            'user' => $user,
        ]);
        return;
    }

    if ($route === 'api/csrf' && $method === 'GET') {
        jsonResponse(['csrf_token' => Security::csrfToken()]);
    }

    if ($route === 'api/servicios' && $method === 'GET') {
        jsonResponse(['services' => $appointments->services()]);
    }

    if (preg_match('#^api/servicios/(\d+)/doctores$#', $route, $matches) && $method === 'GET') {
        $service = $appointments->service((int) $matches[1]);
        if ($service === null) {
            jsonResponse(['error' => 'Servicio no encontrado.'], 404);
        }
        jsonResponse(['doctors' => $appointments->doctors((int) $service['id_servicio'])]);
    }

    if ($route === '' && $method === 'GET') {
        render('home', ['title' => 'Centro Medico', 'services' => $appointments->services(), 'user' => $user]);
        return;
    }

    if ($route === 'login' && $method === 'GET') {
        render('login', ['title' => 'Iniciar sesion', 'user' => $user]);
        return;
    }

    if ($route === 'login' && $method === 'POST') {
        if ($auth->login(
            requestString($_POST, 'correo'),
            requestString($_POST, 'contrasena'),
            Security::clientIp()
        )) {
            Security::rotateCsrfToken();
            $destination = $_SESSION['intended_url'] ?? '/';
            unset($_SESSION['intended_url']);
            redirect($destination);
        }
        if ($auth->retryAfter() > 0) {
            http_response_code(429);
            header('Retry-After: ' . $auth->retryAfter());
            $minutes = max(1, (int) ceil($auth->retryAfter() / 60));
            $error = "Demasiados intentos. Intente nuevamente en {$minutes} minutos.";
        } else {
            $error = 'Correo o contrasena incorrectos.';
        }
        render('login', ['title' => 'Iniciar sesion', 'error' => $error, 'user' => null]);
        return;
    }

    if ($route === 'registro' && $method === 'GET') {
        render('register', ['title' => 'Crear cuenta', 'errors' => [], 'old' => [], 'user' => $user]);
        return;
    }

    if ($route === 'registro' && $method === 'POST') {
        $fields = [
            'primer_nombre', 'segundo_nombre', 'tercer_nombre', 'cuarto_nombre',
            'primer_apellido', 'segundo_apellido', 'apellido_casada', 'correo',
            'contrasena', 'confirmar_contrasena', 'telefono',
        ];
        $registration = [];
        foreach ($fields as $field) {
            $registration[$field] = requestString($_POST, $field);
        }
        $errors = $auth->register($registration);
        if ($errors === []) {
            Security::rotateCsrfToken();
            $destination = $_SESSION['intended_url'] ?? '/';
            unset($_SESSION['intended_url']);
            redirect($destination);
        }
        unset($registration['contrasena'], $registration['confirmar_contrasena']);
        render('register', ['title' => 'Crear cuenta', 'errors' => $errors, 'old' => $registration, 'user' => null]);
        return;
    }

    if ($route === 'logout' && $method === 'POST') {
        $auth->logout();
        redirect('/');
    }

    if (preg_match('#^reservar/(\d+)$#', $route, $matches) && $method === 'GET') {
        $user = requireUser($user);
        $service = $appointments->service((int) $matches[1]);
        if (!$service) {
            http_response_code(404);
            render('error', ['title' => 'Servicio no encontrado', 'message' => 'El servicio solicitado no esta disponible.', 'user' => $user]);
            return;
        }
        render('book', ['title' => 'Reservar cita', 'service' => $service, 'doctors' => $appointments->doctors((int) $service['id_servicio']), 'user' => $user]);
        return;
    }

    if ($route === 'api/disponibilidad' && $method === 'GET') {
        $user = requireApiUser($user);
        $serviceId = filter_input(INPUT_GET, 'servicio', FILTER_VALIDATE_INT);
        $doctorId = filter_input(INPUT_GET, 'doctor', FILTER_VALIDATE_INT);
        $date = requestString($_GET, 'fecha');
        if (!$serviceId || !$doctorId || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
            jsonResponse(['error' => 'Parametros invalidos.'], 422);
        }
        if (!$appointments->doctorProvidesService($serviceId, $doctorId)) {
            jsonResponse(['error' => 'El doctor no atiende el servicio seleccionado.'], 422);
        }
        jsonResponse([
            'slots' => $appointments->availableSlots(
                $serviceId,
                $doctorId,
                (int) $user['id_usuario'],
                $date
            ),
        ]);
    }

    if ($route === 'citas' && $method === 'POST') {
        $user = requireUser($user);
        $serviceId = filter_input(INPUT_POST, 'id_servicio', FILTER_VALIDATE_INT);
        $doctorId = filter_input(INPUT_POST, 'id_doctor', FILTER_VALIDATE_INT);
        $date = requestString($_POST, 'fecha');
        $time = requestString($_POST, 'hora');
        if (!$serviceId || !$doctorId) {
            $_SESSION['flash_error'] = 'Seleccione un servicio y doctor validos.';
            redirect('/');
        }
        $result = $appointments->book((int) $user['id_usuario'], $serviceId, $doctorId, $date, $time);
        if (!$result['ok']) {
            $_SESSION['flash_error'] = $result['message'];
            redirect('/reservar/' . $serviceId);
        }
        redirect('/citas/' . $result['id']);
    }

    if ($route === 'mis-citas' && $method === 'GET') {
        $user = requireUser($user);
        render('appointments', ['title' => 'Mis citas', 'appointments' => $appointments->userAppointments((int) $user['id_usuario']), 'user' => $user]);
        return;
    }

    if (preg_match('#^citas/(\d+)/cancelar$#', $route, $matches) && $method === 'POST') {
        $user = requireUser($user);
        if ($appointments->cancel((int) $matches[1], (int) $user['id_usuario'])) {
            $_SESSION['flash_success'] = 'La cita fue cancelada correctamente.';
        } else {
            $_SESSION['flash_error'] = 'La cita no se puede cancelar o ya no esta disponible.';
        }
        redirect('/mis-citas');
    }

    if (preg_match('#^citas/(\d+)$#', $route, $matches) && $method === 'GET') {
        $user = requireUser($user);
        $appointment = $appointments->appointment((int) $matches[1], (int) $user['id_usuario']);
        if (!$appointment) {
            http_response_code(404);
            render('error', ['title' => 'Cita no encontrada', 'message' => 'La cita no existe o no pertenece a su cuenta.', 'user' => $user]);
            return;
        }
        $appointmentTitle = $appointment['estado'] === 'cancelada' ? 'Cita cancelada' : 'Cita confirmada';
        render('confirmation', ['title' => $appointmentTitle, 'appointment' => $appointment, 'user' => $user]);
        return;
    }

    $allowed = allowedMethods($route);
    if ($allowed !== []) {
        header('Allow: ' . implode(', ', $allowed));
        if (str_starts_with($route, 'api/')) {
            jsonResponse(['error' => 'Metodo no permitido.'], 405);
        }
        http_response_code(405);
        render('error', ['title' => 'Metodo no permitido', 'message' => 'La operacion solicitada no esta disponible.', 'user' => $user]);
        return;
    }
    if (str_starts_with($route, 'api/')) {
        jsonResponse(['error' => 'Endpoint no encontrado.'], 404);
    }
    http_response_code(404);
    render('error', ['title' => 'Pagina no encontrada', 'message' => 'La pagina solicitada no existe.', 'user' => $user]);
} catch (Throwable $exception) {
    error_log($exception->__toString());
    if (str_starts_with($route, 'api/')) {
        jsonResponse(['error' => 'No fue posible completar la solicitud.'], 500);
    }
    http_response_code(500);
    render('error', ['title' => 'Ocurrio un error', 'message' => 'No pudimos completar la solicitud. Intente nuevamente.', 'user' => $user]);
}
