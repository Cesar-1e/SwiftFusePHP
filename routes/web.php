<?php

/**
 * Explicit web routes (optional).
 *
 * Convention-based routing already maps "/{controller}/{method}/{params}" to
 * App\Controllers, so explicit routes here are only needed for custom URLs or
 * placeholders. The $router variable is provided by bootstrap/app.php.
 *
 * @var \SwiftFuse\Routing\Router $router
 */

declare(strict_types=1);

use App\Controllers\HomeController;
use App\Controllers\MediaController;
use App\Controllers\PeopleController;
use App\Controllers\UploadController;

// Landing page.
$router->get('', [HomeController::class, 'index']);

// People list rendered asynchronously (AJAX): the page shell and the JSON feed.
$router->get('people', [PeopleController::class, 'index']);
$router->get('people/list', [PeopleController::class, 'list']);

// Protected media: a page that embeds a signed <video> URL...
$router->get('media/video', [MediaController::class, 'video']);

// ...and the endpoint that validates the signature and streams the file.
$router->get('media/file', [MediaController::class, 'file']);

// File upload demo: show the form, then store into private storage on POST.
$router->get('upload', [UploadController::class, 'index']);
$router->post('upload/store', [UploadController::class, 'store']);
