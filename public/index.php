<?php

/**
 * SwiftFusePHP front controller.
 *
 * This is the ONLY PHP file exposed by the web server. The DocumentRoot must
 * point at this /public directory, keeping the framework core, configuration,
 * environment file and storage completely outside the web root.
 */

declare(strict_types=1);

session_start();

/** @var \SwiftFuse\Foundation\Application $app */
$app = require dirname(__DIR__) . '/bootstrap/app.php';

$app->run();
