<!DOCTYPE html>
<html lang="bn">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ইনভয়েস — {{ $order->order_number }}</title>
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body { font-family: 'Arial', sans-serif; font-size: 13px; color: #111; background: #fff; padding: 24px; }
        .invoice-wrap { max-width: 680px; margin: 0 auto; }

        /* Header */
        .inv-header { display: flex; justify-content: space-between; align-items: flex-start; border-bottom: 2px solid #111; padding-bottom: 14px; margin-bottom: 14px; }
        .inv-logo { font-size: 20px; font-weight: 900; letter-spacing: -0.5px; }
        .inv-logo small { display: block; font-size: 11px; font-weight: 400; color: #555; margin-top: 2px; }
        .inv-meta { text-align: right; }
        .inv-meta .order-no { font-size: 18px; font-weight: 800; }
        .inv-meta small { color: #555; }

        /* Two-col info */
        .inv-parties { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-bottom: 14px; }
        .inv-party h4 { font-size: 10px; text-transform: uppercase; letter-spacing: 1px; color: #888; margin-bottom: 6px; }
        .inv-party p { margin-bottom: 3px; }

        /* Items table */
        table { width: 100%; border-collapse: collapse; margin-bottom: 14px; }
        thead th { background: #111; color: #fff; padding: 7px 10px; text-align: left; font-size: 11px; }
        tbody td { padding: 7px 10px; border-bottom: 1px solid #e5e5e5; }
        tbody tr:last-child td { border-bottom: none; }
        .text-right { text-align: right; }

        /* Totals */
        .totals { margin-left: auto; width: 240px; }
        .totals tr td { padding: 4px 0; }
        .totals tr.grand td { font-weight: 800; font-size: 15px; border-top: 2px solid #111; padding-top: 8px; margin-top: 4px; }

        /* Footer */
        .inv-footer { border-top: 1px dashed #bbb; margin-top: 20px; padding-top: 12px; text-align: center; color: #888; font-size: 11px; }

        /* Status badge */
        .badge { display: inline-block; padding: 2px 10px; border-radius: 20px; font-size: 11px; font-weight: 700; border: 1px solid #ccc; }

        /* Print styles */
        @media print {
            body { padding: 0; }
            .no-print { display: none !important; }
            @page { margin: 15mm; }
        }
    </style>
</head>
<body>
@php
    $settings = \App\Models\StoreSetting::query()->pluck('value', 'key')->all();
    $storeName = $settings['store_name'] ?? 'Best Choice Online Shopping';
    $storePhone = $settings['phone'] ?? '';
    $storeAddress = $settings['store_address'] ?? '';
    $storeEmail = $settings['store_email'] ?? '';
@endphp

<div class="invoice-wrap">

    {{-- Print / Close buttons --}}
    <div class="no-print" style="text-align:right;margin-bottom:16px;display:flex;gap:10px;justify-content:flex-end">
        <button onclick="window.print()" style="padding:8px 20px;background:#111;color:#fff;border:none;border-radius:6px;cursor:pointer;font-size:13px">
            🖨️ প্রিন্ট করুন
        </button>
        <a href="{{ route('admin.orders.show', $order) }}" style="padding:8px 20px;background:#f5f5f5;color:#111;border:1px solid #ddd;border-radius:6px;text-decoration:none;font-size:13px">
            ← ফিরে যান
        </a>
    </div>

    <div class="inv-header">
        <div class="inv-logo">
            {{ $storeName }}
            @if($storePhone)
                <small>📞 {{ $storePhone }}</small>
            @endif
            @if($storeAddress)
                <small>📍 {{ $storeAddress }}</small>
            @endif
        </div>
        <div class="inv-meta">
            <div class="order-no">{{ $order->order_number }}</div>
            <small>তারিখ: {{ $order->created_at->format('d M Y, h:i A') }}</small><br>
            <small>পেমেন্ট: {{ $order->payment_method === 'cash_on_delivery' ? 'ক্যাশ অন ডেলিভারি' : 'অনলাইন পেমেন্ট' }}</small>
        </div>
    </div>

    <div class="inv-parties">
        <div class="inv-party">
            <h4>কাস্টমারের তথ্য</h4>
            <p><b>{{ $order->customer_name }}</b></p>
            <p>📞 {{ $order->customer_phone }}
                @if($order->customer_additional_phone) / {{ $order->customer_additional_phone }}@endif
            </p>
            <p>📍 {{ $order->customer_address }}</p>
            @if($order->customer_district)
                <p>জেলা: {{ $order->customer_district }}</p>
            @endif
        </div>
        <div class="inv-party" style="text-align:right">
            <h4>অর্ডার তথ্য</h4>
            <p>স্ট্যাটাস: <span class="badge">{{ $order->status_label }}</span></p>
            @if($order->note)
                <p style="margin-top:6px;color:#555;font-size:12px">নোট: {{ $order->note }}</p>
            @endif
        </div>
    </div>

    <table>
        <thead>
            <tr>
                <th>#</th>
                <th>পণ্য</th>
                <th>SKU</th>
                <th class="text-right">একক মূল্য</th>
                <th class="text-right">পরিমাণ</th>
                <th class="text-right">সাবটোটাল</th>
            </tr>
        </thead>
        <tbody>
            @foreach($order->items as $i => $item)
                <tr>
                    <td>{{ $i + 1 }}</td>
                    <td>
                        <b>{{ $item->product_name }}</b>
                        @if($item->variant_name)
                            <div style="font-size:11px;color:#666">{{ $item->variant_name }}</div>
                        @endif
                    </td>
                    <td><code style="font-size:11px">{{ $item->product_sku }}</code></td>
                    <td class="text-right">৳{{ number_format($item->unit_price, 0) }}</td>
                    <td class="text-right">{{ $item->quantity }}</td>
                    <td class="text-right"><b>৳{{ number_format($item->subtotal, 0) }}</b></td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <table class="totals">
        <tr><td>সাবটোটাল</td><td class="text-right">৳{{ number_format($order->subtotal, 0) }}</td></tr>
        @if($order->discount_amount > 0)
            <tr><td>ডিসকাউন্ট</td><td class="text-right" style="color:#dc2626">−৳{{ number_format($order->discount_amount, 0) }}</td></tr>
        @endif
        <tr><td>শিপিং চার্জ</td><td class="text-right">৳{{ number_format($order->shipping_charge, 0) }}</td></tr>
        <tr class="grand"><td>মোট</td><td class="text-right">৳{{ number_format($order->total, 0) }}</td></tr>
    </table>

    <div class="inv-footer">
        <p>ধন্যবাদ আমাদের সাথে কেনাকাটার জন্য!</p>
        @if($storeEmail)<p style="margin-top:4px">{{ $storeEmail }}</p>@endif
        <p style="margin-top:4px">{{ $storeName }}</p>
    </div>

</div>

<script>
    // Auto-open print dialog on load (optional — remove if unwanted)
    // window.addEventListener('load', () => window.print());
</script>
</body>
</html>
