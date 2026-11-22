# Best Choice — cPanel Deployment Guide

## Prerequisites (verify with your host)

| Requirement | Minimum | Check in cPanel |
|---|---|---|
| PHP | **8.3+** | Software → Select PHP Version |
| MySQL | 8.0+ or MariaDB 10.6+ | already available |
| mod_rewrite | enabled | (ask host if unsure) |
| Composer | 2.x | Terminal / SSH |
| Node.js | 20+ | (only for building assets locally) |

### Required PHP extensions
Enable all of these in **cPanel → Select PHP Version → Extensions**:
`bcmath`, `ctype`, `curl`, `fileinfo`, `mbstring`, `openssl`, `pdo`, `pdo_mysql`, `tokenizer`, `xml`, `intl`, `zip`

---

## Deployment structure on cPanel

cPanel domains point to `public_html/`. The correct layout is:

```
/home/cpanelusername/
├── public_html/          ← your domain root (only public/ contents go here)
│   ├── .htaccess
│   ├── index.php
│   ├── favicon.ico
│   ├── robots.txt
│   └── build/            ← compiled CSS/JS from npm run build
│
└── best-choice/          ← all other Laravel files (above public_html)
    ├── app/
    ├── bootstrap/
    ├── config/
    ├── database/
    ├── resources/
    ├── routes/
    ├── storage/
    ├── vendor/
    ├── .env
    └── artisan
```

This keeps sensitive files (`.env`, `vendor/`, `app/`) **outside** the web root.

---

## Step-by-step deployment

### Step 1 — Upload files via cPanel File Manager or FTP

Option A — **via File Manager / FTP** (most common on shared hosting):
1. Create folder `/home/cpanelusername/best-choice/` (one level above `public_html`).
2. Upload the entire project there **except** the `public/` directory and `node_modules/`.
3. Upload the contents of your local `public/` into `/home/cpanelusername/public_html/`.

Option B — **via SSH / Terminal** (if your host allows it):
```bash
cd /home/cpanelusername
git clone <your-repo-url> best-choice
cp -r best-choice/public/* public_html/
```

### Step 2 — Fix `public/index.php` paths

After copying `public/index.php` to `public_html/`, open it and update the two `__DIR__` paths so they point to your Laravel root above `public_html`:

```php
// Change these two lines:
require __DIR__.'/../vendor/autoload.php';
$app = require_once __DIR__.'/../bootstrap/app.php';

// To (adjust username and folder name):
require __DIR__.'/../best-choice/vendor/autoload.php';
$app = require_once __DIR__.'/../best-choice/bootstrap/app.php';
```

### Step 3 — Create the database

1. In cPanel → **MySQL Databases**, create a new database, e.g. `cpanelusername_bestchoice`.
2. Create a database user and assign **ALL PRIVILEGES** to it.
3. Note the database name, username, and password.

### Step 4 — Configure `.env`

```bash
cd /home/cpanelusername/best-choice
cp .env.production.example .env
```

Edit `.env` and fill in every `<CHANGE>` value:

```dotenv
APP_ENV=production
APP_DEBUG=false
APP_URL=https://yourdomain.com

DB_HOST=localhost
DB_DATABASE=cpanelusername_bestchoice
DB_USERNAME=cpanelusername_dbuser
DB_PASSWORD=your_db_password

SESSION_DRIVER=file
CACHE_STORE=file
QUEUE_CONNECTION=sync
```

### Step 5 — Install Composer dependencies

```bash
cd /home/cpanelusername/best-choice
composer install --no-dev --optimize-autoloader
```

### Step 6 — Generate app key

```bash
php artisan key:generate
```

### Step 7 — Run migrations and seed

```bash
php artisan migrate --force
php artisan db:seed --force
```

> The seeder creates demo categories, products, and an admin account  
> (only if `DEMO_ADMIN_EMAIL` and `DEMO_ADMIN_PASSWORD` are set in `.env`).

### Step 8 — Build frontend assets (run locally, then upload)

cPanel shared hosting usually can't run Node.js. Build the assets **on your local machine** first:

```bash
# On your local machine:
npm install
npm run build
```

Then upload the generated `public/build/` folder to `public_html/build/` on the server.

### Step 9 — Create the storage symlink

```bash
php artisan storage:link
```

This creates `public_html/storage → /home/cpanelusername/best-choice/storage/app/public`  
so uploaded product images are publicly accessible.

> **If `php artisan storage:link` fails** (some hosts block symlinks):  
> Manually create the link via SSH:  
> `ln -s /home/cpanelusername/best-choice/storage/app/public /home/cpanelusername/public_html/storage`

### Step 10 — Set folder permissions

```bash
chmod -R 755 /home/cpanelusername/best-choice/storage
chmod -R 755 /home/cpanelusername/best-choice/bootstrap/cache
```

### Step 11 — Optimize for production

```bash
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

### Step 12 — Verify the site

1. Visit `https://yourdomain.com` — storefront should load.
2. Visit `https://yourdomain.com/admin` — log in with your admin credentials.
3. Visit `https://yourdomain.com/up` — should return `200 OK`.

---

## SSL / HTTPS

- In cPanel → **SSL/TLS**, install a free Let's Encrypt certificate.
- Once HTTPS is active, set `APP_URL=https://yourdomain.com` in `.env` and re-run `php artisan config:cache`.

---

## Common cPanel issues and fixes

### "No application encryption key has been specified"
```bash
php artisan key:generate
php artisan config:cache
```

### 500 Internal Server Error
1. Temporarily set `APP_DEBUG=true` in `.env` to see the real error.
2. Check `storage/logs/laravel.log` for the exception.
3. **Re-set `APP_DEBUG=false`** once fixed.

### Images / uploads not showing
```bash
php artisan storage:link
```
Or create the symlink manually (see Step 9).

### "Class not found" / autoload errors
```bash
composer dump-autoload --optimize
```

### Session / cache errors
Ensure `storage/framework/sessions/`, `storage/framework/cache/data/`, and `storage/framework/views/` exist and are writable (chmod 755).

### CSS/JS not loading (404 on /build/...)
Re-run `npm run build` locally and re-upload the `public/build/` folder.

### Blank page with no error
Check PHP version in cPanel — must be **PHP 8.3 or higher**.  
Check required extensions are all enabled.

---

## Queue jobs (orders, emails)

`QUEUE_CONNECTION=sync` in `.env` runs jobs inline on each request — no queue worker needed. This is fine for most shared hosting.

If your host supports **cron jobs**, you can optionally run the Laravel scheduler:
```
* * * * * /usr/local/bin/php /home/cpanelusername/best-choice/artisan schedule:run >> /dev/null 2>&1
```

---

## Updating the site

```bash
# 1. Put in maintenance mode
php artisan down

# 2. Upload/pull new files

# 3. Install dependencies
composer install --no-dev --optimize-autoloader

# 4. Run new migrations
php artisan migrate --force

# 5. Re-build assets (locally) and re-upload public/build/

# 6. Clear and re-cache
php artisan config:cache
php artisan route:cache
php artisan view:cache

# 7. Bring back online
php artisan up
```
