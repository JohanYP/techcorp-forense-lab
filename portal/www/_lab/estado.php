<?php
// Andamiaje del laboratorio (excluido de los logs): lo usa el generador para saber si
// el entorno ya contiene un incidente simulado.
header('Content-Type: application/json');
if (!Lab::labMode()) { http_response_code(404); exit; }
$f = '/var/lib/techcorp-lab/php2shell.json';
echo json_encode([
    'webshell' => is_file('/var/www/html/uploads/avatars/thumb_8f3a.php'),
    'estado'   => is_file($f) ? json_decode((string) file_get_contents($f), true) : null,
]);
