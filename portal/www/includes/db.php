<?php
/*
 * Capa de acceso a datos con AUDITORÍA.
 *
 * Cada sentencia se registra en /var/log/techcorp/db/audit.jsonl con un formato inspirado en
 * el plugin de auditoría de MySQL/Percona (usuario de BD, conexión, clase de comando, texto
 * SQL, filas devueltas). Dos detalles importantes para el análisis forense:
 *
 *  - El texto SQL lleva un comentario estilo "sqlcommenter" con el request_id de la petición
 *    HTTP:  SELECT ... /*request_id='...',route='/clientes/ver.php'*\/
 *    Así la base de datos y el servidor web se pueden correlacionar con exactitud.
 *  - Extensión del laboratorio: "row_ids" guarda los id de las filas devueltas/afectadas,
 *    para responder con precisión qué registros quedaron expuestos.
 */
declare(strict_types=1);

final class Db
{
    /** @var array<string, Db> */
    private static array $instancias = [];

    private PDO $pdo;
    private ?int $connectionId = null;

    private function __construct(private string $usuario, string $clave)
    {
        $cfg = self::config();
        $dsn = sprintf('mysql:host=%s;dbname=%s;charset=utf8mb4', $cfg['host'], $cfg['nombre']);
        $this->pdo = new PDO($dsn, $usuario, $clave, [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_PERSISTENT         => true,
            PDO::ATTR_EMULATE_PREPARES   => true, // el servidor recibe el SQL ya interpolado
        ]);
    }

    public static function config(): array
    {
        static $cfg = null;
        return $cfg ??= require '/var/www/html/config/database.php';
    }

    /** Conexión normal del portal (mínimo privilegio). */
    public static function app(): Db
    {
        $c = self::config()['app'];
        return self::conectar($c['user'], $c['pass']);
    }

    public static function conectar(string $usuario, string $clave): Db
    {
        $k = $usuario . "\0" . hash('sha256', $clave);
        return self::$instancias[$k] ??= new Db($usuario, $clave);
    }

    private function connectionId(): int
    {
        if ($this->connectionId === null) {
            $this->connectionId = (int) $this->pdo->query('SELECT CONNECTION_ID()')->fetchColumn();
        }
        return $this->connectionId;
    }

    /** Ejecuta una sentencia y devuelve las filas (SELECT/SHOW) o [] (escritura). */
    public function query(string $sql, array $params = []): array
    {
        $ruta = Lab::uri();
        $comentario = sprintf(" /*request_id='%s',route='%s'*/", Lab::$requestId, str_replace("'", '', $ruta));
        $texto = $this->interpolar($sql, $params) . $comentario;
        $clase = strtolower((string) strtok(ltrim($sql), " \n\t("));

        $t = microtime(true);
        $filas = [];
        $afectadas = 0;
        $estado = 0;
        $ids = [];
        try {
            $st = $this->pdo->prepare($sql . $comentario);
            foreach ($params as $k => $v) {
                $st->bindValue(is_int($k) ? $k + 1 : ':' . ltrim($k, ':'), $v, is_int($v) ? PDO::PARAM_INT : ($v === null ? PDO::PARAM_NULL : PDO::PARAM_STR));
            }
            $st->execute();
            if ($st->columnCount() > 0) {
                $filas = $st->fetchAll();
                foreach ($filas as $f) {
                    if (isset($f['id'])) {
                        $ids[] = (int) $f['id'];
                    }
                }
            } else {
                $afectadas = $st->rowCount();
                if ($clase === 'insert') {
                    $ids[] = (int) $this->pdo->lastInsertId();
                } elseif (isset($params['id'])) {
                    $ids[] = (int) $params['id'];
                }
            }
        } catch (PDOException $e) {
            $estado = (int) ($e->errorInfo[1] ?? 1);
            $this->auditar($clase, $texto, $estado, 0, 0, $t, []);
            throw $e;
        }
        $this->auditar($clase, $texto, $estado, count($filas), $afectadas, $t, $ids);
        return $filas;
    }

    public function uno(string $sql, array $params = []): ?array
    {
        return $this->query($sql, $params)[0] ?? null;
    }

    public function valor(string $sql, array $params = []): mixed
    {
        $f = $this->uno($sql, $params);
        return $f === null ? null : reset($f);
    }

    private function auditar(string $clase, string $texto, int $estado, int $enviadas, int $afectadas, float $t0, array $ids): void
    {
        preg_match_all('/\b(?:FROM|JOIN|INTO|UPDATE)\s+`?(\w+)`?/i', $texto, $m);
        Lab::write('db/audit.jsonl', [
            'ts'            => Lab::iso(),
            'server'        => 'db-01',
            'record_type'   => 'Query',
            'connection_id' => $this->connectionId(),
            'db_user'       => $this->usuario,
            'client_host'   => 'web-01',
            'database'      => self::config()['nombre'],
            'command_class' => $clase,
            'tables'        => array_values(array_unique(array_map('strtolower', $m[1]))),
            'sqltext'       => $texto,
            'status'        => $estado,
            'rows_sent'     => $enviadas,
            'rows_affected' => $afectadas,
            'duration_ms'   => round((microtime(true) - $t0) * 1000, 3),
            'row_ids'       => $ids,
        ]);
    }

    private function interpolar(string $sql, array $params): string
    {
        if (!$params) {
            return $sql;
        }
        $citar = fn ($v): string => match (true) {
            $v === null => 'NULL',
            is_int($v), is_float($v) => (string) $v,
            default => $this->pdo->quote((string) $v),
        };
        if (array_is_list($params)) {
            $i = 0;
            return preg_replace_callback('/\?/', function () use (&$i, $params, $citar) {
                return $citar($params[$i++] ?? null);
            }, $sql);
        }
        foreach ($params as $k => $v) {
            $sql = preg_replace_callback('/:' . preg_quote(ltrim((string) $k, ':'), '/') . '\b/', fn () => $citar($v), $sql);
        }
        return $sql;
    }
}
