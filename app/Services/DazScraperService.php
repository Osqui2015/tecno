<?php

namespace App\Services;

/**
 * Scraper de productos desde dazimportadora.com.ar
 *
 * Toda la lógica de parseo vive en BaseWoodmartScraper.
 * Esta clase solo configura el sitio específico.
 *
 * Estructura específica de Daz:
 *  - Categorías en <div class="wd-product-cats"><a>...</a></div>
 *  - Marca en <div class="wd-product-brands-links"><a>...</a></div>
 *  - Formato de precio: AR ("$ 4.200,50")
 *  - gtm4wp_productdata presente
 */
class DazScraperService extends BaseWoodmartScraper
{
    protected function configure(): void
    {
        $this->baseUrl  = 'https://dazimportadora.com.ar';
        $this->origin   = 'daz';
        $this->referer  = 'https://dazimportadora.com.ar/productos/';
    }
}
