<?php

declare(strict_types=1);

/**
 * Configuración principal de la aplicación.
 * Los valores se cargan desde variables de entorno (.env).
 */
return [
    'name'     => env('APP_NAME', 'Gasolinera ERP'),
    'env'      => env('APP_ENV', 'production'),
    'debug'    => env('APP_DEBUG', false),
    'url'      => env('APP_URL', 'http://localhost/gasolinera/backend/public'),
    'timezone' => env('APP_TIMEZONE', 'America/Guatemala'),

    'db' => [
        'host'     => env('DB_HOST', '127.0.0.1'),
        'port'     => (int) env('DB_PORT', 3306),
        'database' => env('DB_DATABASE', 'gasolinera_erp'),
        'username' => env('DB_USERNAME', 'root'),
        'password' => env('DB_PASSWORD', ''),
        'charset'  => 'utf8mb4',
        'collation'=> 'utf8mb4_unicode_ci',
    ],

    'jwt' => [
        'secret'          => env('JWT_SECRET', ''),
        'algorithm'       => 'HS256',
        'access_ttl'      => (int) env('JWT_ACCESS_TTL', 3600),       // 1 hora
        'refresh_ttl'     => (int) env('JWT_REFRESH_TTL', 604800),    // 7 días
        'issuer'          => env('APP_URL', 'http://localhost/gasolinera/backend/public'),
    ],

    'cors' => [
        'allowed_origins' => explode(',', env('CORS_ORIGINS', 'http://localhost:5173')),
        'allowed_methods' => ['GET', 'POST', 'PUT', 'PATCH', 'DELETE', 'OPTIONS'],
        'allowed_headers' => ['Content-Type', 'Authorization', 'X-Sucursal-Id'],
        'max_age'         => 86400,
    ],

    'iva_rate'  => 0.12,  // Guatemala 12%
    'currency'  => 'GTQ',
];
