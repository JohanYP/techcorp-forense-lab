<?php
// Reinicio RÁPIDO del ataque simulado (solo laboratorio): borra la webshell, el contador de
// intentos y los restos de /tmp para poder repetir el notebook de ataque en vivo sin
// reconstruir los contenedores. NO toca la base de datos.
// Con ?logs=1 además recorta los logs a la línea base (la semana normal, sin ataques).
header('Content-Type: application/json');
if (!Lab::labMode()) { http_response_code(404); exit; }
require_once '/opt/techcorp-lab/linea_base.php';

$webshell = '/var/www/html/uploads/avatars/thumb_8f3a.php';
$estado   = '/var/lib/techcorp-lab/php2shell.json';
$borrados = [];
foreach ([$webshell, $estado, '/tmp/.d.tgz', '/tmp/.w.tgz'] as $f) {
    if (is_file($f) && @unlink($f)) {
        $borrados[] = $f;
    }
}
foreach (glob('/tmp/.d/*') ?: [] as $f) {
    @unlink($f);
}
if (is_dir('/tmp/.d') && @rmdir('/tmp/.d')) {
    $borrados[] = '/tmp/.d';
}

$res = ['reiniciado' => true, 'borrados' => $borrados];
if (($_GET['logs'] ?? '') === '1') {
    $res['logs_bytes_quitados'] = LineaBase::restaurar();
}
echo json_encode($res, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
