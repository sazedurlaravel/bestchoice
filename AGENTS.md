
# AGENTS.md
# Best Choice Online Shopping — Codex Development Rules

## 1. Project Overview

Project: Best Choice Online Shopping

Technology Stack:
- Laravel / PHP
- MySQL
- Laravel Blade
- HTML, CSS, JavaScript
- Vite
- Bengali and English content
- Currency: BDT (৳)
- Timezone: Asia/Dhaka

This is a production-oriented e-commerce application for Bangladesh.

The project includes:
- Customer-facing storefront
- Admin dashboard
- Product and category management
- Inventory and stock management
- Shopping cart
- Checkout and order management
- Customer management
- Store settings
- Payment integrations

The existing implementation is the source of truth.

Never assume a feature is missing without inspecting the codebase.

---

## 2. MOST IMPORTANT RULE: CHANGE ONLY WHAT IS REQUESTED

When the user requests an update:

1. Identify the exact feature, page, component, or file involved.
2. Inspect the relevant existing implementation.
3. Make only the changes necessary to satisfy the request.
4. Preserve everything outside the requested scope.
5. Do not modify unrelated features.
6. Do not remove existing functionality.
7. Do not redesign unrelated UI components.
8. Do not refactor unrelated code.
9. Do not introduce new features unless explicitly requested.
10. Do not change existing business logic without a clear requirement.

Example:

User request:
"Increase the product title font size on mobile."

Allowed:
- Update the mobile product title CSS.

Not allowed:
- Change product card dimensions.
- Change desktop typography.
- Modify the product grid.
- Change product images.
- Modify prices.
- Redesign product cards.
- Rewrite the product listing component.

Always prefer the smallest safe change.

---

## 3. PRESERVE EXISTING FUNCTIONALITY

Before modifying any existing file:

- Read the complete relevant code.
- Identify dependent functions and components.
- Understand the existing behavior.
- Preserve existing routes and API endpoints.
- Preserve database relationships.
- Preserve authentication and authorization.
- Preserve validation rules.
- Preserve existing JavaScript interactions.
- Preserve responsive behavior.

Never remove working code merely because it appears unnecessary.

If a requested change conflicts with existing functionality, explain the conflict before proceeding.

Do not replace a complex working implementation with a simplified version.

---

## 4. FRONTEND DESIGN PROTECTION

The existing Best Choice Online Shopping storefront design is approved.

Preserve its visual identity unless the user explicitly requests changes.

### Branding

- Business name: Best Choice Online Shopping
- Primary colors: Dark forest green and gold
- Typography: Noto Sans Bengali
- Preserve the existing logo
- Preserve the existing icon system
- Preserve existing spacing and typography

### Header

Preserve:
- Desktop header
- Mobile header
- Search functionality
- Cart icon and count
- Wishlist functionality
- Account navigation
- Mobile menu
- Existing responsive behavior

### Homepage

Preserve:
- Hero slider
- Automatic slide transitions
- Main category sections
- Subcategory cards
- Featured product sections
- Existing section order
- Existing banners
- Footer

### Categories

Main categories:
- জিন্স আইটেম
- উইমেন আইটেম
- কিডস আইটেম

Preserve:
- Existing category design
- Category images
- Category titles
- Mobile automatic category carousel
- Touch swipe support
- Category navigation
- Category filtering

Do not change the category carousel unless explicitly requested.

### Product Grid

Desktop:
- 5 products per row

Mobile:
- 3 products per row

Preserve:
- Product card layout
- Product image aspect ratio
- Product image alignment
- Product title typography
- Regular price
- Selling price
- Order button
- Product card spacing
- Responsive breakpoints

### Product Prices

Display:
- Regular price with strikethrough when discounted
- Selling price prominently
- Currency in BDT

Do not change price calculations or discount logic unless requested.

### Product Actions

Preserve:
- Product details navigation
- Add to cart
- Order button behavior
- Wishlist
- Cart quantity updates

Do not change navigation destinations without explicit instructions.

### Mobile Navigation

Preserve the existing fixed bottom navigation:
- Home
- Menu
- Cart
- Search
- Account

Do not remove or redesign it without permission.

---

## 5. ADMIN DASHBOARD DESIGN PROTECTION

