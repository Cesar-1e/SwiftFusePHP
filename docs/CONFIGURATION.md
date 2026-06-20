# Configuration

[← Back to README](../README.md)

Configuration has two layers:

1. **`.env`** — environment-specific values (secrets, hostnames). Never committed.
2. **`config/*.php`** — typed configuration files that read from `.env` with
   sensible defaults. Committed.

At boot, `bootstrap/app.php` loads `.env` (via `SwiftFuse\Support\Env`) and then
every file in `config/` (via `SwiftFuse\Support\Config`).

## Reading configuration

Use the `config()` helper with **dot notation** (`file.key`):

```php
config('app.name');             // 'SwiftFusePHP'
config('database.host');        // 'localhost'
config('app.debug', false);     // second argument is the default
```

Read raw environment values with `env()`:

```php
env('APP_DEBUG', false);        // casts "true"/"false"/"null" to native types
```

> Real server environment variables always win over `.env`: the loader never
> overwrites a variable that already exists in the environment.

## `.env` reference

| Key | Default | Description |
|-----|---------|-------------|
| `APP_NAME` | `SwiftFusePHP` | Application name. |
| `APP_ENV` | `production` | Environment name (`local`, `production`, …). |
| `APP_DEBUG` | `false` | Verbose errors when `true`. Set `false` in production. |
| `APP_VERSION` | `0.9.9` | Application version string. |
| `APP_URL` | `http://localhost` | Base URL used by `base_url()` and signed URLs. |
| `APP_LOCALE` | `en` | Locale for `SwiftFuse\Support\Format` (ICU). |
| `APP_TIMEZONE` | `UTC` | Default timezone. |
| `APP_KEY` | *(empty)* | HMAC key for signed URLs. Generate with `php fuse key:generate`. |
| `DB_DRIVER` | `mysql` | PDO driver. |
| `DB_HOST` | `localhost` | Database host. |
| `DB_PORT` | `3306` | Database port. |
| `DB_DATABASE` | `swiftfusephp_test` | Database name. |
| `DB_USERNAME` | `root` | Database user. |
| `DB_PASSWORD` | *(empty)* | Database password. |
| `DB_CHARSET` | `utf8mb4` | Connection charset. |
| `DB_PERSISTENT` | `true` | Use persistent PDO connections. |
| `DB_SSL_CA` | *(empty)* | Path to an SSL CA bundle (managed/cloud databases). |
| `STORAGE_ACCEL` | `none` | Protected delivery: `none`, `apache`, `nginx`. |
| `STORAGE_NGINX_INTERNAL` | `/protected/` | Internal location for X-Accel-Redirect. |
| `QUEUE_DRIVER` | `file` | Background queue driver: `file` or `async`. |
| `RECAPTCHA_KEY` | *(empty)* | Optional reCAPTCHA secret. |

## `config/` files

Each file returns an array; you may add your own files (e.g. `config/mail.php`)
and read them with `config('mail.host')`.

| File | Consumed by | Notable keys |
|------|-------------|--------------|
| [`config/app.php`](../config/app.php) | core, helpers | `name`, `env`, `debug`, `url`, `locale`, `timezone`, `key` |
| [`config/database.php`](../config/database.php) | `SwiftFuse\Database\Connection` | `driver`, `host`, `port`, `database`, `username`, `password`, `charset`, `persistent`, `ssl_ca` |
| [`config/storage.php`](../config/storage.php) | `SwiftFuse\Storage\StorageManager` | `root`, `accel`, `nginx_internal` |
| [`config/queue.php`](../config/queue.php) | `SwiftFuse\Queue\*` | `driver`, `path`, `php_binary` |
| [`config/services.php`](../config/services.php) | the service container | maps a contract/class → factory closure |

### Adding your own config

```php
// config/mail.php
return [
    'host' => env('MAIL_HOST', 'smtp.example.com'),
    'port' => (int) env('MAIL_PORT', 587),
];
```

```php
config('mail.host');
```

## Overriding services

`config/services.php` is where you swap a core implementation for your own,
without editing the framework. See [EXTENDING.md](EXTENDING.md#3-service-bindings-swap-an-implementation).

## Path helpers

| Helper | Returns |
|--------|---------|
| `base_path($p)` | project root |
| `app_path($p)` | `app/` |
| `config_path($p)` | `config/` |
| `storage_path($p)` | `storage/` |
| `public_path($p)` | `public/` |
| `base_url($p)` | absolute URL from `config('app.url')` |
