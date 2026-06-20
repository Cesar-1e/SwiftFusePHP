<?php
/**
 * People list view (AJAX).
 *
 * Renders an empty shell; public/js/people.js fetches /people/list using the
 * helpers in public/js/main.js and fills the table.
 */
?>
<!DOCTYPE html>
<html lang="<?= htmlspecialchars((string) config('app.locale', 'en'), ENT_QUOTES) ?>">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>People (AJAX) &middot; <?= htmlspecialchars((string) config('app.name', 'SwiftFusePHP'), ENT_QUOTES) ?></title>
    <style>
        body { font-family: system-ui, sans-serif; margin: 0; background: #0f172a; color: #e2e8f0; }
        .wrap { max-width: 760px; margin: 0 auto; padding: 3rem 1.5rem; }
        h1 { font-size: 2rem; margin-bottom: .25rem; }
        .tag { color: #94a3b8; margin-top: 0; }
        .card { background: #1e293b; border-radius: 12px; padding: 1.25rem 1.5rem; margin-top: 1.5rem; }
        table { width: 100%; border-collapse: collapse; }
        th, td { text-align: left; padding: .5rem .25rem; border-bottom: 1px solid #334155; }
        a { color: #38bdf8; }
        button { background: #38bdf8; color: #0f172a; border: 0; border-radius: 8px; padding: .5rem 1rem; font-weight: 600; cursor: pointer; }
        .status { color: #94a3b8; margin: .75rem 0 0; }
    </style>
</head>
<body>
    <div class="wrap">
        <p><a href="<?= htmlspecialchars(base_url(''), ENT_QUOTES) ?>">&larr; Back</a></p>
        <h1>People (AJAX)</h1>
        <p class="tag">Loaded asynchronously from <code>/people/list</code> using the helpers in
           <code>js/main.js</code>.</p>

        <div class="card">
            <button id="reloadPeople" onclick="loadPeople(this)">Reload</button>
            <table>
                <thead><tr><th>ID</th><th>Name</th><th>Email</th></tr></thead>
                <tbody id="peopleBody"></tbody>
            </table>
            <p id="peopleStatus" class="status"></p>
        </div>
    </div>

    <!-- Base URL consumed by main.js ajax() helper. -->
    <script>var RUTA = <?= json_encode(base_url('') . '/', JSON_UNESCAPED_SLASHES) ?>;</script>
    <script src="<?= htmlspecialchars(base_url('js/main.js'), ENT_QUOTES) ?>"></script>
    <script src="<?= htmlspecialchars(base_url('js/people.js'), ENT_QUOTES) ?>"></script>
</body>
</html>