The admin dashboard has its own approved design.

Preserve:
- Sidebar navigation
- Header
- Dashboard cards
- Statistics
- Tables
- Forms
- Search
- Filters
- Modal dialogs
- Mobile responsiveness
- Green and gold branding

Do not replace the custom dashboard with a generic admin template.

Do not modify admin UI while working on storefront UI unless necessary for the requested feature.

---

## 6. PRODUCT MANAGEMENT RULES

Preserve:
- Product names
- Product descriptions
- SKU
- Categories
- Subcategories
- Product images
- Regular prices
- Selling prices
- Stock quantities
- Product variants
- Product visibility
- Featured status

When modifying products:

- Validate all submitted data.
- Preserve existing product images unless removal is requested.
- Do not overwrite unrelated product attributes.
- Do not silently reset stock.
- Do not delete variants unintentionally.
- Do not change product pricing unexpectedly.

Use Laravel Form Requests where appropriate.

---

## 7. INVENTORY AND STOCK MANAGEMENT

Inventory accuracy is critical.

Preserve:
- Stock In
- Stock Out
- Stock Adjustments
- Low Stock Alerts
- Stock History
- Inventory Reports
- Product-level and variant-level stock tracking

Rules:

1. Never allow negative stock.
2. Never modify stock without an authorized operation.
3. Record inventory adjustments in transaction history.
4. Use database transactions for stock changes.
5. Use row-level locking when necessary.
6. Prevent duplicate stock deductions.
7. Prevent overselling.
8. Do not reset inventory during product updates.
9. Do not delete inventory history.
10. Do not modify inventory business rules unless requested.

For order-related stock changes, preserve the existing reservation, deduction, cancellation, and return policies.

Never implement inventory changes using frontend JavaScript alone.

---

## 8. CART AND CHECKOUT PROTECTION

Preserve:
- Add to cart
- Remove from cart
- Quantity updates
- Product prices
- Cart totals
- Checkout validation
- Delivery charges
- Order submission
- Order confirmation

Checkout requirements:
- Customer name
- Primary mobile number
- Additional mobile number
- Delivery address
- Order notes where supported
- Payment method

Do not add an email requirement unless explicitly requested.

Do not reintroduce the division field unless requested.

Never trust totals calculated only in the browser.

Recalculate product prices, shipping charges, discounts, and order totals on the server.

Prevent duplicate order submissions.

---

## 9. DATABASE SAFETY

Never perform destructive database operations without explicit approval.

Prohibited without approval:
- migrate:fresh
- migrate:reset
- db:wipe
- Dropping tables
- Truncating production tables
- Deleting customer data
- Deleting order history
- Resetting inventory
- Replacing production data with seed data

Migration rules:

- Prefer additive migrations.
- Preserve existing data.
- Use foreign keys appropriately.
- Use indexes where needed.
- Do not rename or remove columns unnecessarily.
- Consider rollback safety.
- Explain destructive changes before execution.

Never assume the database is disposable.

---

## 10. AUTHENTICATION AND SECURITY

Preserve:
- Existing login functionality
- Admin authentication
- Customer authentication
- Role-based permissions
- Middleware
- Password hashing
- CSRF protection
- Authorization policies
- Request validation

Never:
- Hardcode passwords
- Expose API keys
- Commit .env
- Disable authentication to fix an error
- Disable CSRF protection unnecessarily
- Expose admin routes publicly
- Log sensitive customer information unnecessarily

Store secrets in environment variables.

Do not modify authentication or permissions unless required.

---

## 11. LARAVEL CODING STANDARDS

Follow Laravel conventions.

Use:
- Controllers for request handling
- Form Requests for complex validation
- Eloquent models and relationships
- Services for reusable business logic
- Policies or gates for authorization
- Database transactions for critical operations
- Queues for long-running jobs
- Blade components for reusable UI

Avoid:
- Duplicating business logic
- Large unnecessary controllers
- Unrelated refactoring
- Unnecessary packages
- Hardcoded configuration
- Direct database queries in Blade views

Keep the code readable and maintainable.

---

## 12. RESPONSIVE DESIGN RULES

Every UI change must preserve:
- Desktop layout
- Tablet layout
- Mobile layout
- Existing breakpoints
- Touch interactions
- Navigation usability

