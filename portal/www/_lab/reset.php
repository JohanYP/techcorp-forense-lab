<?php
// Reinicio RÁPIDO del ataque simulado (solo laboratorio): borra la webshell y el contador de
// intentos para poder repetir el notebook de ataque en vivo sin reconstruir los contenedores.
// NO toca la base de datos ni los logs ya escritos.
header('Content-Type: application/json');
if (!Lab::labMode()) { http_response_code(404); exit; }

$webshell = '/var/www/html/uploads/avatars/thumb_8f3a.php';
$estado   = '/var/lib/techcorp-lab/php2shell.json';
$borrados = [];
foreach ([$webshell, $estado] as $f) {
    if (is_file($f) && @unlink($f)) {
        $borrados[] = $f;
    }
}
echo json_encode(['reiniciado' => true, 'borrados' => $borrados], JSON_UNESCAPED_SLASHES);
