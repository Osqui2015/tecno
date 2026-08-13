<?php

use App\Http\Controllers\Admin\CouponController as AdminCouponController;
use App\Http\Controllers\Admin\OrderController as AdminOrderController;
use App\Http\Controllers\Admin\ProductController as AdminProductController;
use App\Http\Controllers\Admin\UserController as AdminUserController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\CartController;
use App\Http\Controllers\Api\CategoryController;
use App\Http\Controllers\Api\OrderController;
use App\Http\Controllers\Api\ProductController;
use App\Http\Controllers\Api\ProfileController;
use App\Http\Controllers\Api\TwoFactorController;
use App\Http\Controllers\Api\WishlistController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

// ============ Rutas Públicas ============

Route::middleware('throttle:auth')->group(function () {
    Route::post('/register', [AuthController::class, 'register']);
    Route::post('/login',    [AuthController::class, 'login']);
    Route::post('/auth/2fa-challenge', [TwoFactorController::class, 'challenge']);
});

Route::get('/products/search', [ProductController::class, 'search']);
// Alias deprecado (typo histórico). Mantener por retrocompat con integraciones viejas.
Route::get('/products/searchproduc', [ProductController::class, 'search']);
Route::get('/products', [ProductController::class, 'index']);
Route::get('/products/{id}', [ProductController::class, 'show'])->whereNumber('id');
Route::get('/products/{id}/related', [ProductController::class, 'related'])->whereNumber('id');
Route::post('/compare', [ProductController::class, 'compare']);

// Reviews públicas (lectura)
Route::get('/products/{product}/reviews', [\App\Http\Controllers\Api\ReviewController::class, 'index'])->whereNumber('product');

// Coupons (preview de descuento — público)
Route::post('/coupons/validate',          [\App\Http\Controllers\Api\CouponController::class, 'validateCoupon']);

Route::get('/categories', [CategoryController::class, 'index']);
Route::get('/categories/{slug}', [CategoryController::class, 'show']);

Route::get('/health', fn () => response()->json([
    'status' => 'ok',
    'app'    => config('app.name'),
    'time'   => now()->toIso8601String(),
]));

// ============ Rutas Autenticadas (auth:sanctum) ============
// Compartidas por compradores y admins: perfil, logout, pedidos propios.
Route::middleware('auth:sanctum')->group(function () {
    Route::get('/me',           [AuthController::class, 'me']);
    Route::post('/logout',      [AuthController::class, 'logout']);

    // Perfil del comprador
    Route::get('/me/profile',   [ProfileController::class, 'show']);
    Route::patch('/me/profile', [ProfileController::class, 'update']);

    // Carrito del comprador (persiste en backend)
    Route::get('/cart',                 [CartController::class, 'index']);
    Route::post('/cart/items',          [CartController::class, 'store']);
    Route::patch('/cart/items/{id}',    [CartController::class, 'update'])->whereNumber('id');
    Route::delete('/cart/items/{id}',   [CartController::class, 'destroy'])->whereNumber('id');
    Route::delete('/cart',              [CartController::class, 'clear']);

    // Wishlist (favoritos)
    Route::get('/wishlist',              [WishlistController::class, 'index']);
    Route::post('/wishlist',             [WishlistController::class, 'store']);
    Route::delete('/wishlist/{product}', [WishlistController::class, 'destroy'])->whereNumber('product');

    // Reviews (escritura — lectura es pública)
    Route::post('/products/{product}/reviews', [\App\Http\Controllers\Api\ReviewController::class, 'store'])->whereNumber('product');
    Route::delete('/reviews/{review}',         [\App\Http\Controllers\Api\ReviewController::class, 'destroy'])->whereNumber('review');

    // 2FA
    Route::get('/me/two-factor',    [\App\Http\Controllers\Api\TwoFactorController::class, 'status']);
    Route::post('/me/two-factor/setup',  [\App\Http\Controllers\Api\TwoFactorController::class, 'setup']);
    Route::post('/me/two-factor/verify', [\App\Http\Controllers\Api\TwoFactorController::class, 'verify']);
    Route::delete('/me/two-factor',        [\App\Http\Controllers\Api\TwoFactorController::class, 'disable']);

    // Pedidos del comprador autenticado (sus propios pedidos)
    Route::get('/orders',                  [OrderController::class, 'index']);
    Route::post('/orders',                 [OrderController::class, 'store'])->middleware('throttle:checkout');
    Route::get('/orders/{id}',             [OrderController::class, 'show'])->whereNumber('id');
    Route::post('/orders/{id}/cancel',     [OrderController::class, 'cancel'])->whereNumber('id');
});

