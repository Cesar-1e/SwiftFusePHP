<?php
/**
 * Protected video view.
 *
 * @var string $signedUrl Short-lived signed URL that streams the protected file.
 */
?>
<!DOCTYPE html>
<html lang="<?= htmlspecialchars((string) config('app.locale', 'en'), ENT_QUOTES) ?>">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Protected media &middot; <?= htmlspecialchars((string) config('app.name', 'SwiftFusePHP'), ENT_QUOTES) ?></title>
    <style>
        body { font-family: system-ui, sans-serif; margin: 0; background: #0f172a; color: #e2e8f0; }
        .wrap { max-width: 760px; margin: 0 auto; padding: 3rem 1.5rem; }
        video { width: 100%; border-radius: 12px; background: #000; }
        a { color: #38bdf8; }
        code { background: #1e293b; padding: .1rem .35rem; border-radius: 4px; }
    </style>
</head>
<body>
    <div class="wrap">
        <p><a href="<?= htmlspecialchars(base_url(''), ENT_QUOTES) ?>">&larr; Back</a></p>
        <h1>Protected video</h1>
        <p>This file lives in <code>storage/</code>, outside the web root. It is streamed
           through a signed, expiring URL with HTTP Range support (seeking works).</p>

        <video controls preload="metadata">
            <source src="<?= htmlspecialchars($signedUrl, ENT_QUOTES) ?>" type="video/mp4">
            Your browser does not support the video tag.
        </video>
    </div>
</body>
</html>
