<?php
declare(strict_types=1);
require __DIR__ . '/includes/auth.php';
iniciar_sesion();

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $usuario = trim((string) ($_POST['usuario'] ?? ''));
    $clave = (string) ($_POST['password'] ?? '');
    if ($usuario === '' || $clave === '') {
        Lab::log('WARNING', 'login_malformed', 'Petición de inicio de sesión sin credenciales', [
            'content_type' => $_SERVER['CONTENT_TYPE'] ?? null,
            'campos'       => array_keys($_POST),
            'archivos'     => count($_FILES),
        ]);
        $error = 'Complete usuario y contraseña.';
    } else {
        $emp = Db::app()->uno(
            'SELECT id, usuario, nombres, apellidos, rol, departamento, password_hash FROM empleados WHERE usuario = ? AND activo = 1',
            [$usuario]
        );
        if ($emp && password_verify($clave, $emp['password_hash'])) {
            session_regenerate_id(true);
            unset($emp['password_hash']);
            $_SESSION['emp'] = $emp;
            Lab::setUser($emp['usuario']);
            Lab::log('INFO', 'login_success', 'Inicio de sesión correcto', ['rol' => $emp['rol']]);
            $siguiente = (string) ($_GET['next'] ?? '/dashboard.php');
            if (!str_starts_with($siguiente, '/') || str_starts_with($siguiente, '//')) {
                $siguiente = '/dashboard.php';
            }
            header('Location: ' . $siguiente, true, 302);
            exit;
        }
        Lab::log('WARNING', 'login_failed', 'Credenciales inválidas', ['usuario_intentado' => $usuario, 'existe' => (bool) $emp]);
        $error = 'Usuario o contraseña incorrectos.';
    }
}

$cuerpo = '<form method="post" class="tarjeta login">'
    . ($error ? '<div class="alerta">' . e($error) . '</div>' : '')
    . '<label>Usuario<input name="usuario" autocomplete="username" required></label>'
    . '<label>Contraseña<input name="password" type="password" autocomplete="current-password" required></label>'
    . '<button>Ingresar</button>'
    . '<p class="nota">Laboratorio: use cualquier usuario de la tabla empleados (p. ej. <code>demo</code>) con la contraseña de <code>LAB_PASSWORD</code>.</p>'
    . '</form>';
pagina('Iniciar sesión', $cuerpo, false);
