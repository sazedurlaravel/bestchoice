# Mockup UI Page Inventory

This inventory compares the approved references in `design-reference/frontend.html` and `design-reference/admin.html` with the current Laravel application. The mockups are visual references; Laravel remains the source of truth for data, validation, authorization, cart, checkout, orders, and inventory.

## Storefront inventory

| Mockup page/section | Laravel route | Blade view | Status | Main visual differences / dependencies | Priority |
| --- | --- | --- | --- | --- | --- |
| Homepage | `home` `/` | `storefront/home.blade.php` | In Progress | Hero, collection rail, product section, shared header/footer exist; final pixel comparison still requires browser screenshots | High |
| All products | `shop` `/shop` | `storefront/search.blade.php` | In Progress | Real pagination/filter toolbar differs from mockup SPA filter state; product card CSS has layered overrides | High |
| Search results | `search` `/search` | `storefront/search.blade.php` | In Progress | Real query/pagination works; empty/result states and toolbar need reference comparison | High |
| Main category page | `categories.show` `/category/{category:slug}` | `storefront/categories/show.blade.php` | In Progress | Database category route exists; mockup uses category rail/filter presentation | Medium |
| Subcategory page | `categories.show` `/category/{category:slug}` | `storefront/categories/show.blade.php` | In Progress | Same route supports bound category; visual distinction from top-level category needs confirmation | Medium |
| Product detail | `products.show` `/product/{product:slug}` | `storefront/products/show.blade.php` | In Progress | Real product/stock/cart actions exist; gallery/variant presentation needs reference comparison | High |
| Shopping cart | `cart.index` `/cart` | `storefront/cart/index.blade.php` | Implemented / needs visual verification | Mockup-aligned cart layout and responsive summary are present; server forms preserve live cart behavior | High |
| Checkout | `checkout.index` `/checkout` | `storefront/checkout/index.blade.php` | In Progress | Real validation, delivery, payment, and order summary exist; visual match not browser-verified | High |
| Order confirmation | `order.confirmation` `/order/confirmation` | `storefront/checkout/confirmation.blade.php` | In Progress | Functional confirmation exists; inline presentation and mockup success state need comparison | Medium |
| Wishlist | `wishlist` `/wishlist` | `storefront/wishlist/index.blade.php` | In Progress | Real session wishlist and shared product card exist; empty state/layout needs comparison | Medium |
| Order tracking | `track` `/track` | `storefront/track.blade.php` | In Progress | Real lookup exists; mockup tracking step presentation differs and needs visual review | Medium |
| Customer login | `login` `/login` | `auth/login.blade.php` | In Progress | Real authentication exists; auth layout uses a separate CSS entry and needs token alignment | High |
| Customer registration | `register` `/register` | `auth/register.blade.php` | In Progress | Real registration exists; visual comparison pending | Medium |
| Customer account/orders | `account` `/account` | `auth/account.blade.php` | In Progress | Real authenticated account and order list exist; shared storefront shell is present | Medium |
| Offers/promotions | Search query `/search?q=অফার` | `storefront/search.blade.php` | Partial | Header links to a search query; no separate Laravel offers route, while mockup has an offers state | Medium |
| About | Mockup `simple('about')` | No dedicated Laravel view/route | Missing reference-backed page | Existing header/footer use `#about`, but no named server page is present | Medium |
| Contact | Mockup `contact()` | No dedicated Laravel view/route | Missing reference-backed page | Existing links use `#contact`; no server-backed contact page is present | Medium |
| FAQ | Mockup `simple('faq')` | No dedicated Laravel view/route | Missing reference-backed page | Existing links use `#faq`; mockup has a simple FAQ state | Low |
| Policy/privacy/terms/returns | Mockup `simple('policy')` only | No dedicated Laravel view/route | Missing/ambiguous | Mockup has a policy state but does not expose separate privacy, terms, and return routes | Medium |
| Mobile menu | Shared layout partial | `storefront/partials/header.blade.php` | In Progress | Real route links and menu toggle exist; visual/keyboard focus behavior needs browser verification | High |
| Mobile bottom shortcuts | Shared layout partial | `storefront/partials/mobile-bottom-nav.blade.php` | Implemented / needs visual verification | Live Home/Menu/Cart/Search/Account actions exist; current project instruction keeps icon-only presentation | High |
| Footer | Shared layout partial | `storefront/partials/footer.blade.php` | In Progress | Shared footer exists; several links remain anchors because corresponding Laravel pages are absent | Medium |

