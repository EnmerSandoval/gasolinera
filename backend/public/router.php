<?php

/**
 * Router para el servidor built-in de PHP.
 *
 * Uso: php -S localhost:8080 public/router.php
 *
 * Redirige TODAS las peticiones a index.php (simula mod_rewrite).
 */

$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$file = __DIR__ . $path;

// Si el archivo existe (ej: un .css, .js, imagen), servirlo directamente
if ($path !== '/' && is_file($file)) {
    return false;
}

// Todo lo demás va al index.php
require __DIR__ . '/index.php';
