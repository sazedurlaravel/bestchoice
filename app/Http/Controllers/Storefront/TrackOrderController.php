<?php

namespace App\Http\Controllers\Storefront;

use App\Http\Controllers\Controller;
use App\Models\Order;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

class TrackOrderController extends Controller
{
    public function __invoke(Request $request): View
    {
        $validated = $request->validate(['order_number' => ['nullable', 'string', 'max:30']]);
        $order = filled($validated['order_number'] ?? null)
            ? Order::query()->where('order_number', $validated['order_number'])->first()
            : null;

        return view('storefront.track', compact('order'));
    }
}
