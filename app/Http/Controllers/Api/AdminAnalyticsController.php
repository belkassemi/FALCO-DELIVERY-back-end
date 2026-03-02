<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\Store;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * PRD §9.3 — Admin Analytics Dashboard
 *
 * All endpoints (except the base KPI) accept ?period=week|month|all
 * Default: month (last 30 days)
 */
class AdminAnalyticsController extends Controller
{
    /**
     * Resolve the start date for the given period.
     * Returns null for 'all' (no date filter).
     */
    private function periodStart(string $period): ?\Illuminate\Support\Carbon
    {
        return match ($period) {
            'week'  => now()->subDays(7),
            'all'   => null,
            default => now()->subDays(30), // 'month'
        };
    }

    /**
     * GET /api/admin/analytics/revenue?period=week|month|all
     *
     * Daily revenue chart. Only delivered orders counted.
     */
    public function revenue(Request $request)
    {
        $period = $request->get('period', 'month');
        $from   = $this->periodStart($period);

        $query = Order::where('status', 'delivered');
        if ($from) {
            $query->where('created_at', '>=', $from);
        }

        $data = $query
            ->selectRaw("DATE(created_at) as date, SUM(total_price) as revenue, COUNT(*) as orders")
            ->groupBy('date')
            ->orderBy('date')
            ->get();

        return response()->json([
            'period' => $period,
            'total'  => $data->sum('revenue'),
            'data'   => $data,
        ]);
    }

    /**
     * GET /api/admin/analytics/orders?period=week|month|all
     *
     * Order volume breakdown by status for the period.
     */
    public function orders(Request $request)
    {
        $period = $request->get('period', 'month');
        $from   = $this->periodStart($period);

        $query = Order::query();
        if ($from) {
            $query->where('created_at', '>=', $from);
        }

        $rows = $query
            ->selectRaw('status, COUNT(*) as count')
            ->groupBy('status')
            ->pluck('count', 'status');

        // Build full map with all known statuses, defaulting to 0
        $statuses = [
            'pending', 'courier_searching', 'courier_assigned',
            'preparing', 'ready', 'picked_up', 'delivered', 'cancelled',
        ];

        $byStatus = collect($statuses)->mapWithKeys(fn($s) => [$s => (int) ($rows[$s] ?? 0)]);

        return response()->json([
            'period'    => $period,
            'total'     => $byStatus->sum(),
            'by_status' => $byStatus,
        ]);
    }

    /**
     * GET /api/admin/analytics/stores?period=week|month|all
     *
     * Top 10 stores by revenue for the period. Delivered orders only.
     */
    public function stores(Request $request)
    {
        $period = $request->get('period', 'month');
        $from   = $this->periodStart($period);

        $query = DB::table('orders')
            ->join('stores', 'orders.store_id', '=', 'stores.id')
            ->leftJoin('categories', 'stores.category_id', '=', 'categories.id')
            ->where('orders.status', 'delivered');

        if ($from) {
            $query->where('orders.created_at', '>=', $from);
        }

        $topStores = $query
            ->selectRaw('
                stores.id as store_id,
                stores.name as store_name,
                categories.name as category,
                SUM(orders.total_price) as total_revenue,
                COUNT(orders.id) as total_orders,
                ROUND(SUM(orders.total_price) / NULLIF(COUNT(orders.id), 0), 2) as avg_order_value
            ')
            ->groupBy('stores.id', 'stores.name', 'categories.name')
            ->orderByDesc('total_revenue')
            ->limit(10)
            ->get();

        return response()->json([
            'period'     => $period,
            'top_stores' => $topStores,
        ]);
    }

    /**
     * GET /api/admin/analytics/couriers?period=week|month|all
     *
     * Top 10 couriers by completed deliveries for the period.
     */
    public function couriers(Request $request)
    {
        $period = $request->get('period', 'month');
        $from   = $this->periodStart($period);

        $query = DB::table('orders')
            ->join('users', 'orders.courier_id', '=', 'users.id')
            ->where('orders.status', 'delivered')
            ->whereNotNull('orders.courier_id');

        if ($from) {
            $query->where('orders.created_at', '>=', $from);
        }

        $topCouriers = $query
            ->selectRaw('
                users.id as courier_id,
                users.name as courier_name,
                COUNT(orders.id) as deliveries_completed,
                ROUND(SUM(orders.delivery_distance_km)::numeric, 2) as total_distance_km,
                ROUND((SUM(orders.delivery_distance_km) / NULLIF(COUNT(orders.id), 0))::numeric, 2) as avg_distance_km
            ')
            ->groupBy('users.id', 'users.name')
            ->orderByDesc('deliveries_completed')
            ->limit(10)
            ->get();

        return response()->json([
            'period'       => $period,
            'top_couriers' => $topCouriers,
        ]);
    }
}
