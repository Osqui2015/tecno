<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Configuración de visibilidad del catálogo por proveedor.
 *
 * Una fila por origen (daz / tuc / tero / manual). Define:
 *  - `enabled`: si el proveedor se muestra en el catálogo público.
 *  - `category_ids`: categorías habilitadas (null = todas).
 *  - `filter_by_category`: si se filtra estrictamente por esas categorías.
 *
 * Si no hay filas para un origen, se aplica el comportamiento por defecto:
 * ese origen se muestra, sin filtro de categorías.
 */
class CatalogSetting extends Model
{
    protected $table = 'catalog_settings';

    protected $fillable = [
        'origin',
        'enabled',
        'category_ids',
        'filter_by_category',
        'sort_order',
    ];

    protected $casts = [
        'enabled'            => 'boolean',
        'category_ids'       => 'array',
        'filter_by_category' => 'boolean',
        'sort_order'         => 'integer',
    ];

    /**
     * Orígenes conocidos con su etiqueta para el panel admin.
     *
     * @return array<string, string>
     */
    public static function originLabels(): array
    {
        return [
            'daz'    => 'Daz',
            'tuc'    => 'TucTecnología',
            'tero'   => 'Tucumatero',
            'manual' => 'Manual',
        ];
    }

    /**
     * Devuelve la configuración de un origen, creándola con los defaults
     * si todavía no existe.
     */
    public static function forOrigin(string $origin): self
    {
        return static::firstOrCreate(
            ['origin' => $origin],
            [
                'enabled'            => true,
                'category_ids'       => null,
                'filter_by_category' => false,
                'sort_order'         => 0,
            ]
        );
    }

    /**
     * Configuración de todos los orígenes conocidos, indexada por origin.
     *
     * @return array<string, self>
     */
    public static function allByOrigin(): array
    {
        $settings = static::query()->get()->keyBy('origin');

        $result = [];
        foreach (array_keys(static::originLabels()) as $origin) {
            $result[$origin] = $settings->get($origin) ?? static::forOrigin($origin);
        }

        return $result;
    }

    /**
     * ¿Este origen debe mostrarse en el catálogo público?
     */
    public function isVisible(): bool
    {
        return (bool) $this->enabled;
    }

    /**
     * IDs de categorías habilitadas para este origen.
     *
     * @return array<int, int>
     */
    public function allowedCategoryIds(): array
    {
        return array_map('intval', $this->category_ids ?? []);
    }
}
