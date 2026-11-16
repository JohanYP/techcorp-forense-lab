<?php
declare(strict_types=1);
require __DIR__ . '/../includes/auth.php';
$u = requiere_rol(['finanzas', 'gerencia']);
$tipo = (string) ($_GET['tipo'] ?? 'clientes');
$db = Db::app();

if ($tipo === 'tickets') {
    $filas = $db->query("SELECT id, cliente_id, empleado_id, asunto, estado, prioridad, creado_en FROM tickets WHERE creado_en >= DATE_SUB(?, INTERVAL 30 DAY) ORDER BY id", [Lab::sqlNow()]);
} else {
    $tipo = 'clientes';
    $filas = $db->query('SELECT id, tipo_documento, numero_documento, nombres, apellidos, correo, telefono, ciudad, segmento, cupo_credito, saldo FROM clientes ORDER BY id');
}
Lab::log('INFO', 'export_generated', 'Exportación CSV generada', ['tipo' => $tipo, 'filas' => count($filas)]);

header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="techcorp_' . $tipo . '_' . gmdate('Ymd', (int) Lab::now()) . '.csv"');
$out = fopen('php://output', 'w');
if ($filas) {
    fputcsv($out, array_keys($filas[0]));
}
foreach ($filas as $f) {
    fputcsv($out, $f);
}
