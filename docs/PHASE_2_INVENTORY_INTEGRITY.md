# Phase 2: Inventory integrity, cancellation, returns and variants

## Architecture review and conflicting assumptions

The existing application deducts stock immediately at checkout, including pending COD orders. This policy is retained: processing/shipping does not deduct again. Phase 1 checkout tokens, database uniqueness, current-price review and transaction boundaries remain authoritative.

Confirmed before implementation: admin product creation/editing wrote stock directly; stock history lacked operation keys and structured order-line references; arbitrary order status changes had no restoration policy; variants existed in the schema but not in cart/order/inventory processing; product deletion could cascade inventory history. The existing locked, transactional inventory service was extended rather than replaced.

Legacy order status is not deduction evidence. Checkout once wrote only free-text order numbers into ledger reasons, and demo order seeding did not deduct stock at all. Existing parent stock and variant stock also have no verified aggregate relationship. No historical quantities, combinations, order items or ledger records are automatically rewritten.

The approved admin reference contains stock adjustment/history components, but no dedicated variant or return manager. Required new interfaces reuse existing panels, fields, table wrappers, buttons, fonts and icons. Storefront changes are limited to selecting/displaying variants and directing variant purchases through product details. The existing product grids, headers, footer and mobile navigation remain intact.

## Inventory ledger rules

- All application stock mutation paths use `InventoryService`: opening stock, stock in/out, absolute adjustment, checkout, cancellation and return restocking.
- Lock the product first, then its exact variant when applicable. Multi-product checkout takes parent locks in product ID order. Order lifecycle work locks the order and its item rows before stock owners. Transactions retry deadlocks up to three times where configured.
- Stock changes and their ledger entries commit together. Negative stock, nonpositive stock-in/out requests and quantities outside the supported integer bounds are rejected.
- Admin stock forms carry UUID operation keys. Checkout and restoration use deterministic operation keys. Replaying a matching operation returns its existing ledger entry; conflicting reuse is rejected. Absolute stock edits carry the observed stock baseline, so a stale form cannot overwrite a concurrent deduction.
- Simple product stock and variant stock are separate owners. Variant products must have zero parent stock; stock is never copied or allocated into variants implicitly. Opening stock starts at zero and is explicitly recorded through the service.
- Product/variant history is protected from model-level deletion. The admin product delete action archives products with order, variant or stock history. Existing historical rows are retained.
- Inventory references use the existing `reference_type` and `reference_id`: `order_item` for checkout/cancellation, `order_return` for return restocking. New operation keys are unique; old ledger keys remain null.

## Cancellation rules

Admin users may transition pending → processing/cancelled, processing → shipped/cancelled, shipped → delivered. Delivered and cancelled are terminal; fulfillment reversal goes through returns. Repeating the current status is a no-op.

Cancellation requires a reason. Every line must have a matching structured checkout deduction and consistent deducted/restored counters. Unverified legacy or empty orders are blocked for reconciliation. The transaction restores only the remaining proven quantity to the original simple product or exact variant, writes an idempotent ledger movement and status history, and retains all order items, totals and payment data.

Full reasons remain in order/status history. Ledger reasons are capped to the existing 255-character column without truncating the full audit history.

## Return rules

Admin-assisted returns are available for delivered orders. Each return records one order item and a partial quantity; multiple item returns can be recorded separately. Non-rejected returns reserve their quantities under the order lock so simultaneous requests cannot exceed the originally deducted quantity.

State transitions:

```text
requested → approved → received → restocked → completed
          ↘ rejected            ↘ completed (non-restock disposition)
```

Requesting or approving a return does not increase sellable stock. Only a received return with an explicit sellable inspection note can be restocked. Non-sellable received returns can instead be completed with a disposition note. Restocking is atomic, bounded by the remaining original deduction, and protected by a unique return ledger operation. Repeated status submissions do not restore stock twice. Every return stage records actor, time and note in the existing order history stream with a return-specific reference label.

