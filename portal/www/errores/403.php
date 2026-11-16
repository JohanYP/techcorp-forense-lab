<?php
require_once __DIR__ . '/../includes/layout.php';
Lab::log('WARNING', 'forbidden', 'Acceso prohibido por configuración del servidor', []);
http_response_code(403);
pagina('Acceso prohibido', '<div class="alerta">No tiene permiso para acceder a este recurso.</div>', false);
