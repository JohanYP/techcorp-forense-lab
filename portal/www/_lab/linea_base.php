<?php
// Andamiaje del laboratorio (excluido de los logs): el generador lo llama (POST) al terminar
// la semana normal para guardar la línea base de la evidencia. GET solo la consulta.
header('Content-Type: application/json');
if (!Lab::labMode()) { http_response_code(404); exit; }
require_once '/opt/techcorp-lab/linea_base.php';

$res = ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST' ? LineaBase::guardar() : LineaBase::leer();
echo json_encode($res, JSON_UNESCAPED_SLASHES);
