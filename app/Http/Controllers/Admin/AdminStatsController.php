<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\Product;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

/**
 * Métricas para el dashboard admin.
 * Una sola request carga todo lo necesario.
 */
class AdminStatsController extends Controller
{
    public function index(): JsonResponse
    {
        // 1) Conteos generales
        $totalProducts = Product::count();
        $activeProducts = Product::where('active', true)->count();
        $dazProducts   = Product::whereNotNull('external_id')->count();
        $outOfStock    = Product::where('stock', '<=', 0)->count();

        $totalOrders = Order::count();
        $pendingOrders = Order::where('status', Order::STATUS_PENDING)->count();

        // 2) Revenue (excluyendo cancelados)
        $totalRevenue = (float) Order::where('status', '!=', Order::STATUS_CANCELLED)->sum('total');

        // 3) Ventas del mes actual
        $monthRevenue = (float) Order::where('status', '!=', Order::STATUS_CANCELLED)
            ->where('created_at', '>=', now()->startOfMonth())
            ->sum('total');

        $monthOrders = Order::where('created_at', '>=', now()->startOfMonth())->count();

        // 4) Ticket promedio
        $avgTicket = $totalOrders > 0
            ? (float) Order::where('status', '!=', Order::STATUS_CANCELLED)->avg('total')
            : 0;

        // 5) Pedidos agrupados por estado
        $ordersByStatus = Order::select('status', DB::raw('count(*) as count'))
            ->groupBy('status')
            ->pluck('count', 'status')
            ->toArray();

        // 6) Top 5 productos más vendidos
        $topProducts = DB::table('order_items')
            ->join('products', 'order_items.product_id', '=', 'products.id')
            ->select(
                'products.id',
                'products.name',
                'products.image',
                'products.external_id',
                DB::raw('SUM(order_items.qty) as sold_qty'),
                DB::raw('SUM(order_items.qty * order_items.price) as revenue')
            )
            ->groupBy('products.id', 'products.name', 'products.image', 'products.external_id')
            ->orderByDesc('sold_qty')
            ->limit(5)
            ->get();

        // 7) Últimos 5 pedidos
        $recentOrders = Order::with('user')
            ->orderByDesc('created_at')
            ->limit(5)
            ->get(['id', 'user_id', 'total', 'status', 'created_at', 'customer_full_name']);

        // 8) Ventas últimos 30 días (para gráfico interactivo)
        $salesLast30Days = Order::where('status', '!=', Order::STATUS_CANCELLED)
            ->where('created_at', '>=', now()->subDays(29)->startOfDay())
            ->select(
                DB::raw('DATE(created_at) as date'),
                DB::raw('COUNT(*) as orders_count'),
                DB::raw('SUM(total) as revenue')
            )
            ->groupBy('date')
            ->orderBy('date')
            ->get();

        // 9) Ventas por Categoría (Top 5)
        $categoriesSales = DB::table('order_items')
            ->join('products', 'order_items.product_id', '=', 'products.id')
            ->join('categories', 'products.category_id', '=', 'categories.id')
            ->select(
                'categories.name as category',
                DB::raw('SUM(order_items.qty * order_items.price) as total_sales')
            )
            ->groupBy('categories.name')
            ->orderByDesc('total_sales')
            ->limit(5)
            ->get();

        return response()->json([
            'products' => [
                'total'        => $totalProducts,
                'active'       => $activeProducts,
                'from_daz'     => $dazProducts,
                'out_of_stock' => $outOfStock,
            ],
            'orders' => [
                'total'   => $totalOrders,
                'pending' => $pendingOrders,
                'by_status' => $ordersByStatus,
            ],
            'revenue' => [
                'total'      => round($totalRevenue, 2),
                'this_month' => round($monthRevenue, 2),
                'avg_ticket' => round($avgTicket, 2),
                'month_orders_count' => $monthOrders,
            ],
            'top_products'      => $topProducts,
            'recent_orders'     => $recentOrders,
            'sales_7_days'      => $salesLast30Days,
            'sales_last_30_days' => $salesLast30Days,
            'categories_sales'  => $categoriesSales,
        ]);
    }
}
