<?php
declare(strict_types=1);
require __DIR__ . '/includes/auth.php';
$u = requiere_login();
$db = Db::app();

$totalClientes = (int) $db->valor('SELECT COUNT(*) AS total FROM clientes');
$abiertos = (int) $db->valor("SELECT COUNT(*) AS total FROM tickets WHERE empleado_id = ? AND estado <> 'cerrado'", [(int) $u['id']]);
$tickets = $db->query('SELECT id, asunto, estado, prioridad, creado_en FROM tickets WHERE empleado_id = ? ORDER BY creado_en DESC LIMIT 5', [(int) $u['id']]);

$h = '<div class="cifras"><div><b>' . number_format($totalClientes, 0, ',', '.') . '</b>clientes</div>'
   . '<div><b>' . $abiertos . '</b>tickets abiertos a su cargo</div></div>';

if ($u['rol'] === 'comercial') {
    $mios = $db->query('SELECT id, nombres, apellidos, ciudad, segmento FROM clientes WHERE ejecutivo_id = ? ORDER BY id DESC LIMIT 8', [(int) $u['id']]);
    $h .= '<h2>Mis clientes recientes</h2><table><tr><th>Cliente</th><th>Ciudad</th><th>Segmento</th></tr>';
    foreach ($mios as $c) {
        $h .= '<tr><td><a href="/clientes/ver.php?id=' . $c['id'] . '">' . e($c['nombres'] . ' ' . $c['apellidos']) . '</a></td><td>' . e($c['ciudad']) . '</td><td>' . e($c['segmento']) . '</td></tr>';
    }
    $h .= '</table>';
}

$h .= '<h2>Mis últimos tickets</h2><table><tr><th>#</th><th>Asunto</th><th>Estado</th><th>Prioridad</th></tr>';
foreach ($tickets as $t) {
    $h .= '<tr><td><a href="/tickets/ver.php?id=' . $t['id'] . '">' . $t['id'] . '</a></td><td>' . e($t['asunto']) . '</td><td>' . e($t['estado']) . '</td><td>' . e($t['prioridad']) . '</td></tr>';
}
$h .= '</table>';
pagina('Hola, ' . $u['nombres'], $h);
