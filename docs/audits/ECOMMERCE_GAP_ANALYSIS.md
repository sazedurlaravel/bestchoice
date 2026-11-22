# E-commerce gap analysis

Audit date: 2026-11-08. See [feature inventory](ECOMMERCE_FEATURE_AUDIT.md) for all 382 classifications and [security review](ECOMMERCE_SECURITY_REVIEW.md) for severity. Findings below are code observations unless marked as executed checks.

## Architecture and schema actually present

Laravel 13.40.0 / PHP 8.4.17 uses web/session routes, Blade, Vite 8.3.6, Tailwind 4.3.4 for the app/auth entry, and largely custom storefront/admin CSS. MySQL schema metadata confirmed 17 tables. Direct runtime PHP requirements are Laravel and Tinker; no payment, courier, AI or dedicated permission package is installed. Absence of a vendor SDK alone is not proof: routes, controllers, services, configuration and schema were also searched for custom clients/callbacks.

Relationships: category parent/children and product category; product images/variants/inventory movements; order user/items; order-item product; inventory optional variant/user/reference fields. Unique product/category slugs, product/variant SKUs, order references and user email/mobile exist. Products restrict category deletion; order items restrict product deletion; product deletion cascades images/variants/inventory movements. User deletion nulls order ownership. Orders do not contain payment status, parcel, idempotency, settlement, cost or refund fields.

The live dashboard is a distinct inline-CSS/JavaScript page. It receives database snapshots from DashboardController, and most sidebar links have a second handler redirecting to the real server CRUD pages. It also retains browser-only modal mutations with an empty save function. Older documents' description of live LocalStorage persistence is inaccurate for current code. Reference mockups still use demo LocalStorage logic.

## Storefront: homepage, discovery and navigation

**Existing:** hero slides/manual controls/five-second autoplay; real category children/counts; active latest products; session wishlist; 5/3 grids. Source: HomeController.php:15,28; home.blade.php:6; storefront.js:35.

**Gaps:** the customer-favorites section selects latest active products, not sales-ranked or featured products. No distinct new-arrival/discount campaign feed exists. Parent category pages query only direct products, not descendant products (CategoryController.php:18). Selecting a top-level category can show an empty listing despite child-category stock.

SearchController.php:14–16 validates and binds text search and paginates 15 results. Price, size, color, stock and sort controls from the mockup are not wired in Laravel. SQL binding is present; do not classify LIKE wildcards as SQL injection.

Header/footer fragments for about/contact/FAQ/policy/categories have no matching live sections/routes (partials/header.blade.php:8,14; footer.blade.php:2; routes/web.php). These are confirmed dead navigation destinations. Policy/content work requires merchant-approved copy, not invented shipping/refund promises.

**Next:** preserve working search/slug pages, resolve essential destinations, then add explicitly scoped filters and merchandising. Missing advanced merchandising is P2/P3, not a default payment blocker.

## Product details, images and clothing variants

ProductController.php:14 loads images and variants. The view renders one main image and two fixed-quantity-1 purchase forms (products/show.blade.php:16–67). No gallery, zoom, detail wishlist, selectable size/color/variant, quantity selector, size guide, review, structured specification, sharing or related-product workflow is complete.

ProductVariant has options, unique SKU, price override and stock. CartService keys only by product ID and stores no variant identity; OrderItem has no variant snapshot/foreign key; InventoryService locks Product only. A size/color pair therefore cannot be independently purchased or deducted. This is PARTIAL, not absent schema.

**Next:** determine whether the launch assortment actually varies by size/color. If yes, variant completion is a P1 launch dependency. If the merchant launches only single-SKU items, defer it explicitly; do not represent such a launch as a complete clothing variant platform. Media uploads/gallery are separate from the already working URL-main-image capability.

## Cart, authoritative prices and checkout

