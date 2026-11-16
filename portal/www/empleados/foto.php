<?php
declare(strict_types=1);
require __DIR__ . '/../includes/auth.php';
$u = requiere_login();
$msg = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $f = $_FILES['foto'] ?? null;
    if (!$f || $f['error'] !== UPLOAD_ERR_OK) {
        $msg = 'No se recibió la imagen.';
        Lab::log('WARNING', 'upload_failed', 'Subida de foto fallida', ['error' => $f['error'] ?? null]);
    } elseif (@getimagesize($f['tmp_name']) === false) {
        $msg = 'El archivo no es una imagen válida.';
        Lab::log('WARNING', 'upload_rejected', 'Archivo de foto rechazado', ['nombre' => $f['name'], 'tamano' => $f['size']]);
    } else {
        $nombre = 'emp_' . (int) $u['id'] . '.png';
        $destino = '/var/www/html/uploads/avatars/' . $nombre;
        $existia = is_file($destino);
        move_uploaded_file($f['tmp_name'], $destino);
        @touch($destino, (int) Lab::now());
        Lab::fim($existia ? 'file_modified' : 'file_added', $destino);
        Db::app()->query('UPDATE empleados SET foto = :foto WHERE id = :id', ['foto' => $nombre, 'id' => (int) $u['id']]);
        Lab::log('INFO', 'avatar_updated', 'Foto de perfil actualizada', ['archivo' => $nombre, 'tamano' => $f['size']]);
        header('Location: /empleados/ver.php?id=' . (int) $u['id'], true, 302);
        exit;
    }
}
pagina('Foto de perfil', ($msg ? '<div class="alerta">' . e($msg) . '</div>' : '')
    . '<form method="post" enctype="multipart/form-data" class="tarjeta"><input type="file" name="foto" accept="image/png,image/jpeg"><button>Subir</button></form>');
