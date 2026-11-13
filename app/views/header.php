<?php
/**
 * Layout header — dipakai semua halaman.
 * Variabel: $pageTitle, $activeNav, $pageSubtitle (opsional), $useCharts (bool)
 */
if (!isset($pageTitle)) $pageTitle = APP_NAME;
if (!isset($activeNav)) $activeNav = '';
if (!isset($pageSubtitle)) $pageSubtitle = '';
if (!isset($useCharts)) $useCharts = false;

$repo = new Repository(Database::pdo());
$allMatches = $repo->listMatches();
$match = current_match();
$flash = flash_get();
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta name="csrf-token" content="<?= e(csrf_token()) ?>">
<title><?= e($pageTitle) ?> — <?= e(APP_NAME) ?></title>
<link rel="icon" type="image/svg+xml" href="<?= url('assets/img/favicon.svg') ?>">
<link rel="stylesheet" href="<?= url('assets/css/app.css') ?>">
</head>
<body data-page="<?= e($activeNav) ?>">

<!-- ===== SIDEBAR ===== -->
<aside class="sidebar" id="sidebar">
  <div class="sidebar-brand">
    <span class="brand-logo" aria-hidden="true">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round">
        <circle cx="15.5" cy="8.5" r="2.3"></circle>
        <path d="M13.9 10.1 5 19"></path>
        <path d="M7.5 16.5 5 19l2.5 2.5"></path>
        <path d="M10 14l-1.8 4.4"></path>
        <path d="M12.2 11.8l4.4-1.8"></path>
        <path d="M16.4 10.6l3.4 1.1"></path>
        <path d="M17.8 6.2l1.1-3.4"></path>
      </svg>
    </span>
    <span class="brand-text">
      <strong>ShuttleStats</strong>
      <small>Analisis Bulutangkis</small>
    </span>
  </div>

  <nav class="sidebar-nav">
    <p class="nav-section">Analisis</p>
    <a class="nav-item <?= $activeNav === 'dashboard' ? 'is-active' : '' ?>" href="<?= m_url('index.php') ?>">
      <svg viewBox="0 0 24 24" stroke-width="2" fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="7" height="9" rx="1.5"></rect><rect x="14" y="3" width="7" height="5" rx="1.5"></rect><rect x="14" y="12" width="7" height="9" rx="1.5"></rect><rect x="3" y="16" width="7" height="5" rx="1.5"></rect></svg>
      Dashboard
    </a>
    <a class="nav-item <?= $activeNav === 'rekap' ? 'is-active' : '' ?>" href="<?= m_url('rekap.php') ?>">
      <svg viewBox="0 0 24 24" stroke-width="2" fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round"><line x1="8" y1="6" x2="21" y2="6"></line><line x1="8" y1="12" x2="21" y2="12"></line><line x1="8" y1="18" x2="21" y2="18"></line><line x1="3" y1="6" x2="3.01" y2="6"></line><line x1="3" y1="12" x2="3.01" y2="12"></line><line x1="3" y1="18" x2="3.01" y2="18"></line></svg>
      Rekap Pemain
    </a>

    <p class="nav-section">Olah Data</p>
    <a class="nav-item <?= $activeNav === 'input' ? 'is-active' : '' ?>" href="<?= m_url('input.php') ?>">
      <svg viewBox="0 0 24 24" stroke-width="2" fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round"><path d="M12 20h9"></path><path d="M16.5 3.5a2.1 2.1 0 0 1 3 3L7 19l-4 1 1-4Z"></path></svg>
      Input Rally
    </a>
    <a class="nav-item <?= $activeNav === 'data' ? 'is-active' : '' ?>" href="<?= m_url('data.php') ?>">
      <svg viewBox="0 0 24 24" stroke-width="2" fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="4" width="18" height="16" rx="2"></rect><line x1="3" y1="10" x2="21" y2="10"></line><line x1="10" y1="4" x2="10" y2="20"></line></svg>
      Data Rally
    </a>

    <p class="nav-section">Pertandingan</p>
    <a class="nav-item <?= $activeNav === 'pertandingan' ? 'is-active' : '' ?>" href="<?= url('pertandingan.php') ?>">
      <svg viewBox="0 0 24 24" stroke-width="2" fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round"><path d="M8 21h8"></path><path d="M12 17v4"></path><path d="M7 4h10v6a5 5 0 0 1-10 0Z"></path><path d="M17 5h3a2 2 0 0 1-2 4h-1"></path><path d="M7 5H4a2 2 0 0 0 2 4h1"></path></svg>
      Daftar Pertandingan
    </a>
    <a class="nav-item <?= $activeNav === 'import' ? 'is-active' : '' ?>" href="<?= url('import.php') ?>">
      <svg viewBox="0 0 24 24" stroke-width="2" fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path><polyline points="7 9 12 14 17 9"></polyline><line x1="12" y1="14" x2="12" y2="2"></line></svg>
      Import Excel
    </a>
    <a class="nav-item <?= $activeNav === 'export' ? 'is-active' : '' ?>" href="<?= url('export.php') ?>">
      <svg viewBox="0 0 24 24" stroke-width="2" fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path><polyline points="17 8 12 3 7 8"></polyline><line x1="12" y1="3" x2="12" y2="15"></line></svg>
      Export Data
    </a>
    <a class="nav-item <?= $activeNav === 'cetak' ? 'is-active' : '' ?>" href="<?= m_url('cetak.php') ?>" target="_blank" rel="noopener">
      <svg viewBox="0 0 24 24" stroke-width="2" fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round"><polyline points="6 9 6 2 18 2 18 9"></polyline><path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"></path><rect x="6" y="14" width="12" height="8"></rect></svg>
      Cetak Laporan
    </a>
  </nav>

  <div class="sidebar-foot">
    <div class="sidebar-foot-inner">
      <span class="dot"></span> <?= e(APP_NAME) ?> v<?= e(APP_VERSION) ?>
    </div>
  </div>