No refunds are issued, and no order financial history is edited. Customer self-service return authorization and payment/refund processing remain separate future work.

## Variant model and purchasing

Variants retain their existing SKU, price override, options, stock and active fields. Additive size/color fields and a normalized combination hash prevent duplicate size/color combinations per product. A nullable variant regular price and existing selling override independently fall back to parent prices. Effective selling/regular price consistency is checked server-side.

The product detail forms require a valid selection for variant products. Each cart line is identified by product plus variant, while existing simple-product keys stay compatible. Quantity updates/removal affect only that line. Checkout rechecks exact ownership, active status, typed combination, price and stock under locks, then snapshots variant identity/name/SKU and authoritative prices into the order line. Cancellations and returns use that immutable stock owner.

Existing untyped variants are not inferred from free-form options. Mixed legacy parent/variant stock remains blocked until reviewed reconciliation. Creating a first variant is blocked if unrestored simple-product order lines exist; historical size/color combinations cannot be repurposed. Create a separate variant product when changing an existing sold SKU's inventory model.

## Database changes and deployment order

Migration: `database/migrations/2026_11_08_140232_add_inventory_integrity_and_returns.php`.

The additive migration adds variant attributes/unique combination keys, a nullable unique ledger operation key, nullable order-line variant snapshots plus deduction/restoration counters, cancellation metadata, and return/status-history tables. New relationship deletion restrictions preserve order/return history. Historical rows receive only schema defaults/nulls; no reconciliation or stock transfer is performed.

Deployment steps:

1. Back up the database and review legacy reconciliation needs separately.
2. Drain checkout/admin writes and workers while deploying, so old stock writers cannot bypass the new ledger contract.
3. Apply the pending Phase 1 checkout-token migration first, then the Phase 2 additive migration, before serving the updated code. Rehearse against a restored isolated copy; check MySQL DDL locking time on realistic table sizes.
4. Build assets and restart workers. Confirm shared lock-capable cache and server-side sessions for checkout session blocking.
5. Smoke-test with explicitly authorized test data: simple/variant purchase, duplicate submit, stock edit, cancellation and partial return.
6. Reopen traffic only after the smoke checks and operational reconciliation review pass.

Neither migration has been applied to the existing application database in this task. Do not roll back the Phase 2 schema after recording live movements: rollback removes new audit structures. Prefer a forward fix or a coordinated restore/reconciliation plan.

## Separate legacy reconciliation plan

Do not replay checkout deductions. Export legacy order lines and relevant ledger records read-only, then classify each line as proven deducted, proven not deducted or ambiguous. Match exact product, reason/order number, quantity, movement arithmetic and subsequent restorations; free-text matches alone are insufficient. Have an authorized operator review the evidence and physical stock.

For proven deductions only, prepare a separate reviewed script that links existing movements and initializes line counters without moving stock. For ambiguous or unmatched records, retain the block and reconcile manually with a separately authorized adjustment. Review existing parent/variant quantities and duplicate combinations before assigning typed size/color keys. Never copy parent quantities into every variant. No reconciliation script or historical backfill is executed here.

## Verification

The full isolated regression suite passed: **275 tests, 1,609 assertions, zero failures and zero skips when MySQL concurrency tests were enabled**. The default SQLite run passed 263 tests with 12 intentionally skipped opt-in MySQL tests. Coverage includes inventory operations and bounds, idempotent operation keys, stale stock baselines, variant ownership and pricing, simple and variant checkout, duplicate checkout, cancellation, partial returns, exact-once restocking, authorization, validation, deletion/archive guards, and legacy fail-closed behavior.

The 12 MySQL cases used separate PHP worker processes and random temporary databases created only for each test. They covered same-attempt checkout retries, competing simple and variant stock, concurrent inventory operations, cancellation restoration, return restocking and operation-key replay. Each temporary database was migrated and dropped by the test harness; the existing application database was not used or modified.

