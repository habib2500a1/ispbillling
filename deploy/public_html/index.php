<?php

/**
 * cPanel public_html entry — Laravel app lives in sibling ../isp-app/
 * Used by scripts/build-cpanel-release-zip.sh (public_html package).
 */

@ini_set('memory_limit', '1024M');

use Illuminate\Http\Request;

define('LARAVEL_START', microtime(true));

$laravelRoot = dirname(__DIR__).'/isp-app';

if (! is_file($laravelRoot.'/vendor/autoload.php')) {
    $laravelRoot = dirname(__DIR__).'/isp-platform';
}

if (! is_file($laravelRoot.'/vendor/autoload.php')) {
    http_response_code(500);
    echo 'ISP Platform: Laravel app folder not found. Expected ../isp-app or ../isp-platform next to public_html.';
    exit(1);
}

if (file_exists($maintenance = $laravelRoot.'/storage/framework/maintenance.php')) {
    require $maintenance;
}

require $laravelRoot.'/vendor/autoload.php';

(require_once $laravelRoot.'/bootstrap/app.php')
    ->handleRequest(Request::capture());