</aside>
<div class="sidebar-backdrop" id="sidebarBackdrop"></div>

<!-- ===== MAIN ===== -->
<div class="main">

  <header class="topbar">
    <button class="icon-btn sidebar-toggle" id="sidebarToggle" aria-label="Buka menu">
      <svg viewBox="0 0 24 24" stroke-width="2.2" fill="none" stroke="currentColor" stroke-linecap="round"><line x1="3" y1="6" x2="21" y2="6"></line><line x1="3" y1="12" x2="21" y2="12"></line><line x1="3" y1="18" x2="21" y2="18"></line></svg>
    </button>

    <div class="match-switcher <?= $match ? '' : 'is-empty' ?>" id="matchSwitcher">
      <button class="match-switcher-btn" id="matchSwitcherBtn" type="button">
        <svg class="ms-icon" viewBox="0 0 24 24" stroke-width="2" fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round"><path d="M8 21h8"></path><path d="M12 17v4"></path><path d="M7 4h10v6a5 5 0 0 1-10 0Z"></path><path d="M17 5h3a2 2 0 0 1-2 4h-1"></path><path d="M7 5H4a2 2 0 0 0 2 4h1"></path></svg>
        <span class="ms-label">
          <?php if ($match): ?>
            <strong><?= e($match['player1']) ?></strong>
            <span class="ms-vs">vs</span>
            <strong><?= e($match['player2']) ?></strong>
          <?php else: ?>
            Belum ada pertandingan
          <?php endif; ?>
        </span>
        <svg class="ms-caret" viewBox="0 0 24 24" stroke-width="2.4" fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round"><polyline points="6 9 12 15 18 9"></polyline></svg>
      </button>
      <div class="match-dropdown" id="matchDropdown">
        <p class="match-dropdown-title">Pilih pertandingan</p>
        <?php if ($allMatches): foreach ($allMatches as $mOpt): ?>
          <a class="match-dropdown-item <?= $match && (int)$mOpt['id'] === (int)$match['id'] ? 'is-current' : '' ?>"
             href="<?= url('index.php', ['m' => $mOpt['id']]) ?>">
            <span class="mdi-name"><?= e($mOpt['player1']) ?> <em>vs</em> <?= e($mOpt['player2']) ?></span>
            <span class="mdi-meta">
              <?= (int)$mOpt['rally_count'] ?> rally
              <?php if ($mOpt['tournament'] !== ''): ?> · <?= e(mb_strimwidth($mOpt['tournament'], 0, 34, '…')) ?><?php endif; ?>
            </span>
          </a>
        <?php endforeach; else: ?>
          <p class="match-dropdown-empty">Belum ada pertandingan.<br><a href="<?= url('pertandingan.php') ?>">Buat pertandingan baru</a></p>
        <?php endif; ?>
      </div>
    </div>

    <div class="topbar-actions">
      <?php if ($match): ?>
        <a class="btn btn-primary btn-sm" href="<?= m_url('input.php') ?>">
          <svg viewBox="0 0 24 24" stroke-width="2.4" fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round"><line x1="12" y1="5" x2="12" y2="19"></line><line x1="5" y1="12" x2="19" y2="12"></line></svg>
          Input Rally
        </a>
      <?php endif; ?>
    </div>
  </header>

  <main class="content">
    <div class="page-head">
      <div>
        <h1 class="page-title"><?= $pageTitle ?></h1>
        <?php if ($pageSubtitle !== ''): ?><p class="page-subtitle"><?= $pageSubtitle ?></p><?php endif; ?>
      </div>
      <div class="page-head-actions"><?= isset($pageActions) ? $pageActions : '' ?></div>
    </div>

    <?php foreach ($flash as $f): ?>
      <div class="toast-slot" data-flash data-type="<?= e($f['type']) ?>" data-message="<?= e($f['message']) ?>"></div>
    <?php endforeach; ?>
