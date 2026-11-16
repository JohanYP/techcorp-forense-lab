<?php
declare(strict_types=1);

function e(mixed $v): string
{
    return htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
}

function cop(mixed $v): string
{
    return '$' . number_format((float) $v, 0, ',', '.');
}

function enmascarar(string $doc): string
{
    return strlen($doc) <= 4 ? '****' : str_repeat('•', strlen($doc) - 4) . substr($doc, -4);
}

function pagina(string $titulo, string $cuerpo, bool $conMenu = true): void
{
    $u = $_SESSION['emp'] ?? null;
    header('Content-Type: text/html; charset=utf-8');
    $menu = '';
    if ($conMenu && $u) {
        $menu = '<nav class="menu">'
            . '<a href="/dashboard.php">Inicio</a>'
            . '<a href="/clientes/">Clientes</a>'
            . '<a href="/tickets/">Tickets</a>'
            . '<a href="/empleados/">Directorio</a>'
            . '<a href="/reportes/">Reportes</a>'
            . '<span class="quien">' . e($u['nombres'] . ' ' . $u['apellidos']) . ' · ' . e($u['rol'])
            . ' · <a href="/logout.php">Salir</a></span></nav>';
    }
    echo '<!doctype html><html lang="es"><head><meta charset="utf-8">'
        . '<meta name="viewport" content="width=device-width, initial-scale=1">'
        . '<title>' . e($titulo) . ' · Portal TechCorp</title>'
        . '<link rel="icon" href="/favicon.ico"><link rel="stylesheet" href="/assets/css/portal.css">'
        . '<script src="/assets/js/portal.js" defer></script></head><body>'
        . '<header class="barra"><img src="/assets/img/logo.svg" alt="" width="28" height="28">'
        . '<strong>TechCorp</strong> <span>Portal interno</span></header>'
        . $menu . '<main><h1>' . e($titulo) . '</h1>' . $cuerpo . '</main>'
        . '<footer>TechCorp S.A.S. · Uso exclusivo de colaboradores · Laboratorio académico con datos ficticios</footer>'
        . '</body></html>';
}

function paginacion(int $pagina, int $total, int $porPagina, array $extra = []): string
{
    $paginas = max(1, (int) ceil($total / $porPagina));
    $q = fn (int $p) => '?' . http_build_query($extra + ['page' => $p]);
    $h = '<div class="paginas">Página ' . $pagina . ' de ' . $paginas . ' · ' . number_format($total, 0, ',', '.') . ' registros ';
    if ($pagina > 1) {
        $h .= '<a href="' . e($q($pagina - 1)) . '">« Anterior</a> ';
    }
    if ($pagina < $paginas) {
        $h .= '<a href="' . e($q($pagina + 1)) . '">Siguiente »</a>';
    }
    return $h . '</div>';
}
