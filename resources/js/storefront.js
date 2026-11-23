document.querySelector('#mobileSearchToggle')?.addEventListener('click', () => {
    const search = document.querySelector('.search');
    const toggle = document.querySelector('#mobileSearchToggle');
    const isOpen = search?.classList.toggle('mobile-search-open') ?? false;

    toggle?.setAttribute('aria-expanded', String(isOpen));
    if (isOpen) search?.querySelector('input')?.focus();
});

const cartDrawer = document.querySelector('#cartDrawer');
const cartDrawerBackdrop = document.querySelector('#cartDrawerBackdrop');
const setCartDrawerOpen = (open) => {
    if (! cartDrawer || ! cartDrawerBackdrop) return;
    cartDrawer.classList.toggle('open', open);
    cartDrawerBackdrop.hidden = ! open;
    cartDrawer.setAttribute('aria-hidden', String(! open));
    document.body.classList.toggle('cart-drawer-open', open);
};

document.querySelectorAll('[data-cart-drawer-open]').forEach((trigger) => {
    trigger.addEventListener('click', (event) => {
        event.preventDefault();
        if (document.querySelector('#mobileMenu')?.classList.contains('open')) {
            closeMobileMenu();
        }
        setCartDrawerOpen(true);
    });
});
document.querySelectorAll('[data-cart-drawer-close]').forEach((trigger) => trigger.addEventListener('click', () => setCartDrawerOpen(false)));
cartDrawerBackdrop?.addEventListener('click', () => setCartDrawerOpen(false));
document.addEventListener('keydown', (event) => {
    if (event.key === 'Escape') setCartDrawerOpen(false);
});
if (cartDrawer?.dataset.openOnLoad === 'true') setCartDrawerOpen(true);

document.querySelectorAll('.card-cart-form, .detail-purchase-form').forEach((form) => {
    if (form.querySelector('input[name="redirect_to"]')) return;
    form.addEventListener('submit', async (event) => {
        event.preventDefault();
        if (form.dataset.cartSubmitting === 'true') return;
        form.dataset.cartSubmitting = 'true';
        const button = form.querySelector('button[type="submit"]');
        if (button) button.disabled = true;
        try {
            const response = await fetch(form.action, {
                method: 'POST',
                headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                body: new FormData(form),
            });
            if (! response.ok) {
                form.dataset.cartSubmitting = 'false';
                form.submit();
                return;
            }
            window.location.reload();
        } catch {
            form.dataset.cartSubmitting = 'false';
            form.submit();
        }
    });
});

document.querySelectorAll('[data-product-gallery]');
document.querySelectorAll('.product-gallery-thumb').forEach((button) => {
    button.addEventListener('click', () => {
        const main = document.querySelector('#productMainImage');
        if (! main) return;
        main.src = button.dataset.imageSrc;
        main.alt = button.dataset.imageAlt || '';
        document.querySelectorAll('.product-gallery-thumb').forEach((thumb) => thumb.classList.toggle('is-active', thumb === button));
    });
});

document.querySelectorAll('[data-add-gallery-image]').forEach((button) => {
    button.addEventListener('click', () => {
        const list = button.closest('.field').querySelector('.gallery-image-inputs');
        const index = Number(list.dataset.nextIndex || list.children.length);
        list.dataset.nextIndex = String(index + 1);
        list.insertAdjacentHTML('beforeend', `<div class="field-row gallery-image-row"><div class="field"><input type="url" name="gallery_images[${index}][url]" class="form-input" placeholder="https://..."></div><div class="field"><input type="text" name="gallery_images[${index}][alt_text]" class="form-input" placeholder="ছবির বর্ণনা (ঐচ্ছিক)"></div><button type="button" class="btn secondary" data-remove-gallery-image aria-label="ছবি সরান">সরান</button></div>`);
    });
});
document.querySelectorAll('.gallery-image-inputs').forEach((list) => list.addEventListener('click', (event) => {
    if (event.target.matches('[data-remove-gallery-image]')) event.target.closest('.gallery-image-row').remove();
}));