`npm run build` passed. Checkout, product, variant, order, return and inventory routes were listed successfully. Pint formatting passed for changed PHP files, PHP syntax passed for 31 changed PHP files, and the whitespace check passed. Isolated preview pages had no horizontal overflow at 360, 390, 768 or 1440 pixels. The preview covered product selection, variant management, inventory, cancellation and returns. No browser interaction against a live application database was performed.

The source-hash scope check found no deleted files. Approved storefront layout/partials, JavaScript, storefront CSS and design-reference files remained unchanged. Git diff/status is unavailable because this workspace has no Git metadata.

## Remaining risks and next phase

- Existing legacy cancellation/return and mixed variant records deliberately require reconciliation.
- Database administrators/raw SQL can bypass application deletion and ledger safeguards; existing cascade foreign keys are not rewritten in this additive migration. Restrict direct writes and use reviewed operational procedures.
- Production deployment, shared-cache checks, backup/restore rehearsal and live smoke orders remain operational work.
- Existing dashboard prototype actions and unrelated reporting are outside Phase 2. Use the real product/inventory/order administration routes for persisted operations.
- Phase 3 should cover manual COD collection/settlement and refund accounting, courier failure/RTO policy, customer-owned order/return interfaces, notification outbox processing, operational reconciliation tools and release/backup automation. No real payment integrations are enabled here.

## Files modified

Application and schema:

- `app/Http/Controllers/Admin/InventoryController.php`
- `app/Http/Controllers/Admin/OrderController.php`
- `app/Http/Controllers/Admin/OrderReturnController.php`
- `app/Http/Controllers/Admin/ProductController.php`
- `app/Http/Controllers/Admin/ProductVariantController.php`
- `app/Http/Controllers/Storefront/CartController.php`
- `app/Http/Controllers/Storefront/CategoryController.php`
- `app/Http/Controllers/Storefront/CheckoutController.php`
- `app/Http/Controllers/Storefront/HomeController.php`
- `app/Http/Controllers/Storefront/SearchController.php`
- `app/Http/Controllers/Storefront/WishlistController.php`
- `app/Models/InventoryTransaction.php`
- `app/Models/Order.php`
- `app/Models/OrderItem.php`
- `app/Models/OrderReturn.php`
- `app/Models/OrderStatusHistory.php`
- `app/Models/Product.php`
- `app/Models/ProductVariant.php`
- `app/Services/Cart/CartService.php`
- `app/Services/CheckoutService.php`
- `app/Services/Inventory/InventoryService.php`
- `app/Services/Orders/OrderLifecycleService.php`
- `database/migrations/2026_11_08_140232_add_inventory_integrity_and_returns.php`
- `routes/web.php`

Views and styling:

- `resources/css/admin-overrides.css`
- `resources/views/admin/inventory/index.blade.php`
- `resources/views/admin/orders/show.blade.php`
- `resources/views/admin/products/_form.blade.php`
- `resources/views/admin/products/_variants.blade.php`
- `resources/views/admin/products/edit.blade.php`
- `resources/views/storefront/cart/index.blade.php`
- `resources/views/storefront/checkout/index.blade.php`
- `resources/views/storefront/components/product-card.blade.php`
- `resources/views/storefront/home.blade.php`
- `resources/views/storefront/products/show.blade.php`

Tests and documentation:

- `tests/Feature/AdminInventoryManagementTest.php`
- `tests/Feature/CheckoutConcurrencyTest.php`
- `tests/Feature/InventoryIntegrityTest.php`
- `tests/Feature/OrderAdministrationTest.php`
- `tests/Feature/OrderLifecycleTest.php`
- `tests/Feature/VariantCheckoutTest.php`
- `tests/Support/checkout-worker.php`
- `docs/PHASE_2_INVENTORY_INTEGRITY.md`
