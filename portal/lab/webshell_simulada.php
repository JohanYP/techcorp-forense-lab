<?php
/*
 * WEBSHELL SIMULADA — laboratorio forense TechCorp.
 *
 * Representa el archivo que el atacante deja tras explotar PHP2Shell. NO ejecuta nada en el
 * sistema operativo ni acepta SQL libre: reconoce un conjunto CERRADO de órdenes y devuelve
 * salidas predefinidas. Las órdenes "mysql" se traducen a consultas SELECT fijas para que la
 * auditoría de la base de datos registre el acceso real a los datos (que son ficticios).
 *
 * Parámetros (como hacen muchas webshells, en Base64 para esquivar filtros simples):
 *   ?x=<base64 de la orden>     ejecutar una orden "simulada"
 *   ?dl=<base64 de una ruta>    descargar un archivo de /tmp (exfiltración)
 */
declare(strict_types=1);

if (!Lab::labMode()) {
    http_response_code(404);
    exit;
}
require_once '/var/www/html/includes/db.php';

header('Content-Type: text/plain; charset=utf-8');

$dl = isset($_GET['dl']) ? base64_decode((string) $_GET['dl'], true) : false;
if ($dl !== false && $dl !== null && $dl !== '') {
    $ruta = realpath($dl);
    if ($ruta === false || !str_starts_with($ruta, '/tmp/') || !is_file($ruta)) {
        http_response_code(404);
        exit;
    }
    header('Content-Type: application/octet-stream');
    header('Content-Disposition: attachment; filename="' . basename($ruta) . '"');
    header('Content-Length: ' . filesize($ruta));
    readfile($ruta);
    exit;
}

$cmd = isset($_GET['x']) ? base64_decode((string) $_GET['x'], true) : false;
if ($cmd === false || $cmd === null) {
    exit; // sin orden: página en blanco, como muchas webshells
}
$cmd = trim($cmd);

/** Listado tipo "ls -la" de un directorio permitido. */
$ls = static function (string $dir): string {
    $permitidos = ['/var/www/html', '/var/www/html/config', '/var/www/html/uploads', '/var/www/html/uploads/avatars', '/tmp', '/tmp/.d'];
    if (!in_array(rtrim($dir, '/'), $permitidos, true)) {
        return "ls: cannot open directory '$dir': Permission denied\n";
    }
    if (!is_dir($dir)) {
        return "ls: cannot access '$dir': No such file or directory\n";
    }
    $salida = '';
    $entradas = scandir($dir) ?: [];
    $salida .= 'total ' . count($entradas) . "\n";
    foreach ($entradas as $n) {
        $p = rtrim($dir, '/') . '/' . $n;
        $st = @stat($p);
        if (!$st) {
            continue;
        }
        $tipo = is_dir($p) ? 'd' : '-';
        $perm = $tipo . (is_dir($p) ? 'rwxr-xr-x' : 'rw-r--r--');
        $usr = posix_getpwuid($st['uid'])['name'] ?? $st['uid'];
        $grp = posix_getgrgid($st['gid'])['name'] ?? $st['gid'];
        $salida .= sprintf("%s %2d %-8s %-8s %8d %s %s\n", $perm, $st['nlink'], $usr, $grp, $st['size'], gmdate('M d H:i', $st['mtime']), $n);
    }
    return $salida;
};

/** Empaqueta un directorio en .tgz (equivale a "tar czf"). */
$tar = static function (string $origen, string $destino): string {
    @unlink($destino);
    $base = preg_replace('/\.tgz$/', '', $destino);
    @unlink($base . '.tar');
    @unlink($base . '.tar.gz');
    $pd = new PharData($base . '.tar');
    $pd->buildFromDirectory($origen);
    $pd->compress(Phar::GZ);
    unset($pd);
    @unlink($base . '.tar');
    rename($base . '.tar.gz', $destino);
    return '';
};

