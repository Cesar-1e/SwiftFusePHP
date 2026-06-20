<?php

/**
 * Service container bindings.
 *
 * Maps an abstract identifier to a factory closure. Because every core service
 * is resolved through the container, developers can swap any implementation for
 * their own here (or rebind it in app/bootstrap.php) WITHOUT editing the
 * framework — this is the backbone of SwiftFusePHP's modular architecture.
 *
 * Example override:
 *   SwiftFuse\Storage\StorageManager::class => fn () => new App\Services\MyStorage(),
 */

declare(strict_types=1);

use SwiftFuse\Foundation\Container;
use SwiftFuse\Queue\QueueManager;
use SwiftFuse\Routing\Router;
use SwiftFuse\Storage\StorageManager;

return [
    Router::class => static fn (Container $app): Router => new Router(),

    StorageManager::class => static fn (Container $app): StorageManager => new StorageManager(),

    QueueManager::class => static fn (Container $app): QueueManager => new QueueManager(),
];
