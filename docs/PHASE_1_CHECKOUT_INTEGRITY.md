# Phase 1: Checkout and order integrity

## Confirmed issues and changes

- Session cart prices previously became order prices without a database recheck. Checkout now quotes current product records and rechecks prices, active status, quantity, discount consistency and stock inside the order transaction. Amounts are calculated in paisa before storing existing decimal amounts. Forged request prices, discounts, totals and delivery charges do not enter order calculations.
- A price change refreshes the checkout summary and displays the existing Bengali error feedback. A change after the customer opened checkout rejects submission and requires reviewing a new quote and attempt token. Changed cart contents or account identity also invalidate the old attempt.
- A session-bound UUID identifies each checkout attempt. A unique nullable `orders.checkout_token` index enforces idempotency across database connections. Same-session checkout POSTs also use Laravel session blocking. A successful retry returns the original confirmation without another stock deduction; replaying a completed attempt preserves a newer cart. Separate attempts remain separate orders.
- Order creation reserves the unique token before inventory operations. Product rows are locked in ID order, authoritative totals and order lines are saved, and the existing inventory service records deductions within the same outer transaction. Any failure rolls everything back, including the token reservation, so retries remain possible. Deadlocks receive Laravel's bounded transaction retries.
- No notification or payment processing was added. COD continues to create a pending order, without payment records or outbound notifications.
- Existing cart and checkout have no variant selection or variant-aware stock deduction. Explicit variant submissions and products with variant records are rejected rather than falling through to base-product stock. Full variant purchasing is deferred.

## Database and deployment

Migration: `database/migrations/2026_11_08_134355_add_checkout_token_to_orders_table.php`.

The migration adds one nullable UUID column and a unique index. Historical orders retain null tokens; no historical data is backfilled, replaced or deleted. Rollback removes only this new index and column and therefore loses its attempt keys; do not roll it back while this checkout implementation is serving traffic.

The migration has **not** been applied to the existing application database. Apply the reviewed additive migration before serving the updated code; otherwise order-token queries will fail. Coordinate rollout so old checkout workers cannot continue accepting submissions without idempotency protection. Forms opened before rollout need to reopen checkout to obtain a token.

Production session blocking needs a shared lock-capable cache and server-side sessions. Cookie sessions are unsupported for this route. The database unique constraint remains the final duplicate-order guard, including if a session lock expires. Existing authentication, customer fields, COD method and fixed ৳60 shipping policy are preserved. Settings-based or location-based shipping is outside this phase.

## Verification

Focused HTTP tests: `tests/Feature/CheckoutIntegrityTest.php` (29 tests, 192 assertions). Coverage includes current and changed prices, invalid discounts, inactive/deleted products, unavailable/insufficient stock, invalid quantities and variants, forged amounts, session-bound tokens, cart changes, guest/authenticated COD, correct line totals, duplicate and legitimate repeat orders, failure rollback, safe retry, confirmation, and existing cart add/update/remove routes.

MySQL integration tests: `tests/Feature/CheckoutConcurrencyTest.php`, with two independent PHP processes using `tests/Support/checkout-worker.php`. Tests race the same token and distinct tokens competing for the final unit. Both passed on local MySQL (2 tests, 26 assertions). They exercise the service/database concurrency boundary; HTTP/session validation is covered separately by the feature tests.

Default regression command:

```bash
APP_ENV=testing DB_CONNECTION=sqlite DB_DATABASE=:memory: DB_URL='' CACHE_STORE=array SESSION_DRIVER=array MAIL_MAILER=array QUEUE_CONNECTION=sync LOG_CHANNEL=null vendor/bin/phpunit --do-not-cache-result
```

Result: 76 passed, 285 assertions; the 2 opt-in MySQL tests are skipped in this command.

The complete suite was also executed with `CHECKOUT_MYSQL_TESTS=1` and the same isolated environment: **78 passed, 311 assertions, zero failures and zero skips**.

MySQL integration command:

```bash
CHECKOUT_MYSQL_TESTS=1 APP_ENV=testing DB_CONNECTION=sqlite DB_DATABASE=:memory: DB_URL='' CACHE_STORE=array SESSION_DRIVER=array LOG_CHANNEL=null vendor/bin/phpunit tests/Feature/CheckoutConcurrencyTest.php --do-not-cache-result
```

The MySQL tests require local MySQL credentials with create/drop-database privileges. Each test generates its own random `best_choice_checkout_test_<hex>` database, migrates only that new database, and drops only the database it created. No existing application, staging or production database is migrated, seeded or cleared. The subprocess refuses any other database name. Tests intentionally fail if opted in and MySQL cannot be reached.

`npm run build`, checkout route listing, targeted Pint formatting checks and PHP syntax checks on all 10 changed PHP files passed. No browser-based visual test or production smoke order was performed. Git diff/status is unavailable because this workspace has no Git repository metadata; pre-task SHA-256 hashes and source review verify the limited scope instead. Cart, storefront/admin layouts, CSS, JavaScript and design references are unchanged. Checkout markup changes only by adding a hidden attempt token; feedback uses existing components.

## Remaining risks and Phase 2 dependencies

- Complete variant selection, authoritative variant pricing, eligibility, locking and variant-level inventory before enabling variant purchases. Products with any variant records remain unavailable at checkout in Phase 1.
- Unify admin stock edits and all other inventory writers through a consistent locked ledger path. Checkout prevents overselling between checkout requests, but unrelated stale stock overwrites elsewhere are not repaired here.
- Implement transactional, idempotent cancellation/return restocking and explicit inventory-to-order references. Existing cancellation behavior and ledger schema are unchanged.
- Add a transactional after-commit/outbox strategy before introducing external payment or notification side effects. Current checkout has none to dispatch prematurely.
- Multi-tab cart mutation and simultaneous checkout GET/session writes are not globally serialized; only checkout POST is session-blocked. The immutable attempt fingerprint rejects stale cart submissions, and the unique index protects repeated attempts, but broader session/cart concurrency is a separate concern.
- Existing short order-number generation can theoretically collide; failures roll back and remain retryable. Replacing the order-reference scheme is deferred.
- Production migration execution, deployment coordination, distributed-cache validation and live smoke testing remain deployment work; no live data was modified for this task.

## Files changed

- `app/Http/Controllers/Storefront/CartController.php`
- `app/Http/Controllers/Storefront/CheckoutController.php`
- `app/Models/Order.php`
- `app/Services/Cart/CartService.php`
- `app/Services/CheckoutService.php` (new)
- `database/migrations/2026_11_08_134355_add_checkout_token_to_orders_table.php` (new)
- `resources/views/storefront/checkout/index.blade.php`
- `routes/web.php`
- `tests/Feature/CheckoutIntegrityTest.php` (new)
- `tests/Feature/CheckoutConcurrencyTest.php` (new)
- `tests/Support/checkout-worker.php` (new)
- `docs/PHASE_1_CHECKOUT_INTEGRITY.md` (new)
