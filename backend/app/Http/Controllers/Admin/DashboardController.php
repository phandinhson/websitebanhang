<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use App\Models\Category;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

/**
 * Controller Dashboard Admin - Thống kê tổng quan hệ thống
 */
class DashboardController extends Controller
{
    /**
     * Lấy thống kê tổng quan cho dashboard admin
     *
     * GET /api/admin/dashboard/stats
     *
     * Query params:
     *   - period: Khoảng thời gian (today, week, month, year, all) - mặc định: month
     */
    public function getStats(Request $request): JsonResponse
    {
        $period = $request->get('period', 'month');

        // Xác định khoảng thời gian
        [$dateFrom, $dateTo] = $this->getPeriodDates($period);

        try {
            // ============================================================
            // Thống kê tổng quát (toàn thời gian)
            // ============================================================
            $totalUsers    = User::where('role', 'customer')->count();
            $totalProducts = Product::count();
            $totalCategories = Category::count();

            // ============================================================
            // Thống kê đơn hàng theo khoảng thời gian
            // ============================================================
            $ordersQuery = Order::query();
            if ($dateFrom) {
                $ordersQuery->where('created_at', '>=', $dateFrom);
            }
            if ($dateTo) {
                $ordersQuery->where('created_at', '<=', $dateTo);
            }

            $totalOrders  = $ordersQuery->count();

            // Đếm theo từng trạng thái
            $pendingOrders   = (clone $ordersQuery)->where('status', Order::STATUS_PENDING)->count();
            $confirmedOrders = (clone $ordersQuery)->where('status', Order::STATUS_CONFIRMED)->count();
            $shippingOrders  = (clone $ordersQuery)->where('status', Order::STATUS_SHIPPING)->count();
            $deliveredOrders = (clone $ordersQuery)->where('status', Order::STATUS_DELIVERED)->count();
            $cancelledOrders = (clone $ordersQuery)->where('status', Order::STATUS_CANCELLED)->count();

            // Tổng doanh thu (chỉ tính đơn hàng đã giao)
            $revenueOrders = (clone $ordersQuery)->where('status', Order::STATUS_DELIVERED)->get();
            $totalRevenue  = $revenueOrders->sum('final_amount');

            // Doanh thu COD
            $revenueCod = $revenueOrders->where('payment_method', Order::PAYMENT_COD)->sum('final_amount');

            // ============================================================
            // Thống kê sản phẩm
            // ============================================================
            $activeProducts   = Product::where('is_active', true)->count();
            $outOfStockProducts = Product::where('stock', 0)->count();
            $lowStockProducts = Product::where('stock', '>', 0)
                                       ->where('stock', '<=', 5)
                                       ->count();

            // ============================================================
            // Thống kê người dùng mới theo khoảng thời gian
            // ============================================================
            $newUsersQuery = User::where('role', 'customer');
            if ($dateFrom) {
                $newUsersQuery->where('created_at', '>=', $dateFrom);
            }
            if ($dateTo) {
                $newUsersQuery->where('created_at', '<=', $dateTo);
            }
            $newUsers = $newUsersQuery->count();

            // ============================================================
            // Top sản phẩm bán chạy (dựa trên đơn hàng đã giao)
            // ============================================================
            $topProducts = $this->getTopSellingProducts($dateFrom, $dateTo, 5);

            // ============================================================
            // Doanh thu theo tháng (12 tháng gần nhất)
            // ============================================================
            $monthlyRevenue = $this->getMonthlyRevenue(12);

            // ============================================================
            // Đơn hàng gần đây nhất
            // ============================================================
            $recentOrders = Order::orderBy('created_at', 'desc')
                                 ->limit(10)
                                 ->get()
                                 ->map(fn($o) => [
                                     'id'           => (string) $o->_id,
                                     'order_code'   => $o->order_code,
                                     'status'       => $o->status,
                                     'status_label' => $o->status_label,
                                     'total_price'  => $o->total_price,
                                     'final_amount' => $o->final_amount,
                                     'created_at'   => $o->created_at?->toISOString(),
                                 ]);

            return response()->json([
                'success' => true,
                'data'    => [
                    // Tổng quan
                    'overview' => [
                        'total_users'       => $totalUsers,
                        'total_products'    => $totalProducts,
                        'total_categories'  => $totalCategories,
                        'total_orders'      => $totalOrders,
                        'total_revenue'     => $totalRevenue,
                        'revenue_cod'       => $revenueCod,
                        'new_users'         => $newUsers,
                    ],

                    // Trạng thái đơn hàng
                    'order_stats' => [
                        'pending'   => $pendingOrders,
                        'confirmed' => $confirmedOrders,
                        'shipping'  => $shippingOrders,
                        'delivered' => $deliveredOrders,
                        'cancelled' => $cancelledOrders,
                    ],

                    // Thống kê sản phẩm
                    'product_stats' => [
                        'active'      => $activeProducts,
                        'out_of_stock' => $outOfStockProducts,
                        'low_stock'   => $lowStockProducts,
                    ],

                    // Top sản phẩm bán chạy
                    'top_products' => $topProducts,

                    // Doanh thu theo tháng
                    'monthly_revenue' => $monthlyRevenue,

                    // Đơn hàng gần đây
                    'recent_orders' => $recentOrders,

                    // Thông tin khoảng thời gian
                    'period' => [
                        'type'      => $period,
                        'date_from' => $dateFrom,
                        'date_to'   => $dateTo,
                    ],
                ],
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Lỗi khi lấy thống kê.',
                'error'   => config('app.debug') ? $e->getMessage() : null,
            ], 500);
        }
    }

