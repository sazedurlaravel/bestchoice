# E-commerce security, data integrity and reliability review

Audit date: 2026-11-08. Documentation-only assessment of the local Laravel code and read-only MySQL schema metadata. No exploit against a deployed system, real order placement, courier booking, charge or persistent record mutation was performed.

## Executive assessment

The application has useful protections: auth+admin route grouping, scoped customer order queries, forced customer signup role, password hashing, regenerated sessions, CSRF tokens and Eloquent query bindings. The strongest confirmed release risks concern business integrity rather than a demonstrated remote compromise. No Critical-severity compromise is asserted. P0 denotes an integrity/release gate; it is not synonymous with a proven Critical security exploit.

## Finding register

| ID | Severity / priority | Evidence and status | Consequence / minimal next action |
| --- | --- | --- | --- |
| SEC-01 | High / P0 | **Confirmed algorithm defect:** CartService.php:27–33 stores selling_price at add; CheckoutController.php:53–80 persists session price without comparing current Product selling price. | Stale price orders after catalog edits. Reprice and validate current eligibility at placement, communicate changes, preserve immutable order snapshots. No client total is trusted, which should remain true. |
| SEC-02 | High / P0 | **Confirmed protection gap; parallel outcome unverified:** CheckoutController.php:38–92 has no idempotency key/unique attempt record; routes/web.php:39 has no session block. Cart clear happens after commit. | Parallel submits may both read a populated session, create separate orders and deduct stock if available. Add atomic idempotency and concurrent tests; do not claim duplication was reproduced. |
| SEC-03 | High / P0 | **Confirmed bypass:** admin ProductController.php:88–98 accepts direct stock_quantity writes; InventoryService.php:21–40 is not invoked on product edit. | No ledger entry and possible lost updates against checkout stock. Route stock changes through an authorized, locked and audited adjustment while preserving metadata edits. |
| SEC-04 | High / P0 | **Confirmed missing lifecycle action:** CheckoutController.php:71 deducts stock; admin OrderController.php:40–46 only updates status. | Cancelled orders retain deducted stock, with no auditable reversal. Agree policy and implement atomic once-only restock/return accounting and transition history. |
| SEC-05 | Medium / P1 | **Confirmed validation gap:** CartController.php:62–69 and CartService.php:47–59 permit qty to exceed current stock. | Invalid cart state; final stockOut guard still prevents service-level negative stock. Validate current stock on updates, including changed product eligibility. |
| SEC-06 | Medium / P1 | **Confirmed inconsistency:** SettingsController.php:22–29 stores charge; CartController.php:23–24 and CheckoutController.php:33–34,55 hardcode60. | Merchant settings and customer charge diverge. Use one server-side shipping policy for display and submission. |
| SEC-07 | Medium / P1 | **Confirmed exception path:** admin ProductController.php:47–60,80–93 validates each price individually; Product.php:22–28 throws on invalid relationship. VARCHAR255 image/description columns accept controller max500. | Ordinary invalid input can yield model/DB failure rather than useful validation. Add cross-field and schema-length validation; retain model invariant. Exact SQL-mode-specific long-string outcome was not tested. |
| SEC-08 | High / P1 | **Confirmed schema/operation exposure:** admin ProductController.php:103–105 hard-deletes; inventory migration 2026_11_06_073138:16 cascades product history; order-item migration:14 restricts sold-product deletion. | Unordered product deletion can destroy its stock audit trail; sold-product deletion may throw. Define archival/retention and graceful handling before enabling destructive operations in production. |
| SEC-09 | Medium / P1 | **Confirmed missing guard:** admin CategoryController.php:34–45,59–70 validates parent existence only and regenerates slug without collision validation. | Crafted self/cyclic hierarchy or duplicate slug can break navigation/save. Add hierarchy and slug rules; migration self-parent FK does not prohibit cycles. |
| SEC-10 | Medium / P1 | **Confirmed limited abuse controls:** AppServiceProvider.php:28–31 throttles login/register by identifier+IP. Public cart/wishlist/checkout/tracking routes have no dedicated limits. | Spam/resource abuse and order/reference enumeration remain possible. Define rate limits and verification appropriate to guest checkout/tracking; no demonstrated customer-data exfiltration claim. |
| SEC-11 | Medium / P2 | **Code observation:** TrackOrderController.php:14–17 looks up any reference; track.blade.php:4 renders status and total. Order.php:38 truncates a hash to eight hex characters. | Reference bearer receives status/total without secondary verification; reference space and no limiter need review. Do not report full address/phone disclosure: the public view does not render those fields. Consider a verifier/token plus rate limits without blocking legitimate guest tracking. |
| SEC-12 | Medium / P1 | **Confirmed validation weakness:** CheckoutController.php:45–50 and AuthController.php:51,83 allow arbitrary phone strings by length. | Invalid delivery contacts, inconsistent identity matching and unreliable manual operations. Normalize and validate BD numbers while retaining optional second number. No mandatory email or division is proposed. |
| SEC-13 | Medium / P1 | **Confirmed false-success path:** dashboard.blade.php:4 defines save=()=>{}; editOrder/editProduct/adjustStock/settings handlers mutate memory and toast success. | Admin may believe an operation persisted. Keep real server endpoints authoritative; wire or clearly remove inert controls only in a separately authorized implementation. |
| SEC-14 | Low / P2 | **Review needed, not proven exploitable:** dashboard.blade.php:4–7 mixes innerHTML, inline event attributes, escaped values and unescaped category option markup. | HTML escaping is not automatically JavaScript-context escaping. Review with synthetic malicious category/name fixtures and move to safe DOM/data/event handling. Current Blade text and @json protections should be preserved. |
| SEC-15 | Medium / P1 | **Operational evidence missing:** config/session.php:172,185,202; config/app.php:29,42; no production deployment/backup/restore runbook found. | TLS, secure cookies, debug mode, backups/restoration and log retention cannot be certified. Verify deployment configuration and rehearse restoration; local .env values were not exposed or changed. |
| SEC-16 | Medium / P1 | **Confirmed coverage gap:** tests only cover catalog/auth/wishlist/access and product inventory service, not checkout/cart mutations/status lifecycle. | Existing green tests cannot certify order correctness. Add isolated acceptance/rollback/concurrency/permission tests before release. |