const couponFeedback = document.querySelector('#couponFeedback');
const formatMoney = (amount) => `৳${Number(amount).toLocaleString('en-US', { maximumFractionDigits: 0 })}`;
const variantPicker = document.querySelector('#variantPicker');
const variantPickerOptions = document.querySelector('#variantPickerOptions');
const variantPickerPrice = document.querySelector('#variantPickerPrice');
const variantPickerRegularPrice = document.querySelector('#variantPickerRegularPrice');
const variantPickerForm = document.querySelector('#variantPickerForm');
const variantPickerProductId = document.querySelector('#variantPickerProductId');
const variantPickerVariantId = document.querySelector('#variantPickerVariantId');
const variantPickerError = document.querySelector('#variantPickerError');
const decodeVariantData = (encoded) => {
    try {
        const bytes = Uint8Array.from(atob(encoded), (character) => character.charCodeAt(0));
        return JSON.parse(new TextDecoder().decode(bytes));
    } catch {
        return [];
    }
};
const updateVariantPickerPrice = (variant) => {
    const sellingPrice = Number(variant?.price || 0);
    const regularPrice = Number(variant?.regular_price || 0);
    variantPickerPrice.textContent = formatMoney(sellingPrice);
    if (variantPickerRegularPrice) {
        variantPickerRegularPrice.textContent = formatMoney(regularPrice);
        variantPickerRegularPrice.hidden = !(regularPrice > sellingPrice);
    }
};
const closeVariantPicker = () => {
    if (! variantPicker) return;
    variantPicker.hidden = true;
    variantPicker.setAttribute('aria-hidden', 'true');
    document.body.classList.remove('variant-picker-open');
};
document.querySelectorAll('[data-variant-picker]').forEach((trigger) => trigger.addEventListener('click', () => {
    if (! variantPicker) return;
    const variants = decodeVariantData(trigger.dataset.variants || '');
    variantPickerProductId.value = trigger.dataset.productId || '';
    variantPickerVariantId.value = '';
    variantPickerError.hidden = true;
    variantPickerOptions.replaceChildren();
    variants.forEach((variant) => {
        const option = document.createElement('button');
        option.type = 'button';
        option.className = 'variant-picker-option';
        option.disabled = Number(variant.stock) <= 0;
        option.dataset.variantId = String(variant.id);
        const title = document.createElement('strong');
        title.textContent = variant.size || variant.color || variant.name || 'ভ্যারিয়েন্ট';
        const meta = document.createElement('span');
        meta.textContent = `${formatMoney(variant.price)} · ${Number(variant.stock) > 0 ? `স্টকে আছে · ${variant.stock} পিস` : 'স্টক নেই'}`;
        option.append(title, meta);
        option.addEventListener('click', () => {
            variantPickerVariantId.value = String(variant.id);
            updateVariantPickerPrice(variant);
            variantPickerError.hidden = true;
            variantPickerOptions.querySelectorAll('.variant-picker-option').forEach((item) => item.classList.toggle('selected', item === option));
        });
        variantPickerOptions.append(option);
    });
    const firstAvailable = variants.find((variant) => Number(variant.stock) > 0) || variants[0];
    if (firstAvailable) {
        updateVariantPickerPrice(firstAvailable);
        variantPickerOptions.querySelector(`[data-variant-id="${firstAvailable.id}"]`)?.click();
    }
    variantPicker.hidden = false;
    variantPicker.setAttribute('aria-hidden', 'false');
    document.body.classList.add('variant-picker-open');
    variantPicker.querySelector('.variant-picker-close')?.focus();
}));
document.querySelectorAll('[data-variant-picker-close]').forEach((button) => button.addEventListener('click', closeVariantPicker));
document.addEventListener('keydown', (event) => { if (event.key === 'Escape') closeVariantPicker(); });
variantPickerForm?.addEventListener('submit', (event) => {
    if (! variantPickerVariantId.value) {
        event.preventDefault();
        variantPickerError.hidden = false;
    }
});
const deliveryZone = document.querySelector('#deliveryZone');
let currentCheckoutSubtotal = Number(document.querySelector('#checkoutSubtotal')?.dataset.amount || 0);
let currentCheckoutDiscount = Number(document.querySelector('#checkoutDiscount')?.dataset.amount || 0);
const recalculateDeliveryQuote = () => {
    if (!deliveryZone) return;
    const rates = JSON.parse(deliveryZone.dataset.rates || '{}');
    const shipping = Number(rates[deliveryZone.value] || 0);
    document.querySelector('#checkoutShipping').textContent = formatMoney(shipping);
    document.querySelector('#checkoutTotal').textContent = formatMoney(currentCheckoutSubtotal - currentCheckoutDiscount + shipping);
};
const updateCouponQuote = (quote) => {
    document.querySelector('#checkoutToken')?.setAttribute('value', quote.checkoutToken);
    const tokenInput = document.querySelector('input[name="checkout_token"]');
    if (tokenInput) tokenInput.value = quote.checkoutToken;
    const codeInput = document.querySelector('#checkoutCouponCode');
    if (codeInput) codeInput.value = quote.couponCode || '';
    document.querySelector('#checkoutSubtotal').textContent = formatMoney(quote.subtotal);
    currentCheckoutSubtotal = Number(quote.subtotal);
    document.querySelector('#checkoutShipping').textContent = formatMoney(quote.shippingCharge);
    document.querySelector('#checkoutTotal').textContent = formatMoney(quote.total);
    const discountRow = document.querySelector('#checkoutDiscountRow');
    discountRow.hidden = Number(quote.discountAmount) <= 0;
    document.querySelector('#checkoutDiscount').textContent = `− ${formatMoney(quote.discountAmount || 0)}`;
    currentCheckoutDiscount = Number(quote.discountAmount || 0);
    const hasCoupon = Boolean(quote.couponCode);
    document.querySelector('#couponEntry').hidden = hasCoupon;
    document.querySelector('#applyCouponButton').hidden = hasCoupon;
    document.querySelector('#removeCouponButton').hidden = !hasCoupon;
    let applied = document.querySelector('#appliedCoupon');
    if (hasCoupon && !applied) {
        applied = document.createElement('p');
        applied.id = 'appliedCoupon';
        document.querySelector('.coupon-box').prepend(applied);
    }
    if (applied) applied.innerHTML = hasCoupon ? `কুপন <b>${String(quote.couponCode).replace(/[&<>"']/g, (char) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' })[char])}</b> প্রয়োগ করা হয়েছে` : '';
    couponFeedback.textContent = quote.message;
};
deliveryZone?.addEventListener('change', recalculateDeliveryQuote);
document.querySelectorAll('[data-zone-choice]').forEach((button) => button.addEventListener('click', () => {
    if (! deliveryZone) return;
    deliveryZone.value = button.dataset.zoneChoice;
    document.querySelectorAll('[data-zone-choice]').forEach((choice) => choice.classList.toggle('is-selected', choice === button));
    deliveryZone.dispatchEvent(new Event('change', { bubbles: true }));
}));
const submitCouponAction = async (url, method, payload = {}) => {
    couponFeedback.textContent = 'যাচাই হচ্ছে…';
    const form = document.querySelector('.checkout-layout');
    const body = new FormData();
    body.append('_token', form.querySelector('input[name="_token"]').value);
    if (deliveryZone) body.append('delivery_zone', deliveryZone.value);
    Object.entries(payload).forEach(([key, value]) => body.append(key, value));
    try {
        const response = await fetch(url, { method, headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' }, body });
        const result = await response.json();
        if (!response.ok) throw new Error(result.message || 'কুপন প্রয়োগ করা যায়নি।');
        updateCouponQuote(result);
    } catch (error) {
        couponFeedback.textContent = error.message;
    }
};
document.querySelector('#applyCouponButton')?.addEventListener('click', (event) => {
    const button = event.currentTarget;
    submitCouponAction(button.dataset.url, 'POST', { coupon_code: document.querySelector('#couponInput').value });
});
document.querySelector('#removeCouponButton')?.addEventListener('click', (event) => submitCouponAction(event.currentTarget.dataset.url, 'DELETE'));

document.querySelector('#mobileBottomMenu')?.addEventListener('click', () => {
    document.querySelector('#mobileMenuToggle')?.click();
});

document.querySelector('#mobileBottomSearch')?.addEventListener('click', () => {
    document.querySelector('#mobileSearchToggle')?.click();
});

document.querySelector('#mobileMenuToggle')?.addEventListener('click', () => {
    document.querySelector('#mobileMenu')?.classList.add('open');
    document.querySelector('#mobileMenuBackdrop')?.classList.add('open');
    document.querySelector('#mobileMenu')?.setAttribute('aria-hidden', 'false');
    document.querySelector('#mobileMenuToggle')?.setAttribute('aria-expanded', 'true');
});

const closeMobileMenu = () => {
    document.querySelector('#mobileMenu')?.classList.remove('open');
    document.querySelector('#mobileMenuBackdrop')?.classList.remove('open');
    document.querySelector('#mobileMenu')?.setAttribute('aria-hidden', 'true');
    document.querySelector('#mobileMenuToggle')?.setAttribute('aria-expanded', 'false');
};

document.querySelector('#mobileMenuClose')?.addEventListener('click', closeMobileMenu);
document.querySelector('#mobileMenuBackdrop')?.addEventListener('click', closeMobileMenu);

const slides = [...document.querySelectorAll('.bc-slide')];
const dots = [...document.querySelectorAll('.bc-dots button')];
let activeSlide = 0;
const showSlide = (index) => {
    if (!slides.length) return;
    activeSlide = (index + slides.length) % slides.length;
    slides.forEach((slide, position) => slide.classList.toggle('bc-active', position === activeSlide));
    dots.forEach((dot, position) => dot.classList.toggle('selected', position === activeSlide));
};
document.querySelector('.bc-prev')?.addEventListener('click', () => showSlide(activeSlide - 1));
document.querySelector('.bc-next')?.addEventListener('click', () => showSlide(activeSlide + 1));
dots.forEach((dot, position) => dot.addEventListener('click', () => showSlide(position)));
if (slides.length > 1) window.setInterval(() => showSlide(activeSlide + 1), 5000);

const updateMobileBottomActive = () => {
    const path = window.location.pathname;
    const route = path === '/'
        ? 'home'
        : path.startsWith('/cart')
            ? 'cart'
            : path.startsWith('/account') || path.startsWith('/login') || path.startsWith('/register')
                ? 'account'
                : '';

    document.querySelectorAll('.mobile-bottom-nav [data-route]').forEach((link) => {
        link.classList.toggle('active', link.dataset.route === route);
    });
};
updateMobileBottomActive();
document.querySelectorAll('a[href="#wishlist"]').forEach((link) => {
    link.href = '/wishlist';
});

const updateWishlistButton = (button, saved) => {
    button.classList.toggle('is-saved', saved);
    button.setAttribute('aria-label', saved ? 'পছন্দের তালিকা থেকে সরান' : 'পছন্দে যোগ করুন');
    const icon = button.querySelector('i');
    icon?.classList.toggle('fa-solid', saved);
    icon?.classList.toggle('fa-regular', ! saved);
};

const showWishlistToast = (message) => {
    const toast = document.querySelector('#toast');

    if (! toast) {
        return;
    }

    toast.textContent = message;
    toast.style.display = 'block';
    window.setTimeout(() => {
        toast.style.display = 'none';
    }, 2400);
};

document.querySelectorAll('.card-wishlist-form').forEach((form) => {
    form.addEventListener('submit', async (event) => {
        event.preventDefault();

        const button = form.querySelector('.wish-button');
        button.disabled = true;

        try {
            const response = await fetch(form.action, {
                method: 'POST',
                headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                body: new FormData(form),
            });
            const result = await response.json();

            updateWishlistButton(button, result.saved);
            document.querySelectorAll('.wishlist-count').forEach((badge) => {
                badge.textContent = result.count;
                badge.hidden = result.count === 0;
            });
            showWishlistToast(result.message);

            if (! result.saved && window.location.pathname === '/wishlist') {
                form.closest('.product')?.remove();
            }
        } finally {
            button.disabled = false;
        }
    });
});

const detailRoot = document.querySelector('[data-product-detail]');
if (detailRoot) {
    const readDetailJson = (id, fallback) => {
        try {
            return JSON.parse(document.getElementById(id)?.textContent || JSON.stringify(fallback));
        } catch {
            return fallback;
        }
    };
    const detailGallery = readDetailJson('detailGalleryData', []);
    const detailVariants = readDetailJson('detailVariantData', []);
    const detailProduct = readDetailJson('detailProductData', { has_variants: false, stock: 0 });
    const detailState = { image: 0, color: null, size: null, quantity: 1 };
    const mainPhoto = document.querySelector('#detailMainPhoto');
    const galleryCounter = document.querySelector('#galleryCounter');
    const lightbox = document.querySelector('#detailLightbox');
    const selectedVariant = () => detailVariants.find((variant) =>
        (variant.color === null || variant.color === detailState.color)
        && (variant.size === null || variant.size === detailState.size));
    const hasColorOptions = detailVariants.some((variant) => variant.color);
    const hasSizeOptions = detailVariants.some((variant) => variant.size);
    const currentVariant = () => detailProduct.has_variants ? selectedVariant() : null;
    const setGalleryImage = (index) => {
        if (!detailGallery.length) return;
        detailState.image = (index + detailGallery.length) % detailGallery.length;
        if (mainPhoto) {
            mainPhoto.src = detailGallery[detailState.image];
            mainPhoto.alt = `${detailRoot.dataset.productName || 'পণ্য'} — ছবি ${detailState.image + 1}`;
        }
        document.querySelectorAll('[data-gallery-index]').forEach((thumb, thumbIndex) => {
            const selected = thumbIndex === detailState.image;
            thumb.classList.toggle('selected', selected);
            thumb.setAttribute('aria-pressed', String(selected));
        });
        if (galleryCounter) galleryCounter.textContent = `${detailState.image + 1} / ${detailGallery.length}`;
    };
    const refreshDetailVariant = () => {
        const variant = currentVariant();
        document.querySelectorAll('[data-detail-color]').forEach((button) => {
            const selected = button.dataset.detailColor === detailState.color;
            const available = detailVariants.some((item) => item.color === button.dataset.detailColor && item.stock > 0 && (!detailState.size || item.size === detailState.size));
            button.classList.toggle('selected', selected);
            button.disabled = !available;
            button.setAttribute('aria-pressed', String(selected));
        });
        document.querySelectorAll('[data-detail-size]').forEach((button) => {
            const selected = button.dataset.detailSize === detailState.size;
            const available = detailVariants.some((item) => item.size === button.dataset.detailSize && item.stock > 0 && (!detailState.color || item.color === detailState.color));
            button.classList.toggle('selected', selected);
            button.disabled = !available;
            button.setAttribute('aria-pressed', String(selected));
        });
        const colorLabel = document.querySelector('[data-selected-color]');
        const sizeLabel = document.querySelector('[data-selected-size]');
        if (colorLabel) colorLabel.textContent = detailState.color || 'একটি রং বেছে নিন';
        if (sizeLabel) sizeLabel.textContent = detailState.size || 'সাইজ বেছে নিন';
        if (variant) {
            document.querySelector('#detailPrice')?.replaceChildren(document.createTextNode(formatMoney(variant.price)));
            const sku = document.querySelector('#detailSku');
            if (sku) sku.textContent = variant.sku;
        } else if (! detailProduct.has_variants) {
            document.querySelector('#detailPrice')?.replaceChildren(document.createTextNode(formatMoney(detailProduct.price)));
            const sku = document.querySelector('#detailSku');
            if (sku) sku.textContent = sku.dataset.productSku || sku.textContent;
        }
        const stock = document.querySelector('#detailStock');
        if (stock) stock.textContent = detailProduct.has_variants
            ? (!detailState.color && hasColorOptions) || (!detailState.size && hasSizeOptions)
                ? 'প্রথমে রং ও সাইজ নির্বাচন করুন'
                : variant?.stock > 0 ? `স্টকে আছে · ${variant.stock} পিস` : 'স্টক নেই'
            : detailProduct.stock > 0 ? `স্টকে আছে · ${detailProduct.stock} পিস` : 'স্টক নেই';
        const ready = detailProduct.has_variants ? Boolean(variant?.stock > 0) : detailProduct.stock > 0;
        document.querySelectorAll('[data-detail-purchase-form]').forEach((form) => {
            const button = form.querySelector('.detail-purchase');
            const variantInput = form.querySelector('[data-detail-variant-input]');
            const qtyInput = form.querySelector('[data-detail-qty-input]');
            if (variantInput) variantInput.value = variant?.id || '';
            if (qtyInput) qtyInput.value = detailState.quantity;
            if (button) button.disabled = !ready;
        });
    };
    document.querySelectorAll('[data-gallery-index]').forEach((button) => button.addEventListener('click', () => setGalleryImage(Number(button.dataset.galleryIndex))));
    document.querySelectorAll('[data-gallery-move]').forEach((button) => button.addEventListener('click', () => setGalleryImage(detailState.image + Number(button.dataset.galleryMove))));
    document.querySelectorAll('[data-detail-color]').forEach((button) => button.addEventListener('click', () => {
        detailState.color = button.dataset.detailColor;
        detailState.size = null;
        detailState.quantity = 1;
        refreshDetailVariant();
    }));
    document.querySelectorAll('[data-detail-size]').forEach((button) => button.addEventListener('click', () => {
        detailState.size = button.dataset.detailSize;
        detailState.quantity = 1;
        refreshDetailVariant();
    }));
    document.querySelectorAll('[data-detail-quantity]').forEach((button) => button.addEventListener('click', () => {
        const variant = currentVariant();
        const max = variant?.stock || detailProduct.stock || 1;
        detailState.quantity = Math.min(max, Math.max(1, detailState.quantity + Number(button.dataset.detailQuantity)));
        const quantity = document.querySelector('#detailQuantity');
        if (quantity) quantity.textContent = detailState.quantity;
        refreshDetailVariant();
    }));
    document.querySelectorAll('[data-detail-purchase-form]').forEach((form) => form.addEventListener('submit', (event) => {
        if (detailProduct.has_variants && !currentVariant()) {
            event.preventDefault();
            showWishlistToast('রং ও সাইজ নির্বাচন করুন');
        }
    }));
    const openLightbox = () => {
        if (!lightbox || !mainPhoto) return;
        const image = lightbox.querySelector('img');
        if (image) image.src = mainPhoto.src;
        lightbox.hidden = false;
        document.body.style.overflow = 'hidden';
    };
    const closeLightbox = () => {
        if (!lightbox) return;
        lightbox.hidden = true;
        document.body.style.overflow = '';
    };
    mainPhoto?.addEventListener('click', openLightbox);
    document.querySelector('[data-detail-zoom-button]')?.addEventListener('click', openLightbox);
    document.querySelector('[data-detail-close-zoom]')?.addEventListener('click', closeLightbox);
    lightbox?.addEventListener('click', (event) => { if (event.target === lightbox) closeLightbox(); });
    refreshDetailVariant();
}

