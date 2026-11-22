# E-commerce UI and mockup consistency audit

Audit date: 2026-11-08. Read-only comparison of current code against design-reference/frontend.html and design-reference/admin.html, with docs/DESIGN_SYSTEM.md as historical guidance.

## Verification boundary

This audit did not create or modify application UI files. It did not take a new whole-site browser screenshot matrix. Previous-turn cart screenshots at390/1440 and overflow metrics at360/390/430/768/1024/1440 are historical evidence, not verification of all pages in this audit. No page is certified pixel-perfect here.

The supplied mockups are dynamic JavaScript applications: frontend functions home/shop/detail/cartPage/checkout/account/contact/track/simple and admin dashboard/products/inventory/orders/customers/coupons/settings generate multiple states. Static initial HTML alone is not a complete reference.

Recent user-approved differences are intentional: icon-only mobile bottom shortcuts, no mobile product-card heart, a common cart-shopping icon on purchase buttons, and removal of cart recommendations. Do not restore older mockup behavior without considering those instructions.

## Shared design and source findings

| Area | Evidence | Assessment and recommended scoped follow-up |
| --- | --- | --- |
| Brand | storefront.css:2,120,162; admin.css:2; app.css:10 | Forest green/gold identity is present. Repeated root declarations and inline colors create drift; derive shared aliases from approved values after visual review. |
| Typography | layouts/storefront.blade.php:8; layouts/admin.blade.php:1; layouts/auth.blade.php:3–8 | Storefront/admin load Noto Sans Bengali and Inter. Auth specifies Noto in CSS but does not load it, so availability depends on cache/system fonts. Preserve Bengali font intent and align loading. |
| Titles/metadata | storefront layout:6; admin layout:1; page @section(title) | Shared layouts read $title while children declare title sections. Default browser titles remain instead of page titles unless a controller provides $title. Confirmed wiring defect. |
| Icons | partials/mobile-bottom-nav.blade.php:2–6; product detail:50,59 | Font Awesome6 and the unified cart icon are present. Text-symbol arrows/checkmarks and admin variants still differ. Keep same semantic meaning across routes. |
| Primary/secondary actions | storefront.css:199,360,469,518,690; admin.css:2 | Repeated global border-radius !important and component overrides influence the result. Normalize only targeted equivalent controls, never all pages in one global change. |
| Product cards | home.blade.php:70–85 versus components/product-card.blade.php:2 | Homepage duplicates card markup and uses a different order label. Shared component handles category/search/wishlist. Source duplication makes action/pricing changes easy to diverge. Preserve server forms and5/3 grid. |
| Product grid | storefront.css:237,350,717–722 | Desktop5/mobile3 rules present, portrait image proportions preserved; broad important overrides complicate variants. No new browser matrix verified. |
| Mobile icon names | bottom-nav:2–6 and storefront.css:282–285 | Label spans are display:none and icons aria-hidden; controls need explicit accessible names. Cart count alone is not a meaningful cart control name. |
| Menu interaction | storefront.js:18–33 | Open/close/backdrop behavior exists; no Escape, focus trap/restore or background inert handling found. Needs keyboard/browser checks. |
| Forms | admin/products/_form.blade.php:8,43; admin-overrides.css:5–12 | Form panels have inline explicit column placements while mobile collapses the grid. Implicit second column can remain on narrow screens; code-based responsive risk requires browser reproduction. Labels often lack for/id association. |
| Alerts | storefront.js:89–116; layout flash; admin-overrides.css:24 | Server flash, auth inline errors, admin alerts and JS toast have separate patterns. Wishlist fetch lacks response.ok/catch handling; server errors can produce unhandled UI failure. |
| Pagination | category/search/admin ->links(); storefront layout loads only storefront assets | Laravel pagination templates use utility classes generated in app.css, which storefront/admin do not load. Styling consistency is unverified; inspect rendered paginator at representative page counts. |
| Admin shell | layouts/admin.blade.php:1 versus admin/dashboard.blade.php:1–8 | Dashboard owns its own complete shell and inline CSS; other admin pages extend shared layout. Static dashboard-active class and default top title persist in the shared layout. |
| Admin status badges | admin.css:2; admin-overrides.css:35; orders/show.blade.php:47 | Pending/processing/cancelled styles exist, while detail uses generic status class. Shared status-to-style mapping would avoid misleading colors. |

## Page and state comparison

