# Best Choice Online Shopping Design System

This specification documents the existing approved visual language. It is a reference for future consistency work, not a request to replace the current design.

## Brand and color tokens

The current implementation uses these core values:

| Semantic token | Current value | Usage |
| --- | --- | --- |
| Primary forest green | `#164c39` | Main buttons, sidebar, navigation, headings, active states |
| Primary hover/deep green | `#0e3728` | Button hover and deeper emphasis |
| Dark brand text | `#173d2f` / `#21362c` | Admin and storefront text variations that should converge carefully |
| Gold accent | `#d9903b` | Brand wordmark, accents, gold buttons, badges, selected indicators |
| Page cream | `#faf8f1` | Storefront background |
| Admin background | `#f5f7f5` | Admin page background |
| Card white | `#ffffff` | Cards, panels, forms, header surfaces |
| Storefront muted | `#708078` | Secondary text and helper text |
| Admin muted | `#73837b` | Admin secondary text |
| Storefront border | `#e3e8df` | Storefront separators and card borders |
| Admin border | `#e3eae5` | Admin separators and panels |
| Success | green family, currently `#ecfdf5` / `#065f46` | Success alerts and positive status |
| Warning | gold family, currently `#fff3df` / `#b5771f` | Pending and warning states |
| Error/danger | currently `#fef2f2` / `#991b1b` and `#c46f53` | Errors, destructive actions, cancelled/out-of-stock states |
| Info | currently `#edf1ff` / `#5872b6` | Processing/info status |
| Focus | green/gold outline | Keyboard focus; standardize to a visible 2–3px ring |

Future CSS should expose semantic names such as `--color-primary`, `--color-primary-hover`, `--color-accent`, `--color-surface`, `--color-page`, `--color-text`, `--color-muted`, `--color-border`, `--color-success`, `--color-warning`, `--color-danger`, `--color-info`, and `--color-focus`. Existing aliases (`--green`, `--gold`, etc.) should be retained during migration to avoid a visual break.

## Typography

- Primary UI font: `Noto Sans Bengali`.
- Latin display/numeric text is currently sometimes rendered with `Inter` or `Arial`; this should be limited to clearly intentional numeric/brand display cases.
- Page titles: one shared storefront/admin scale, with responsive reduction on small screens.
- Section headings: bold, forest green, consistent line-height.
- Body/helper text: muted token, readable line-height, no inline one-off sizes unless content requires it.
- Buttons: semibold/bold with consistent icon gap and touch target.

Recommended baseline scale for future components:

| Role | Desktop | Mobile |
| --- | --- | --- |
| Page title | 28–32px | 23–27px |
| Section title | 22–26px | 19–23px |
| Card title | 15–17px | 12–15px depending on the fixed 3-column grid |
| Body | 14–16px | 13–15px |
| Helper/meta | 11–13px | 10–12px |
| Button | 13–15px | 11–14px |

## Spacing, shape, and elevation

- Use a 4px base spacing rhythm; common existing values are 8, 10, 12, 14, 16, 20, and 24px.
- Standard card/panel radius: 10–16px. Larger hero surfaces may use 18–22px.
- Icon-only controls: minimum 36–44px touch target depending on context.
- Use one light border plus one restrained shadow for cards; avoid stacking several competing shadows.
- Keep mobile bottom navigation clearance in the document flow and account for safe-area insets.

## Button variants

1. Primary: forest green background, white text, deep-green hover.
2. Accent: gold background, white or deep-green text where contrast permits.
3. Secondary: white or pale green surface, green text, green-tinted border.
4. Danger: pale red surface/red text or a clearly confirmed destructive action.
5. Icon-only: semantic `aria-label`/title, visible focus ring, 36–44px target.
6. Disabled: muted background/text, no hover emphasis, preserved disabled semantics.

Equivalent actions must not receive different variants merely because they appear on different pages.

## Cards and grids

- Storefront product grid: 5 columns desktop, 3 columns mobile; preserve current image proportions and interactions.
- Product cards: white surface, light border, restrained shadow, consistent action row.
- Collection/category cards: image-first, count badge, consistent label alignment.
- Admin cards: white surface on admin background, 12px radius, shared border/shadow.
- Recommendation cards may use a horizontal mobile variant, but must keep the same color, type, and action tokens.

## Forms and feedback

- Inputs use white/pale surface, green-tinted border, 8–10px radius, consistent height and focus ring.
- Labels use Noto Sans Bengali, semibold, with required markers in the danger token.
- Use `.flash`/`.alert`/`.toast`/`.notice` only after defining their roles; do not silently mix them for the same interaction.
- Empty states should use a shared icon, muted explanation, and optional primary action.

## Icon map

Font Awesome 6 is the existing icon system. The following mapping should be treated as canonical:

| Meaning | Icon |
| --- | --- |
| Home | `fa-house` |
| Menu | `fa-bars` |
| Cart | `fa-cart-shopping` |
| Wishlist | `fa-heart` |
| Search | `fa-magnifying-glass` |
| Account | `fa-user` |
| Orders | `fa-bag-shopping` |
| Product | `fa-box-open` |
| Inventory | `fa-warehouse` |
| Categories | `fa-layer-group` |
| Customers | `fa-users` |
| Settings | `fa-gear` |
| Edit | `fa-pen` |
| Delete | `fa-trash` |
| View | `fa-eye` |
| Success | `fa-circle-check` |
| Warning | `fa-triangle-exclamation` |
| Error | `fa-circle-xmark` |
| Back/forward | `fa-arrow-left` / `fa-arrow-right` |
| Close | `fa-xmark` |
| Logout | `fa-right-from-bracket` |

Icon-only actions must have an accessible name. Decorative icons should use `aria-hidden="true"`.

## Responsive breakpoints

The current code uses several legacy breakpoints. Until consolidation is approved, preserve behavior at:

- 360px: smallest phone adjustments.
- 390–430px: standard phone QA targets.
- 650px: storefront product/grid/mobile card behavior.
- 767/768px: storefront/admin mobile navigation and stacked layouts.
- 850–900px: admin/sidebar and storefront layout transitions.
- 1000–1150px: tablet/desktop grid density transitions.

Future work should document any breakpoint merge and verify 360, 390, 430, 768, 1024, 1280, 1440, and 1920px.

## Structural rules

- Shared storefront header/footer/mobile navigation remain in partials.
- Reuse the existing product-card component; add explicit variants rather than page-specific markup forks.
- Keep admin and storefront layouts distinct but use the same semantic brand tokens.
- Preserve named routes, CSRF forms, server-side totals, inventory rules, and authorization.
