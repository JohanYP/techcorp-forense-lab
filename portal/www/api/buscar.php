<?php
// Autocompletado de clientes (JSON).
declare(strict_types=1);
require __DIR__ . '/../includes/auth.php';
requiere_login();
$q = trim((string) ($_GET['q'] ?? ''));
$filas = $q === '' ? [] : Db::app()->query(
    'SELECT id, nombres, apellidos, ciudad FROM clientes WHERE apellidos LIKE ? ORDER BY apellidos LIMIT 10',
    [$q . '%']
);
header('Content-Type: application/json; charset=utf-8');
echo json_encode($filas, JSON_UNESCAPED_UNICODE);
