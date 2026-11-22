<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Order;
use Illuminate\Contracts\View\View;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Pagination\Paginator;

class CustomerController extends Controller
{
    public function index(): View
    {
        $customerCollection = Order::query()->latest()->get()->groupBy('customer_phone')->map(fn ($orders) => $orders->first())->values();
        $currentPage = Paginator::resolveCurrentPage();
        $customers = new LengthAwarePaginator(
            $customerCollection->forPage($currentPage, 30)->values(),
            $customerCollection->count(),
            30,
            $currentPage,
            ['path' => Paginator::resolveCurrentPath(), 'query' => request()->query()],
        );

        return view('admin.customers.index', compact('customers'));
    }
}
