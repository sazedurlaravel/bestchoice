# Best Choice Online Shopping — Local Setup

## Prerequisites
- PHP 8.3+
- Composer
- MySQL 8.0+ (or MariaDB 10.6+)
- Node.js 20+ and npm

---

## 1. Clone & install dependencies

```bash
git clone <repo-url> best-choice
cd best-choice
composer install
npm install
```

---

## 2. Environment configuration

```bash
cp .env.example .env
php artisan key:generate
```

Edit `.env` and set your database credentials:

```dotenv
DB_DATABASE=best_choice
DB_USERNAME=root
DB_PASSWORD=your_password
```

To seed a demo admin account, also set:

```dotenv
DEMO_ADMIN_EMAIL=admin@example.com
DEMO_ADMIN_PASSWORD=your_secure_password
```

**Never commit a real password to `.env`.**

---

## 3. Database setup

```bash
mysql -u root -p -e "CREATE DATABASE IF NOT EXISTS best_choice CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
php artisan migrate
php artisan db:seed
```

The seeder creates:
- 3 top-level categories (জিন্স আইটেম, উইমেন আইটেম, কিডস আইটেম)
- 8 subcategories spread across the three groups
- 19 demo products with stock, prices and images
- Admin user (only if `DEMO_ADMIN_PASSWORD` is set)

---

## 4. Build frontend assets

```bash
npm run build        # production build
# or
npm run dev          # watch mode while developing
```

---

## 5. Start the development server

```bash
php artisan serve
```

Visit [http://localhost:8000](http://localhost:8000) for the storefront.
Admin panel: [http://localhost:8000/admin](http://localhost:8000/admin)

---

## 6. Running tests

```bash
php artisan test --compact
```

Tests use an in-memory SQLite database (configured in `phpunit.xml`).
No extra database setup is needed.

---

## Timezone & locale

The application is pre-configured for:
- **Timezone**: `Asia/Dhaka`
- **Locale**: Bengali (`bn`)
- **Currency**: BDT (৳)

These are set in `config/app.php`.
