<?php

namespace App\Support;

use App\Models\Product;
use App\Models\ProductUpdateHistory;
use Carbon\CarbonImmutable;

/**
 * Helper que centraliza el cálculo de "cuándo corrió / cuándo corre" el scraper.
 *
 * El scheduler está definido en bootstrap/app.php con cron cada 6 horas
 * (00:00, 06:00, 12:00, 18:00 hora del servidor).
 *
 * Si la frecuencia del cron cambia, ajustar INTERVAL_HOURS.
 */
class ScrapeSchedule
{
    /** Cada cuántas horas corre el scraper (debe coincidir con el scheduler). */
    public const INTERVAL_HOURS = 6;

    /**
     * Devuelve el próximo slot de scrape (>= now), alineado al múltiplo del intervalo.
     */
    public static function nextRun(?CarbonImmutable $from = null): CarbonImmutable
    {
        $from ??= CarbonImmutable::now();
        $hour = (int) $from->format('H');
        $nextSlot = intdiv($hour, self::INTERVAL_HOURS) * self::INTERVAL_HOURS + self::INTERVAL_HOURS;

        // Importante: primero calcular el día (por si la hora rolloverea al día siguiente),
        // después aplicar el time. Si vamos a un slot >= 24, hay que avanzar un día.
        $addDays = $nextSlot >= 24 ? 1 : 0;
        $effectiveHour = $nextSlot % 24;

        return $from
            ->setDate($from->year, $from->month, $from->day)
            ->addDays($addDays)
            ->setTime($effectiveHour, 0, 0);
    }

    /**
     * Devuelve el slot ANTERIOR al actual (el "último" si ya pasó, o el previo si todavía no).
     */
    public static function lastRun(?CarbonImmutable $from = null): CarbonImmutable
    {
        $from ??= CarbonImmutable::now();
        $hour = (int) $from->format('H');
        $lastSlot = intdiv($hour, self::INTERVAL_HOURS) * self::INTERVAL_HOURS;

        return $from
            ->setDate($from->year, $from->month, $from->day)
            ->setTime($lastSlot, 0, 0);
    }

    /**
     * Diferencia en segundos entre $from y el próximo slot.
     * Útil para mostrar "Faltan Xh Ym" en la UI.
     */
    public static function secondsUntilNextRun(?CarbonImmutable $from = null): int
    {
        $from ??= CarbonImmutable::now();
        return self::nextRun($from)->getTimestamp() - $from->getTimestamp();
    }

    /**
     * Última ejecución REAL del scraper, inferida del historial de actualizaciones.
     * Toma el `ProductUpdateHistory` más reciente cuyo source sea 'scraper:daz' o
     * 'scraper:tuc'. Devuelve null si nunca corrió.
     */
    public static function lastActualRun(): ?CarbonImmutable
    {
        $row = ProductUpdateHistory::query()
            ->whereIn('source', [
                ProductUpdateHistory::SOURCE_SCRAPER_DAZ,
                ProductUpdateHistory::SOURCE_SCRAPER_TUC,
            ])
            ->orderByDesc('created_at')
            ->first();

        return $row?->created_at?->toImmutable();
    }

    /**
     * Cuántos productos se actualizaron en la última corrida (entre ambos scrapers,
     * usando una ventana de ±2h alrededor del último evento).
     */
    public static function lastRunStats(): array
    {
        $last = self::lastActualRun();
        if (! $last) {
            return ['last_run_at' => null, 'updated' => 0, 'created' => 0, 'by_source' => []];
        }

        $from = $last->subHours(2);
        $to   = $last->addHours(2);

        $q = ProductUpdateHistory::query()
            ->whereIn('source', [
                ProductUpdateHistory::SOURCE_SCRAPER_DAZ,
                ProductUpdateHistory::SOURCE_SCRAPER_TUC,
            ])
            ->whereBetween('created_at', [$from, $to]);

        $rows = (clone $q)->get(['source', 'event', 'changed_fields']);

        $bySource = [];
        $created = 0;
        $updated = 0;
        foreach ($rows as $r) {
            $bySource[$r->source] = ($bySource[$r->source] ?? 0) + 1;
            if ($r->event === ProductUpdateHistory::EVENT_CREATED) {
                $created++;
            } else {
                $updated++;
            }
        }

        return [
            'last_run_at' => $last,
            'updated'     => $updated,
            'created'     => $created,
            'by_source'   => $bySource,
        ];
    }

    /**
     * Snapshot completo para mostrar en el panel admin.
     */
    public static function snapshot(): array
    {
        $now = CarbonImmutable::now();
        $next = self::nextRun($now);
        $lastActual = self::lastActualRun();
        $stats = self::lastRunStats();

        return [
            'now'                  => $now->toIso8601String(),
            'next_run_at'          => $next->toIso8601String(),
            'next_run_human'       => $next->translatedFormat('d/m/Y H:i'),
            'seconds_until_next'   => $next->getTimestamp() - $now->getTimestamp(),
            'interval_hours'       => self::INTERVAL_HOURS,
            'last_actual_run_at'   => $lastActual?->toIso8601String(),
            'last_actual_run_human'=> $lastActual?->translatedFormat('d/m/Y H:i'),
            'last_run_stats'       => $stats,
        ];
    }
}
