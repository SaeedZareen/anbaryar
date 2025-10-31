<?php

declare(strict_types=1);

session_start();

if (!file_exists(__DIR__ . '/config.php')) {
    header('Location: installer.php');
    exit;
}

require_once __DIR__ . '/core/helpers.php';
require_once __DIR__ . '/core/db.php';
require_once __DIR__ . '/core/auth.php';
require_once __DIR__ . '/core/plugin.php';
require_once __DIR__ . '/app/Http/Request.php';
require_once __DIR__ . '/app/Http/Response.php';
require_once __DIR__ . '/app/View/View.php';
require_once __DIR__ . '/app/Application.php';

$request = new App\Http\Request($_GET, $_POST, $_SERVER);

$app = new App\Application($request);
$app->run();
