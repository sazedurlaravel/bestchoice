# E-commerce implementation roadmap

Audit date: 2026-11-08. Planning only; no feature implementation is authorized by this report. Preserve working Laravel workflows and approved UI. Feature-level evidence is in [the inventory](ECOMMERCE_FEATURE_AUDIT.md).

## Planning assumptions and effort

One experienced Laravel developer, eight productive hours per developer-day. Estimates are preliminary engineering ranges including scoped implementation and review; they depend on business decisions, integration requirements and tests. They are not a fixed quotation or elapsed calendar promise. Existing correctly implemented features are excluded from remaining work.

- **Reliable single-SKU, COD/manual-courier MVP:** W01–W10 + W13 + W14 = **164–304 hours (20.5–38 developer-days)**.
- **Size/color clothing assortment:** add W11 **44–72 hours**; conditional MVP total **208–376 hours (26–47 developer-days)**. Variant scope must be an explicit merchant decision.
- **Additional production/product/operations hardening:** W12 + W15 + W20 = **88–156 hours (11–19.5 days)** beyond the MVP above. This includes a reporting foundation and account/discovery/media extensions, not a claim all are mandatory to release.
- **One courier + one gateway + transactional notification channel:** W16–W18 = **108–196 hours (13.5–24.5 days)**, optional beyond a manual COD MVP.
- **Optional marketing/SEO and AI/voice/loyalty pilot scope:** W19 + W21 = **176–344 hours (22–43 days)**. The AI estimate covers a bounded experiment/pilot; multiple languages/providers, training, telephony rollout and advanced BI require separate scoping.

These package totals are additive only across the named scopes. Per-feature row estimates below overlap within packages and must not be summed; the checklist repeats capabilities in multiple contexts. W06 estimates cross-workflow acceptance/concurrency/CI work; package implementation includes its local regression checks, avoiding a second full test effort.

Third-party merchant onboarding, identity/compliance approvals, sandbox credentials, SMS sender registration, courier contracts, policy-copy review and hosting access are **external waiting time**, excluded from engineering ranges. Obtain provider timelines rather than promising an arbitrary wait. Consider a15–25% reserve after requirements review; it is not already added to totals.

## Phase order adapted to the existing application

### Phase 0 — Prove and fix business integrity first

W01–W04. Exit only when current-price/eligibility checks, configured shipping, valid cart stock, once-only order creation and cancellation/restock policy are agreed and covered. Critical policy decisions: price changes after add, immediate deduction versus reservation, allowed status transitions, exactly-once reversal and manual failed-delivery handling.

Do not rebuild InventoryService: preserve its row lock/transaction/negative-stock guard. Extend the lifecycle around it. Do not introduce mandatory checkout email or division.

### Phase 1 — Reliable administration and release foundation

W05–W08 + W14; begin W06 alongside Phase0. Use real server CRUD endpoints for dashboard actions. Replace sample charts/capped totals, protect inventory history, validate catalog constraints, verify auth mutation boundaries and add CI/backup/restore/release evidence.

A reliable release requires these checks even if online gateways and AI remain deferred. Deployment stays out of this task.

### Phase 2 — Complete the chosen MVP operating model

W09, W10, W13; W11 only if the actual clothing SKUs need independent sizes/colors. Fix dead information links with approved business content; establish manual COD payment/dispatch/failure/reconciliation records; run responsive and accessibility acceptance.

Keep approved green/gold, Bengali fonts,5 desktop/3 mobile product columns, portrait images, live routes and existing icon-only bottom shortcuts.

### Phase 3 — Complete catalog, account, media and reporting operations

W12, W15, W20. Add gallery/upload management, optional detail controls, valid filters/sort, full owned-order detail/history, password reset and scoped CRM. Define cost/expense/payment ledgers before promising gross/net profit, product margins, valuation or CLV.

### Phase 4 — Bangladesh provider integrations

W16–W18. Integrate one provider at a time using sandbox/fixtures. Booking/payment callbacks must be authenticated, idempotent and auditable; use retries, deduplication and reconciliation. Real charges/bookings require separate authorization.

### Phase 5 — Marketing and measurement

W19 after reliable orders/identity/consent/notifications. Coupons need authoritative server rules and usage/expiry controls, not the inert dashboard demo. Analytics/SEO must respect the chosen consent and content plan.

### Phase 6 — Optional AI and advanced automation

W21 after stable operational data and explicit business approval. Bengali voice pilot requires provider spend caps, opt-in/opt-out, recording-retention rules, limited retries, quiet-hour policy and human review. Do not treat AI absence as a COD launch blocker.

## Work packages

| Package | Scope | Priority / phase | Complexity / technical risk | Dependencies | Remaining effort |
| --- | --- | --- | --- | --- | --- |
| W01: Checkout price and eligibility integrity | Reprice live product/variant rows, reject inactive/deleted items, validate stock on cart updates; preserve cart behavior. | P0 / 0 | Medium / High | Catalog pricing policy; line snapshot design | 12–20 h |
| W02: Order lifecycle and inventory audit safety | Define transitions, atomic idempotent restock/reversals, order-line ledger references; protect product stock edits and history deletion. | P0 / 0 | High / High | Cancellation/return policy; InventoryService | 20–36 h |
| W03: Duplicate-submit and transactional checkout protection | Idempotent placement, unique operation keys, deadlock retry policy; prove rollback and concurrent behavior. | P0 / 0 | High / High | W01; checkout token/session strategy | 12–24 h |
| W04: Delivery settings and checkout validation | Connect real settings, validate BD contact inputs, preserve optional additional number, address and notes. | P1 / 0 | Medium / Medium | Single flat-rate policy and phone normalization | 8–16 h |
| W05: Persisted admin actions and accurate dashboard | Wire dashboard actions to real endpoints, resolve duplicate navigation, replace fake charts and capped totals; retain approved shell. | P1 / 1 | Medium / Medium | Server CRUD contract; revenue/order-count definitions | 16–28 h |
| W06: Core regression tests and CI | Cover cart/checkout/rollback/status transitions/admin CRUD/auth boundaries; MySQL race tests and repeatable CI. | P1 / 1 | Medium / Medium | W01–W05; isolated MySQL test database | 20–32 h |
| W07: Catalog/category validation and deletion guards | Validate pricing relationships and actual DB lengths; handle sold-product delete; prevent category cycles/slug collisions. | P1 / 1 | Medium / Medium | Unique slug policy; historical data retention | 12–24 h |
| W08: Public endpoint and operational security hardening | Checkout/tracking abuse limits, tracking privacy, session settings review, bounded errors and sensitive-action logs. | P1 / 1 | Medium / Medium | TLS/deployment settings; monitoring owner | 8–16 h |
| W09: Essential policy/support content and navigation | Resolve dead navigation, publish essential shipping/return/privacy/terms/support content; no invented business claims. | P1 / 2 | Low / Low | Merchant-approved policy/contact copy | 12–24 h |
| W10: Minimum manual COD fulfillment operations | Track payment due/collected, dispatch/tracking details and failed delivery/RTO; manual mode can precede APIs. | P1 / 2 | Medium / Medium | W02; merchant manual-courier process | 12–24 h |
| W11: Clothing variant purchase/stock workflow (conditional MVP) | Finish existing variant model through management, selectors, cart identity, order snapshot and per-variant locking/returns. | P1 / 2 | High / High | Actual size/color/SKU policy; W01/W02/W06 | 44–72 h |
| W12: Media management and product detail completion | Safe media upload/delete, gallery, structured specs and optional quantity/size guide; keep real stock/prices. | P2 / 3 | Medium / Medium | Storage/upload policy; existing images model | 16–28 h |
| W13: Storefront/admin usability and visual QA | Verify mobile forms, search, icon names, focus and wishlist errors; apply small scoped fixes and screenshot comparisons. | P1 / 2 | Medium / Medium | Approved references; representative fixtures | 20–36 h |
| W14: Backup/restore and deployment release gate | Document and rehearse restore, production TLS/cookies/debug/asset checks, rollback and operational alerts. | P1 / 1 | Medium / High | Hosting access; backup storage/retention | 12–24 h |
| W15: Catalog discovery, account and CRM hardening | Filters/sort, descendant listing, password reset, paginated owned-order details, optional address book and customer search/spend. | P2 / 3 | Medium / Medium | Customer identity policy; product attributes | 32–56 h |
| W16: One courier provider integration | Authenticated booking, webhook/poll sync, idempotency, retries, failure/RTO and settlement reconciliation. | P2 / 4 | High / High | Provider sandbox/approval; W02/W10; queues | 40–72 h |
| W17: One online payment gateway | Initiate/verify payments, authenticate callbacks, prevent duplicates, reconcile/refund; retain COD. | P2 / 4 | High / High | Merchant contract/sandbox; transaction ledger; W03 | 48–88 h |
| W18: Transactional customer notifications | Queued SMS/email templates, deduplication, delivery status, retries and failure review. | P2 / 4 | Medium / Medium | Provider approval, consent, queues, retry logs | 20–36 h |
| W19: Foundational marketing/SEO/measurement | Real coupons, reviews, analytics, SEO metadata/sitemap/schema and optional cart recovery; all deferred by default. | P3 / 5 | Medium / Medium | Consent plan; marketing rules; W02/W18 | 56–104 h |
| W20: Financial/operations reporting foundation | Accurate revenue/profit/returns/settlements, inventory valuation and CSV/Excel; no profit claims from gross order totals. | P2 / 3 | High / High | COGS/expense/payment/COD ledgers; finance definitions | 40–72 h |
| W21: Optional AI/voice/loyalty experiments | Bengali support/voice pilot, recommendations, optional loyalty/referrals; production rollout estimated separately. | P3 / 6 | High / High | Consent/legal review, telephony sandbox, spend caps, human fallback | 120–240 h |

