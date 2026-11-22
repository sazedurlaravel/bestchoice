@extends('layouts.admin')
@section('title', 'ড্যাশবোর্ড')
@section('content')
<div class="pagehead">
    <div><h1>ড্যাশবোর্ড</h1><div class="sub">Best Choice Online Shopping-এর বর্তমান সারসংক্ষেপ</div></div>
    <a class="btn" href="{{ route('admin.orders') }}"><i class="fa-solid fa-bag-shopping"></i> অর্ডার দেখুন</a>
</div>

<section class="panel" style="margin-bottom:20px">
    <div class="panelhead"><h3>লাভ রিপোর্ট</h3></div>
    <form method="GET" action="{{ route('admin.dashboard') }}" class="filter-form">
        <label>সময়কাল
            <select name="profit_period" class="form-input">
                <option value="daily" @selected($profitPeriod === 'daily')>দৈনিক</option>
                <option value="weekly" @selected($profitPeriod === 'weekly')>সাপ্তাহিক</option>
                <option value="monthly" @selected($profitPeriod === 'monthly')>মাসিক</option>
                <option value="yearly" @selected($profitPeriod === 'yearly')>বার্ষিক</option>
                <option value="lifetime" @selected($profitPeriod === 'lifetime')>সর্বকাল</option>
            </select>
        </label>
        <button class="btn" type="submit"><i class="fa-solid fa-filter"></i> রিপোর্ট দেখুন</button>
    </form>
    <div class="dashboard-summary-row" style="margin:0 20px 20px"><span>{{ $profitPeriodLabel }}</span><b>৳{{ number_format((float) $filteredProfit, 0) }}</b></div>
</section>

<div class="row">
    <div class="col-lg-4 col-12"><div class="small-box bg-success"><div class="inner"><h3>৳{{ number_format((float) $todayProfit, 0) }}</h3><p>আজকের লাভ</p></div><div class="icon"><i class="fa-solid fa-chart-line"></i></div><span class="small-box-footer">ক্রয়মূল্য বাদ দিয়ে</span></div></div>
    <div class="col-lg-4 col-12"><div class="small-box bg-success"><div class="inner"><h3>৳{{ number_format((float) $weekProfit, 0) }}</h3><p>এই সপ্তাহের লাভ</p></div><div class="icon"><i class="fa-solid fa-calendar-week"></i></div><span class="small-box-footer">ক্রয়মূল্য বাদ দিয়ে</span></div></div>
    <div class="col-lg-4 col-12"><div class="small-box bg-success"><div class="inner"><h3>৳{{ number_format((float) $monthProfit, 0) }}</h3><p>এই মাসের লাভ</p></div><div class="icon"><i class="fa-solid fa-calendar-days"></i></div><span class="small-box-footer">ক্রয়মূল্য বাদ দিয়ে</span></div></div>
</div>

<div class="row">
    <div class="col-lg-3 col-6"><div class="small-box bg-success"><div class="inner"><h3>{{ number_format($salesTotal, 0) }}<sup class="dashboard-currency">৳</sup></h3><p>মোট বিক্রয়</p></div><div class="icon"><i class="fa-solid fa-sack-dollar"></i></div><a href="{{ route('admin.orders') }}" class="small-box-footer">অর্ডার তালিকা <i class="fa-solid fa-arrow-circle-right"></i></a></div></div>
    <div class="col-lg-3 col-6"><div class="small-box bg-warning"><div class="inner"><h3>{{ number_format($pendingOrders) }}</h3><p>অপেক্ষমাণ অর্ডার</p></div><div class="icon"><i class="fa-solid fa-clock"></i></div><a href="{{ route('admin.orders', ['status' => 'pending']) }}" class="small-box-footer">অর্ডার দেখুন <i class="fa-solid fa-arrow-circle-right"></i></a></div></div>
    <div class="col-lg-3 col-6"><div class="small-box bg-info"><div class="inner"><h3>{{ number_format($productCount) }}</h3><p>মোট পণ্য</p></div><div class="icon"><i class="fa-solid fa-box-open"></i></div><a href="{{ route('admin.products') }}" class="small-box-footer">পণ্য পরিচালনা <i class="fa-solid fa-arrow-circle-right"></i></a></div></div>
    <div class="col-lg-3 col-6"><div class="small-box bg-danger"><div class="inner"><h3>{{ number_format($customerCount) }}</h3><p>কাস্টমার</p></div><div class="icon"><i class="fa-solid fa-users"></i></div><a href="{{ route('admin.customers') }}" class="small-box-footer">কাস্টমার দেখুন <i class="fa-solid fa-arrow-circle-right"></i></a></div></div>
</div>

