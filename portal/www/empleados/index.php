<?php
declare(strict_types=1);
require __DIR__ . '/../includes/auth.php';
requiere_login();
$db = Db::app();

$q = trim((string) ($_GET['q'] ?? ''));
$pagina = max(1, (int) ($_GET['page'] ?? 1));
$porPagina = 30;
$where = 'WHERE activo = 1';
$params = [];
if ($q !== '') {
    $where .= ' AND (apellidos LIKE ? OR nombres LIKE ? OR departamento = ?)';
    $params = [$q . '%', $q . '%', $q];
}
$total = (int) $db->valor("SELECT COUNT(*) AS total FROM empleados $where", $params);
$filas = $db->query(
    "SELECT id, nombres, apellidos, cargo, departamento, correo, telefono FROM empleados $where ORDER BY apellidos, nombres LIMIT ? OFFSET ?",
    array_merge($params, [$porPagina, ($pagina - 1) * $porPagina])
);
$h = '<form class="buscar"><input name="q" value="' . e($q) . '" placeholder="Apellido, nombre o área"><button>Buscar</button></form>'
   . '<table><tr><th>Colaborador</th><th>Cargo</th><th>Área</th><th>Correo</th><th>Teléfono</th></tr>';
foreach ($filas as $p) {
    $h .= '<tr><td><a href="/empleados/ver.php?id=' . $p['id'] . '">' . e($p['apellidos'] . ', ' . $p['nombres']) . '</a></td>'
        . '<td>' . e($p['cargo']) . '</td><td>' . e($p['departamento']) . '</td><td>' . e($p['correo']) . '</td><td>' . e($p['telefono']) . '</td></tr>';
}
$h .= '</table>' . paginacion($pagina, $total, $porPagina, $q !== '' ? ['q' => $q] : []);
pagina('Directorio de colaboradores', $h);
