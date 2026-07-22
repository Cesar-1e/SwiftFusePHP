# Storage & Protected File Delivery

[← Back to README](../README.md)

Private files live under `storage/app/` — **outside the web root** — so they can
never be requested directly. `SwiftFuse\Storage\StorageManager` stores and streams
them, and `SwiftFuse\Storage\SignedUrl` builds short-lived links you can embed in
HTML. Behavior is configured in [`config/storage.php`](../config/storage.php).

## Why this is safe and fast

- **Private by construction:** the storage root is not under `public/`, so there
  is no URL that maps to it.
- **Authorization first:** files are only streamed after your callback returns
  `true`.
- **No memory blow-up:** delivery uses the web server (X-Sendfile /
  X-Accel-Redirect) or a chunked **HTTP Range** stream, so even large videos are
  served without loading them into PHP memory, and seeking works.

## Storing files

`store()` moves an uploaded file (`$_FILES` entry) into the private root and
returns its storage-relative path:

```php
use SwiftFuse\Storage\StorageManager;

$storage = app(StorageManager::class);
$path = $storage->store($_FILES['avatar'], 'avatars');   // e.g. "avatars/ab12….png"
```

A unique, safe filename is generated unless you pass one. See the working example
in [`UploadController`](../app/Controllers/UploadController.php) and the
[`upload` view](../resources/views/upload/index.php).

## Streaming files (with authorization)

```php
$storage->stream('invoices/2026/inv-1.pdf', function (string $absolutePath): bool {
    return isset($_SESSION['user']);   // return true to allow, false → 403
});
```

`stream()` resolves the path safely (guarding against path traversal), runs your
authorization callback, then delivers the bytes using the configured transport.

## Signed URLs (embed protected media in HTML)

For media a browser must fetch directly (e.g. `<video>`, `<img>`), generate a
**signed, expiring URL**. The signature (HMAC-SHA256, keyed with `APP_KEY`) proves
authorization without exposing the real path.

```php
use SwiftFuse\Storage\SignedUrl;

// In a controller, after checking the user is allowed:
$url = SignedUrl::make('media/file', 'video/intro.mp4', 300); // valid 5 minutes
$this->view('media.video', ['signedUrl' => $url]);
```

```php
<video src="<?= htmlspecialchars($signedUrl, ENT_QUOTES) ?>" controls></video>
```

The serving endpoint validates the signature and streams the file:

```php
public function file(): never
{
    $resource  = (string) ($_GET['resource'] ?? '');
    $expires   = (int) ($_GET['expires'] ?? 0);
    $signature = (string) ($_GET['signature'] ?? '');

    if (!SignedUrl::isValid($resource, $expires, $signature)) {
        throw new \SwiftFuse\Http\HttpException(403, 'Invalid or expired link.');
    }

    app(StorageManager::class)->stream($resource, fn () => true);
}
```

Full working example: [`MediaController`](../app/Controllers/MediaController.php).

## Acceleration (recommended in production)

By default (`STORAGE_ACCEL=none`) PHP streams the file in chunks with Range
support. For best performance, let the web server stream it instead.

### X-Sendfile (Apache)

1. Install/enable `mod_xsendfile`.
2. In your vhost: `XSendFile On` and
   `XSendFilePath /var/www/html/SwiftFusePHP/storage`.
3. Set `STORAGE_ACCEL=apache`.

### X-Accel-Redirect (Nginx)

1. Map an **internal** location to the storage root:

   ```nginx
   location /protected/ {
       internal;
       alias /var/www/html/SwiftFusePHP/storage/app/;
   }
   ```
2. Set `STORAGE_ACCEL=nginx` and `STORAGE_NGINX_INTERNAL=/protected/`.

In both modes PHP only sends headers; the web server streams the bytes (with
native range/resume), using virtually no PHP memory.

## API summary

### `StorageManager`

| Method | Description |
|--------|-------------|
| `path(string $rel): string` | Resolve a safe absolute path inside the storage root (404 if invalid). |
| `store(array $file, string $dir = 'uploads', ?string $name = null): string` | Move an uploaded file into storage; returns the relative path. |
| `stream(string $rel, callable $authorize): never` | Authorize, then deliver the file. |

### `SignedUrl`

| Method | Description |
|--------|-------------|
| `make(string $route, string $resource, int $ttl = 3600): string` | Build a signed, expiring URL. |
| `isValid(string $resource, int $expires, string $signature): bool` | Validate signature + expiry. |

> Signed URLs require `APP_KEY`. Generate it with `php fuse key:generate`.