// ============ Rutas de Administrador (auth:sanctum + admin) ============
// Solo accesibles para usuarios con role = 'admin'.
Route::middleware(['auth:sanctum', 'admin', 'throttle:admin-write'])->prefix('admin')->group(function () {
    // Gestión de catálogo (categorías)
    Route::post('/categories',           [CategoryController::class, 'store']);

    // Gestión de productos
    Route::get('/products',              [AdminProductController::class, 'index']);
    Route::get('/products/export/csv',   [AdminProductController::class, 'exportCsv']);
    Route::post('/products/import/csv',  [AdminProductController::class, 'importCsv']);
    Route::post('/products',             [AdminProductController::class, 'store']);
    Route::get('/products/{id}',         [AdminProductController::class, 'show'])->whereNumber('id');
    Route::patch('/products/{id}',       [AdminProductController::class, 'update'])->whereNumber('id');
    Route::delete('/products/{id}',      [AdminProductController::class, 'destroy'])->whereNumber('id');
    Route::post('/products/bulk-markup', [AdminProductController::class, 'bulkMarkup']);

    // Gestión de cupones (Admin CRUD)
    Route::get('/coupons',               [AdminCouponController::class, 'index']);
    Route::post('/coupons',              [AdminCouponController::class, 'store']);
    Route::get('/coupons/{id}',          [AdminCouponController::class, 'show'])->whereNumber('id');
    Route::patch('/coupons/{id}',        [AdminCouponController::class, 'update'])->whereNumber('id');
    Route::patch('/coupons/{id}/toggle', [AdminCouponController::class, 'toggleActive'])->whereNumber('id');
    Route::delete('/coupons/{id}',       [AdminCouponController::class, 'destroy'])->whereNumber('id');

    // Gestión de usuarios / perfiles (Admin CRUD)
    Route::get('/users',                 [AdminUserController::class, 'index']);
    Route::post('/users',                [AdminUserController::class, 'store']);
    Route::get('/users/{id}',            [AdminUserController::class, 'show'])->whereNumber('id');
    Route::patch('/users/{id}',          [AdminUserController::class, 'update'])->whereNumber('id');
    Route::delete('/users/{id}',         [AdminUserController::class, 'destroy'])->whereNumber('id');

    // Gestión de pedidos
    Route::get('/orders',                       [AdminOrderController::class, 'index']);
    Route::get('/orders/export/csv',            [AdminOrderController::class, 'exportCsv']);
    Route::get('/orders/{id}',                  [AdminOrderController::class, 'show'])->whereNumber('id');
    Route::patch('/orders/{id}',                [AdminOrderController::class, 'update'])->whereNumber('id');
    Route::delete('/orders/{id}',               [AdminOrderController::class, 'destroy'])->whereNumber('id');

    // Confirmación de disponibilidad + WhatsApp
    Route::get('/orders/{id}/whatsapp-preview',      [AdminOrderController::class, 'whatsappPreview'])->whereNumber('id');
    Route::post('/orders/{id}/confirm-availability', [AdminOrderController::class, 'confirmAvailability'])->whereNumber('id');

    // Audit log
    Route::get('/audit-logs',            [\App\Http\Controllers\Admin\AuditLogController::class, 'index']);

    // Stats (sub-endpoints cacheados independientemente)
    Route::get('/stats',                 [\App\Http\Controllers\Admin\AdminStatsController::class, 'index']);
    Route::get('/stats/kpis',            [\App\Http\Controllers\Admin\AdminStatsController::class, 'kpis']);
    Route::get('/stats/sales-chart',     [\App\Http\Controllers\Admin\AdminStatsController::class, 'salesChart']);
    Route::get('/stats/top-products',    [\App\Http\Controllers\Admin\AdminStatsController::class, 'topProducts']);
    Route::get('/stats/recent-orders',   [\App\Http\Controllers\Admin\AdminStatsController::class, 'recentOrders']);
    Route::get('/stats/categories-sales',[\App\Http\Controllers\Admin\AdminStatsController::class, 'categoriesSales']);

    // Reporte de márgenes
    Route::get('/products/margins',      [\App\Http\Controllers\Admin\AdminStatsController::class, 'margins']);
});
