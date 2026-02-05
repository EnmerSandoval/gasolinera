<?php

declare(strict_types=1);

/**
 * Definición de rutas de la API.
 *
 * Variables externas: $router (Router), $request (Request)
 */

use App\middleware\AuthMiddleware;
use App\middleware\CorsMiddleware;
use App\middleware\RoleMiddleware;
use App\middleware\SucursalMiddleware;

use App\controllers\AuthController;
use App\controllers\CompraController;
use App\controllers\DashboardController;
use App\controllers\InventarioController;
use App\controllers\SucursalController;
use App\controllers\TurnoController;
use App\controllers\ValeController;
use App\controllers\VentaController;

// -------------------------------------------------------
// Middleware global: CORS en todas las rutas
// -------------------------------------------------------
$router->use(new CorsMiddleware());

// Instanciar middleware reutilizables
$auth     = new AuthMiddleware();
$sucursal = new SucursalMiddleware();
$adminOnly = new RoleMiddleware('admin');
$adminGerente = new RoleMiddleware('admin', 'gerente');

// -------------------------------------------------------
// Rutas públicas (sin autenticación)
// -------------------------------------------------------
$router->post('/auth/login', [AuthController::class, 'login']);
$router->post('/auth/refresh', [AuthController::class, 'refresh']);

// Health check
$router->get('/health', function () {
    jsonResponse([
        'status'  => 'ok',
        'service' => 'Gasolinera ERP API',
        'version' => '1.0.0',
        'time'    => date('c'),
    ]);
});

// -------------------------------------------------------
// Rutas protegidas (requieren JWT)
// -------------------------------------------------------
$router->group('', function ($router) use ($sucursal, $adminOnly, $adminGerente) {

    // --- Auth ---
    $router->post('/auth/logout', [AuthController::class, 'logout']);
    $router->get('/auth/me', [AuthController::class, 'me']);

    // --- Dashboard ---
    $router->get('/dashboard', [DashboardController::class, 'index'], [$sucursal]);

    // --- Sucursales ---
    $router->group('/sucursales', function ($router) use ($adminOnly) {
        $router->get('', [SucursalController::class, 'index']);
        $router->get('/{id}', [SucursalController::class, 'show']);
        $router->post('', [SucursalController::class, 'store'], [$adminOnly]);
        $router->put('/{id}', [SucursalController::class, 'update'], [$adminOnly]);
    });

    // --- Turnos ---
    $router->group('/turnos', function ($router) use ($sucursal) {
        $router->get('/activo', [TurnoController::class, 'activo'], [$sucursal]);
        $router->post('/abrir', [TurnoController::class, 'abrir'], [$sucursal]);
        $router->post('/{id}/cerrar', [TurnoController::class, 'cerrar'], [$sucursal]);
    });

    // --- Ventas (POS) ---
    $router->group('/ventas', function ($router) use ($sucursal, $adminGerente) {
        $router->get('', [VentaController::class, 'index'], [$sucursal]);
        $router->get('/totales', [VentaController::class, 'totales'], [$sucursal]);
        $router->get('/{id}', [VentaController::class, 'show'], [$sucursal]);
        $router->post('', [VentaController::class, 'store'], [$sucursal]);
        $router->post('/{id}/anular', [VentaController::class, 'anular'], [$sucursal, $adminGerente]);
    });

    // --- Inventario y Control Volumétrico ---
    $router->group('/inventario', function ($router) use ($sucursal, $adminGerente) {
        $router->get('/tanques', [InventarioController::class, 'tanques'], [$sucursal]);
        $router->get('/movimientos', [InventarioController::class, 'movimientos'], [$sucursal]);
        $router->get('/mermas', [InventarioController::class, 'reporteMermas'], [$sucursal]);
        $router->post('/corte-diario', [InventarioController::class, 'registrarCorte'], [$sucursal]);
    });

    // --- Vales y Créditos ---
    $router->group('/vales', function ($router) use ($adminGerente) {
        $router->get('', [ValeController::class, 'index']);
        $router->get('/validar/{codigo}', [ValeController::class, 'validar']);
        $router->post('', [ValeController::class, 'store'], [$adminGerente]);
        $router->post('/{id}/anular', [ValeController::class, 'anular'], [$adminGerente]);
    });

    // --- Clientes Corporativos ---
    $router->get('/clientes', [ValeController::class, 'clientes']);
    $router->get('/clientes/{id}/estado-cuenta', [ValeController::class, 'estadoCuenta']);

    // --- Compras ---
    $router->group('/compras', function ($router) use ($sucursal, $adminGerente) {
        $router->get('', [CompraController::class, 'index'], [$sucursal]);
        $router->get('/{id}', [CompraController::class, 'show'], [$sucursal]);
        $router->post('', [CompraController::class, 'store'], [$sucursal, $adminGerente]);
    });

}, [$auth]);
