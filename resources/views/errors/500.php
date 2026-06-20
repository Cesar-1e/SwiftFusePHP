<?php
/**
 * 500 error view.
 *
 * @var int $status HTTP status code.
 * @var string $message Detail message (shown only in debug mode).
 * @var bool $debug Whether debug mode is enabled.
 */
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>500 &middot; Server Error</title>
    <style>
        body { font-family: system-ui, sans-serif; display: grid; place-items: center; height: 100vh; margin: 0; background: #0f172a; color: #e2e8f0; }
        .box { text-align: center; max-width: 640px; padding: 1rem; }
        h1 { font-size: 4rem; margin: 0; }
        a { color: #38bdf8; }
        pre { text-align: left; background: #1e293b; padding: 1rem; border-radius: 8px; overflow: auto; color: #fca5a5; }
    </style>
</head>
<body>
    <div class="box">
        <h1>500</h1>
        <p>Something went wrong on our end.</p>
        <?php if (!empty($debug) && !empty($message)): ?><pre><?= htmlspecialchars((string) $message, ENT_QUOTES) ?></pre><?php endif; ?>
        <p><a href="<?= htmlspecialchars(base_url(''), ENT_QUOTES) ?>">Go home</a></p>
    </div>
</body>
</html>
