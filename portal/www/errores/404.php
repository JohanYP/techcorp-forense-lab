<?php
require_once __DIR__ . '/../includes/layout.php';
Lab::log('WARNING', 'not_found', 'Recurso inexistente', ['query' => $_SERVER['REDIRECT_QUERY_STRING'] ?? null]);
http_response_code(404);
pagina('Página no encontrada', '<div class="alerta">La dirección solicitada no existe.</div>', false);
