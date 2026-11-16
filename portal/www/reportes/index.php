<?php
declare(strict_types=1);
require __DIR__ . '/../includes/auth.php';
$u = requiere_login();
$db = Db::app();

// Ruido realista: de vez en cuando la consulta agregada choca con un bloqueo (≈0,3 %).
if (random_int(1, 1000) <= 3) {
    throw new RuntimeException('SQLSTATE[HY000]: General error: 1205 Lock wait timeout exceeded; try restarting transaction');
}
$seg = $db->query('SELECT segmento, COUNT(*) AS clientes, SUM(saldo) AS saldo FROM clientes GROUP BY segmento ORDER BY clientes DESC');
$ciu = $db->query('SELECT ciudad, COUNT(*) AS clientes FROM clientes GROUP BY ciudad ORDER BY clientes DESC LIMIT 10');

$h = '<h2>Clientes por segmento</h2><table><tr><th>Segmento</th><th>Clientes</th><th>Saldo total</th></tr>';
foreach ($seg as $s) {
    $h .= '<tr><td>' . e($s['segmento']) . '</td><td>' . (int) $s['clientes'] . '</td><td>' . cop($s['saldo']) . '</td></tr>';
}
$h .= '</table><h2>Top 10 ciudades</h2><table><tr><th>Ciudad</th><th>Clientes</th></tr>';
foreach ($ciu as $c) {
    $h .= '<tr><td>' . e($c['ciudad']) . '</td><td>' . (int) $c['clientes'] . '</td></tr>';
}
$h .= '</table>';
if (in_array($u['rol'], ['finanzas', 'gerencia'], true)) {
    $h .= '<p><a class="boton" href="/reportes/exportar.php?tipo=clientes">Exportar clientes (CSV)</a> '
        . '<a class="boton" href="/reportes/exportar.php?tipo=tickets">Exportar tickets del mes (CSV)</a></p>';
}
pagina('Reportes', $h);
