<?php

use App\Http\Controllers\SeoController;
use Illuminate\Support\Facades\Route;

// SEO: rutas con meta tags dinámicos para que el preview de WhatsApp/Facebook
// muestre la imagen y datos del producto. El shell SPA se sigue sirviendo
// (Vue Router toma el control en el cliente), pero el HTML del servidor
// ya viene con los <meta property="og:*" correctos.
Route::get('/productos/{id}', [SeoController::class, 'product'])
    ->whereNumber('id')
    ->name('seo.product');
Route::get('/productos/{slug}/{id}', [SeoController::class, 'productBySlug'])
    ->whereNumber('id')
    ->name('seo.product.slug');
Route::get('/categorias/{slug}', [SeoController::class, 'category'])
    ->name('seo.category');

// SPA catch-all: cualquier otra ruta se sirve con Vue
Route::get('/{any?}', function () {
    return view('app');
})->where('any', '^(?!api).*$');
