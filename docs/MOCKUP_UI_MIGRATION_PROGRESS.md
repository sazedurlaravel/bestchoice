# Mockup UI Migration Progress

Last updated: 2026-11-08

Statuses: `Not Started`, `In Progress`, `Implemented`, `Visually Verified`, `Blocked`.

Visual verification is currently unavailable in this environment, so no page is marked `Visually Verified`.

| Page | Reference section | Laravel route | Blade file | Implementation status | Functional verification | Visual verification | Remaining differences |
| --- | --- | --- | --- | --- | --- | --- | --- |
| Homepage | `home()` | `home` | `storefront/home.blade.php` | In Progress | Existing test/build pass | Not verified | Need same-viewport screenshot comparison |
| Products/search | `shop()` | `shop`, `search` | `storefront/search.blade.php` | In Progress | Route/controller exists | Not verified | Filter/list presentation needs comparison |
| Category/subcategory | `categoryGrid()`, shop category state | `categories.show` | `storefront/categories/show.blade.php` | In Progress | Route/model binding exists | Not verified | Category rail and page states need comparison |
| Product detail | `detail()` | `products.show` | `storefront/products/show.blade.php` | In Progress | Cart/stock forms preserved | Not verified | Gallery/variant/action layout needs comparison |
| Cart | `cartPage()`, `cartLines()`, `summary()` | `cart.index` | `storefront/cart/index.blade.php` | Implemented | Server cart forms/routes preserved; full test suite passes | Not verified | Exact screenshot comparison pending |
| Checkout | `checkout()` | `checkout.index` | `storefront/checkout/index.blade.php` | In Progress | Server validation/order flow preserved | Not verified | Visual match pending |
| Order success | `success()` | `order.confirmation` | `storefront/checkout/confirmation.blade.php` | In Progress | Confirmation route exists | Not verified | Visual match pending |
| Wishlist | Mockup wishlist state | `wishlist` | `storefront/wishlist/index.blade.php` | In Progress | Session toggle/count behavior exists | Not verified | Empty/card state comparison pending |
| Login/register/account | `account()` and auth UI | `login`, `register`, `account` | `auth/*.blade.php` | In Progress | Auth routes and middleware preserved | Not verified | Auth token/layout alignment pending |
| Tracking | `track()` | `track` | `storefront/track.blade.php` | In Progress | Lookup route exists | Not verified | Step/status presentation pending |
| Simple information pages | `simple()` | No matching named routes | None | Blocked | No server-backed pages to verify | Not verified | Requires route/view decision; do not fabricate automatically |
| Storefront shared shell | Header/footer/mobile nav | Shared layout | `layouts/storefront.blade.php`, partials | Implemented / needs verification | Existing navigation/count tests pass | Not verified | Exact spacing/icon comparison pending |
| Admin shell | Sidebar/topbar | `admin.*` | `layouts/admin.blade.php`, admin CSS | In Progress | Admin routes/middleware exist | Not verified | Split CSS and active-state differences |
| Admin dashboard | `dashboard` | `admin.dashboard` | `admin/dashboard.blade.php` | Blocked for pixel migration | Two data models coexist | Not verified | Source-of-truth decision required before interaction migration |
| Admin products | `products()` | `admin.products*` | `admin/products/*` | In Progress | CRUD routes exist | Not verified | Form/table visual comparison pending |
| Admin categories | `categoriesPage()` / CRUD | `admin.categories*` | `admin/categories/*` | In Progress | CRUD routes exist | Not verified | Visual comparison pending |
| Admin inventory | `inventory()` | `admin.inventory*` | `admin/inventory/index.blade.php` | In Progress | Inventory service/controller exists | Not verified | Visual comparison pending |
| Admin orders | `ordersPage()` / CRUD | `admin.orders*` | `admin/orders/*` | In Progress | Status update routes exist | Not verified | Visual comparison pending |
| Admin customers | `customers()` | `admin.customers` | `admin/customers/index.blade.php` | In Progress | Route/controller exists | Not verified | Visual comparison pending |
| Admin coupons | `coupons()` | No Laravel route | None | Blocked | Mockup-only localStorage state | Not verified | Do not add without business approval |
| Admin settings | `settings()` | `admin.settings*` | `admin/settings/edit.blade.php` | In Progress | Settings update route exists | Not verified | Visual comparison pending |

## Completed migration batches

### Shared storefront foundation

- Shared storefront layout includes header, footer, and mobile shortcuts.
- Shared cart/wishlist counters are sourced from Laravel session/services.
- Product cards use Laravel routes/forms rather than mockup LocalStorage behavior.
- Font Awesome 6 remains the shared icon library.

### Cart batch

- Cart Blade now follows the mockup's cart/title/item/summary hierarchy.
- Live quantity, remove, totals, delivery, and checkout routes remain server-backed.
- Mobile spacing and card proportions were aligned to the supplied cart reference.

## Next implementation batch

1. Capture browser screenshots of the current Laravel homepage, shop, product detail, checkout, and admin dashboard at the required widths.
2. Compare shared header/product-card tokens against the mockup before changing more pages.
3. Normalize only the shared storefront primitives that differ, then update this file with screenshot evidence.
4. Handle admin dashboard separately after deciding whether the server-backed controller or embedded prototype is authoritative.
