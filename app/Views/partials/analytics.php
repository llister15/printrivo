<?php
// Only fixed public pages are measured. No auth, admin, billing, or editor pages.
$analytics = config('Analytics');
$route = trim(uri_string(), '/');
if (!$analytics->ready() || auth()->loggedIn() || !in_array($route, ['', 'index.php', 'pricing', 'terms', 'privacy'], true)) { return; }
?>
<div class="container analytics-preferences"><button type="button" id="analytics-settings">Analytics preferences</button></div>
<aside id="analytics-consent" class="analytics-consent" aria-label="Optional analytics" hidden>
<strong>Help us improve Printrivo</strong>
<p>May we use Google Analytics to understand visits to our public pages? It uses cookies and shares usage information with Google. Your choice won’t affect access to Printrivo. <a href="<?= site_url('privacy') ?>">Privacy Policy</a></p>
<div><button type="button" class="button compact" id="analytics-accept">Accept analytics</button><button type="button" class="button compact" id="analytics-decline">Decline analytics</button></div>
</aside>
<script src="<?= base_url('assets/js/analytics.js?v=1') ?>" data-measurement-id="<?= esc($analytics->measurementId, 'attr') ?>" data-page-url="<?= esc(site_url($route === 'index.php' ? '' : $route), 'attr') ?>" defer></script>
