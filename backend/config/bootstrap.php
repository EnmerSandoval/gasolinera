<?php

declare(strict_types=1);

/**
 * Bootstrap: carga env, helpers, autoloader y configuración.
 */

// Autoload de clases (PSR-4 simplificado)
spl_autoload_register(function (string $class): void {
    // Namespace base: App\
    $prefix = 'App\\';
    $baseDir = dirname(__DIR__) . '/';

    if (strncmp($prefix, $class, strlen($prefix)) !== 0) {
        return;
    }

    $relativeClass = substr($class, strlen($prefix));
    $file = $baseDir . str_replace('\\', '/', $relativeClass) . '.php';

    if (file_exists($file)) {
        require_once $file;
    }
});

// Helpers globales
require_once __DIR__ . '/../helpers/functions.php';

// Cargar variables de entorno
loadEnv(dirname(__DIR__) . '/.env');

// Timezone
date_default_timezone_set(env('APP_TIMEZONE', 'America/Guatemala'));

// Reporte de errores según entorno
if (env('APP_DEBUG', false)) {
    error_reporting(E_ALL);
    ini_set('display_errors', '1');
} else {
    error_reporting(0);
    ini_set('display_errors', '0');
}

// Cargar configuración
$config = require __DIR__ . '/app.php';

return $config;