/** Traduce una orden "mysql" a una consulta fija y la ejecuta con las credenciales dadas. */
$mysql = static function (string $usuario, string $clave, string $sql, ?string $archivo): string {
    $consulta = null;
    if (preg_match('/^SHOW TABLES$/i', $sql)) {
        $consulta = 'SHOW TABLES';
    } elseif (preg_match('/^SELECT COUNT\(\*\) FROM (clientes|empleados|tickets)$/i', $sql, $m)) {
        $consulta = 'SELECT COUNT(*) AS total FROM ' . strtolower($m[1]);
    } elseif (preg_match('/^SELECT \* FROM (clientes|empleados) ORDER BY id(?: LIMIT (\d+) OFFSET (\d+))?$/i', $sql, $m)) {
        $consulta = 'SELECT * FROM ' . strtolower($m[1]) . ' ORDER BY id';
        if (isset($m[2]) && $m[2] !== '') {
            $consulta .= sprintf(' LIMIT %d OFFSET %d', (int) $m[2], (int) $m[3]);
        }
    }
    if ($consulta === null) {
        return "ERROR 1064 (42000): You have an error in your SQL syntax\n";
    }
    if (!in_array($usuario, ['techcorp_app', 'techcorp_admin'], true)) {
        return "ERROR 1045 (28000): Access denied for user '$usuario'@'web-01' (using password: YES)\n";
    }
    try {
        $filas = Db::conectar($usuario, $clave)->query($consulta);
    } catch (PDOException $e) {
        return 'ERROR ' . ($e->errorInfo[1] ?? 1045) . ': ' . ($e->errorInfo[2] ?? 'Access denied') . "\n";
    }
    $tsv = '';
    if ($filas) {
        $tsv .= implode("\t", array_keys($filas[0])) . "\n";
        foreach ($filas as $f) {
            $tsv .= implode("\t", array_map(static fn ($v) => $v === null ? 'NULL' : str_replace(["\t", "\n"], ' ', (string) $v), $f)) . "\n";
        }
    }
    if ($archivo !== null) {
        if (!str_starts_with($archivo, '/tmp/.d/') || !is_dir('/tmp/.d')) {
            return "sh: 1: cannot create $archivo: Directory nonexistent\n";
        }
        file_put_contents($archivo, $tsv);
        return '';
    }
    return $tsv;
};

$salida = match (true) {
    $cmd === 'id'       => "uid=33(www-data) gid=33(www-data) groups=33(www-data)\n",
    $cmd === 'whoami'   => "www-data\n",
    $cmd === 'hostname' => "web-01\n",
    $cmd === 'pwd'      => "/var/www/html/uploads/avatars\n",
    $cmd === 'uname -a' => "Linux web-01 6.1.0-28-amd64 #1 SMP PREEMPT_DYNAMIC Debian 6.1.119-1 (2024-11-22) x86_64 GNU/Linux\n",
    $cmd === 'php -v'   => 'PHP ' . PHP_VERSION . " (cli) (built: Jan 13 2026) (NTS)\nCopyright (c) The PHP Group\nZend Engine v4.3, with Zend OPcache\n",
    (bool) preg_match('#^ls -la (\S+)$#', $cmd, $m) => $ls($m[1]),
    (bool) preg_match('#^du -sh((?: /tmp/[\w.]+)+)$#', $cmd, $m) => implode('', array_map(
        static fn (string $f): string => is_file($f)
            ? sprintf("%s\t%s\n", filesize($f) >= 1048576 ? round(filesize($f) / 1048576, 1) . 'M' : (int) ceil(filesize($f) / 1024) . 'K', $f)
            : "du: cannot access '$f': No such file or directory\n",
        preg_split('/\s+/', trim($m[1]))
    )),
    $cmd === 'cat /var/www/html/config/database.php' => (string) file_get_contents('/var/www/html/config/database.php'),
    $cmd === 'cat /etc/hostname' => "web-01\n",
    $cmd === 'mkdir -p /tmp/.d' => (@mkdir('/tmp/.d', 0700, true) || is_dir('/tmp/.d')) ? '' : "mkdir: cannot create directory\n",
    (bool) preg_match('/^mysql -h \S+ -u (\S+) -p\'([^\']*)\' techcorp -e "([^"]+)"(?: > (\S+))?$/', $cmd, $m)
        => $mysql($m[1], $m[2], $m[3], $m[4] ?? null),
    $cmd === 'tar czf /tmp/.d.tgz -C /tmp .d'        => $tar('/tmp/.d', '/tmp/.d.tgz'),
    $cmd === 'tar czf /tmp/.w.tgz /var/www/html'     => $tar('/var/www/html', '/tmp/.w.tgz'),
    $cmd === 'rm -rf /tmp/.d /tmp/.d.tgz /tmp/.w.tgz' => (static function (): string {
        foreach (glob('/tmp/.d/*') ?: [] as $f) {
            @unlink($f);
        }
        @rmdir('/tmp/.d');
        @unlink('/tmp/.d.tgz');
        @unlink('/tmp/.w.tgz');
        return '';
    })(),
    default => 'sh: 1: ' . strtok($cmd, ' ') . ": not found\n",
};
echo $salida;
