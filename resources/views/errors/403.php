<?php
/**
 * 403 error view.
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
    <title>403 &middot; Forbidden</title>
    <style>
        body { font-family: system-ui, sans-serif; display: grid; place-items: center; height: 100vh; margin: 0; background: #0f172a; color: #e2e8f0; }
        .box { text-align: center; }
        h1 { font-size: 4rem; margin: 0; }
        a { color: #38bdf8; }
        small { color: #fca5a5; }
    </style>
</head>
<body>
    <div class="box">
        <h1>403</h1>
        <p>You are not authorized to access this resource.</p>
        <?php if (!empty($debug) && !empty($message)): ?><small><?= htmlspecialchars((string) $message, ENT_QUOTES) ?></small><br><?php endif; ?>
        <p><a href="<?= htmlspecialchars(base_url(''), ENT_QUOTES) ?>">Go home</a></p>
    </div>
</body>
</html>
