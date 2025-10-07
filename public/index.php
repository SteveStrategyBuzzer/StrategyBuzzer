<?php

use Illuminate\Contracts\Http\Kernel;
use Illuminate\Http\Request;

define('LARAVEL_START', microtime(true));

// Force PostgreSQL connection with environment variables
putenv('DB_CONNECTION=pgsql');
if (getenv('PGHOST')) putenv('DB_HOST=' . getenv('PGHOST'));
if (getenv('PGPORT')) putenv('DB_PORT=' . getenv('PGPORT'));
if (getenv('PGDATABASE')) putenv('DB_DATABASE=' . getenv('PGDATABASE'));
if (getenv('PGUSER')) putenv('DB_USERNAME=' . getenv('PGUSER'));
if (getenv('PGPASSWORD')) putenv('DB_PASSWORD=' . getenv('PGPASSWORD'));

/*
|--------------------------------------------------------------------------
| Check If The Application Is Under Maintenance
|--------------------------------------------------------------------------
|
| If the application is in maintenance / demo mode via the "down" command
| we will load this file so that any pre-rendered content can be shown
| instead of starting the framework, which could cause an exception.
|
*/

if (file_exists($maintenance = __DIR__.'/../storage/framework/maintenance.php')) {
    require $maintenance;
}

/*
|--------------------------------------------------------------------------
| Register The Auto Loader
|--------------------------------------------------------------------------
|
| Composer provides a convenient, automatically generated class loader for
| this application. We just need to utilize it! We'll simply require it
| into the script here so we don't need to manually load our classes.
|
*/

require __DIR__.'/../vendor/autoload.php';

/*
|--------------------------------------------------------------------------
| Run The Application
|--------------------------------------------------------------------------
|
| Once we have the application, we can handle the incoming request using
| the application's HTTP kernel. Then, we will send the response back
| to this client's browser, allowing them to enjoy our application.
|
*/

$app = require_once __DIR__.'/../bootstrap/app.php';

$kernel = $app->make(Kernel::class);

$response = $kernel->handle(
    $request = Request::capture()
)->send();

$kernel->terminate($request, $response);
