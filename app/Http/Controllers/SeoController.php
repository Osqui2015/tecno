<?php

namespace App\Http\Controllers;

use App\Models\Product;
use Illuminate\Contracts\View\View;

/**
 * Controller SEO: renderiza la vista de la SPA con meta tags dinámicos
 * según el producto/categoría que se está compartiendo.
 *
 * Sin esto, WhatsApp / Facebook / Twitter no pueden generar el preview
 * rico (con imagen, título y descripción del producto) porque el shell
 * HTML de la SPA es siempre el mismo.
 */
class SeoController extends Controller
{
    /**
     * GET /productos/{id}
     * Devuelve la vista SPA con Open Graph tags del producto.
     */
    public function product(int $id): View
    {
        $product = Product::with('category')
            ->active()
            ->findOrFail($id);

        // URL absoluta de la imagen (debe ser pública para que los bots
        // de WhatsApp/Facebook/Twitter la puedan descargar).
        $imageUrl = $this->resolveImageUrl($product);

        // Descripción limpia: si está vacía o es muy corta, armamos una.
        $description = $this->buildDescription($product);

        return view('app', [
            'seoProduct'      => $product,
            'seoTitle'        => $product->name,
            'seoDescription'  => $description,
            'seoImage'        => $imageUrl,
            'seoUrl'          => url("/productos/{$product->id}"),
            'seoType'         => 'product',
        ]);
    }

    /**
     * GET /productos/{slug}/{id} (formato SEO-friendly opcional)
     */
    public function productBySlug(string $slug, int $id): View
    {
        return $this->product($id);
    }

    /**
     * GET /categorias/{slug}
     */
    public function category(string $slug): View
    {
        $category = \App\Models\Category::where('slug', $slug)->firstOrFail();

        return view('app', [
            'seoCategory'     => $category,
            'seoTitle'        => $category->name . ' — Tecno-Rexs',
            'seoDescription'  => $category->description
                ?: "Explorá nuestra selección de {$category->name} en Tecno-Rexs.",
            'seoImage'        => asset('icons/icon-512.png'),
            'seoUrl'          => url("/categorias/{$category->slug}"),
            'seoType'         => 'website',
        ]);
    }

    /**
     * Resuelve la URL absoluta de la imagen del producto.
     * Prioriza la imagen local; si es externa, la devuelve tal cual.
     */
    private function resolveImageUrl(Product $product): string
    {
        if (! $product->image) {
            return asset('icons/icon-512.png');
        }

        // Si ya es una URL absoluta (http/https), devolverla
        if (str_starts_with($product->image, 'http://') || str_starts_with($product->image, 'https://')) {
            return $product->image;
        }

        // Si es path local (products/52.jpg), armar la URL pública
        // Asume que está en storage/app/public
        if (str_starts_with($product->image, 'products/')) {
            return asset('storage/' . $product->image);
        }

        // Fallback
        return asset($product->image);
    }

    /**
     * Arma una descripción útil para el preview OG.
     */
    private function buildDescription(Product $product): string
    {
        $price = (float) ($product->final_price ?? $product->price);
        $parts = [];

        if ($product->brand) {
            $parts[] = $product->brand;
        }
        if ($product->category) {
            $parts[] = $product->category->name;
        }
        $parts[] = '$' . number_format($price, 0, ',', '.');

        $header = implode(' · ', $parts);

        // Si tiene descripción, agregar un extracto
        if ($product->description) {
            $excerpt = \Illuminate\Support\Str::limit(strip_tags($product->description), 120);
            return "{$header} — {$excerpt}";
        }

        return $header;
    }
}
