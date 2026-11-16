<?php
declare(strict_types=1);
require __DIR__ . '/../includes/auth.php';
$u = requiere_login();
$estado = (string) ($_GET['estado'] ?? '');
$params = [(int) $u['id']];
$filtro = '';
if (in_array($estado, ['abierto', 'en_proceso', 'cerrado'], true)) {
    $filtro = ' AND t.estado = ?';
    $params[] = $estado;
}
$filas = Db::app()->query(
    "SELECT t.id, t.asunto, t.estado, t.prioridad, t.creado_en, c.nombres, c.apellidos FROM tickets t JOIN clientes c ON c.id = t.cliente_id WHERE t.empleado_id = ?$filtro ORDER BY t.creado_en DESC LIMIT 20",
    $params
);
$h = '<p><a href="?estado=abierto">Abiertos</a> · <a href="?estado=en_proceso">En proceso</a> · <a href="?estado=cerrado">Cerrados</a> · <a href="?">Todos</a></p>'
   . '<table><tr><th>#</th><th>Asunto</th><th>Cliente</th><th>Estado</th><th>Prioridad</th><th>Creado (UTC)</th></tr>';
foreach ($filas as $t) {
    $h .= '<tr><td><a href="/tickets/ver.php?id=' . $t['id'] . '">' . $t['id'] . '</a></td><td>' . e($t['asunto']) . '</td><td>' . e($t['nombres'] . ' ' . $t['apellidos']) . '</td>'
        . '<td>' . e($t['estado']) . '</td><td>' . e($t['prioridad']) . '</td><td>' . e($t['creado_en']) . '</td></tr>';
}
pagina('Mis tickets', $h . '</table>');
