<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Support\ScrapeSchedule;
use Illuminate\Http\JsonResponse;

/**
 * Endpoint que devuelve el "estado" del scraper:
 *  - Última ejecución real (inferida del historial).
 *  - Próxima ejecución según el cron configurado.
 *  - Segundos restantes hasta el próximo slot.
 *  - Stats de la última corrida (productos actualizados / creados).
 *
 * El frontend usa esto para mostrar el countdown "Faltan Xh Ym para el próximo
 * scrape" en el dashboard y en la lista de productos.
 */
class ScrapeStatusController extends Controller
{
    /**
     * GET /api/admin/scrape-status
     */
    public function index(): JsonResponse
    {
        return response()->json(ScrapeSchedule::snapshot());
    }
}
