# SwiftFusePHP Coding Standard

[← Back to README](../README.md)

> SwiftFusePHP: an efficient and versatile PHP framework for seamless web
> development. Boost productivity with its modular architecture and smooth
> integration capabilities.

This document defines the conventions every contribution to the framework core
and to application code must follow.

## Language

- **English only** for all new code: class names, methods, variables, comments
  and PHPDoc blocks.
- The legacy Spanish core (`Controlador/`, `Modelo/`, `Libreria/`, `Config/…`) is
  `@deprecated` and kept solely for backward compatibility until version `1.0`.

## Code style

- Follow **PSR-12**.
- `declare(strict_types=1);` at the top of every PHP file.
- One class/interface/trait per file; the file name matches the type name.
- Type every property, parameter and return value where the language allows it.

## Naming

| Element      | Convention            | Example                         |
|--------------|-----------------------|---------------------------------|
| Class        | `PascalCase`          | `StorageManager`                |
| Method/prop  | `camelCase`           | `streamWithRange()`             |
| Constant     | `UPPER_SNAKE_CASE`    | `CHUNK_SIZE`                    |
| Interface    | `…Interface` suffix   | `JobInterface`                  |
| Namespace    | `PascalCase` segments | `SwiftFuse\Storage`             |

## Namespaces & autoloading (PSR-4)

| Prefix        | Directory         | Purpose                              |
|---------------|-------------------|--------------------------------------|
| `SwiftFuse\`  | `src/SwiftFuse/`  | Framework core — **do not edit**     |
| `App\`        | `app/`            | Your application code — edit freely   |

Classes are loaded by the built-in autoloader in `bootstrap/autoload.php` (no
`composer install` required). If `vendor/autoload.php` exists it is also loaded,
so Composer packages integrate seamlessly.

## Documentation

Every method/function MUST have a PHPDoc block describing its purpose, its
`@param`s, its `@return`, and `@throws` when relevant. Deprecated code MUST carry
`@deprecated <version> <reason + replacement>`.

## Architecture

- **MVC**: Controllers (`app/Controllers`) → Models (`app/Models`, PDO) → Views
  (`resources/views`).
- **Database**: PDO only, through `SwiftFuse\Database\Connection`.
- **Web root**: the server's DocumentRoot points at `public/`; everything else
  (core, config, `.env`, storage) lives outside the web root.
- **Modularity**: core services are resolved from the container, so they can be
  replaced without editing the core. See [EXTENDING.md](EXTENDING.md).

## Versioning

Semantic Versioning. Deprecated APIs are only removed in a **major** release.
