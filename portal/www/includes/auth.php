<?php
declare(strict_types=1);
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/layout.php';

function iniciar_sesion(): void
{
    if (session_status() !== PHP_SESSION_ACTIVE) {
        session_start();
    }
    if (!empty($_SESSION['emp'])) {
        Lab::setUser($_SESSION['emp']['usuario']);
    }
}

function usuario_actual(): ?array
{
    iniciar_sesion();
    return $_SESSION['emp'] ?? null;
}

function requiere_login(): array
{
    $u = usuario_actual();
    if ($u === null) {
        $siguiente = urlencode((string) ($_SERVER['REQUEST_URI'] ?? '/dashboard.php'));
        header('Location: /login.php?next=' . $siguiente, true, 302);
        exit;
    }
    return $u;
}

function requiere_rol(array $roles): array
{
    $u = requiere_login();
    if (!in_array($u['rol'], $roles, true)) {
        Lab::log('WARNING', 'access_denied', 'Acceso denegado por rol', ['rol' => $u['rol'], 'requeridos' => $roles]);
        http_response_code(403);
        pagina('Acceso denegado', '<div class="alerta">No tiene permisos para ver esta sección.</div>');
        exit;
    }
    return $u;
}

function puede_ver_salarios(array $u, int $empleadoId): bool
{
    return in_array($u['rol'], ['rrhh', 'gerencia'], true) || (int) $u['id'] === $empleadoId;
}
