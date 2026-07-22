<?php

/**
 * Third-party asset publishing map.
 *
 * Used by the `php fuse assets:publish` command. Each entry maps a SOURCE to a
 * DESTINATION:
 *
 *   'source (relative to the project root)' => 'destination (relative to public/)'
 *
 * The map is source-agnostic — the source may come from an npm install, a
 * Composer package, or a manually downloaded file. `node_modules/` and `vendor/`
 * are only ever read as sources; they are never served.
 *
 * Recommendation: publish under "assets/..." (i.e. public/assets/). Do NOT use
 * "vendor/..." as a destination — the project's root .htaccess blocks a top-level
 * /vendor path. Git-ignore the published destination (see .gitignore).
 */

declare(strict_types=1);

return [
    // --- Examples (uncomment and adjust) ---

    // From an npm install (node_modules is the source, never the web root):
    // 'node_modules/htmx.org/dist/htmx.min.js'            => 'assets/htmx/htmx.min.js',
    // 'node_modules/bootstrap/dist/css/bootstrap.min.css' => 'assets/bootstrap/bootstrap.min.css',

    // From a Composer package:
    // 'vendor/twbs/bootstrap/dist/js/bootstrap.bundle.min.js' => 'assets/bootstrap/bootstrap.bundle.min.js',

    // A whole directory (copied recursively, or symlinked with --link):
    // 'node_modules/@fortawesome/fontawesome-free/webfonts' => 'assets/fontawesome/webfonts',

    // A manually downloaded file placed anywhere in the project:
    // 'resources/vendor/alpine.min.js' => 'assets/alpine/alpine.min.js',
];
