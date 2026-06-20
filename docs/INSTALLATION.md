# Installation

[← Back to README](../README.md)

## Requirements

- **PHP >= 8.4** with extensions: `pdo` (+ `pdo_mysql`), `json`, `gd`, `intl`.
- **Apache** or **Nginx** (or PHP's built-in server for local development).
- **MySQL / MariaDB** for the database examples.

## 1. Get the code & configure the environment

```bash
cp .env.example .env
php fuse key:generate          # writes a random APP_KEY into .env
```

Edit `.env` with your settings (database credentials, `APP_URL`, etc.). Every key
is documented in **[CONFIGURATION.md](CONFIGURATION.md)**.

> `APP_KEY` is required for signed URLs. Never commit your real `.env`
> (it is already in `.gitignore`).

## 2. (Optional) import the example database

```bash
mysql -u root -p < DB/swiftfusephp_test.sql
```

This creates the `people` table used by the home/people demos.

## 3. Permissions

The web server user must be able to write to `storage/`:

```bash
chmod -R u+rwX storage
```

## 4. Serve the application

### Local development (built-in server)

```bash
php -S localhost:8000 -t public
```

`-t public` sets the document root to `public/`. Open <http://localhost:8000/>.

### Apache — DocumentRoot at `public/` (recommended)

```apache
<VirtualHost *:80>
    ServerName swiftfuse.local
    DocumentRoot "/var/www/html/SwiftFusePHP/public"

    <Directory "/var/www/html/SwiftFusePHP/public">
        AllowOverride All
        Require all granted
    </Directory>
</VirtualHost>
```

Enable `mod_rewrite`, then set `APP_URL=http://swiftfuse.local`. The framework
core, `config/`, `.env` and `storage/` are now unreachable from the web by
construction.

### Apache — serving from a sub-folder (XAMPP/WAMP/shared hosting)

If your DocumentRoot is the project root (e.g. the project sits in `htdocs` and
you browse `http://localhost/SwiftFusePHP/`), you don't have to change anything:
the **root `.htaccess`** transparently forwards every request into `public/` and
blocks direct access to the internals.

In that case set:

```ini
APP_URL=http://localhost/SwiftFusePHP
```

> An even cleaner option is an `Alias` that maps the URL to `public/` while
> keeping the rest private:
>
> ```apache
> Alias /SwiftFusePHP "/var/www/html/SwiftFusePHP/public"
> <Directory "/var/www/html/SwiftFusePHP/public">
>     AllowOverride All
>     Require all granted
> </Directory>
> ```

### Nginx — DocumentRoot at `public/`

```nginx
server {
    listen 80;
    server_name swiftfuse.local;
    root /var/www/html/SwiftFusePHP/public;
    index index.php;

    location / {
        try_files $uri /index.php?url=$uri&$args;
    }

    location ~ \.php$ {
        include fastcgi_params;
        fastcgi_pass unix:/run/php/php8.4-fpm.sock;
        fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
    }
}
```

For accelerated protected-file delivery on Nginx, see
[STORAGE.md](STORAGE.md#x-accel-redirect-nginx).

## 5. Verify

Visit:

- `/` — landing page (MVC + PDO demo)
- `/people` — the same list loaded via AJAX
- `/media/video` — a protected video streamed through a signed URL
- `/upload` — upload files into private storage

To confirm privatization, requesting `/.env`, `/config/...`, `/src/...` or
`/storage/...` must return **403/404**.

## Composer (optional)

SwiftFusePHP does **not** require Composer. If you want to pull in third-party
libraries, run `composer install` — the built-in autoloader detects
`vendor/autoload.php` automatically and loads it alongside the framework.
