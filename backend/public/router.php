<?php

/**
 * Router para el servidor built-in de PHP.
 *
 * Uso: php -S localhost:8080 public/router.php
 *
 * Redirige TODAS las peticiones a index.php (simula mod_rewrite).
 */

// Verificar version minima de PHP
if (version_compare(PHP_VERSION, '8.2.0', '<')) {
    header('Content-Type: application/json; charset=utf-8');
    http_response_code(500);
    echo json_encode([
        'error' => true,
        'message' => 'PHP 8.2+ requerido. Version actual: ' . PHP_VERSION,
        'ayuda' => 'En WampServer: clic izquierdo > PHP > Version > 8.2.x. Si no aparece, descargar addon de wampserver.aviatechno.net'
    ]);
    exit;
}

$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$file = __DIR__ . $path;

// Si el archivo existe (ej: un .css, .js, imagen), servirlo directamente
if ($path !== '/' && is_file($file)) {
    return false;
}

// Todo lo demás va al index.php
require __DIR__ . '/index.php';
