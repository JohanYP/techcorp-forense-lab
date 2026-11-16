<?php
declare(strict_types=1);
require __DIR__ . '/../includes/auth.php';
$u = requiere_login();
$db = Db::app();
$id = (int) ($_GET['id'] ?? 0);

$c = $db->uno('SELECT * FROM clientes WHERE id = ?', [$id]);
if (!$c) {
    http_response_code(404);
    Lab::log('NOTICE', 'record_not_found', 'Cliente inexistente', ['cliente_id' => $id]);
    pagina('Cliente no encontrado', '<div class="alerta">El cliente solicitado no existe.</div>');
    exit;
}
$tickets = $db->query('SELECT id, asunto, estado, creado_en FROM tickets WHERE cliente_id = ? ORDER BY creado_en DESC LIMIT 10', [$id]);
$verCompleto = in_array($u['rol'], ['finanzas', 'gerencia'], true);

$h = '<div class="tarjeta"><dl>'
   . '<dt>Documento</dt><dd>' . e($c['tipo_documento']) . ' ' . e($verCompleto ? $c['numero_documento'] : enmascarar($c['numero_documento'])) . '</dd>'
   . ($c['razon_social'] ? '<dt>Razón social</dt><dd>' . e($c['razon_social']) . '</dd>' : '')
   . '<dt>Correo</dt><dd>' . e($c['correo']) . '</dd><dt>Teléfono</dt><dd>' . e($c['telefono']) . '</dd>'
   . '<dt>Dirección</dt><dd>' . e($c['direccion']) . ', ' . e($c['ciudad']) . ' (' . e($c['departamento']) . ')</dd>'
   . '<dt>Segmento</dt><dd>' . e($c['segmento']) . '</dd>'
   . '<dt>Cupo de crédito</dt><dd>' . cop($c['cupo_credito']) . '</dd><dt>Saldo</dt><dd>' . cop($c['saldo']) . '</dd>'
   . '</dl><p><a class="boton" href="/tickets/nuevo.php?cliente_id=' . $id . '">Nuevo ticket</a></p></div>'
   . '<h2>Tickets del cliente</h2><table><tr><th>#</th><th>Asunto</th><th>Estado</th></tr>';
foreach ($tickets as $t) {
    $h .= '<tr><td><a href="/tickets/ver.php?id=' . $t['id'] . '">' . $t['id'] . '</a></td><td>' . e($t['asunto']) . '</td><td>' . e($t['estado']) . '</td></tr>';
}
pagina($c['nombres'] . ' ' . $c['apellidos'], $h . '</table>');