## Feature-level estimation ledger

Each incomplete feature has a preliminary range, complexity, risk, dependency and phase. These are discovery estimates for that feature's contribution, not standalone sums. Multiple entries may be resolved by the same change (e.g. configured shipping appears in cart, checkout and delivery settings). IMPLEMENTED entries are intentionally omitted.

Classification is inherited from the audited feature inventory. For a future API authentication/payment integration, estimate applies only if the provider/API is chosen. Missing privacy/support content requires approved merchant copy, not assumed policy text.

| Feature ID / name | Classification / priority | Package / phase | Complexity | Technical risk | Estimated contribution | Dependencies |
| --- | --- | --- | --- | --- | --- | --- |
| 41-06: Featured products | PARTIAL / P2 | W13 / 2 | Medium | Medium | 4–16 h | Active products/categories; shared header, CSS, session. |
| 41-07: New arrivals | PARTIAL / P2 | W13 / 2 | Medium | Medium | 4–16 h | Active products/categories; shared header, CSS, session. |
| 41-08: Best-selling products | MISSING / P3 | W13 / 2 | Medium | Medium | 4–16 h | Active products/categories; shared header, CSS, session. |
| 41-12: Header navigation | BROKEN / P2 | W09 / 2 | Low | Low | 2–12 h | Business-approved policy/support copy; optional contact transport. |
| 41-13: Footer | BROKEN / P2 | W09 / 2 | Low | Low | 2–12 h | Business-approved policy/support copy; optional contact transport. |
| 41-14: Mobile bottom navigation | PARTIAL / P2 | W13 / 2 | Medium | Medium | 4–16 h | Approved mockups, representative fixtures, browser accessibility/viewport review. |
| 41-16: Empty states | PARTIAL / P2 | W13 / 2 | Medium | Medium | 4–16 h | Active products/categories; shared header, CSS, session. |
| 41-17: Responsive layout | PARTIAL / P2 | W13 / 2 | Medium | Medium | 4–16 h | Approved mockups, representative fixtures, browser accessibility/viewport review. |
| 42-05: Price filtering | MISSING / P2 | W15 / 3 | Medium | Medium | 4–16 h | Product query; safe filter validation; pagination. |
| 42-06: Size filtering | MISSING / P2 | W15 / 3 | Medium | Medium | 4–16 h | Product query; safe filter validation; pagination. |
| 42-07: Color filtering | MISSING / P2 | W15 / 3 | Medium | Medium | 4–16 h | Product query; safe filter validation; pagination. |
| 42-08: Stock availability filtering | MISSING / P2 | W15 / 3 | Medium | Medium | 4–16 h | Product query; safe filter validation; pagination. |
| 42-09: Product sorting | MISSING / P2 | W15 / 3 | Medium | Medium | 4–16 h | Product query; safe filter validation; pagination. |
| 42-16: Featured products | PARTIAL / P2 | W15 / 3 | Medium | Medium | 4–16 h | Product query; safe filter validation; pagination. |
| 43-03: Image gallery | MISSING / P2 | W12 / 3 | Medium | Medium | 4–16 h | Product, category, image and variant records; cart. |
| 43-04: Multiple images | PARTIAL / P2 | W12 / 3 | Medium | Medium | 4–16 h | Product, category, image and variant records; cart. |
| 43-05: Image zoom | MISSING / P2 | W12 / 3 | Medium | Medium | 4–16 h | Product, category, image and variant records; cart. |
| 43-06: Product variants | PARTIAL / P2 | W11 / 2 | High | High | 8–24 h | Product, category, image and variant records; cart. |
| 43-07: Size selection | PARTIAL / P2 | W11 / 2 | High | High | 8–24 h | Product, category, image and variant records; cart. |
| 43-08: Color selection | PARTIAL / P2 | W11 / 2 | High | High | 8–24 h | Product, category, image and variant records; cart. |
| 43-09: Size guide | MISSING / P2 | W12 / 3 | Medium | Medium | 4–16 h | Product, category, image and variant records; cart. |
| 43-10: SKU | PARTIAL / P2 | W12 / 3 | Medium | Medium | 4–16 h | Product, category, image and variant records; cart. |
| 43-12: Quantity selector | MISSING / P2 | W12 / 3 | Medium | Medium | 4–16 h | Product, category, image and variant records; cart. |
| 43-15: Wishlist | MISSING / P2 | W12 / 3 | Medium | Medium | 4–16 h | Product, category, image and variant records; cart. |
| 43-16: Related products | MISSING / P2 | W12 / 3 | Medium | Medium | 4–16 h | Product, category, image and variant records; cart. |
| 43-17: Product reviews | MISSING / P2 | W12 / 3 | Medium | Medium | 4–16 h | Product, category, image and variant records; cart. |
| 43-18: Product specifications | PARTIAL / P2 | W12 / 3 | Medium | Medium | 4–16 h | Product, category, image and variant records; cart. |
| 43-19: Product sharing | MISSING / P2 | W12 / 3 | Medium | Medium | 4–16 h | Product, category, image and variant records; cart. |
| 44-07: Manual quantity updates | PARTIAL / P1 | W01 / 0 | Medium | High | 4–16 h | Session driver; live product stock/pricing; checkout. |
| 44-08: Cart persistence | PARTIAL / P1 | W01 / 0 | Medium | High | 4–16 h | Session driver; live product stock/pricing; checkout. |
| 44-09: Product variant handling | MISSING / P1 | W11 / 2 | High | High | 8–24 h | Session driver; live product stock/pricing; checkout. |
| 44-11: Discounts | MISSING / P1 | W01 / 0 | Medium | High | 4–16 h | Session driver; live product stock/pricing; checkout. |
| 44-12: Delivery charges | BROKEN / P1 | W04 / 0 | Medium | Medium | 2–12 h | Session driver; live product stock/pricing; checkout. |
| 44-14: Stock validation | BROKEN / P1 | W01 / 0 | Medium | High | 2–12 h | Session driver; live product stock/pricing; checkout. |
| 44-19: Server-side pricing integrity | BROKEN / P0 | W01 / 0 | Medium | High | 2–12 h | Session driver; live product stock/pricing; checkout. |
| 45-01: Guest checkout | PARTIAL / P1 | W03 / 0 | High | High | 8–24 h | Cart snapshot; InventoryService; Order/OrderItem; delivery policy. |
| 45-02: Customer checkout | PARTIAL / P1 | W03 / 0 | High | High | 8–24 h | Cart snapshot; InventoryService; Order/OrderItem; delivery policy. |
| 45-04: Primary mobile number | PARTIAL / P1 | W04 / 0 | Medium | Medium | 4–16 h | Cart snapshot; InventoryService; Order/OrderItem; delivery policy. |
| 45-05: Additional mobile number | PARTIAL / P1 | W04 / 0 | Medium | Medium | 4–16 h | Cart snapshot; InventoryService; Order/OrderItem; delivery policy. |
| 45-08: Delivery area | MISSING / P2 | W03 / 0 | High | High | 8–24 h | Cart snapshot; InventoryService; Order/OrderItem; delivery policy. |
| 45-09: Delivery charge calculation | BROKEN / P1 | W04 / 0 | Medium | Medium | 2–12 h | Cart snapshot; InventoryService; Order/OrderItem; delivery policy. |
| 45-11: Online payment methods | MISSING / P3 | W03 / 0 | High | High | 8–24 h | Cart snapshot; InventoryService; Order/OrderItem; delivery policy. |
| 45-12: Coupon application | MISSING / P3 | W03 / 0 | High | High | 8–24 h | Cart snapshot; InventoryService; Order/OrderItem; delivery policy. |
| 45-14: Final total validation | BROKEN / P0 | W01 / 0 | Medium | High | 2–12 h | Cart snapshot; InventoryService; Order/OrderItem; delivery policy. |
| 45-16: Duplicate order prevention | PARTIAL / P1 | W03 / 0 | High | High | 8–24 h | Cart snapshot; InventoryService; Order/OrderItem; delivery policy. |
| 45-17: Order placement | PARTIAL / P1 | W03 / 0 | High | High | 8–24 h | Cart snapshot; InventoryService; Order/OrderItem; delivery policy. |
| 45-20: Validation error handling | PARTIAL / P1 | W03 / 0 | High | High | 8–24 h | Cart snapshot; InventoryService; Order/OrderItem; delivery policy. |
| 46-04: Password reset | MISSING / P2 | W15 / 3 | Medium | Medium | 4–16 h | User model, sessions, mail/OTP provider if reset added. |
| 46-06: Address management | MISSING / P2 | W15 / 3 | Medium | Medium | 4–16 h | User model, sessions, mail/OTP provider if reset added. |
| 46-07: Order history | PARTIAL / P2 | W15 / 3 | Medium | Medium | 4–16 h | User model, sessions, mail/OTP provider if reset added. |
| 46-08: Order details | MISSING / P2 | W15 / 3 | Medium | Medium | 4–16 h | User model, sessions, mail/OTP provider if reset added. |
| 46-09: Order tracking | PARTIAL / P2 | W02 / 0 | High | High | 8–24 h | Order/item records, InventoryService, operational fulfillment policy. |
| 46-10: Order cancellation | MISSING / P2 | W15 / 3 | Medium | Medium | 4–16 h | User model, sessions, mail/OTP provider if reset added. |
| 46-11: Return requests | MISSING / P2 | W15 / 3 | Medium | Medium | 4–16 h | User model, sessions, mail/OTP provider if reset added. |
| 46-13: Account security | PARTIAL / P2 | W15 / 3 | Medium | Medium | 4–16 h | User model, sessions, mail/OTP provider if reset added. |
| 47-01: About Us | MISSING / P2 | W09 / 2 | Low | Low | 2–8 h | Business-approved policy/support copy; optional contact transport. |
| 47-02: Contact Us | MISSING / P1 | W09 / 2 | Low | Low | 2–8 h | Business-approved policy/support copy; optional contact transport. |
| 47-03: Privacy Policy | MISSING / P1 | W09 / 2 | Low | Low | 2–8 h | Business-approved policy/support copy; optional contact transport. |
| 47-04: Terms and Conditions | MISSING / P1 | W09 / 2 | Low | Low | 2–8 h | Business-approved policy/support copy; optional contact transport. |
| 47-05: Shipping Policy | MISSING / P1 | W09 / 2 | Low | Low | 2–8 h | Business-approved policy/support copy; optional contact transport. |
| 47-06: Return and Refund Policy | MISSING / P1 | W09 / 2 | Low | Low | 2–8 h | Business-approved policy/support copy; optional contact transport. |
| 47-07: FAQ | MISSING / P2 | W09 / 2 | Low | Low | 2–8 h | Business-approved policy/support copy; optional contact transport. |
| 47-08: Customer support information | PARTIAL / P1 | W09 / 2 | Low | Low | 2–8 h | Business-approved policy/support copy; optional contact transport. |
| 47-09: Dynamic content management | MISSING / P2 | W09 / 2 | Low | Low | 2–8 h | Business-approved policy/support copy; optional contact transport. |
| 51-01: Total orders | BROKEN / P1 | W05 / 1 | Medium | Medium | 2–12 h | Server CRUD routes, KPI definitions, audit-safe read model. |
| 51-02: Total revenue | PARTIAL / P1 | W05 / 1 | Medium | Medium | 4–16 h | Server CRUD routes, KPI definitions, audit-safe read model. |
| 51-03: Total sales | PARTIAL / P1 | W05 / 1 | Medium | Medium | 4–16 h | Server CRUD routes, KPI definitions, audit-safe read model. |
| 51-04: Net profit | MISSING / P1 | W05 / 1 | Medium | Medium | 4–16 h | Server CRUD routes, KPI definitions, audit-safe read model. |
| 51-05: Pending orders | BROKEN / P1 | W05 / 1 | Medium | Medium | 2–12 h | Server CRUD routes, KPI definitions, audit-safe read model. |
| 51-06: Confirmed orders | MISSING / P1 | W05 / 1 | Medium | Medium | 4–16 h | Server CRUD routes, KPI definitions, audit-safe read model. |
| 51-07: Delivered orders | PARTIAL / P1 | W05 / 1 | Medium | Medium | 4–16 h | Server CRUD routes, KPI definitions, audit-safe read model. |
| 51-08: Cancelled orders | PARTIAL / P1 | W05 / 1 | Medium | Medium | 4–16 h | Server CRUD routes, KPI definitions, audit-safe read model. |
| 51-09: Low-stock products | PARTIAL / P1 | W05 / 1 | Medium | Medium | 4–16 h | Server CRUD routes, KPI definitions, audit-safe read model. |
| 51-11: Best-selling products | MISSING / P1 | W05 / 1 | Medium | Medium | 4–16 h | Server CRUD routes, KPI definitions, audit-safe read model. |
| 51-12: Sales charts | BROKEN / P1 | W05 / 1 | Medium | Medium | 2–12 h | Server CRUD routes, KPI definitions, audit-safe read model. |
| 51-13: Date filters | MISSING / P1 | W05 / 1 | Medium | Medium | 4–16 h | Server CRUD routes, KPI definitions, audit-safe read model. |
| 51-14: KPI calculation accuracy | BROKEN / P1 | W05 / 1 | Medium | Medium | 2–12 h | Server CRUD routes, KPI definitions, audit-safe read model. |
| 52-02: Edit product | PARTIAL / P1 | W07 / 1 | Medium | Medium | 4–16 h | Category, Product pricing invariant, inventory service, order-item FK. |
| 52-03: Delete/archive product | PARTIAL / P1 | W07 / 1 | Medium | Medium | 4–16 h | Category, Product pricing invariant, inventory service, order-item FK. |
| 52-06: Product images | PARTIAL / P1 | W07 / 1 | Medium | Medium | 4–16 h | Category, Product pricing invariant, inventory service, order-item FK. |
| 52-07: Multiple image uploads | MISSING / P1 | W07 / 1 | Medium | Medium | 4–16 h | Category, Product pricing invariant, inventory service, order-item FK. |
| 52-08: Image deletion | MISSING / P1 | W07 / 1 | Medium | Medium | 4–16 h | Category, Product pricing invariant, inventory service, order-item FK. |
| 52-13: Discount validation | BROKEN / P1 | W07 / 1 | Medium | Medium | 2–12 h | Category, Product pricing invariant, inventory service, order-item FK. |
| 52-14: Product variants | PARTIAL / P1 | W11 / 2 | High | High | 8–24 h | Category, Product pricing invariant, inventory service, order-item FK. |
| 52-15: Size management | MISSING / P1 | W11 / 2 | High | High | 8–24 h | Category, Product pricing invariant, inventory service, order-item FK. |
| 52-16: Color management | MISSING / P1 | W11 / 2 | High | High | 8–24 h | Category, Product pricing invariant, inventory service, order-item FK. |
| 52-19: Bulk operations | MISSING / P3 | W07 / 1 | Medium | Medium | 4–16 h | Category, Product pricing invariant, inventory service, order-item FK. |
| 52-21: Product validation | PARTIAL / P1 | W07 / 1 | Medium | Medium | 4–16 h | Category, Product pricing invariant, inventory service, order-item FK. |
| 53-03: Category hierarchy | PARTIAL / P1 | W07 / 1 | Medium | Medium | 4–16 h | Self-FK with nullOnDelete; unique slug; product restrict FK. |
| 53-04: Category images | PARTIAL / P1 | W07 / 1 | Medium | Medium | 4–16 h | Self-FK with nullOnDelete; unique slug; product restrict FK. |
| 53-05: Category slugs | BROKEN / P1 | W07 / 1 | Medium | Medium | 2–12 h | Self-FK with nullOnDelete; unique slug; product restrict FK. |
| 53-08: Safe deletion | PARTIAL / P1 | W07 / 1 | Medium | Medium | 4–16 h | Self-FK with nullOnDelete; unique slug; product restrict FK. |
| 54-02: Variant-level stock | MISSING / P1 | W11 / 2 | High | High | 8–24 h | InnoDB row locks; product/variant stock policy; order lifecycle. |
| 54-05: Stock Adjustments | PARTIAL / P1 | W02 / 0 | High | High | 8–24 h | InnoDB row locks; product/variant stock policy; order lifecycle. |
| 54-09: Inventory reports | PARTIAL / P1 | W02 / 0 | High | High | 8–24 h | InnoDB row locks; product/variant stock policy; order lifecycle. |
| 54-11: Stock reservation | MISSING / P2 | W02 / 0 | High | High | 8–24 h | InnoDB row locks; product/variant stock policy; order lifecycle. |
| 54-13: Stock restoration | MISSING / P1 | W02 / 0 | High | High | 8–24 h | InnoDB row locks; product/variant stock policy; order lifecycle. |
| 54-14: Overselling prevention | PARTIAL / P1 | W02 / 0 | High | High | 8–24 h | InnoDB row locks; product/variant stock policy; order lifecycle. |
| 54-16: Concurrent order handling | PARTIAL / P1 | W02 / 0 | High | High | 8–24 h | InnoDB row locks; product/variant stock policy; order lifecycle. |
| 54-19: Duplicate deduction prevention | PARTIAL / P1 | W02 / 0 | High | High | 8–24 h | InnoDB row locks; product/variant stock policy; order lifecycle. |
| 54-20: Cancellation handling | PARTIAL / P1 | W02 / 0 | High | High | 8–24 h | InnoDB row locks; product/variant stock policy; order lifecycle. |
| 54-21: Return handling | MISSING / P1 | W02 / 0 | High | High | 8–24 h | InnoDB row locks; product/variant stock policy; order lifecycle. |
| 54-22: Inventory audit trail | PARTIAL / P1 | W02 / 0 | High | High | 8–24 h | InnoDB row locks; product/variant stock policy; order lifecycle. |
| 55-04: Order confirmation | PARTIAL / P1 | W02 / 0 | High | High | 8–24 h | Order/item records, InventoryService, operational fulfillment policy. |
| 55-05: Order status transitions | PARTIAL / P1 | W02 / 0 | High | High | 8–24 h | Order/item records, InventoryService, operational fulfillment policy. |
| 55-07: Packing | MISSING / P2 | W02 / 0 | High | High | 8–24 h | Order/item records, InventoryService, operational fulfillment policy. |
| 55-10: Cancellation | PARTIAL / P1 | W02 / 0 | High | High | 8–24 h | Order/item records, InventoryService, operational fulfillment policy. |
| 55-11: Return handling | MISSING / P2 | W02 / 0 | High | High | 8–24 h | Order/item records, InventoryService, operational fulfillment policy. |
| 55-12: Refund handling | MISSING / P2 | W02 / 0 | High | High | 8–24 h | Order/item records, InventoryService, operational fulfillment policy. |
| 55-13: Payment status | MISSING / P1 | W02 / 0 | High | High | 8–24 h | Order/item records, InventoryService, operational fulfillment policy. |
| 55-14: Courier information | MISSING / P1 | W02 / 0 | High | High | 8–24 h | Order/item records, InventoryService, operational fulfillment policy. |
| 55-15: Tracking number | MISSING / P1 | W02 / 0 | High | High | 8–24 h | Order/item records, InventoryService, operational fulfillment policy. |
| 55-17: Internal admin notes | MISSING / P2 | W02 / 0 | High | High | 8–24 h | Order/item records, InventoryService, operational fulfillment policy. |
| 55-19: Order history | MISSING / P2 | W02 / 0 | High | High | 8–24 h | Order/item records, InventoryService, operational fulfillment policy. |
| 55-20: Order activity logs | MISSING / P2 | W02 / 0 | High | High | 8–24 h | Order/item records, InventoryService, operational fulfillment policy. |
| 55-21: Printable invoices | MISSING / P2 | W02 / 0 | High | High | 8–24 h | Order/item records, InventoryService, operational fulfillment policy. |
| 55-22: Packing slips | MISSING / P2 | W02 / 0 | High | High | 8–24 h | Order/item records, InventoryService, operational fulfillment policy. |
| 55-23: Duplicate order detection | MISSING / P2 | W02 / 0 | High | High | 8–24 h | Order/item records, InventoryService, operational fulfillment policy. |
| 56-02: Customer details | PARTIAL / P2 | W15 / 3 | Medium | Medium | 4–16 h | Phone normalization, user/order identity, admin middleware. |
| 56-03: Order history | PARTIAL / P2 | W15 / 3 | Medium | Medium | 4–16 h | Phone normalization, user/order identity, admin middleware. |
| 56-04: Total spending | PARTIAL / P2 | W15 / 3 | Medium | Medium | 4–16 h | Phone normalization, user/order identity, admin middleware. |
| 56-05: Saved addresses | MISSING / P2 | W15 / 3 | Medium | Medium | 4–16 h | Phone normalization, user/order identity, admin middleware. |
| 56-06: Customer status | MISSING / P2 | W15 / 3 | Medium | Medium | 4–16 h | Phone normalization, user/order identity, admin middleware. |
| 56-07: Customer search | MISSING / P2 | W15 / 3 | Medium | Medium | 4–16 h | Phone normalization, user/order identity, admin middleware. |
| 56-08: Duplicate phone detection | PARTIAL / P2 | W15 / 3 | Medium | Medium | 4–16 h | Phone normalization, user/order identity, admin middleware. |
| 56-09: Customer notes | MISSING / P2 | W15 / 3 | Medium | Medium | 4–16 h | Phone normalization, user/order identity, admin middleware. |
| 57-03: Staff accounts | PARTIAL / P2 | W08 / 1 | Medium | Medium | 4–16 h | Role model and authorization policy definitions. |
| 57-04: Permission management | MISSING / P2 | W08 / 1 | Medium | Medium | 4–16 h | Role model and authorization policy definitions. |
| 57-06: Authorization policies | MISSING / P2 | W08 / 1 | Medium | Medium | 4–16 h | Role model and authorization policy definitions. |
| 57-07: Activity logs | MISSING / P2 | W08 / 1 | Medium | Medium | 4–16 h | Role model and authorization policy definitions. |
| 57-08: Sensitive action auditing | MISSING / P2 | W08 / 1 | Medium | Medium | 4–16 h | Role model and authorization policy definitions. |
| 61-02: COD payment status | MISSING / P2 | W10 / 2 | Medium | Medium | 4–16 h | Provider contracts/credentials, sandbox, idempotency, signed webhooks, queues and operational ownership. |
| 61-03: COD collection amount | PARTIAL / P2 | W10 / 2 | Medium | Medium | 4–16 h | Provider contracts/credentials, sandbox, idempotency, signed webhooks, queues and operational ownership. |
| 61-04: Courier settlement status | MISSING / P2 | W10 / 2 | Medium | Medium | 4–16 h | Provider contracts/credentials, sandbox, idempotency, signed webhooks, queues and operational ownership. |
| 61-05: Failed delivery handling | MISSING / P2 | W10 / 2 | Medium | Medium | 4–16 h | Provider contracts/credentials, sandbox, idempotency, signed webhooks, queues and operational ownership. |
| 61-06: Return-to-origin tracking | MISSING / P2 | W10 / 2 | Medium | Medium | 4–16 h | Provider contracts/credentials, sandbox, idempotency, signed webhooks, queues and operational ownership. |
| 61-07: COD reconciliation | MISSING / P2 | W10 / 2 | Medium | Medium | 4–16 h | Provider contracts/credentials, sandbox, idempotency, signed webhooks, queues and operational ownership. |
| 62-01: Steadfast | MISSING / P3 | W16 / 4 | High | High | 8–24 h | Provider contracts/credentials, sandbox, idempotency, signed webhooks, queues and operational ownership. |
| 62-02: Pathao Courier | MISSING / P3 | W16 / 4 | High | High | 8–24 h | Provider contracts/credentials, sandbox, idempotency, signed webhooks, queues and operational ownership. |
| 62-03: REDX | MISSING / P3 | W16 / 4 | High | High | 8–24 h | Provider contracts/credentials, sandbox, idempotency, signed webhooks, queues and operational ownership. |
| 62-04: Other configured couriers | MISSING / P3 | W16 / 4 | High | High | 8–24 h | Provider contracts/credentials, sandbox, idempotency, signed webhooks, queues and operational ownership. |
| 62-05: Credentials configuration | MISSING / P3 | W16 / 4 | High | High | 8–24 h | Provider contracts/credentials, sandbox, idempotency, signed webhooks, queues and operational ownership. |
| 62-06: API integration | MISSING / P3 | W16 / 4 | High | High | 8–24 h | Provider contracts/credentials, sandbox, idempotency, signed webhooks, queues and operational ownership. |
| 62-07: Parcel creation | MISSING / P3 | W16 / 4 | High | High | 8–24 h | Provider contracts/credentials, sandbox, idempotency, signed webhooks, queues and operational ownership. |
| 62-08: Delivery status synchronization | MISSING / P3 | W16 / 4 | High | High | 8–24 h | Provider contracts/credentials, sandbox, idempotency, signed webhooks, queues and operational ownership. |
| 62-09: Tracking number | MISSING / P3 | W16 / 4 | High | High | 8–24 h | Provider contracts/credentials, sandbox, idempotency, signed webhooks, queues and operational ownership. |
| 62-10: Courier charge | MISSING / P3 | W16 / 4 | High | High | 8–24 h | Provider contracts/credentials, sandbox, idempotency, signed webhooks, queues and operational ownership. |
| 62-11: COD amount | MISSING / P3 | W16 / 4 | High | High | 8–24 h | Provider contracts/credentials, sandbox, idempotency, signed webhooks, queues and operational ownership. |
| 62-12: Error handling | MISSING / P3 | W16 / 4 | High | High | 8–24 h | Provider contracts/credentials, sandbox, idempotency, signed webhooks, queues and operational ownership. |
| 62-13: Retry handling | MISSING / P3 | W16 / 4 | High | High | 8–24 h | Provider contracts/credentials, sandbox, idempotency, signed webhooks, queues and operational ownership. |
| 62-14: Webhooks, if supported | MISSING / P3 | W16 / 4 | High | High | 8–24 h | Provider contracts/credentials, sandbox, idempotency, signed webhooks, queues and operational ownership. |
| 62-15: Settlement reconciliation | MISSING / P3 | W16 / 4 | High | High | 8–24 h | Provider contracts/credentials, sandbox, idempotency, signed webhooks, queues and operational ownership. |
| 63-02: bKash | MISSING / P3 | W17 / 4 | High | High | 8–24 h | Provider contracts/credentials, sandbox, idempotency, signed webhooks, queues and operational ownership. |
| 63-03: Nagad | MISSING / P3 | W17 / 4 | High | High | 8–24 h | Provider contracts/credentials, sandbox, idempotency, signed webhooks, queues and operational ownership. |
| 63-04: SSLCommerz or other payment gateways | MISSING / P3 | W17 / 4 | High | High | 8–24 h | Provider contracts/credentials, sandbox, idempotency, signed webhooks, queues and operational ownership. |
| 63-05: Payment initiation | MISSING / P3 | W17 / 4 | High | High | 8–24 h | Provider contracts/credentials, sandbox, idempotency, signed webhooks, queues and operational ownership. |
| 63-06: Callback verification | MISSING / P3 | W17 / 4 | High | High | 8–24 h | Provider contracts/credentials, sandbox, idempotency, signed webhooks, queues and operational ownership. |
| 63-07: Webhook validation | MISSING / P3 | W17 / 4 | High | High | 8–24 h | Provider contracts/credentials, sandbox, idempotency, signed webhooks, queues and operational ownership. |
| 63-08: Duplicate callback protection | MISSING / P3 | W17 / 4 | High | High | 8–24 h | Provider contracts/credentials, sandbox, idempotency, signed webhooks, queues and operational ownership. |
| 63-09: Payment reconciliation | MISSING / P3 | W17 / 4 | High | High | 8–24 h | Provider contracts/credentials, sandbox, idempotency, signed webhooks, queues and operational ownership. |
| 63-10: Refund handling | MISSING / P3 | W17 / 4 | High | High | 8–24 h | Provider contracts/credentials, sandbox, idempotency, signed webhooks, queues and operational ownership. |
| 63-11: Payment failure handling | MISSING / P3 | W17 / 4 | High | High | 8–24 h | Provider contracts/credentials, sandbox, idempotency, signed webhooks, queues and operational ownership. |
| 63-12: Transaction logging | MISSING / P3 | W17 / 4 | High | High | 8–24 h | Provider contracts/credentials, sandbox, idempotency, signed webhooks, queues and operational ownership. |
| 64-01: Delivery zones | MISSING / P2 | W10 / 2 | Medium | Medium | 4–16 h | Provider contracts/credentials, sandbox, idempotency, signed webhooks, queues and operational ownership. |
| 64-02: Inside/outside Dhaka charges | MISSING / P2 | W10 / 2 | Medium | Medium | 4–16 h | Provider contracts/credentials, sandbox, idempotency, signed webhooks, queues and operational ownership. |
| 64-03: District and upazila support | PARTIAL / P2 | W10 / 2 | Medium | Medium | 4–16 h | Provider contracts/credentials, sandbox, idempotency, signed webhooks, queues and operational ownership. |
| 64-04: Delivery charge settings | BROKEN / P2 | W04 / 0 | Medium | Medium | 2–12 h | Provider contracts/credentials, sandbox, idempotency, signed webhooks, queues and operational ownership. |
| 64-05: Free delivery thresholds | MISSING / P2 | W10 / 2 | Medium | Medium | 4–16 h | Provider contracts/credentials, sandbox, idempotency, signed webhooks, queues and operational ownership. |
| 64-06: Courier selection | MISSING / P2 | W10 / 2 | Medium | Medium | 4–16 h | Provider contracts/credentials, sandbox, idempotency, signed webhooks, queues and operational ownership. |
| 64-07: Tracking links | MISSING / P2 | W10 / 2 | Medium | Medium | 4–16 h | Provider contracts/credentials, sandbox, idempotency, signed webhooks, queues and operational ownership. |
| 64-08: Delivery status | PARTIAL / P2 | W10 / 2 | Medium | Medium | 4–16 h | Provider contracts/credentials, sandbox, idempotency, signed webhooks, queues and operational ownership. |
| 64-09: Failed delivery management | MISSING / P2 | W10 / 2 | Medium | Medium | 4–16 h | Provider contracts/credentials, sandbox, idempotency, signed webhooks, queues and operational ownership. |
| 65-01: Order confirmation SMS | MISSING / P2 | W18 / 4 | Medium | Medium | 4–16 h | Provider contracts/credentials, sandbox, idempotency, signed webhooks, queues and operational ownership. |
| 65-02: Order status SMS | MISSING / P2 | W18 / 4 | Medium | Medium | 4–16 h | Provider contracts/credentials, sandbox, idempotency, signed webhooks, queues and operational ownership. |
| 65-03: Email notifications | MISSING / P2 | W18 / 4 | Medium | Medium | 4–16 h | Provider contracts/credentials, sandbox, idempotency, signed webhooks, queues and operational ownership. |
| 65-04: Courier notifications | MISSING / P2 | W18 / 4 | Medium | Medium | 4–16 h | Provider contracts/credentials, sandbox, idempotency, signed webhooks, queues and operational ownership. |
| 65-05: Failed notification handling | MISSING / P2 | W18 / 4 | Medium | Medium | 4–16 h | Provider contracts/credentials, sandbox, idempotency, signed webhooks, queues and operational ownership. |
| 65-06: Queued notifications | MISSING / P2 | W18 / 4 | Medium | Medium | 4–16 h | Provider contracts/credentials, sandbox, idempotency, signed webhooks, queues and operational ownership. |
| 65-07: Notification templates | MISSING / P2 | W18 / 4 | Medium | Medium | 4–16 h | Provider contracts/credentials, sandbox, idempotency, signed webhooks, queues and operational ownership. |
| 65-08: Delivery logs | MISSING / P2 | W18 / 4 | Medium | Medium | 4–16 h | Provider contracts/credentials, sandbox, idempotency, signed webhooks, queues and operational ownership. |
| 65-09: Customer consent where applicable | MISSING / P2 | W18 / 4 | Medium | Medium | 4–16 h | Provider contracts/credentials, sandbox, idempotency, signed webhooks, queues and operational ownership. |
| 70-01: Coupon codes | PARTIAL / P3 | W19 / 5 | Medium | Medium | 4–16 h | Consent and measurement plan, customer identity, coupon policy, notification channel. |
| 70-02: Percentage discounts | PARTIAL / P3 | W19 / 5 | Medium | Medium | 4–16 h | Consent and measurement plan, customer identity, coupon policy, notification channel. |
| 70-03: Fixed discounts | PARTIAL / P3 | W19 / 5 | Medium | Medium | 4–16 h | Consent and measurement plan, customer identity, coupon policy, notification channel. |
| 70-04: Coupon expiry | MISSING / P3 | W19 / 5 | Medium | Medium | 4–16 h | Consent and measurement plan, customer identity, coupon policy, notification channel. |
| 70-05: Coupon usage limits | MISSING / P3 | W19 / 5 | Medium | Medium | 4–16 h | Consent and measurement plan, customer identity, coupon policy, notification channel. |
| 70-06: Promotional campaigns | PARTIAL / P3 | W19 / 5 | Medium | Medium | 4–16 h | Consent and measurement plan, customer identity, coupon policy, notification channel. |
| 70-07: Product reviews | MISSING / P3 | W19 / 5 | Medium | Medium | 4–16 h | Consent and measurement plan, customer identity, coupon policy, notification channel. |
| 70-08: Verified purchase reviews | MISSING / P3 | W19 / 5 | Medium | Medium | 4–16 h | Consent and measurement plan, customer identity, coupon policy, notification channel. |
| 70-10: Abandoned cart recovery | MISSING / P3 | W19 / 5 | Medium | Medium | 4–16 h | Consent and measurement plan, customer identity, coupon policy, notification channel. |
| 70-11: Customer loyalty points | MISSING / P3 | W19 / 5 | Medium | Medium | 4–16 h | Consent and measurement plan, customer identity, coupon policy, notification channel. |
| 70-12: Referral programs | MISSING / P3 | W19 / 5 | Medium | Medium | 4–16 h | Consent and measurement plan, customer identity, coupon policy, notification channel. |
| 70-13: Newsletter subscriptions | MISSING / P3 | W19 / 5 | Medium | Medium | 4–16 h | Consent and measurement plan, customer identity, coupon policy, notification channel. |
| 70-14: Meta Pixel | MISSING / P3 | W19 / 5 | Medium | Medium | 4–16 h | Consent and measurement plan, customer identity, coupon policy, notification channel. |
| 70-15: Meta Conversions API | MISSING / P3 | W19 / 5 | Medium | Medium | 4–16 h | Consent and measurement plan, customer identity, coupon policy, notification channel. |
| 70-16: Google Analytics 4 | MISSING / P3 | W19 / 5 | Medium | Medium | 4–16 h | Consent and measurement plan, customer identity, coupon policy, notification channel. |
| 70-17: Google Search Console integration | MISSING / P3 | W19 / 5 | Medium | Medium | 4–16 h | Consent and measurement plan, customer identity, coupon policy, notification channel. |
| 70-18: SEO metadata | BROKEN / P2 | W19 / 5 | Medium | Medium | 2–12 h | Consent and measurement plan, customer identity, coupon policy, notification channel. |
| 70-19: Sitemap | MISSING / P3 | W19 / 5 | Medium | Medium | 4–16 h | Consent and measurement plan, customer identity, coupon policy, notification channel. |
| 70-21: Canonical URLs | MISSING / P3 | W19 / 5 | Medium | Medium | 4–16 h | Consent and measurement plan, customer identity, coupon policy, notification channel. |
| 70-22: Open Graph metadata | MISSING / P3 | W19 / 5 | Medium | Medium | 4–16 h | Consent and measurement plan, customer identity, coupon policy, notification channel. |
| 70-23: Structured product data | MISSING / P3 | W19 / 5 | Medium | Medium | 4–16 h | Consent and measurement plan, customer identity, coupon policy, notification channel. |
| 80-01: Bengali AI customer support | MISSING / P3 | W21 / 6 | High | High | 8–24 h | Consent, telephony spend limits, opt-out, human escalation, queues, signed callbacks. |
| 80-02: AI product recommendations | MISSING / P3 | W21 / 6 | High | High | 8–24 h | Consent, telephony spend limits, opt-out, human escalation, queues, signed callbacks. |
| 80-03: Automated order confirmation | PARTIAL / P3 | W21 / 6 | High | High | 8–24 h | Consent, telephony spend limits, opt-out, human escalation, queues, signed callbacks. |
| 80-04: Bengali AI voice calls | MISSING / P3 | W21 / 6 | High | High | 8–24 h | Consent, telephony spend limits, opt-out, human escalation, queues, signed callbacks. |
| 80-05: Manual call confirmation | PARTIAL / P3 | W21 / 6 | High | High | 8–24 h | Consent, telephony spend limits, opt-out, human escalation, queues, signed callbacks. |
| 80-06: Confirmation call logs | MISSING / P3 | W21 / 6 | High | High | 8–24 h | Consent, telephony spend limits, opt-out, human escalation, queues, signed callbacks. |
| 80-07: Customer response recording/status | MISSING / P3 | W21 / 6 | High | High | 8–24 h | Consent, telephony spend limits, opt-out, human escalation, queues, signed callbacks. |
| 80-08: Failed confirmation handling | MISSING / P3 | W21 / 6 | High | High | 8–24 h | Consent, telephony spend limits, opt-out, human escalation, queues, signed callbacks. |
| 80-09: Human review workflows | PARTIAL / P3 | W21 / 6 | High | High | 8–24 h | Consent, telephony spend limits, opt-out, human escalation, queues, signed callbacks. |
| 80-10: Automated order notifications | MISSING / P3 | W21 / 6 | High | High | 8–24 h | Consent, telephony spend limits, opt-out, human escalation, queues, signed callbacks. |
| 80-11: Abandoned cart reminders | MISSING / P3 | W21 / 6 | High | High | 8–24 h | Consent, telephony spend limits, opt-out, human escalation, queues, signed callbacks. |
| 80-12: Low-stock notifications | MISSING / P3 | W21 / 6 | High | High | 8–24 h | Consent, telephony spend limits, opt-out, human escalation, queues, signed callbacks. |
| 80-13: Scheduled reports | MISSING / P3 | W21 / 6 | High | High | 8–24 h | Consent, telephony spend limits, opt-out, human escalation, queues, signed callbacks. |
| 90-01: Daily sales | BROKEN / P2 | W20 / 3 | High | High | 2–12 h | Revenue recognition policy; order/payment lifecycle; cost/expense ledgers. |
| 90-02: Weekly sales | MISSING / P2 | W20 / 3 | High | High | 8–24 h | Revenue recognition policy; order/payment lifecycle; cost/expense ledgers. |
| 90-03: Monthly sales | MISSING / P2 | W20 / 3 | High | High | 8–24 h | Revenue recognition policy; order/payment lifecycle; cost/expense ledgers. |
| 90-04: Revenue reports | PARTIAL / P2 | W20 / 3 | High | High | 8–24 h | Revenue recognition policy; order/payment lifecycle; cost/expense ledgers. |
| 90-05: Gross profit | MISSING / P2 | W20 / 3 | High | High | 8–24 h | Revenue recognition policy; order/payment lifecycle; cost/expense ledgers. |
| 90-06: Net profit | MISSING / P2 | W20 / 3 | High | High | 8–24 h | Revenue recognition policy; order/payment lifecycle; cost/expense ledgers. |
| 90-07: Cost of goods sold | MISSING / P2 | W20 / 3 | High | High | 8–24 h | Revenue recognition policy; order/payment lifecycle; cost/expense ledgers. |
| 90-08: Product profitability | MISSING / P2 | W20 / 3 | High | High | 8–24 h | Revenue recognition policy; order/payment lifecycle; cost/expense ledgers. |
| 90-09: Delivery costs | PARTIAL / P2 | W20 / 3 | High | High | 8–24 h | Revenue recognition policy; order/payment lifecycle; cost/expense ledgers. |
| 90-10: Payment fees | MISSING / P2 | W20 / 3 | High | High | 8–24 h | Revenue recognition policy; order/payment lifecycle; cost/expense ledgers. |
| 90-11: Refund totals | MISSING / P2 | W20 / 3 | High | High | 8–24 h | Revenue recognition policy; order/payment lifecycle; cost/expense ledgers. |
| 90-12: Return-to-origin costs | MISSING / P2 | W20 / 3 | High | High | 8–24 h | Revenue recognition policy; order/payment lifecycle; cost/expense ledgers. |
| 90-13: Outstanding COD settlements | MISSING / P2 | W20 / 3 | High | High | 8–24 h | Revenue recognition policy; order/payment lifecycle; cost/expense ledgers. |
| 90-14: Best-selling products | PARTIAL / P2 | W20 / 3 | High | High | 8–24 h | Revenue recognition policy; order/payment lifecycle; cost/expense ledgers. |
| 90-15: Slow-moving inventory | MISSING / P2 | W20 / 3 | High | High | 8–24 h | Revenue recognition policy; order/payment lifecycle; cost/expense ledgers. |
| 90-16: Stock valuation | MISSING / P2 | W20 / 3 | High | High | 8–24 h | Revenue recognition policy; order/payment lifecycle; cost/expense ledgers. |
| 90-17: Customer lifetime value | MISSING / P2 | W20 / 3 | High | High | 8–24 h | Revenue recognition policy; order/payment lifecycle; cost/expense ledgers. |
| 90-18: CSV/Excel exports | PARTIAL / P2 | W20 / 3 | High | High | 8–24 h | Revenue recognition policy; order/payment lifecycle; cost/expense ledgers. |
| 10-02: Authorization | PARTIAL / P1 | W08 / 1 | Medium | Medium | 4–16 h | Deployment TLS/configuration and business action safety. |
| 10-04: Input validation | PARTIAL / P1 | W08 / 1 | Medium | Medium | 4–16 h | Deployment TLS/configuration and business action safety. |
| 10-06: XSS protection | PARTIAL / P1 | W08 / 1 | Medium | Medium | 4–16 h | Deployment TLS/configuration and business action safety. |
| 10-07: File upload security | MISSING / P1 | W08 / 1 | Medium | Medium | 4–16 h | Deployment TLS/configuration and business action safety. |
| 10-09: Rate limiting | PARTIAL / P1 | W08 / 1 | Medium | Medium | 4–16 h | Deployment TLS/configuration and business action safety. |
| 10-10: Session security | PARTIAL / P1 | W08 / 1 | Medium | Medium | 4–16 h | Deployment TLS/configuration and business action safety. |
| 10-11: API authentication | MISSING / P3 | W08 / 1 | Medium | Medium | 4–16 h | Deployment TLS/configuration and business action safety. |
| 10-12: Sensitive data exposure | PARTIAL / P1 | W08 / 1 | Medium | Medium | 4–16 h | Deployment TLS/configuration and business action safety. |
| 10-13: Environment configuration | PARTIAL / P1 | W08 / 1 | Medium | Medium | 4–16 h | Deployment TLS/configuration and business action safety. |
| 10-14: Error handling | PARTIAL / P1 | W08 / 1 | Medium | Medium | 4–16 h | Deployment TLS/configuration and business action safety. |
| 10-15: Logging | PARTIAL / P1 | W08 / 1 | Medium | Medium | 4–16 h | Deployment TLS/configuration and business action safety. |
| 10-16: Queue configuration | PARTIAL / P1 | W14 / 1 | Medium | High | 4–16 h | Hosting, TLS, MySQL backups, monitoring, retention, cache/worker strategy. |
| 10-17: Scheduler configuration | PARTIAL / P1 | W14 / 1 | Medium | High | 4–16 h | Hosting, TLS, MySQL backups, monitoring, retention, cache/worker strategy. |
| 10-18: Database indexes | PARTIAL / P1 | W14 / 1 | Medium | High | 4–16 h | Hosting, TLS, MySQL backups, monitoring, retention, cache/worker strategy. |
| 10-19: N+1 queries | PARTIAL / P1 | W14 / 1 | Medium | High | 4–16 h | Hosting, TLS, MySQL backups, monitoring, retention, cache/worker strategy. |
| 10-20: Transaction safety | PARTIAL / P1 | W08 / 1 | Medium | Medium | 4–16 h | Deployment TLS/configuration and business action safety. |
| 10-21: Backup strategy | MISSING / P1 | W14 / 1 | Medium | High | 4–16 h | Hosting, TLS, MySQL backups, monitoring, retention, cache/worker strategy. |
| 10-22: Restore procedures | MISSING / P1 | W14 / 1 | Medium | High | 4–16 h | Hosting, TLS, MySQL backups, monitoring, retention, cache/worker strategy. |
| 10-23: Cache strategy | PARTIAL / P1 | W14 / 1 | Medium | High | 4–16 h | Hosting, TLS, MySQL backups, monitoring, retention, cache/worker strategy. |
| 10-24: Deployment readiness | PARTIAL / P1 | W14 / 1 | Medium | High | 4–16 h | Hosting, TLS, MySQL backups, monitoring, retention, cache/worker strategy. |
| 11-01: Colors | PARTIAL / P2 | W13 / 2 | Medium | Medium | 4–16 h | Approved mockups, representative fixtures, browser accessibility/viewport review. |
| 11-02: Typography | PARTIAL / P2 | W13 / 2 | Medium | Medium | 4–16 h | Approved mockups, representative fixtures, browser accessibility/viewport review. |
| 11-03: Icons | PARTIAL / P2 | W13 / 2 | Medium | Medium | 4–16 h | Approved mockups, representative fixtures, browser accessibility/viewport review. |
| 11-04: Spacing | PARTIAL / P2 | W13 / 2 | Medium | Medium | 4–16 h | Approved mockups, representative fixtures, browser accessibility/viewport review. |
| 11-05: Buttons | PARTIAL / P2 | W13 / 2 | Medium | Medium | 4–16 h | Approved mockups, representative fixtures, browser accessibility/viewport review. |
| 11-06: Cards | PARTIAL / P2 | W13 / 2 | Medium | Medium | 4–16 h | Approved mockups, representative fixtures, browser accessibility/viewport review. |
| 11-07: Tables | PARTIAL / P2 | W13 / 2 | Medium | Medium | 4–16 h | Approved mockups, representative fixtures, browser accessibility/viewport review. |
| 11-08: Forms | PARTIAL / P2 | W13 / 2 | Medium | Medium | 4–16 h | Approved mockups, representative fixtures, browser accessibility/viewport review. |
| 11-09: Navigation | PARTIAL / P2 | W13 / 2 | Medium | Medium | 4–16 h | Approved mockups, representative fixtures, browser accessibility/viewport review. |
| 11-10: Product grids | PARTIAL / P2 | W13 / 2 | Medium | Medium | 4–16 h | Approved mockups, representative fixtures, browser accessibility/viewport review. |
| 11-11: Cart | PARTIAL / P2 | W13 / 2 | Medium | Medium | 4–16 h | Approved mockups, representative fixtures, browser accessibility/viewport review. |
| 11-12: Checkout | PARTIAL / P2 | W13 / 2 | Medium | Medium | 4–16 h | Approved mockups, representative fixtures, browser accessibility/viewport review. |
| 11-13: Admin dashboard | PARTIAL / P2 | W13 / 2 | Medium | Medium | 4–16 h | Approved mockups, representative fixtures, browser accessibility/viewport review. |
| 11-14: Mobile responsiveness | PARTIAL / P2 | W13 / 2 | Medium | Medium | 4–16 h | Approved mockups, representative fixtures, browser accessibility/viewport review. |
| 11-15: Loading states | PARTIAL / P2 | W13 / 2 | Medium | Medium | 4–16 h | Approved mockups, representative fixtures, browser accessibility/viewport review. |
| 11-16: Empty states | PARTIAL / P2 | W13 / 2 | Medium | Medium | 4–16 h | Approved mockups, representative fixtures, browser accessibility/viewport review. |
| 11-17: Error states | PARTIAL / P2 | W13 / 2 | Medium | Medium | 4–16 h | Approved mockups, representative fixtures, browser accessibility/viewport review. |
| 12-01: Unit tests | PARTIAL / P1 | W06 / 1 | Medium | Medium | 4–16 h | Ephemeral fixtures, MySQL concurrency test setup, CI runner, business invariants. |
| 12-02: Feature tests | PARTIAL / P1 | W06 / 1 | Medium | Medium | 4–16 h | Ephemeral fixtures, MySQL concurrency test setup, CI runner, business invariants. |
| 12-03: Browser tests | MISSING / P1 | W06 / 1 | Medium | Medium | 4–16 h | Ephemeral fixtures, MySQL concurrency test setup, CI runner, business invariants. |
| 12-04: Checkout tests | MISSING / P1 | W06 / 1 | Medium | Medium | 4–16 h | Ephemeral fixtures, MySQL concurrency test setup, CI runner, business invariants. |
| 12-07: Authorization tests | PARTIAL / P1 | W06 / 1 | Medium | Medium | 4–16 h | Ephemeral fixtures, MySQL concurrency test setup, CI runner, business invariants. |
| 12-08: Payment tests | MISSING / P1 | W06 / 1 | Medium | Medium | 4–16 h | Ephemeral fixtures, MySQL concurrency test setup, CI runner, business invariants. |
| 12-09: Order lifecycle tests | MISSING / P1 | W06 / 1 | Medium | Medium | 4–16 h | Ephemeral fixtures, MySQL concurrency test setup, CI runner, business invariants. |
| 12-10: Validation tests | PARTIAL / P1 | W06 / 1 | Medium | Medium | 4–16 h | Ephemeral fixtures, MySQL concurrency test setup, CI runner, business invariants. |
| 12-11: Existing CI configuration | MISSING / P1 | W06 / 1 | Medium | Medium | 4–16 h | Ephemeral fixtures, MySQL concurrency test setup, CI runner, business invariants. |