## Admin inventory

| Mockup page/section | Laravel route | Blade view | Status | Main visual differences / dependencies | Priority |
| --- | --- | --- | --- | --- | --- |
| Dashboard overview | `admin.dashboard` `/admin` | `admin/dashboard.blade.php` | High-risk partial | Laravel route/view currently contains an embedded localStorage/client-state dashboard prototype; server-backed dashboard controller also exists | Critical |
| Admin layout/sidebar/topbar | Shared admin layout | `layouts/admin.blade.php`, `admin.css`, `admin-overrides.css` | In Progress | Brand and structure exist; CSS is split and active state is partly hardcoded | High |
| Product listing | `admin.products` `/admin/products` | `admin/products/index.blade.php` | In Progress | Real CRUD table/pagination exists; mockup dashboard product state is separate | High |
| Add product | `admin.products.create` | `admin/products/create.blade.php`, `_form.blade.php` | In Progress | Real validation/upload fields exist; form visual normalization pending | Medium |
| Edit product | `admin.products.edit` | `admin/products/edit.blade.php`, `_form.blade.php` | In Progress | Real update flow exists; same form styling issue as create | Medium |
| Category management | `admin.categories` and create/edit routes | `admin/categories/*` | In Progress | Real CRUD exists; icon/table/alert styles need shared admin primitives | Medium |
| Inventory management | `admin.inventory` `/admin/inventory` | `admin/inventory/index.blade.php` | In Progress | Real stock adjustment/history/low-stock data exists; mockup/client prototype differs | High |
| Stock in/out/adjustment | `admin.inventory.adjust` | `admin/inventory/index.blade.php`, `InventoryService` | Functional / visual pending | Server transaction and locking logic must remain unchanged | High |
| Stock history/low-stock alerts | `admin.inventory` | `admin/inventory/index.blade.php` | Functional / visual pending | Table/status patterns need consolidation | Medium |
| Order listing | `admin.orders` `/admin/orders` | `admin/orders/index.blade.php` | In Progress | Real filters/status updates exist; visual/status mapping needs reference comparison | High |
| Order details | `admin.orders.show` | `admin/orders/show.blade.php` | In Progress | Real order/customer/items view exists; mockup modal/detail presentation differs | Medium |
| Customer management | `admin.customers` `/admin/customers` | `admin/customers/index.blade.php` | In Progress | Real order-derived customer list exists; visual table normalization pending | Medium |
| Coupon management | Mockup `coupons()` | No Laravel route/view | Missing in Laravel | Mockup contains localStorage coupon UI; do not add business functionality without approval | High-risk review |
| Store settings | `admin.settings` `/admin/settings` | `admin/settings/edit.blade.php` | In Progress | Real settings update exists; mockup has client-side settings prototype | Medium |
| Admin notifications/modal | Layout/dashboard prototype | `layouts/admin.blade.php`, `dashboard.blade.php`, `admin.js` | Partial | Notice/modal patterns coexist with native `confirm()` | Medium |

## Cross-cutting dependencies

- Shared header/footer/mobile navigation affect every storefront page.
- Product card and product-grid rules affect home, shop, category, wishlist, cart recommendations if reintroduced, and product-related surfaces.
- Admin token/table/form rules affect every admin view.
- The admin dashboard source-of-truth decision must precede any attempt to make its product/inventory/order interactions pixel-identical to the mockup.
- Browser screenshots are required before assigning `Visually Verified`.