    // ============================================================
    // Private Helper Methods
    // ============================================================

    /**
     * Lấy khoảng thời gian theo period
     *
     * @return array [string|null $from, string|null $to]
     */
    private function getPeriodDates(string $period): array
    {
        $now = now();

        return match ($period) {
            'today'  => [$now->startOfDay()->toDateTimeString(), $now->endOfDay()->toDateTimeString()],
            'week'   => [$now->startOfWeek()->toDateTimeString(), $now->endOfWeek()->toDateTimeString()],
            'month'  => [$now->startOfMonth()->toDateTimeString(), $now->endOfMonth()->toDateTimeString()],
            'year'   => [$now->startOfYear()->toDateTimeString(), $now->endOfYear()->toDateTimeString()],
            default  => [null, null], // all time
        };
    }

    /**
     * Lấy top sản phẩm bán chạy nhất
     */
    private function getTopSellingProducts(?string $dateFrom, ?string $dateTo, int $limit = 5): array
    {
        // Lấy các đơn hàng đã giao trong khoảng thời gian
        $ordersQuery = Order::where('status', Order::STATUS_DELIVERED);
        if ($dateFrom) {
            $ordersQuery->where('created_at', '>=', $dateFrom);
        }
        if ($dateTo) {
            $ordersQuery->where('created_at', '<=', $dateTo);
        }

        $orders = $ordersQuery->get();

        // Tổng hợp số lượng và doanh thu theo từng sản phẩm
        $productStats = [];
        foreach ($orders as $order) {
            foreach ($order->items ?? [] as $item) {
                $productId = $item['product_id'];
                if (!isset($productStats[$productId])) {
                    $productStats[$productId] = [
                        'product_id'    => $productId,
                        'name'          => $item['name'],
                        'image'         => $item['image'] ?? null,
                        'slug'          => $item['slug'] ?? null,
                        'total_sold'    => 0,
                        'total_revenue' => 0,
                    ];
                }
                $productStats[$productId]['total_sold']    += $item['quantity'];
                $productStats[$productId]['total_revenue'] += $item['subtotal'];
            }
        }

        // Sắp xếp theo số lượng bán và lấy top
        usort($productStats, fn($a, $b) => $b['total_sold'] - $a['total_sold']);

        return array_slice(array_values($productStats), 0, $limit);
    }

    /**
     * Lấy doanh thu theo tháng trong N tháng gần nhất
     */
    private function getMonthlyRevenue(int $months = 12): array
    {
        $result = [];
        $now    = now();

        for ($i = $months - 1; $i >= 0; $i--) {
            $date    = $now->copy()->subMonths($i);
            $from    = $date->startOfMonth()->toDateTimeString();
            $to      = $date->endOfMonth()->toDateTimeString();
            $label   = $date->format('m/Y'); // Ví dụ: 01/2025

            $revenue = Order::where('status', Order::STATUS_DELIVERED)
                            ->where('created_at', '>=', $from)
                            ->where('created_at', '<=', $to)
                            ->get()
                            ->sum('final_amount');

            $orderCount = Order::where('created_at', '>=', $from)
                               ->where('created_at', '<=', $to)
                               ->count();

            $result[] = [
                'month'       => $label,
                'revenue'     => $revenue,
                'order_count' => $orderCount,
            ];
        }

        return $result;
    }
}
