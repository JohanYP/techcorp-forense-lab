<?php
/*
 * Arranque común del portal TechCorp (se carga antes de cada script con auto_prepend_file).
 *
 *  - request_id: el identificador que Apache asigna a la petición (mod_unique_id). Se propaga
 *    al log de aplicación y a la auditoría SQL para poder correlacionar las fuentes.
 *  - Reloj de laboratorio: con LAB_MODE=1 la hora de los eventos sale de la cabecera
 *    X-Sim-Time (el generador simula 7 días de actividad en unos minutos). Sin la cabecera
 *    se usa el reloj real.
 *  - Registro estructurado en JSON (una línea por evento) en /var/log/techcorp/{app,db,fim}.
 */
declare(strict_types=1);

if (PHP_SAPI === 'cli') {
    return;
}

final class Lab
{
    public const LOG_DIR = '/var/log/techcorp';

    public static float $t0 = 0.0;
    public static ?float $simBase = null;
    public static string $requestId = '';
    public static string $clientIp = '';
    public static ?string $user = null;

    public static function init(): void
    {
        self::$t0 = microtime(true);
        self::$requestId = (string) ($_SERVER['UNIQUE_ID'] ?? $_SERVER['REDIRECT_UNIQUE_ID'] ?? bin2hex(random_bytes(12)));
        self::$clientIp = (string) ($_SERVER['REMOTE_ADDR'] ?? '0.0.0.0');
        if (self::labMode() && !empty($_SERVER['HTTP_X_SIM_TIME'])) {
            self::$simBase = self::parseIso((string) $_SERVER['HTTP_X_SIM_TIME']);
        }
    }

    public static function labMode(): bool
    {
        return getenv('LAB_MODE') === '1';
    }

    /** Hora actual (epoch con decimales): simulada si hay X-Sim-Time, real si no. */
    public static function now(): float
    {
        $transcurrido = microtime(true) - self::$t0;
        return (self::$simBase ?? self::$t0) + $transcurrido;
    }

    public static function iso(?float $t = null): string
    {
        $t ??= self::now();
        $s = (int) floor($t);
        $ms = min(999, (int) floor(($t - $s) * 1000));
        return gmdate('Y-m-d\TH:i:s', $s) . sprintf('.%03dZ', $ms);
    }

    /** Fecha para columnas DATETIME (UTC). */
    public static function sqlNow(): string
    {
        return gmdate('Y-m-d H:i:s', (int) self::now());
    }

    public static function parseIso(string $v): ?float
    {
        if (!preg_match('/^(\d{4}-\d{2}-\d{2})T(\d{2}:\d{2}:\d{2})(?:\.(\d{1,6}))?Z$/', $v, $m)) {
            return null;
        }
        $s = strtotime($m[1] . ' ' . $m[2] . ' UTC');
        if ($s === false) {
            return null;
        }
        return $s + (isset($m[3]) ? (float) ('0.' . $m[3]) : 0.0);
    }

    public static function uri(): string
    {
        $u = (string) ($_SERVER['REDIRECT_URL'] ?? parse_url((string) ($_SERVER['REQUEST_URI'] ?? '/'), PHP_URL_PATH) ?? '/');
        return $u;
    }

    public static function write(string $rel, array $rec): void
    {
        $line = json_encode($rec, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE) . "\n";
        @file_put_contents(self::LOG_DIR . '/' . $rel, $line, FILE_APPEND | LOCK_EX);
    }

    /** Evento del log de aplicación (app/app.jsonl). */
    public static function log(string $level, string $event, string $message, array $context = [], string $component = 'portal', bool $conPeticion = true): void
    {
        self::write('app/app.jsonl', [
            'ts'         => self::iso(),
            'level'      => $level,
            'component'  => $component,
            'server'     => 'web-01',
            'request_id' => $conPeticion ? self::$requestId : null,
            'client_ip'  => $conPeticion ? self::$clientIp : null,
            'user'       => $conPeticion ? self::$user : null,
            'method'     => $conPeticion ? ($_SERVER['REQUEST_METHOD'] ?? null) : null,
            'uri'        => $conPeticion ? self::uri() : null,
            'event'      => $event,
            'message'    => $message,
            'context'    => $context ?: new stdClass(),
        ]);
    }

    /**
     * Evento del monitor de integridad de archivos (fim/fim.jsonl). En una empresa real lo
     * produciría un agente tipo Wazuh/OSSEC (syscheck); aquí lo emite el código que escribe
     * el archivo. No lleva request_id: un FIM no sabe qué petición HTTP causó el cambio.
     */
    public static function fim(string $event, string $path, string $process = 'apache2'): void
    {
        clearstatcache(true, $path);
        $existe = is_file($path);
        self::write('fim/fim.jsonl', [
            'ts'      => self::iso(),
            'agent'   => 'fim-web-01',
            'server'  => 'web-01',
            'event'   => $event,
            'path'    => $path,
            'size'    => $existe ? filesize($path) : null,
            'sha256'  => $existe ? hash_file('sha256', $path) : null,
            'owner'   => $existe ? (posix_getpwuid(fileowner($path))['name'] ?? (string) fileowner($path)) : null,
            'mode'    => $existe ? substr(sprintf('%o', fileperms($path)), -4) : null,
            'process' => $process,
        ]);
    }

    public static function setUser(?string $u): void
    {
        self::$user = $u;
        if ($u !== null && function_exists('apache_note')) {
            apache_note('techcorp_user', $u);
        }
    }
}

Lab::init();

set_error_handler(static function (int $no, string $str, string $file, int $line): bool {
    if (!(error_reporting() & $no)) {
        return false;
    }
    $nivel = match (true) {
        in_array($no, [E_WARNING, E_USER_WARNING, E_CORE_WARNING, E_COMPILE_WARNING], true) => 'WARNING',
        in_array($no, [E_NOTICE, E_USER_NOTICE, E_DEPRECATED, E_USER_DEPRECATED], true)       => 'NOTICE',
        default                                                                                => 'ERROR',
    };
    Lab::log($nivel, 'php_error', $str, ['errno' => $no, 'file' => str_replace('/var/www/html', '', $file), 'line' => $line]);
    return true;
});

set_exception_handler(static function (Throwable $e): void {
    Lab::log('ERROR', 'unhandled_exception', $e->getMessage(), [
        'class' => get_class($e),
        'file'  => str_replace('/var/www/html', '', $e->getFile()),
        'line'  => $e->getLine(),
    ]);
    if (!headers_sent()) {
        http_response_code(500);
        header('Content-Type: text/html; charset=utf-8');
    }
    echo '<!doctype html><meta charset="utf-8"><title>Error interno</title>'
       . '<h1>500 · Error interno</h1><p>Ocurrió un problema. Intente de nuevo en unos minutos.</p>';
});

// Simulador del comportamiento de PHP2Shell (solo en modo laboratorio).
if (Lab::labMode()) {
    require __DIR__ . '/simulador_php2shell.php';
}
