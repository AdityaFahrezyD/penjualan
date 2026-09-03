<?php

use Illuminate\Foundation\Application;
use Illuminate\Http\Request;

define('LARAVEL_START', microtime(true));

// Determine the application base path. Locally, public/ sits directly
// beside vendor/ and bootstrap/. On cPanel, this file is copied to
// public_html while the Laravel application remains in repositories/penjualan.
$basePath = is_dir(__DIR__.'/../vendor')
    ? dirname(__DIR__)
    : dirname(__DIR__).'/repositories/penjualan';

// Determine if the application is in maintenance mode...
if (file_exists($maintenance = $basePath.'/storage/framework/maintenance.php')) {
    require $maintenance;
}

// Register the Composer autoloader...
require $basePath.'/vendor/autoload.php';

// Bootstrap Laravel and handle the request...
/** @var Application $app */
$app = require_once $basePath.'/bootstrap/app.php';

$app->handleRequest(Request::capture());
