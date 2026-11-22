<div class="cart-drawer-backdrop" id="cartDrawerBackdrop" hidden></div>
<aside aria-label="শপিং কার্ট" aria-hidden="true" class="cart-drawer" id="cartDrawer">
    <div class="cart-drawer-header">
        <div>
            <span class="cart-drawer-kicker">আপনার কেনাকাটা</span>
            <h2>শপিং কার্ট</h2>
        </div>
        <button aria-label="কার্ট বন্ধ করুন" class="cart-drawer-close" data-cart-drawer-close type="button">
            <i class="fa-solid fa-xmark" aria-hidden="true"></i>
        </button>
    </div>

    @if(empty($cartItems))
        <div class="cart-drawer-empty">
            <i class="fa-solid fa-cart-shopping" aria-hidden="true"></i>
            <p>আপনার কার্ট এখন খালি।</p>
            <a class="primary" href="{{ route('home') }}">কেনাকাটা শুরু করুন</a>
        </div>
    @else
        <div class="cart-drawer-items">
            @foreach($cartItems as $item)
                <article class="cart-drawer-item">
                    <img src="{{ $item['image'] ?? '' }}" alt="{{ $item['name'] ?? 'কার্টের পণ্য' }}">
                    <div class="cart-drawer-item-info">
                        <h3>{{ $item['name'] ?? 'কার্টের পণ্য' }}</h3>
                        @if(!empty($item['variant_name']))
                            <p>{{ $item['variant_name'] }}</p>
                        @endif
                        <span>৳{{ number_format($item['price'] ?? 0, 0) }} প্রতি পিস</span>
                        <div class="cart-drawer-item-actions">
                            <form method="POST" action="{{ route('cart.update') }}">
                                @csrf
                                <input type="hidden" name="product_id" value="{{ $item['product_id'] ?? 0 }}">
                                @if(isset($item['variant_id']))<input type="hidden" name="variant_id" value="{{ $item['variant_id'] }}">@endif
                                <input type="hidden" name="drawer" value="1">
                                <button aria-label="পরিমাণ কমান" name="qty" value="{{ max(0, ($item['qty'] ?? 0) - 1) }}" type="submit">−</button>
                                <b>{{ $item['qty'] ?? 0 }}</b>
                                <button aria-label="পরিমাণ বাড়ান" name="qty" value="{{ ($item['qty'] ?? 0) + 1 }}" type="submit">+</button>
                            </form>
                            <form method="POST" action="{{ route('cart.remove') }}">
                                @csrf
                                <input type="hidden" name="product_id" value="{{ $item['product_id'] ?? 0 }}">
                                @if(isset($item['variant_id']))<input type="hidden" name="variant_id" value="{{ $item['variant_id'] }}">@endif
                                <input type="hidden" name="drawer" value="1">
                                <button aria-label="কার্ট থেকে সরান" type="submit"><i class="fa-solid fa-trash-can" aria-hidden="true"></i></button>
                            </form>
                        </div>
                    </div>
                    <strong>৳{{ number_format(($item['price'] ?? 0) * ($item['qty'] ?? 0), 0) }}</strong>
                </article>
            @endforeach
        </div>

        <div class="cart-drawer-footer">
            <div class="cart-drawer-subtotal"><span>সাবটোটাল</span><strong>৳{{ number_format($cartSubtotal, 0) }}</strong></div>
            <a class="primary cart-drawer-checkout" href="{{ route('checkout.index') }}">চেকআউট করুন <i class="fa-solid fa-arrow-right" aria-hidden="true"></i></a>
            <button class="cart-drawer-view" data-cart-drawer-close type="button">কেনাকাটা চালিয়ে যান</button>
        </div>
    @endif
</aside>
