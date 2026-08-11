<?php

namespace App\Services;

use DOMElement;
use DOMNode;
use DOMXPath;

/**
 * Scraper de productos desde tustecnologiastuc.com
 *
 * Mismo patrón Woodmart+AJAX que Daz, pero con dos diferencias que
 * sobreescribimos acá:
 *  - Las categorías NO vienen en <div class="wd-product-cats"><a>.
 *    Vienen en el `class` del root como tokens `product_cat-{slug}`.
 *    Ej: `class="... product_cat-belleza product_cat-hogar ..."`.
 *  - No muestra marca (no hay `wd-product-brands-links`).
 *
 * Diferencia de precios:
 *  - Tuc usa formato US ("$20,500.00") con coma como separador de miles.
 *  - Daz usa formato AR ("$ 4.200,50") con punto como separador de miles.
 *  El BaseWoodmartScraper::extractPriceAmount() detecta automáticamente ambos.
 */
class TucScraperService extends BaseWoodmartScraper
{
    protected function configure(): void
    {
        $this->baseUrl  = 'https://www.tustecnologiastuc.com';
        $this->origin   = 'tuc';
        $this->referer  = 'https://www.tustecnologiastuc.com/';
        $this->shopPath = '/tienda/';
    }


    /**
     * Tuc guarda las categorías en el atributo `class` del root como
     * tokens `product_cat-{slug}`. Extraemos todos y los convertimos a
     * nombres legibles ("hogar" → "Hogar").
     */
    protected function extractCategories(DOMXPath $xpath, DOMNode $node): array
    {
        if (! $node instanceof DOMElement) {
            return [];
        }

        $class = $node->getAttribute('class');
        if ($class === '') {
            return [];
        }

        if (! preg_match_all('/\bproduct_cat-([a-z0-9\-]+)/i', $class, $matches)) {
            return [];
        }

        // Convertir slug → nombre legible y deduplicar
        return array_values(array_unique(array_map(
            fn (string $slug) => ucwords(str_replace('-', ' ', $slug)),
            $matches[1]
        )));
    }

    /**
     * Tuc no muestra marca en la card del producto.
     */
    protected function extractBrand(DOMXPath $xpath, DOMNode $node): ?string
    {
        return null;
    }
}
