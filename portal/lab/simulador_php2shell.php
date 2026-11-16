<?php
/*
 * SIMULADOR DEL COMPORTAMIENTO DE PHP2Shell (CVE-2026-109618) — solo laboratorio.
 *
 * Qué NO hace: no corrompe memoria, no ejecuta código enviado por el cliente y no contiene
 * ningún exploit. Solo reproduce la HUELLA que deja un ataque de este tipo:
 *
 *   1. Ráfaga de POST multipart sin autenticar contra la página de subir la foto de perfil (/empleados/foto.php) que
 *      incluyen el campo PHP_SESSION_UPLOAD_PROGRESS (seguimiento de progreso de subida de
 *      la sesión, la vía de ataque descrita para PHP2Shell) y cuerpos inusualmente grandes.
 *   2. Mientras el "exploit" no acierta, el proceso hijo de Apache "se cae": respuesta 500 y
 *      un evento AH00052 (Segmentation fault) en el log, como ocurriría con una corrupción
 *      de memoria real.
 *   3. Al intento N (LAB_EXPLOIT_ATTEMPTS) el ataque "funciona": aparece un archivo PHP en
 *      /uploads/avatars/ (la webshell) y el monitor de integridad (FIM) lo registra.
 *
 * Los usuarios legítimos nunca envían PHP_SESSION_UPLOAD_PROGRESS, así que el simulador
 * no interfiere con el tráfico normal.
 */
declare(strict_types=1);

(static function (): void {
    if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
        return;
    }
    $campo = (string) ini_get('session.upload_progress.name');
    if ($campo === '' || !isset($_POST[$campo]) || empty($_FILES)) {
        return;
    }

    $webshell = '/var/www/html/uploads/avatars/thumb_8f3a.php';
    if (is_file($webshell)) {
        return; // ya comprometido: la petición sigue su curso normal
    }

    $estado = '/var/lib/techcorp-lab/php2shell.json';
    $fp = fopen($estado, 'c+');
    flock($fp, LOCK_EX);
    $datos = json_decode((string) stream_get_contents($fp), true) ?: ['intentos' => 0];
    $datos['intentos']++;
    $necesarios = max(1, (int) (getenv('LAB_EXPLOIT_ATTEMPTS') ?: 19));
    $exito = $datos['intentos'] >= $necesarios;
    if ($exito) {
        $datos['exito_ts'] = Lab::iso();
    }
    ftruncate($fp, 0);
    rewind($fp);
    fwrite($fp, json_encode($datos));
    flock($fp, LOCK_UN);
    fclose($fp);

    if (!$exito) {
        // "Caída" del proceso: el registro de Apache no sabe qué petición la provocó,
        // por eso el evento no lleva request_id ni IP (se correlaciona por tiempo).
        Lab::log(
            'CRITICAL',
            'child_crash',
            sprintf('AH00052: child pid %d exit signal Segmentation fault (11)', random_int(300, 9999)),
            ['signal' => 11, 'simulado' => true],
            'apache',
            false
        );
        http_response_code(500);
        header('Connection: close');
        exit;
    }

    // "Implante": la webshell. Es un archivo inofensivo que delega en el simulador.
    $contenido = "<?php\n"
        . "/* LABORATORIO TechCorp - artefacto del incidente SIMULADO (webshell).\n"
        . "   No ejecuta comandos: delega en un simulador con respuestas predefinidas. */\n"
        . "require '/opt/techcorp-lab/webshell_simulada.php';\n";
    @mkdir(dirname($webshell), 0755, true);
    file_put_contents($webshell, $contenido);
    @touch($webshell, (int) Lab::now()); // fecha de modificación coherente con la simulación
    Lab::fim('file_added', $webshell);
})();