**Existing:** session guest and authenticated cart; add/remove/plus/minus; product-ID duplicate merge; count; empty state; server sum and checkout links. CartController.php:28 validates add against current stock. Checkout creates an order/items inside a DB transaction and calls locked stockOut.

**Confirmed price defect (P0):** CartService.php:27–33 captures selling price once. CheckoutController.php:53–80 reads that stored value, even after loading Product, without repricing. Synthetic example: catalog price changes from 490 to 590 after add; placement still uses session 490. This is a code trace, not a live order experiment. Ignoring browser-supplied totals is good, but a server-held stale snapshot is not current catalog authority. Recheck active/deleted eligibility in the same boundary.

**Confirmed stock-update defect (P1):** cart.update accepts integer 0–99 and CartService.updateQty writes it without querying stock (CartController.php:62–69). A stock-1 item can have cart qty99. Checkout stockOut should reject insufficient stock, so this is not proof of overselling through the locked service.

**Confirmed delivery defect (P1):** admin SettingsController.php:20–29 persists delivery_charge, while cart and checkout always use 60. Shipping zones/free thresholds are MISSING; a flat rate is acceptable if configured and consistently applied.

**Incomplete duplicate prevention (P0 release gate):** session clearing after commit handles a subsequent sequential request; it does not create a per-checkout idempotency token, session block or unique operation key for parallel requests. Parallel duplication was not reproduced in this audit. Preserve one atomic checkout boundary and add an isolated multi-request/MySQL regression.

**Validation:** primary/additional numbers accept arbitrary strings up to20. Keep guest checkout and optional additional phone; do not make email/division mandatory. Form errors and old input exist, but missing/deleted products and cross-field catalog errors need bounded recovery.

**Persistence:** same browser session supports both guest/customer, but no account cart merge, cross-device recovery or variant lines. Manual quantity endpoint exists; a numeric editor does not.

## Inventory and order lifecycle

InventoryService.php:21–40 performs a transaction, locks Product, calculates before/after, rejects negative stock and writes a ledger. Eight service tests pass. Do not rebuild that working foundation. SQLite tests do not validate MySQL row contention/deadlocks.

**High-integrity gaps:** Admin ProductController.php:88–98 directly updates stock_quantity without InventoryService or ledger. A routine product save can overwrite stock concurrently and creates no stock adjustment trail. Initial stock similarly has no opening movement. Deleting a product with no order items cascades its historical inventory movements.

Checkout stockOut passes an order reference only as a reason string; structured reference_type/reference_id fields remain unused. Product-variant rows have independent stock columns but no variant locking/purchasing flow.

OrderController.php:40–46 accepts any allowed target status without checking the previous state, history or inventory action. Changing to cancelled does not restore stock already deducted at placement. Repeating cancellation currently does not double-restore because no restore exists; a future fix must restore at most once. Return/refund/payment/settlement state and activity logs are absent.

**Next:** agree the reserve/deduct/cancel/return policy and implement idempotent transitions, ledger references and audited product-stock edits. These are coordinated integrity fixes, not permission to rewrite unrelated CRUD.

## Admin dashboard and catalog operations

Real product/category/inventory/order/customer/settings server routes exist behind auth+admin. They must not be discarded because the dashboard prototype contains parallel UI.

Confirmed dashboard defects:
- Latest50 orders are used as visible total and pending counts (DashboardController.php:26; dashboard.blade.php:4).
- Visible +12.5% trend, seven bar heights and donut are constants; they are labelled as operational data.
- Browser modal updates call save=()=>{} (dashboard.blade.php:4–7). The recent-order modal can claim success without persisting; most sidebar destinations redirect to separate live pages.
- Dashboard low-stock threshold is a global5 rather than product thresholds used by the real inventory page.
- View-store opens an obsolete HTML filename (dashboard.blade.php:3).

