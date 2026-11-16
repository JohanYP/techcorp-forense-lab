<?php
// Andamiaje del laboratorio (excluido de los logs): lo usa el generador para saber si
// el entorno ya está preparado o ya contiene un incidente simulado.
header('Content-Type: application/json');
if (!Lab::labMode()) { http_response_code(404); exit; }
require_once '/opt/techcorp-lab/linea_base.php';
$f = '/var/lib/techcorp-lab/php2shell.json';
echo json_encode([
    'webshell'   => is_file('/var/www/html/uploads/avatars/thumb_8f3a.php'),
    'estado'     => is_file($f) ? json_decode((string) file_get_contents($f), true) : null,
    'linea_base' => LineaBase::leer(),
    'logs'       => LineaBase::tamanos(),
], JSON_UNESCAPED_SLASHES);
