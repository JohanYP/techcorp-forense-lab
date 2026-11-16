<?php
/*
 * LÍNEA BASE DE LA EVIDENCIA — andamiaje del laboratorio (modo presentación).
 *
 * Al terminar de simular la semana normal, el generador guarda aquí el tamaño de cada log.
 * El notebook del ataque en vivo llama a /_lab/reset.php?logs=1, que recorta los logs a ese
 * punto: así el ataque se puede repetir cuantas veces se quiera y la evidencia siempre
 * contiene la semana normal + UN solo ataque.
 *
 * Recortar (ftruncate) es seguro con Apache encendido: Apache escribe en modo O_APPEND, así
 * que su siguiente línea va al nuevo final del archivo. Por eso los logs se crean con 0666
 * (entrypoint.sh): PHP corre como www-data y debe poder recortarlos.
 */
declare(strict_types=1);

final class LineaBase
{
    public const ARCHIVO = Lab::LOG_DIR . '/.linea_base.json';
    public const FUENTES = ['web/access.jsonl', 'app/app.jsonl', 'db/audit.jsonl', 'fim/fim.jsonl'];

    /** Tamaño actual (bytes) de cada log. */
    public static function tamanos(): array
    {
        clearstatcache();
        $t = [];
        foreach (self::FUENTES as $f) {
            $p = Lab::LOG_DIR . '/' . $f;
            $t[$f] = is_file($p) ? (int) filesize($p) : 0;
        }
        return $t;
    }

    public static function leer(): ?array
    {
        if (!is_file(self::ARCHIVO)) {
            return null;
        }
        $d = json_decode((string) file_get_contents(self::ARCHIVO), true);
        return is_array($d) ? $d : null;
    }

    public static function guardar(): array
    {
        $d = ['guardada_utc' => gmdate('Y-m-d\TH:i:s\Z'), 'tamanos' => self::tamanos()];
        file_put_contents(self::ARCHIVO, json_encode($d, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n");
        @chmod(self::ARCHIVO, 0666);
        return $d;
    }

    /** Recorta cada log a su tamaño de la línea base. Devuelve los bytes quitados por archivo. */
    public static function restaurar(): array
    {
        $base = self::leer();
        if ($base === null) {
            return ['error' => 'No hay línea base: el generador todavía no terminó la semana normal.'];
        }
        $quitados = [];
        foreach ($base['tamanos'] as $f => $tam) {
            $p = Lab::LOG_DIR . '/' . $f;
            clearstatcache(true, $p);
            if (!is_file($p) || filesize($p) <= $tam) {
                $quitados[$f] = 0;          // nada que quitar (o el log es más corto: no se toca)
                continue;
            }
            $antes = (int) filesize($p);
            $fp = @fopen($p, 'r+');         // @: un aviso de PHP iría a app.jsonl y ensuciaría la evidencia
            if ($fp === false) {
                $quitados[$f] = 'sin permiso de escritura';
                continue;
            }
            flock($fp, LOCK_EX);
            ftruncate($fp, (int) $tam);
            flock($fp, LOCK_UN);
            fclose($fp);
            $quitados[$f] = $antes - (int) $tam;
        }
        return $quitados;
    }
}
