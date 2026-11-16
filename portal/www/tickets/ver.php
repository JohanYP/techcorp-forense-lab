<?php
declare(strict_types=1);
require __DIR__ . '/../includes/auth.php';
requiere_login();
$id = (int) ($_GET['id'] ?? 0);
$t = Db::app()->uno(
    'SELECT t.id, t.asunto, t.descripcion, t.estado, t.prioridad, t.creado_en, t.cliente_id, c.nombres, c.apellidos, e.usuario AS responsable FROM tickets t JOIN clientes c ON c.id = t.cliente_id JOIN empleados e ON e.id = t.empleado_id WHERE t.id = ?',
    [$id]
);
if (!$t) {
    http_response_code(404);
    Lab::log('NOTICE', 'record_not_found', 'Ticket inexistente', ['ticket_id' => $id]);
    pagina('Ticket no encontrado', '<div class="alerta">No existe.</div>');
    exit;
}
pagina('Ticket #' . $t['id'], '<div class="tarjeta"><dl><dt>Asunto</dt><dd>' . e($t['asunto']) . '</dd>'
    . '<dt>Cliente</dt><dd><a href="/clientes/ver.php?id=' . $t['cliente_id'] . '">' . e($t['nombres'] . ' ' . $t['apellidos']) . '</a></dd>'
    . '<dt>Estado</dt><dd>' . e($t['estado']) . '</dd><dt>Prioridad</dt><dd>' . e($t['prioridad']) . '</dd>'
    . '<dt>Responsable</dt><dd>' . e($t['responsable']) . '</dd><dt>Creado (UTC)</dt><dd>' . e($t['creado_en']) . '</dd></dl>'
    . '<p>' . nl2br(e($t['descripcion'])) . '</p></div>');
