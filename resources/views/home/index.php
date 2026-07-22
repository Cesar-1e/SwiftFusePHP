<?php
/**
 * Home / landing view.
 *
 * @var array<int, object> $people People loaded from the database.
 * @var string|null $error Error message when the query failed.
 */
?>
<!DOCTYPE html>
<html lang="<?= htmlspecialchars((string) config('app.locale', 'en'), ENT_QUOTES) ?>">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= htmlspecialchars((string) config('app.name', 'SwiftFusePHP'), ENT_QUOTES) ?></title>
    <style>
        body { font-family: system-ui, sans-serif; margin: 0; background: #0f172a; color: #e2e8f0; }
        .wrap { max-width: 760px; margin: 0 auto; padding: 3rem 1.5rem; }
        h1 { font-size: 2rem; margin-bottom: .25rem; }
        .tag { color: #94a3b8; margin-top: 0; }
        .card { background: #1e293b; border-radius: 12px; padding: 1.25rem 1.5rem; margin-top: 1.5rem; }
        table { width: 100%; border-collapse: collapse; }
        th, td { text-align: left; padding: .5rem .25rem; border-bottom: 1px solid #334155; }
        a { color: #38bdf8; }
        .note { color: #fca5a5; }
    </style>
</head>
<body>
    <div class="wrap">
        <h1><?= htmlspecialchars((string) config('app.name', 'SwiftFusePHP'), ENT_QUOTES) ?></h1>
        <p class="tag">An efficient and versatile PHP framework for seamless web development.</p>

        <div class="card">
            <h2>People (MVC + PDO demo)</h2>
            <p><a href="<?= htmlspecialchars(base_url('people'), ENT_QUOTES) ?>">See the same list loaded via AJAX &rarr;</a></p>
            <?php if (!empty($error)): ?>
                <p class="note">Database message: <?= htmlspecialchars((string) $error, ENT_QUOTES) ?></p>
            <?php endif; ?>

            <?php if (!empty($people)): ?>
                <table>
                    <thead><tr><th>ID</th><th>Name</th><th>Email</th></tr></thead>
                    <tbody>
                    <?php foreach ($people as $person): ?>
                        <tr>
                            <td><?= htmlspecialchars((string) $person->peopleId, ENT_QUOTES) ?></td>
                            <td><?= htmlspecialchars((string) $person->name, ENT_QUOTES) ?></td>
                            <td><?= htmlspecialchars((string) $person->email, ENT_QUOTES) ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            <?php elseif (empty($error)): ?>
                <p>No people found.</p>
            <?php endif; ?>
        </div>

        <div class="card">
            <h2>Protected media demo</h2>
            <p><a href="<?= htmlspecialchars(base_url('media/video'), ENT_QUOTES) ?>">Watch a protected video &rarr;</a></p>
            <p><a href="<?= htmlspecialchars(base_url('upload'), ENT_QUOTES) ?>">Upload files to private storage &rarr;</a></p>
        </div>
    </div>
</body>
</html>
