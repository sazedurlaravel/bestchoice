<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\OrderReturn;
use App\Services\Orders\OrderLifecycleService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class OrderReturnController extends Controller
{
    public function store(Request $request, Order $order, OrderLifecycleService $lifecycle): RedirectResponse
    {
        $data = $request->validate([
            'order_item_id' => ['required', 'integer'],
            'quantity' => ['required', 'integer', 'min:1'],
            'reason' => ['required', 'string', 'max:1000'],
            'operation_key' => ['required', 'uuid'],
        ]);
        $item = $order->items()->findOrFail($data['order_item_id']);
        $lifecycle->requestReturn($order, $item, $data['quantity'], $data['reason'], $data['operation_key'], $request->user());

        return back()->with('success', 'রিটার্ন অনুরোধ সংরক্ষিত হয়েছে।');
    }

    public function update(Request $request, Order $order, OrderReturn $orderReturn, OrderLifecycleService $lifecycle): RedirectResponse
    {
        abort_unless($orderReturn->order_id === $order->id, 404);
        $data = $request->validate([
            'status' => ['required', 'in:approved,rejected,received,restocked,completed'],
            'note' => ['nullable', 'string', 'max:1000'],
        ]);
        $lifecycle->transitionReturn($orderReturn, $data['status'], $data['note'] ?? null, $request->user());

        return back()->with('success', 'রিটার্ন স্ট্যাটাস আপডেট হয়েছে।');
    }
}
