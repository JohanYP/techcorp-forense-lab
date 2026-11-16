<?php
// Chequeo de salud para el monitoreo (Zabbix). No requiere sesión.
declare(strict_types=1);
require __DIR__ . '/../includes/db.php';
$ok = (int) Db::app()->valor('SELECT 1 AS ok') === 1;
header('Content-Type: application/json');
http_response_code($ok ? 200 : 503);
echo json_encode(['status' => $ok ? 'ok' : 'degradado', 'servicio' => 'portal', 'nodo' => 'web-01']);
