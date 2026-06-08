## SwiftFusePHP Framework

This repository contains a custom PHP framework designed for PHP 8.4.

### Latest updates
- Added environment configuration support using `.env` and `Config/env.php`.
- Introduced a secure `storage/` folder with web access denial.
- Implemented a background execution service in `Libreria/Services/BackgroundService.php`.
- Added an `App/` folder for custom controllers, models, and services.
- Deprecated the legacy `Archivos/` directory and legacy file routing.
- Added extension hooks in `Libreria/Controlador.user.php` for `beforeAction` and `afterAction`.

### Recommendations for the next restructure phase
- Move the public web root to `public/` and keep core files outside the web root.
- Keep framework files read-only and extend functionality through `App/`.
- Use the `storage/` folder for private binaries and streaming protected media.

`SwiftFusePHP version: 0.9.3`

