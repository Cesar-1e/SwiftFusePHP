<?php
/**
 * File upload view.
 *
 * @var array{ok:bool,message:string,items:array<int,array{name:string,ok:bool,message:string,url?:string}>}|null $result
 *      The upload result, or null when the form has not been submitted yet.
 */
?>
<!DOCTYPE html>
<html lang="<?= htmlspecialchars((string) config('app.locale', 'en'), ENT_QUOTES) ?>">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Upload files &middot; <?= htmlspecialchars((string) config('app.name', 'SwiftFusePHP'), ENT_QUOTES) ?></title>
    <style>
        body { font-family: system-ui, sans-serif; margin: 0; background: #0f172a; color: #e2e8f0; }
        .wrap { max-width: 760px; margin: 0 auto; padding: 3rem 1.5rem; }
        h1 { font-size: 2rem; margin-bottom: .25rem; }
        .tag { color: #94a3b8; margin-top: 0; }
        .card { background: #1e293b; border-radius: 12px; padding: 1.25rem 1.5rem; margin-top: 1.5rem; }
        input[type=file] { width: 100%; padding: 1rem; background: #0f172a; border: 1px dashed #475569; border-radius: 8px; color: #e2e8f0; }
        button { margin-top: 1rem; background: #38bdf8; color: #0f172a; border: 0; border-radius: 8px; padding: .6rem 1.2rem; font-weight: 600; cursor: pointer; }
        ul { list-style: none; padding: 0; }
        li { padding: .5rem .25rem; border-bottom: 1px solid #334155; }
        a { color: #38bdf8; }
        .ok { color: #86efac; }
        .bad { color: #fca5a5; }
        code { background: #0f172a; padding: .1rem .35rem; border-radius: 4px; }
    </style>
</head>
<body>
    <div class="wrap">
        <p><a href="<?= htmlspecialchars(base_url(''), ENT_QUOTES) ?>">&larr; Back</a></p>
        <h1>Upload files</h1>
        <p class="tag">Files are stored in <code>storage/app/uploads</code> (outside the web root)
           and served back only through short-lived signed URLs.</p>

        <div class="card">
            <form action="<?= htmlspecialchars(base_url('upload/store'), ENT_QUOTES) ?>" method="post" enctype="multipart/form-data">
                <input type="file" name="files[]" multiple
                       accept=".jpg,.jpeg,.png,.gif,.webp,.pdf,.mp4" required>
                <p class="tag">Allowed: jpg, png, gif, webp, pdf, mp4 &middot; up to 10&nbsp;MB each.</p>
                <button type="submit">Upload</button>
            </form>
        </div>

        <?php if (!empty($result)): ?>
            <div class="card">
                <h2 class="<?= $result['ok'] ? 'ok' : 'bad' ?>"><?= htmlspecialchars($result['message'], ENT_QUOTES) ?></h2>
                <?php if (!empty($result['items'])): ?>
                    <ul>
                        <?php foreach ($result['items'] as $item): ?>
                            <li>
                                <span class="<?= $item['ok'] ? 'ok' : 'bad' ?>"><?= $item['ok'] ? '✔' : '✖' ?></span>
                                <strong><?= htmlspecialchars($item['name'], ENT_QUOTES) ?></strong>
                                &mdash; <?= htmlspecialchars($item['message'], ENT_QUOTES) ?>
                                <?php if (!empty($item['url'])): ?>
                                    &middot; <a href="<?= htmlspecialchars($item['url'], ENT_QUOTES) ?>" target="_blank" rel="noopener">view (signed)</a>
                                <?php endif; ?>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </div>
</body>
</html>
