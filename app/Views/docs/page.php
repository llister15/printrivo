<?= $this->extend('marketing/layout') ?>
<?= $this->section('title') ?><?= esc($title) ?><?= $this->endSection() ?>
<?= $this->section('body_class') ?>docs-page<?= $this->endSection() ?>
<?= $this->section('head') ?><link rel="stylesheet" href="<?= base_url('assets/css/docs.css?v=20260923-expanded') ?>"><?= $this->endSection() ?>
<?= $this->section('main') ?>
<div class="container docs-shell">
<aside class="docs-sidebar"><nav aria-label="Documentation">
<?php foreach (['index' => 'Documentation overview', 'gang-sheet' => 'Printrivo Gang Sheet Builder', 'image-studio' => 'Printrivo Image Studio', 'qr-generator' => 'Printrivo QR Generator'] as $slug => $label): ?>
<a href="<?= site_url('docs' . ($slug === 'index' ? '' : '/' . $slug)) ?>"<?= $page === $slug ? ' aria-current="page"' : '' ?>><?= esc($label) ?></a>
<?php endforeach ?>
</nav></aside>
<article class="docs-content">
<nav class="docs-breadcrumb" aria-label="Breadcrumb"><a href="<?= site_url('docs') ?>">Documentation</a><?php if ($page !== 'index'): ?> <span aria-hidden="true">/</span> <span aria-current="page"><?= esc($title) ?></span><?php endif ?></nav>
<h1><?= esc($title) ?></h1>
<?= view('docs/' . $page) ?>
</article>
</div>
<?= $this->endSection() ?>
