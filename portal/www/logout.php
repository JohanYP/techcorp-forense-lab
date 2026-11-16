<?php
require __DIR__ . '/includes/auth.php';
if (usuario_actual()) {
    Lab::log('INFO', 'logout', 'Cierre de sesión');
}
$_SESSION = [];
session_destroy();
header('Location: /login.php', true, 302);
