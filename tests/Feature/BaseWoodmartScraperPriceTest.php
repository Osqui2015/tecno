<?php

namespace Tests\Feature;

use App\Services\BaseWoodmartScraper;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use ReflectionClass;
use Tests\TestCase;

/**
 * Tests de la lógica de BaseWoodmartScraper::extractPriceAmount()
 * vía reflexión, ya que el método es protected.
 *
 * Cubre la heurística para distinguir formato AR (Daz) vs US (Tuc).
 */
class BaseWoodmartScraperPriceTest extends TestCase
{
    private BaseWoodmartScraper $scraper;

    protected function setUp(): void
    {
        parent::setUp();
        // Construimos una instancia anónima para poder testear el método protected
        $this->scraper = new class extends BaseWoodmartScraper {
            protected function configure(): void
            {
                $this->baseUrl = 'https://example.com';
            }
        };
    }

    private function extractPrice(string $text): ?float
    {
        $reflection = new ReflectionClass(BaseWoodmartScraper::class);
        $method = $reflection->getMethod('extractPriceAmount');
        return $method->invoke($this->scraper, $text);
    }

    // ============================================================
    //  Datos de prueba
    // ============================================================

    public static function priceProvider(): array
    {
        return [
            // AR (Daz) — punto = miles, coma = decimal
            'AR simple thousands'        => ['$ 4.200',     4200.0],
            'AR miles with decimal'      => ['$ 4.200,50',  4200.5],
            'AR millions'                => ['$ 1.234.567', 1234567.0],
            'AR decimals small'          => ['$ 4.7',       4.7],
            'AR decimals 2'              => ['$ 12,50',     12.5],
            'AR no decimals'             => ['$ 20.500',    20500.0],

            // US (Tuc) — coma = miles, punto = decimal
            'US simple thousands'        => ['$20,500.00',  20500.0],
            'US thousands decimal'       => ['$2,383.11',   2383.11],
            'US high value'              => ['$190,000.00', 190000.0],
            'US decimal small'           => ['$1,750.00',   1750.0],
            'US millions'                => ['$1,234,567.89', 1234567.89],
            'US decimal alone'           => ['$4.7',        4.7],
            'US decimal 2'               => ['$12.50',      12.5],

            // Sin separadores
            'plain number'               => ['$123',        123.0],
            'plain decimal'              => ['$12.50',      12.5],
            'plain decimal US'           => ['$12.50',      12.5],
        ];
    }

    #[Test]
    #[DataProvider('priceProvider')]
    public function it_detects_price_format_correctly(string $input, float $expected): void
    {
        $this->assertSame($expected, $this->extractPrice($input));
    }

    #[Test]
    public function it_returns_null_for_empty_or_invalid_input(): void
    {
        $this->assertNull($this->extractPrice(''));
        $this->assertNull($this->extractPrice('$'));
        $this->assertNull($this->extractPrice('sin precio'));
    }

    #[Test]
    public function it_handles_negative_prices(): void
    {
        $this->assertSame(-100.0, $this->extractPrice('-100'));
        $this->assertSame(-20500.0, $this->extractPrice('-$20,500.00'));
    }
}
