# UI Consistency Implementation Plan

This plan is intentionally staged. The audit task stops before production UI implementation. No item below should be applied until the user approves a batch.

## 1. Safe, low-risk visual fixes

These changes should not alter routes, business rules, submitted fields, or data:

- Add semantic token aliases while retaining existing variables.
- Normalize focus rings, icon-only target sizes, and button hover/disabled states.
- Standardize shared storefront page title, breadcrumb, empty-state, and flash spacing.
- Remove repeated inline presentation styles from storefront empty/confirmation states by moving equivalent rules into scoped CSS classes.
- Add route-aware admin sidebar active state in the shared layout.
- Standardize accessible labels and `aria-hidden` handling for decorative Font Awesome icons.
- Align status badge colors with the documented semantic mapping.

## 2. Shared component improvements

- Define a small shared button contract: primary, secondary, accent, danger, icon-only, disabled.
- Define product-card variants for listing, wishlist, and recommendation contexts without changing the approved 5/3 grid.
- Define shared alert/flash/toast/notice roles and migrate one surface at a time.
- Define a consistent form field contract for admin and storefront forms.
- Replace text-symbol navigation actions (`✓`, `%`, and raw arrows) with the canonical icon map where the change is semantically equivalent and accessibility is preserved.
- Keep header/footer/mobile bottom-nav partials as the storefront source of truth.

## 3. Responsive fixes

- Test and record screenshots at 360, 390, 430, 768, 1024, 1280, 1440, and 1920px.
- Check horizontal overflow on product grids, cart/checkout layouts, tables, modals, and admin forms.
- Consolidate only duplicate media-query rules that produce the same behavior.
- Ensure mobile action controls retain touch targets after the recent icon-only bottom-nav and product-card changes.
- Verify mobile menu, search toggle, cart count, wishlist count, and sticky bottom navigation interactions.

## 4. Changes requiring additional review

- Consolidating the large appended storefront stylesheet into a normalized file structure. This has a medium regression risk because later overrides currently define the effective design.
- Moving all inline styles into CSS classes. Dynamic slide background custom properties and data-driven values should remain inline or be replaced with a carefully scoped alternative.
- Unifying auth, storefront, and admin token files. They currently use separate Vite entries and must be migrated without changing login/register rendering.
- Replacing native `confirm()` with a shared modal. This affects destructive-action flow and keyboard/focus behavior.
- Adding recommendation/product-card variants to all listings. Confirm product image proportions and the existing 5/3 grid after each change.

## 5. High-risk changes that must not be made automatically

- Do not replace or rewrite the admin dashboard.
- Do not merge the browser-state admin dashboard in `resources/views/admin/dashboard.blade.php` with database-backed admin controllers without a separate architecture decision.
- Do not change checkout validation, order totals, delivery charge, stock deduction, inventory locking, authorization, or route destinations.
- Do not change database schema or seed/production data.
- Do not install a new CSS/icon/UI framework.
- Do not remove the mobile bottom navigation, product grid, wishlist/cart behavior, or existing approved hero/category interactions.

## Proposed implementation order after approval

1. Token aliases and accessibility baseline.
2. Shared button, status, alert, and form primitives.
3. Storefront page shells and product-card variants.
4. Admin table/form/sidebar consistency, without touching dashboard data architecture.
5. Responsive QA and targeted fixes across the viewport matrix.
6. Separate review of the admin dashboard source-of-truth problem.

## Verification checklist for every approved batch

- Run the narrowest relevant PHPUnit tests, then `php artisan test --compact` when shared behavior changes.
- Run `npm run build`.
- Run `php artisan view:cache`.
- Run `git diff --check` and inspect the diff for unrelated changes.
- Check affected routes with `php artisan route:list` where routing is involved.
- Verify keyboard focus and icon-only accessible names.
- Verify mobile and desktop screenshots at the affected breakpoints.
- Report any browser-testing limitation instead of claiming visual verification.
