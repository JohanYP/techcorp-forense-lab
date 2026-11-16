<?php
declare(strict_types=1);
require __DIR__ . '/../includes/auth.php';
$u = requiere_login();
$id = (int) ($_GET['id'] ?? 0);
$db = Db::app();

$conSalario = puede_ver_salarios($u, $id);
$cols = 'id, usuario, nombres, apellidos, correo, telefono, cargo, departamento, fecha_ingreso, foto' . ($conSalario ? ', salario' : '');
$p = $db->uno("SELECT $cols FROM empleados WHERE id = ? AND activo = 1", [$id]);
if (!$p) {
    http_response_code(404);
    Lab::log('NOTICE', 'record_not_found', 'Empleado inexistente', ['empleado_id' => $id]);
    pagina('Colaborador no encontrado', '<div class="alerta">No existe.</div>');
    exit;
}
if ($conSalario && (int) $u['id'] !== $id) {
    Lab::log('INFO', 'salary_view', 'Consulta de salario', ['empleado_id' => $id]);
}
$foto = $p['foto'] ? '<img class="avatar" src="/uploads/avatars/' . e($p['foto']) . '" alt="" width="96" height="96">' : '';
$h = '<div class="tarjeta">' . $foto . '<dl>'
   . '<dt>Usuario</dt><dd>' . e($p['usuario']) . '</dd><dt>Cargo</dt><dd>' . e($p['cargo']) . '</dd>'
   . '<dt>Área</dt><dd>' . e($p['departamento']) . '</dd><dt>Correo</dt><dd>' . e($p['correo']) . '</dd>'
   . '<dt>Teléfono</dt><dd>' . e($p['telefono']) . '</dd><dt>Ingreso</dt><dd>' . e($p['fecha_ingreso']) . '</dd>'
   . ($conSalario ? '<dt>Salario</dt><dd>' . cop($p['salario']) . '</dd>' : '')
   . '</dl>' . ((int) $u['id'] === $id ? '<p><a class="boton" href="/empleados/foto.php">Cambiar foto</a></p>' : '') . '</div>';
pagina($p['nombres'] . ' ' . $p['apellidos'], $h);
