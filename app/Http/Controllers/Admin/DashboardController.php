<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\User;
use App\Services\Inventory\InventoryService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class DashboardController extends Controller
{
    public function __invoke(Request $request, InventoryService $inventory): View
    {
        $sales = Order::query()->whereNotIn('status', ['cancelled']);
        $now = now();
        $profitPeriod = $request->validate([
            'profit_period' => ['nullable', 'in:daily,weekly,monthly,yearly,lifetime'],
        ])['profit_period'] ?? 'daily';
        $profitPeriodStart = match ($profitPeriod) {
            'weekly' => $now->copy()->startOfWeek(),
            'monthly' => $now->copy()->startOfMonth(),
            'yearly' => $now->copy()->startOfYear(),
            'lifetime' => null,
            default => $now->copy()->startOfDay(),
        };
        $profitPeriodLabels = [
            'daily' => 'আজকের লাভ',
            'weekly' => 'এই সপ্তাহের লাভ',
            'monthly' => 'এই মাসের লাভ',
            'yearly' => 'এই বছরের লাভ',
            'lifetime' => 'সর্বকালের লাভ',
        ];

        return view('admin.dashboard', [
            'totalOrders' => Order::count(),
            'pendingOrders' => Order::where('status', 'pending')->count(),
            'productCount' => Product::count(),
            'activeProductCount' => Product::active()->count(),
            'categoryCount' => Category::count(),
            'customerCount' => User::query()->where('role', 'customer')->count(),
            'salesTotal' => (clone $sales)->sum('total'),
            'todaySales' => (clone $sales)->whereDate('created_at', today())->sum('total'),
            'monthSales' => (clone $sales)->whereYear('created_at', now()->year)->whereMonth('created_at', now()->month)->sum('total'),
            'todayProfit' => $this->profitSince($now->copy()->startOfDay(), $now),
            'weekProfit' => $this->profitSince($now->copy()->startOfWeek(), $now),
            'monthProfit' => $this->profitSince($now->copy()->startOfMonth(), $now),
            'filteredProfit' => $this->profitSince($profitPeriodStart, $now),
            'profitPeriod' => $profitPeriod,
            'profitPeriodLabel' => $profitPeriodLabels[$profitPeriod],
            'statusCounts' => Order::query()->selectRaw('status, count(*) as count')->groupBy('status')->pluck('count', 'status'),
            'recentOrders' => Order::query()->withCount('items')->with('items.product')->latest()->limit(8)->get(),
            'lowStockProducts' => $inventory->lowStockProducts(),
            'lowStockVariants' => $inventory->lowStockVariants(),
        ]);
    }

    private function profitSince(?Carbon $start, Carbon $end): float
    {
        return (float) OrderItem::query()
            ->join('orders', 'orders.id', '=', 'order_items.order_id')
            ->join('products', 'products.id', '=', 'order_items.product_id')
            ->whereNotIn('orders.status', ['cancelled'])
            ->where(function ($query): void {
                $query->whereNotNull('order_items.buying_price')
                    ->orWhereNotNull('products.buying_price');
            })
            ->when($start !== null, fn ($query) => $query->where('orders.created_at', '>=', $start))
            ->where('orders.created_at', '<=', $end)
            ->selectRaw('COALESCE(SUM(order_items.subtotal - (order_items.quantity * COALESCE(order_items.buying_price, products.buying_price, 0))), 0) AS profit')
            ->value('profit');
    }
}
