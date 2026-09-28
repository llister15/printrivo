<?php $loggedIn = auth()->loggedIn(); ?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <meta name="theme-color" content="#1c293b">
    <title>404 — PC LOAD LETTER | Printrivo</title>
    <link rel="icon" href="<?= base_url('assets/img/printrivo_light_logo.png') ?>" type="image/png">
    <link rel="stylesheet" href="<?= base_url('assets/css/error-404.css') ?>">
<link rel="stylesheet" href="<?= base_url('assets/css/back-to-top.css') ?>">
</head>
<body>
    <header class="error-header">
        <a href="<?= site_url('/') ?>" aria-label="Printrivo home"><img src="<?= base_url('assets/img/printrivo_dark_logo.png') ?>" alt="Printrivo" width="142" height="120"></a>
        <span>PRINT QUEUE <span aria-hidden="true">/</span> JOB 00404</span>
    </header>
    <main class="error-layout" id="main">
        <section class="error-copy" aria-labelledby="error-title">
            <p class="error-eyebrow"><span aria-hidden="true"></span> PRINTER SAYS NO.</p>
            <h1 id="error-title"><span>404 —</span><br>PC LOAD LETTER</h1>
            <h2>What the %$#! does that mean?</h2>
            <p class="error-description">Apparently, it means the page you're looking for isn't print-ready.</p>
            <ul class="preflight" aria-label="Page preflight checks">
                <li><span>300 DPI?</span><span class="pass"><span aria-hidden="true">✓</span><span class="sr-only">Passed</span></span></li>
                <li><span>sRGB?</span><span class="pass"><span aria-hidden="true">✓</span><span class="sr-only">Passed</span></span></li>
                <li><span>Transparent background?</span><span class="pass"><span aria-hidden="true">✓</span><span class="sr-only">Passed</span></span></li>
                <li class="missing-page"><span>Actual page?</span><span><span aria-hidden="true">✕</span><span class="sr-only">Missing</span></span></li>
            </ul>
            <a class="error-button" href="<?= site_url($loggedIn ? 'dashboard' : '/') ?>">FIX IT <span aria-hidden="true">→</span> <?= $loggedIn ? 'BACK TO DASHBOARD' : 'BACK TO HOME' ?></a>
        </section>
        <figure class="error-image">
            <div class="printer-display"><span class="status-light" aria-hidden="true"></span><span>ERROR 404</span><span class="display-status">NOT PRINT-READY</span></div>
            <img src="<?= base_url('assets/img/pc-load-letter-office-space.png') ?>" width="498" height="498" alt="Michael Bolton from Office Space looking at the office printer, with the caption PC LOAD LETTER.">
            <figcaption><span>PC LOAD LETTER</span><span aria-hidden="true">▰ ▰ ▰ ▱</span></figcaption>
        </figure>
    </main>
    <footer>Printrivo <span aria-hidden="true">·</span> Professional print tools. Occasionally uncooperative pages.</footer>
<?= view('partials/back_to_top') ?>
</body>
</html>