## Acceptance checks before any release

1. Tests prove stale-price correction, inactive/deleted products, changed stock, row lock behavior and rollback on a failed later line.
2. Two concurrent submits produce one logical order and one deduction; restock/cancel/return retries are idempotent.
3. Settings changes are reflected consistently in cart, checkout, saved orders and any notification.
4. Admin saves persist through reload; sample KPI data is gone or clearly excluded from operational reporting.
5. Guest/customer/admin permissions and ownership are tested for every relevant mutation; no secret/PII appears in error or audit outputs.
6. Product/category invalid inputs produce usable validation, preserving historical data and preventing hierarchy cycles.
7. Chosen variant, media, COD dispatch/payment/failure workflow is exercised using isolated fixtures.
8. Pages render at360/375/390/430/768/1024/1280/1440/1920 with no clipping, meaningful icon names and keyboard access.
9. Encrypted/off-host backup and restore evidence, rollback steps, TLS/cookies/debug, logs, workers and alerts are signed off by the operator.
10. Optional integrations use signed callbacks, retries, unique operation IDs and sandbox reconciliation before production enablement.

## Scope protections

No schema/route/UI/feature changes were made during the audit. Future migrations should be additive and reviewed; data retention, auth, payments and stock policy need careful approval. Preserve existing user changes and do not run destructive migrations/demo seeders on persistent environments.