| Surface | Reference | Live evidence | Current match/gap | Fidelity |
| --- | --- | --- | --- | --- |
| Home hero | frontend home(), bc-hero | home.blade.php:6–44; storefront.js:35–47 | Same slide structure/images/content; JS uses5000ms rather than reference4500ms and lacks reference visibility/hover/focus/reduced-motion pause behavior. | Unverified |
| Home categories | bcCollectionSection() | HomeController.php:15–26; home.blade.php:45 | Database-driven children/counts and scroll CSS exist. Reference automatic carousel setup/dots do not have a corresponding live JS implementation. Touch scroll remains possible via CSS. | Unverified |
| Home favorites | productGrid() | home.blade.php:66 | Same section intent; latest data rather than curated/sales-ranked set. Duplicated action markup differs. | Unverified |
| All products/search | shop() plus filter drawer | SearchController.php:12–19; search.blade.php:11–46 | Real query/pagination; reference category filter bar, filter drawer and sorting are absent. Mobile .search rule can hide the in-page search too, since toggle addresses the first shared .search only. | Unverified |
| Main/subcategory | category shop state | CategoryController.php:12–20; categories/show.blade.php:22 | Live child chips and direct-category list; not all reference discovery controls. Parent descendants not aggregated. | Unverified |
| Product detail | detail() | products/show.blade.php:16–67 | Main image/price/description/stock/cart/buy forms exist. Related products and detail wishlist absent; no variant/gallery UI. | Unverified |
| Cart populated | cartPage()/cartLines()/summary() | cart/index.blade.php:22–55; storefront.css:671–708 | Current panel dimensions and quantity-under-price hierarchy follow reference; real delivery60 replaces demo80. Real products/Latin digits are data-format differences. Cart line subtotal is screen-reader-only. | Historical cart comparison only |
| Cart empty | cartLines() empty | cart/index.blade.php:15–20 | Cart icon/Bengali message/action exist. Live action goes home, reference goes shop; retain established destination until deliberately changed. | Unverified this audit |
| Checkout | checkout() | checkout/index.blade.php:19–47 | All approved real fields/COD retained; extra section/step/security styling differs from simpler reference. Payment placeholder text is not a real integration. | Unverified |
| Confirmation | success() | checkout/confirmation.blade.php:5–22 | Real session reference and amount, rather than demo/localStorage. Inline card styles differ; no redirect/ownership issue asserted here. | Unverified |
| Wishlist | reference wishlist state | wishlist/index.blade.php:4; product-card.blade.php:2 | Real toggle/list/counters. Last AJAX removal has no empty-state replacement; mobile hides the entire removal form per prior requested styling. Review a usable mobile removal affordance separately. | Unverified |
| Customer login/register | account() two forms | auth/login.blade.php; auth/register.blade.php; layouts/auth | Real separate auth shell and full registration contract; different header/font/card sizing from reference combined storefront page. | Unverified |
| Customer account/orders | mockup account() lacks live account history | auth/account.blade.php:6–9 | Functional extension: profile and last10 owned orders. Preserve functionality and use shared brand; no exact reference for full history. | No exact full-state reference |
| Tracking | track() | track.blade.php:4 | Live order status/total lookup; reference demo stage list not reproduced. Do not invent courier events. | Unverified |
| About/contact/FAQ/policy | contact()/simple() | header/footer fragment links | Corresponding live pages/content absent; confirmed destination gap. | Missing |
| Admin overview | admin dashboard() | DashboardController.php:16–64; dashboard.blade.php:4 | Strong structural resemblance; fixed chart/trend/donut are not real metrics. Recent-order modal save is memory-only. | Unverified |
| Products/create/edit | admin products()/editProduct modal | admin/products/* | Server paginated table/forms differ from card grid/modal reference; real validation and lifecycle must remain authoritative. | Unverified |
| Categories | categoriesPage() | admin/categories/* | Live CRUD extends mockup static three-category surface; preserve hierarchy editing and add brand consistency. | No exact full CRUD reference |
| Inventory | inventory()/adjustStock | admin/inventory/index.blade.php | Real adjustment/history/threshold table; different filters and controls from prototype. | Unverified |
| Orders/details | ordersPage()/editOrder | admin/orders/index/show | Real detail route exceeds simple status modal; status colors and responsive panels need review. | Unverified |
| Customers | customers() | admin/customers/index.blade.php | Order-derived list; scope/content differs; full CRM not present. | Unverified |
| Settings | settings() | admin/settings/edit.blade.php | Real settings form shares broad layout; charge persistence does not affect checkout. UI resemblance is not workflow completeness. | Unverified |
| Coupons | coupons()/editCoupon | dashboard.blade.php:7 | UI demo only, no real coupon engine. Do not make it appear functional through styling alone. | Partial prototype |

## Required visual verification matrix

360,375,390,430,768,1024,1280,1440,1920px: **pending for the full current site**.

Use read-only page visits with synthetic fixtures in an isolated environment for auth/checkout/admin screenshots. Include populated, empty, long-title, discount, out-of-stock, validation-error, pagination and modal states. Verify:
- no unintended page overflow, 5/3 product columns and approved portrait ratios;
- header, search, footer, icon-only bottom-nav and safe-area spacing;
- tap target, focus ring, keyboard menu/modal closure and accessible action names;
- quantity/removal alignment and checkout content clearance;
- real form field labels and mobile admin columns;
- provider/stock state has no invented display data.

Keep source and behavior protections intact. Pixel equivalence cannot be inferred from matching CSS alone.

