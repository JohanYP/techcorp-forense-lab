<?php
declare(strict_types=1);
require __DIR__ . '/../includes/auth.php';
$u = requiere_login();
$db = Db::app();
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $clienteId = (int) ($_POST['cliente_id'] ?? 0);
    $asunto = trim((string) ($_POST['asunto'] ?? ''));
    $prioridad = in_array($_POST['prioridad'] ?? '', ['baja', 'media', 'alta'], true) ? $_POST['prioridad'] : 'media';
    if ($clienteId > 0 && $asunto !== '') {
        $db->query(
            'INSERT INTO tickets (cliente_id, empleado_id, asunto, descripcion, estado, prioridad, creado_en) VALUES (?, ?, ?, ?, ?, ?, ?)',
            [$clienteId, (int) $u['id'], mb_substr($asunto, 0, 160), (string) ($_POST['descripcion'] ?? ''), 'abierto', $prioridad, Lab::sqlNow()]
        );
        $nuevo = (int) $db->valor('SELECT LAST_INSERT_ID() AS nuevo');
        Lab::log('INFO', 'ticket_created', 'Ticket creado', ['ticket_id' => $nuevo, 'cliente_id' => $clienteId]);
        header('Location: /tickets/ver.php?id=' . $nuevo, true, 302);
        exit;
    }
    Lab::log('NOTICE', 'validation_error', 'Formulario de ticket incompleto', []);
}
$clienteId = (int) ($_GET['cliente_id'] ?? 0);
pagina('Nuevo ticket', '<form method="post" class="tarjeta"><input type="hidden" name="cliente_id" value="' . $clienteId . '">'
    . '<label>Asunto<input name="asunto" required></label><label>Descripción<textarea name="descripcion"></textarea></label>'
    . '<label>Prioridad<select name="prioridad"><option>baja</option><option selected>media</option><option>alta</option></select></label>'
    . '<button>Crear</button></form>');
