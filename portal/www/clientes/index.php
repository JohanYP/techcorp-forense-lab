<?php
declare(strict_types=1);
require __DIR__ . '/../includes/auth.php';
$u = requiere_login();
$db = Db::app();

$q = trim((string) ($_GET['q'] ?? ''));
$pagina = max(1, (int) ($_GET['page'] ?? 1));
$porPagina = 25;
$where = '';
$params = [];
if ($q !== '') {
    $where = 'WHERE apellidos LIKE ? OR nombres LIKE ? OR ciudad LIKE ? OR numero_documento = ?';
    $params = [$q . '%', $q . '%', $q . '%', $q];
}
$total = (int) $db->valor("SELECT COUNT(*) AS total FROM clientes $where", $params);
$filas = $db->query(
    "SELECT id, tipo_documento, numero_documento, nombres, apellidos, ciudad, segmento FROM clientes $where ORDER BY apellidos, nombres LIMIT ? OFFSET ?",
    array_merge($params, [$porPagina, ($pagina - 1) * $porPagina])
);

$h = '<form class="buscar"><input name="q" value="' . e($q) . '" placeholder="Apellido, nombre, ciudad o documento"><button>Buscar</button></form>'
   . '<table><tr><th>Documento</th><th>Cliente</th><th>Ciudad</th><th>Segmento</th></tr>';
foreach ($filas as $c) {
    $h .= '<tr><td>' . e($c['tipo_documento']) . ' ' . e(enmascarar($c['numero_documento'])) . '</td>'
        . '<td><a href="/clientes/ver.php?id=' . $c['id'] . '">' . e($c['apellidos'] . ', ' . $c['nombres']) . '</a></td>'
        . '<td>' . e($c['ciudad']) . '</td><td>' . e($c['segmento']) . '</td></tr>';
}
$h .= '</table>' . paginacion($pagina, $total, $porPagina, $q !== '' ? ['q' => $q] : []);
pagina('Clientes', $h);