Do not fix one screen size by breaking another.

When changing CSS:
- Prefer targeted selectors.
- Avoid unnecessary global overrides.
- Avoid excessive !important.
- Do not remove existing media queries.
- Check for horizontal overflow.
- Preserve product grid column counts.

---

## 13. JAVASCRIPT RULES

Preserve:
- Existing event handlers
- Existing interactive components
- Existing AJAX requests
- Existing frontend state
- Existing modal behavior
- Existing slider behavior

Do not:
- Replace working JavaScript unnecessarily.
- Duplicate event listeners.
- Introduce conflicting global variables.
- Break existing selectors.
- Remove functionality while simplifying code.

Prefer minimal, isolated changes.

---

## 14. FILE MODIFICATION RULES

Before editing:

1. Identify the files that must change.
2. Inspect their current contents.
3. Understand dependencies.
4. Check whether the change can be isolated.
5. Modify only necessary files.

Do not:
- Rewrite entire files for small changes.
- Delete unrelated code.
- Reformat entire files unnecessarily.
- Rename files without need.
- Move directories without approval.
- Change dependencies without justification.
- Change environment configuration unnecessarily.

Preserve existing comments when relevant.

---

## 15. GIT SAFETY

Before making changes:

- Check git status.
- Respect existing uncommitted work.
- Do not overwrite user modifications.
- Do not automatically discard changes.

Never run destructive Git commands without explicit permission.

Examples:
- git reset --hard
- git clean -fd
- git checkout -- .
- git push --force

Do not automatically commit or push unless requested.

After editing:

- Review git diff.
- Check for unrelated changes.
- Check for accidental deletions.
- Check for exposed secrets.

---

## 16. TESTING AND VERIFICATION

After each change:

1. Run relevant tests.
2. Check for PHP syntax errors.
3. Check affected Laravel routes.
4. Verify database compatibility.
5. Verify affected frontend behavior.
6. Verify responsive layouts where possible.
7. Check JavaScript errors.
8. Review the final diff.

Use appropriate commands:

```bash
php artisan test
php artisan route:list
npm run build
git diff --check
git diff
```

Run only the commands relevant to the change.

If a test cannot be run, clearly explain why.

Never claim that functionality was tested when it was not.

---

## 17. NO UNREQUESTED CHANGES

Codex must not make extra changes merely because they seem beneficial.

Examples:

If asked to:
"Fix mobile product card spacing."

Do not:
- Redesign the header.
- Change footer colors.
- Modify checkout.
- Add new features.
- Change database structure.
- Update packages.
- Refactor controllers.

If additional problems are discovered, report them separately.

Do not fix unrelated issues without permission.

Exception:
A directly blocking issue or security risk may be addressed only to the minimum extent necessary, with the reason clearly documented.

---

## 18. ASK BEFORE HIGH-RISK CHANGES

Request clarification before:

- Destructive database changes
- Major architectural changes
- Authentication redesign
- Payment processing changes
- Inventory calculation changes
- Removing existing features
- Replacing approved UI
- Changing hosting configuration
- Installing major dependencies
- Deleting files
- Modifying production data

If a request is ambiguous, ask a focused question rather than guessing.

---

## 19. RESPONSE FORMAT AFTER EVERY TASK

After implementing a change, report:

### Completed
Brief description of the requested changes.

### Files Modified
List every created, modified, or deleted file.

### Preserved
Identify important existing functionality that remains unchanged.

### Verification
List tests and commands executed, with results.

### Notes
Mention:
- Assumptions
- Limitations
- Unresolved issues
- Any additional changes requiring approval

Keep the report concise and factual.

---

## 20. FINAL RULE

The existing Best Choice Online Shopping application is the source of truth.

The user's explicit request defines the scope.

Always:

- Make the smallest safe change.
- Preserve all unrelated functionality.
- Preserve the approved design.
- Protect customer and order data.
- Protect inventory accuracy.
- Respect existing code.
- Verify before reporting success.

Never redesign, rebuild, simplify, or remove existing features unless the user explicitly requests it.

**PRIMARY PRINCIPLE: CHANGE ONLY WHAT THE USER REQUESTS. KEEP EVERYTHING ELSE INTACT.**