Catalog validation gaps:
- regular_price < selling_price reaches Product's InvalidArgumentException, rather than a field-validation response (Product.php:20; admin ProductController.php:47–60,80–93).
- short_description/main_image permit500 while their migration columns are VARCHAR255. Long valid controller input may fail/truncate depending on SQL mode.
- Hard-delete for sold products hits order_items restrict FK; no graceful archive/error flow. Unsold product deletion loses inventory history.
- Category slug regeneration has no uniqueness collision handling; parent_id allows self/cyclic relationships via crafted requests.
- category.image_path exists, but the admin form/controller cannot edit it.

**Next:** fix data validation and retention guards before broader UI/admin extensions.

## Customer accounts, staff and permissions

Registration, email/mobile login, logout, profile edits and last10 owned orders have tests. AuthController scopes account orders by user_id; registration forces customer role. No confirmed arbitrary role escalation is found merely because role is fillable.

Missing: password reset endpoints despite a token table, address book, customer-facing order detail/cancel/return flows, full order pagination, staff account/permission UI and sensitive-action logs. Binary admin protection exists and passes basic access tests.

Admin customers are a complete-order collection grouped by raw phone (CustomerController.php:13). It is not a customer CRM: no detail/spend/search/status/address/note workflow, normalized deduplication or pagination. Account order isolation and admin customer grouping are different features.

## COD, delivery, courier, payment and notifications

COD is a valid launch model. payment_method is saved, but due/collected/remitted status, delivery failure/RTO and courier settlements are not tracked.

No Steadfast/Pathao/REDX/custom courier booking, callback/poll sync, retry, parcel IDs or settlement integration was found. No bKash/Nagad/SSLCommerz initiation/verification/webhook/refund/reconciliation was found. Provider names in a brief or framework services config are not integrations.

No custom SMS/mail notification classes/jobs/listeners, consent/templates/delivery logs or retries exist. Generic mail config and jobs tables are framework infrastructure only. schedule:list reports no tasks.

**Next:** agree a reliable manual COD fulfillment/reconciliation process for MVP. Then integrate one courier, one payment provider and transactional SMS in sandbox stages. Multiple providers, online payments and AI are not automatically required for an initial COD-only launch.

## Marketing, automation and financial reporting

Real catalog discounts/session wishlist exist. Coupons have browser-only demo controls but no schema/checkout engine; campaign expiry/usage limits, reviews, loyalty/referrals/newsletter/cart recovery and analytics tags are missing.

SEO-friendly slugs exist. Page title sections are not consumed by storefront/admin layouts, which read $title; no canonical, Open Graph, structured product data or sitemap pipeline found. robots.txt exists and permits crawling; it is not SEO completeness or access control.

Revenue-like sum is all non-cancelled order totals, including shipping/uncollected COD (DashboardController.php:53); it is neither collected revenue nor profit. No cost of goods, fees/expenses/refunds/settlement ledger supports gross/net profit, valuation or CLV. Inventory CSV exports injected browser data only. Date sales reports are missing; sample daily bars are broken reporting.

No Bengali AI support/recommendation/calling, manual-call outcomes, call logs, automated confirmation/recovery/stock notifications or reports are complete. Before any voice pilot: provider cost caps, consent/opt-out, recording retention, retry limits and human escalation. These are P3 unless an explicit operating model elevates them.

## Security/reliability/testing and UI

See the dedicated reviews. SQL values are bound, Blade normally escapes content, passwords are hashed and sessions regenerate. Production cookie/TLS/debug/backup/restore/worker controls cannot be inferred from local defaults. No external pen test or advisory scan was executed.

Safe test run: 47 passed/93 assertions. No cart mutations, checkout creation/rollback, order lifecycle, admin CRUD, payment callbacks, full browser matrix or MySQL concurrency tests are in the suite. Some existing example assertions add little assurance. No CI configuration found.

The current cart has been corrected since older audit documents. This audit uses current code, preserving that change, approved product ratios, 5/3 grids and icon-only bottom shortcuts. No new screenshots or UI changes were made in this audit. Full mockup fidelity remains unverified.

