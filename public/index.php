<?php

use Illuminate\Foundation\Application;
use Illuminate\Http\Request;

define('LARAVEL_START', microtime(true));

/*
|--------------------------------------------------------------------------
| cPanel / Shared Hosting Path Resolution
|--------------------------------------------------------------------------
| On a standard local setup the project root is one level above public/.
| On cPanel the public/ contents live in public_html/ while the rest of
| the app lives in a sibling folder (e.g. best-choice/).  We auto-detect
| which layout is in use so the same index.php works in both.
|
| Layout A (local dev / VPS):
|   /project-root/
|   ├── public/index.php   (this file)
|   ├── vendor/
|   └── bootstrap/
|
| Layout B (cPanel shared hosting):
|   /home/user/public_html/index.php   (this file, copied here)
|   /home/user/best-choice/vendor/     (Laravel root beside public_html)
|
*/

// Try the standard location first (one level up from this file).
$laravelBase = dirname(__DIR__);

// If vendor/autoload.php is not there, look for the app folder next to
// public_html/ — the typical cPanel layout.
if (! file_exists($laravelBase.'/vendor/autoload.php')) {
    // Walk up and look for the best-choice/ sibling (or any folder with vendor/)
    $candidates = [
        dirname($laravelBase).'/best-choice',
        dirname($laravelBase).'/laravel',
        dirname($laravelBase).'/app',
    ];
    foreach ($candidates as $candidate) {
        if (file_exists($candidate.'/vendor/autoload.php')) {
            $laravelBase = $candidate;
            break;
        }
    }
}

// Determine if the application is in maintenance mode...
if (file_exists($maintenance = $laravelBase.'/storage/framework/maintenance.php')) {
    require $maintenance;
}

// Register the Composer autoloader...
require $laravelBase.'/vendor/autoload.php';

// Bootstrap Laravel and handle the request...
/** @var Application $app */
$app = require_once $laravelBase.'/bootstrap/app.php';

$app->handleRequest(Request::capture());