<div class="row">
    <div class="col-lg-8">
        <section class="panel">
            <div class="panelhead"><h3>সাম্প্রতিক অর্ডার</h3><a href="{{ route('admin.orders') }}">সব অর্ডার →</a></div>
            <div class="tablewrap"><table class="table">
                <thead><tr><th>অর্ডার</th><th>কাস্টমার</th><th>পণ্য</th><th>আইটেম</th><th>মোট</th><th>স্ট্যাটাস</th><th>তারিখ</th><th></th></tr></thead>
                <tbody>
                @forelse($recentOrders as $order)
                    @php($statusClasses = ['pending' => 'pending', 'processing' => 'processing', 'shipped' => 'info', 'delivered' => 'active', 'cancelled' => 'inactive'])
                    <tr>
                        <td><code>{{ $order->order_number }}</code></td>
                        <td><b>{{ $order->customer_name }}</b><div class="muted small">{{ $order->customer_phone }}</div></td>
                        <td>
                            @foreach($order->items as $item)
                                @if($item->product?->main_image)
                                    <img src="{{ $item->product->main_image }}" alt="{{ $item->product_name }}" title="{{ $item->product_name }}" loading="lazy" style="width:44px;height:44px;object-fit:cover;border-radius:6px;border:1px solid #e5e9e6">
                                    @break
                                @endif
                            @endforeach
                        </td>
                        <td>{{ $order->items_count }}</td>
                        <td><b>৳{{ number_format((float) $order->total, 0) }}</b></td>
                        <td><span class="status {{ $statusClasses[$order->status] ?? '' }}">{{ $order->status_label }}</span></td>
                        <td>{{ $order->created_at->format('d M Y') }}</td>
                        <td><a href="{{ route('admin.orders.show', $order) }}" class="iconbtn" title="বিস্তারিত"><i class="fa-solid fa-eye"></i></a></td>
                    </tr>
                @empty
                    <tr><td colspan="8" class="empty">এখনও কোনো অর্ডার নেই।</td></tr>
                @endforelse
                </tbody>
            </table></div>
        </section>
    </div>

    <div class="col-lg-4">
        <section class="panel dashboard-summary-card">
            <div class="panelhead"><h3>স্টোর সারসংক্ষেপ</h3></div>
            <div class="dashboard-summary-row"><span>আজকের বিক্রয়</span><b>৳{{ number_format((float) $todaySales, 0) }}</b></div>
            <div class="dashboard-summary-row"><span>এই মাসের বিক্রয়</span><b>৳{{ number_format((float) $monthSales, 0) }}</b></div>
            <div class="dashboard-summary-row"><span>আজকের লাভ</span><b>৳{{ number_format((float) $todayProfit, 0) }}</b></div>
            <div class="dashboard-summary-row"><span>এই সপ্তাহের লাভ</span><b>৳{{ number_format((float) $weekProfit, 0) }}</b></div>
            <div class="dashboard-summary-row"><span>এই মাসের লাভ</span><b>৳{{ number_format((float) $monthProfit, 0) }}</b></div>
            <small class="muted" style="display:block;margin:10px 0">ক্রয়মূল্য না দেওয়া পণ্য লাভের হিসাবে ধরা হয়নি।</small>
            <div class="dashboard-summary-row"><span>মোট অর্ডার</span><b>{{ number_format($totalOrders) }}</b></div>
            <div class="dashboard-summary-row"><span>সক্রিয় পণ্য</span><b>{{ number_format($activeProductCount) }}</b></div>
            <div class="dashboard-summary-row"><span>ক্যাটেগরি</span><b>{{ number_format($categoryCount) }}</b></div>
            <div class="dashboard-summary-row"><span>কম স্টকের পণ্য</span><b>{{ $lowStockProducts->count() + $lowStockVariants->count() }}</b></div>
        </section>

        <section class="panel dashboard-status-card">
            <div class="panelhead"><h3>অর্ডার স্ট্যাটাস</h3></div>
            @foreach(['pending' => 'অপেক্ষমাণ', 'processing' => 'প্রক্রিয়াকরণ', 'shipped' => 'পাঠানো হয়েছে', 'delivered' => 'ডেলিভারি হয়েছে', 'cancelled' => 'বাতিল'] as $key => $label)
                <div class="dashboard-summary-row"><span>{{ $label }}</span><b>{{ number_format($statusCounts[$key] ?? 0) }}</b></div>
            @endforeach
        </section>
    </div>
</div>

<section class="panel" style="margin-top:20px">
    <div class="panelhead"><h3><i class="fa-solid fa-triangle-exclamation" style="color:#d9903b"></i> কম স্টকের পণ্য</h3><a href="{{ route('admin.inventory') }}">স্টক ব্যবস্থাপনা →</a></div>
    <div class="tablewrap"><table class="table">
        <thead><tr><th>পণ্য</th><th>SKU / ভ্যারিয়েন্ট</th><th>বর্তমান স্টক</th><th>সীমা</th></tr></thead>
        <tbody>
        @foreach($lowStockProducts as $product)
            <tr><td><b>{{ $product->name }}</b></td><td>{{ $product->sku }}</td><td><span class="status pending">{{ $product->stock_quantity }}</span></td><td>{{ $product->low_stock_threshold }}</td></tr>
        @endforeach
        @foreach($lowStockVariants as $variant)
            <tr><td><b>{{ $variant->product->name }}</b></td><td>{{ $variant->sku }} · {{ $variant->name }}</td><td><span class="status pending">{{ $variant->stock_quantity }}</span></td><td>{{ $variant->product->low_stock_threshold }}</td></tr>
        @endforeach
        @if($lowStockProducts->isEmpty() && $lowStockVariants->isEmpty())
            <tr><td colspan="4" class="empty">All product stock levels are normal.</td></tr>
        @endif
        </tbody>
    </table></div>
</section>
@endsection