## Existing controls worth preserving

- All declared admin routes sit inside auth + admin middleware (routes/web.php:64; AdminMiddleware.php:17–21). Guests/customers/admin access cases pass for the dashboard.
- Registration hardcodes role=customer; profile updates whitelist name/email/mobile. User role fillability alone is not an observed privilege-escalation path.
- Password hashing uses the User hashed cast and explicit registration hashing; login session regeneration and logout invalidation/token regeneration are present.
- Normal write forms carry @csrf; web routing uses Laravel defaults with no custom CSRF exclusions found. PHPUnit framework tests generally disable CSRF verification, so passing tests are not proof of deployed CSRF behavior.
- Search/filter values use Eloquent bindings. The fixed COALESCE order expression contains no request interpolation. No confirmed raw-SQL injection path was found.
- Most frontend text uses escaped Blade output; description rendering is escaped. No global unescaped-HTML vulnerability is asserted.
- Product stockOut checks stock while holding a row lock in a DB transaction; checkout wraps order/items/stock in a parent transaction.
- Unique database reference/SKU/slug/email/mobile constraints and important foreign keys exist.
- Schema metadata reads included no customer rows, credentials or transaction values.

## Reliability observations

Inventory locks are a positive code-level guarantee, but eight SQLite service tests do not prove production MySQL concurrency. Nested transactions should participate in one connection, but multi-product lock order/retry handling and rollback on later-line failure require a MySQL test. There is no exactly-once operation reference or stock reservation workflow.

Checkout fails to recheck is_active and uses Product::findOrFail for removed products, with only ValidationException handled. A deleted cart product may produce an unhelpful exception. Decimal storage exists, but arithmetic uses floats; consistent money precision/rounding and order decimal(10,2) versus product decimal(12,2) limits need boundary tests.

HomeController.php:28 loads all active products although home.blade.php displays the first8. Dashboard loads all products, and CustomerController.php:13 loads all orders before grouping. These are confirmed scalability risks, not measured production outages. Eager loading is used on major product/category lists; do not claim an N+1 without a demonstrated query pattern. Checkout does per-line product reads/locks, so query volume scales with lines.

Live schema orders has unique order_number and user_id indexes, but no status/date composite index. Measure query plans with representative data before proposing extra indexes. No MySQL benchmark or persistent-data EXPLAIN was needed for this code audit.

Queue/cache/log configs and jobs/failed-jobs tables exist. No custom dispatch/notification jobs, business scheduler tasks, worker deployment or retry monitoring was found. schedule:list confirms no tasks. Framework defaults are not a notification/report delivery implementation.

## Uploads, integrations and secrets

Product images are remote URLs; no file upload processing is present. This is a missing media workflow, not a demonstrated unsafe-upload or backend SSRF vulnerability. Implement future uploads with MIME/size limits, safe names, image processing, non-executable storage and access policies. External browser image requests have privacy/availability implications, to review separately.

No exposed application API, payment/courier callback or telephony endpoint is present; no API authentication flaw is claimed merely because an API package is absent. Any future provider callback needs authentication, idempotency and reconciliation.

DatabaseSeeder.php:90–114 can generate and print a one-time demo admin password; setup documentation's conditional-account wording is not fully aligned. Do not run demo seeders on production or log generated credentials into shared pipelines. No seeder was executed and no credentials are included here.

Dependency advisory scanning, hosting/WAF controls, TLS/security headers, backup storage and actual production flags were not verified. No recommendation here claims legal/regulatory compliance. Audit-only findings require a separately authorized implementation and operational validation.

