<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\OrderCancellationRequest;
use App\Models\OrderRefusal;
use App\Models\Shipment;
use App\Services\Courier\SteadfastService;
use App\Services\Orders\FraudScoreService;
use App\Services\Orders\OrderLifecycleService;
use App\Services\Orders\OrderReviewService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class OrderController extends Controller
{
    public function index(Request $request): View
    {
        $query = Order::with([
            'items.product',
            'shipments:id,order_id,courier,booking_state,local_status,tracking_code',
        ])->latest();

        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }

        if ($request->filled('date_from')) {
            $query->whereDate('created_at', '>=', $request->input('date_from'));
        }

        if ($request->filled('date_to')) {
            $query->whereDate('created_at', '<=', $request->input('date_to'));
        }

        if ($request->filled('sale_type')) {
            $query->where('payment_method', $request->input('sale_type'));
        }

        if ($request->filled('courier')) {
            $query->whereHas('shipments', fn ($shipmentQuery) => $shipmentQuery->where('courier', $request->input('courier')));
        }

        if ($search = $request->input('q')) {
            $query->where(fn ($q) => $q->where('order_number', 'like', "%{$search}%")
                ->orWhere('customer_name', 'like', "%{$search}%")
                ->orWhere('customer_phone', 'like', "%{$search}%"));
        }

        $orders = $query->paginate(25)->withQueryString();
        $statusCounts = Order::query()->selectRaw('status, count(*) as count')->groupBy('status')->pluck('count', 'status');
        $couriers = Shipment::query()->whereNotNull('courier')->distinct()->orderBy('courier')->pluck('courier');
        $fraudScores = app(FraudScoreService::class)->scoreMany($orders->getCollection());

        return view('admin.orders.index', compact('orders', 'statusCounts', 'couriers', 'fraudScores'));
    }

    public function show(Order $order): View
    {
        $order->load(['items.product', 'items.variant', 'returns', 'payments', 'statusHistories.user', 'shipments.statusHistories']);
        $cancellationRequest = OrderCancellationRequest::query()->where('order_id', $order->id)->latest()->first();

        return view('admin.orders.show', compact('order', 'cancellationRequest'));
    }

    public function updateStatus(Request $request, Order $order): RedirectResponse
    {
        $data = $request->validate([
            'status' => ['required', 'in:pending,processing,shipped,delivered,cancelled'],
            'reason' => ['nullable', 'string', 'max:1000'],
        ]);

        app(OrderLifecycleService::class)->transition($order, $data['status'], $data['reason'] ?? null, $request->user());

        return back()->with('success', 'অর্ডার স্ট্যাটাস আপডেট হয়েছে।');
    }

    public function bulkUpdate(Request $request, SteadfastService $steadfast): RedirectResponse
    {
        $data = $request->validate([
            'order_ids' => ['required', 'array', 'min:1'],
            'order_ids.*' => ['integer', 'exists:orders,id'],
            'action' => ['required', 'in:status,assign,note,courier'],
            'status' => ['nullable', 'in:pending,processing,shipped,delivered,cancelled'],
            'assigned_to' => ['nullable', 'integer', 'exists:users,id'],
            'admin_note' => ['nullable', 'string', 'max:1000'],
            'courier' => ['nullable', 'in:steadfast'],
        ]);

        $orders = Order::query()->whereIn('id', $data['order_ids'])->get();
        $courierFailures = [];
        $courierBooked = 0;

        foreach ($orders as $order) {
            if ($data['action'] === 'status' && ! empty($data['status'])) {
                app(OrderLifecycleService::class)->transition($order, $data['status'], null, $request->user());
            } elseif ($data['action'] === 'assign') {
                $order->update(['assigned_to' => $data['assigned_to'] ?: null]);
            } elseif ($data['action'] === 'note' && ! empty($data['admin_note'])) {
                $order->update(['admin_note' => $data['admin_note']]);
            } elseif ($data['action'] === 'courier') {
                try {
                    if (in_array($order->review_status, ['needs_confirmation', 'needs_correction'], true)) {
                        throw new \RuntimeException('COD review is not complete.');
                    }

                    $shipment = $steadfast->book($order);
                    if ($shipment->booking_state === 'booked') {
                        $courierBooked++;
                    } else {
                        $courierFailures[] = $order->order_number.': '.($shipment->failure_reason ?? 'Booking needs reconciliation.');
                    }
                } catch (\Illuminate\Validation\ValidationException $exception) {
                    $courierFailures[] = $order->order_number.': '.(collect($exception->errors())->flatten()->first() ?? 'Courier booking was rejected.');
                } catch (\Throwable $exception) {
                    report($exception);
                    $courierFailures[] = $order->order_number.': Courier booking failed.';
                }
            }
        }

        if ($data['action'] === 'courier') {
            $message = $courierBooked.' shipment(s) sent to Steadfast.';
            if ($courierFailures) {
                $message .= ' Failed: '.implode(' | ', $courierFailures);
            }

            return back()->with($courierBooked > 0 ? 'success' : 'error', $message);
        }

        return back()->with('success', count($data['order_ids']).' টি অর্ডার আপডেট হয়েছে।');
    }

    public function updateNote(Request $request, Order $order): RedirectResponse
    {
        $data = $request->validate(['admin_note' => ['nullable', 'string', 'max:1000']]);
        $order->update(['admin_note' => $data['admin_note']]);

        return back()->with('success', 'নোট সংরক্ষিত হয়েছে।');
    }

    public function updateAssignment(Request $request, Order $order): RedirectResponse
    {
        $data = $request->validate(['assigned_to' => ['nullable', 'integer', 'exists:users,id']]);
        $order->update(['assigned_to' => $data['assigned_to'] ?: null]);

        return back()->with('success', 'অ্যাসাইনমেন্ট আপডেট হয়েছে।');
    }

    public function printInvoice(Order $order): View
    {
        $order->load(['items.product', 'items.variant']);

        return view('admin.orders.print', compact('order'));
    }

    public function updateCancellationRequest(Request $request, Order $order, OrderCancellationRequest $cancellationRequest): RedirectResponse
    {
        abort_unless($cancellationRequest->order_id === $order->id, 404);
        $data = $request->validate(['status' => ['required', 'in:approved,rejected'], 'note' => ['nullable', 'string', 'max:1000']]);
        abort_unless($cancellationRequest->status === 'requested', 422);
        DB::transaction(function () use ($order, $cancellationRequest, $data, $request): void {
            if ($data['status'] === 'approved') {
                app(OrderLifecycleService::class)->transition($order, 'cancelled', $data['note'] ?: $cancellationRequest->reason, $request->user());
            }
            $cancellationRequest->update(['status' => $data['status']]);
        }, 3);

        return back()->with('success', 'কাস্টমারের বাতিল অনুরোধ পর্যালোচনা করা হয়েছে।');
    }

    public function confirmReview(Request $request, Order $order): RedirectResponse
    {
        $data = $request->validate(['note' => ['nullable', 'string', 'max:1000']]);

        $order->update([
            'review_status' => 'ready',
            'confirmed_by' => $request->user()->id,
            'confirmed_at' => now(),
            'confirmation_note' => $data['note'] ?? null,
        ]);

        return back()->with('success', 'অর্ডারটি নিশ্চিত করা হয়েছে এবং শিপমেন্টের জন্য প্রস্তুত।');
    }

    public function rerunReview(Order $order): RedirectResponse
    {
        app(OrderReviewService::class)->resetAndReapply($order);

        return back()->with('success', 'অর্ডার রিভিউ নতুনভাবে চালানো হয়েছে।');
    }

    public function markRefusal(Request $request, Order $order): RedirectResponse
    {
        $data = $request->validate(['reason' => ['nullable', 'string', 'max:500']]);

        $phone = app(OrderReviewService::class)->normalizePhone($order->customer_phone);

        OrderRefusal::create([
            'customer_phone' => $phone,
            'order_id' => $order->id,
            'reason' => $data['reason'] ?? null,
        ]);

        // Re-run review so new refusal count is reflected
        app(OrderReviewService::class)->resetAndReapply($order);

        return back()->with('success', 'ডেলিভারি রিফিউজাল রেকর্ড করা হয়েছে।');
    }
}
