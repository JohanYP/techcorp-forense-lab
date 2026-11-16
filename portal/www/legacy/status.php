<?php
// Endpoint heredado (2019) que aún consulta el viejo monitor Nagios cada 5 minutos.
// Código antiguo: genera advertencias en cada llamada (ruido normal en el log de aplicación).
require __DIR__ . '/../includes/db.php';
$monitor = $_SERVER['HTTP_X_MONITOR'];          // Warning: Undefined array key
$hora = Db::app()->valor('SELECT NOW() AS ahora');
header('Content-Type: text/plain');
echo "OK " . $hora . "\n";
