<div class="variant-picker" id="variantPicker" hidden aria-hidden="true">
    <div class="variant-picker-backdrop" data-variant-picker-close></div>
    <section class="variant-picker-dialog" role="dialog" aria-modal="true" aria-labelledby="variantPickerTitle">
        <button type="button" class="variant-picker-close" data-variant-picker-close aria-label="বন্ধ করুন">&times;</button>
        <div class="variant-picker-price-row">
            <div class="variant-picker-price" id="variantPickerPrice">৳0</div>
            <del class="variant-picker-regular-price" id="variantPickerRegularPrice" hidden>৳0</del>
        </div>
        <h2 id="variantPickerTitle">Size</h2>
        <div class="variant-picker-options" id="variantPickerOptions"></div>
        <p class="variant-picker-error" id="variantPickerError" role="alert" hidden>একটি ভ্যারিয়েন্ট বেছে নিন।</p>
        <form method="POST" action="{{ route('cart.add') }}" class="variant-picker-form" id="variantPickerForm">
            @csrf
            <input type="hidden" name="product_id" id="variantPickerProductId">
            <input type="hidden" name="variant_id" id="variantPickerVariantId">
            <input type="hidden" name="qty" value="1">
            <input type="hidden" name="cart_request_id" value="{{ \Illuminate\Support\Str::uuid() }}">
            <button type="submit" class="bc-buy-now variant-picker-submit">অর্ডার করুন</button>
        </form>
    </section>
</div>
