<?php
/**
 * DASHBOARD — ringkasan pertandingan + grafik analisis.
 */
require_once __DIR__ . '/app/bootstrap.php';

$match = current_match();
$pageTitle = 'Dashboard';
$activeNav = 'dashboard';
$useCharts = true;

require __DIR__ . '/app/views/header.php';

if (!$match) {
    echo '<div class="card"><div class="empty-state">
        <div class="es-icon"><svg viewBox="0 0 24 24" stroke-width="2" fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="7" height="9" rx="1.5"></rect><rect x="14" y="3" width="7" height="5" rx="1.5"></rect><rect x="14" y="12" width="7" height="9" rx="1.5"></rect><rect x="3" y="16" width="7" height="5" rx="1.5"></rect></svg></div>
        <h3>Selamat datang di ' . e(APP_NAME) . '</h3>
        <p>Sistem untuk mengolah data notasi pertandingan bulutangkis: input pukulan per rally, rekap otomatis per pemain, dan dashboard analisis — mengikuti format template <em>Input Data Task</em>.</p>
        <a class="btn btn-primary btn-lg" href="' . url('pertandingan.php') . '">Buat Pertandingan Pertama</a>
        <a class="btn btn-ghost btn-lg" href="' . url('import.php') . '" style="margin-left:8px">Import File Excel</a>
      </div></div>';
    require __DIR__ . '/app/views/footer.php';
    exit;
}

$repo = new Repository(Database::pdo());
$analyzer = new Analyzer(Database::pdo(), $match);
$matchId = (int)$match['id'];
$entriesCount = $repo->countEntries($matchId);
$summary = $analyzer->summary();
$p1 = $summary['players'][1];
$p2 = $summary['players'][2];

$pageSubtitle = 'Analisis otomatis: ' . e($match['player1']) . ' vs ' . e($match['player2']);
?>

<?php if ($entriesCount === 0): ?>
<section class="match-hero">
  <div class="mh-top">
    <div>
      <p class="mh-meta"><?= $match['tournament'] !== '' ? e($match['tournament']) : 'Pertandingan' ?> · <?= e(fmt_date_id($match['match_date'])) ?></p>
      <div class="mh-players">
        <div class="mh-player mh-p1"><span class="mh-pnum">Pemain 1</span><span class="mh-pname"><?= e($match['player1']) ?></span></div>
        <span class="mh-vs">VS</span>
        <div class="mh-player mh-p2"><span class="mh-pnum">Pemain 2</span><span class="mh-pname"><?= e($match['player2']) ?></span></div>
      </div>
      <p class="mh-sub">Pertandingan ini belum memiliki data rally.</p>
    </div>
    <div class="mh-actions">
      <a class="btn btn-primary" href="<?= m_url('input.php') ?>">Mulai Input Rally</a>
      <a class="btn btn-ghost" href="<?= url('import.php') ?>">Import Excel</a>
    </div>
  </div>
</section>
<div class="card"><div class="empty-state">
  <h3>Belum ada data untuk dianalisis</h3>
  <p>Input rally satu per satu lewat form cepat, atau import langsung dari file Excel template <em>Input Data Task</em>. Dashboard dan rekap akan terisi otomatis.</p>
</div></div>
<?php else: ?>

<!-- ==== Hero pertandingan ==== -->
<section class="match-hero">
  <div class="mh-top">
    <div>
      <p class="mh-meta">
        <?= $match['tournament'] !== '' ? e($match['tournament']) . ' · ' : '' ?><?= e(fmt_date_id($match['match_date'])) ?>
      </p>
      <div class="mh-players">
        <div class="mh-player mh-p1"><span class="mh-pnum">Pemain 1</span><span class="mh-pname"><?= e($match['player1']) ?></span></div>
        <span class="mh-vs">VS</span>
        <div class="mh-player mh-p2"><span class="mh-pnum">Pemain 2</span><span class="mh-pname"><?= e($match['player2']) ?></span></div>
      </div>
      <p class="mh-sub">
        <?= fmt_num($summary['total_rallies']) ?> rally · <?= fmt_num($summary['total_shots']) ?> pukulan · rata-rata
        <strong><?= number_format($summary['avg_rally_len'], 1, ',', '.') ?></strong> pukulan/rally
      </p>
    </div>
    <div class="mh-actions">
      <a class="btn btn-primary" href="<?= m_url('input.php') ?>">+ Input Rally</a>
      <a class="btn btn-ghost" href="<?= m_url('export.php', ['type' => 'xlsx']) ?>">Export XLSX</a>
      <a class="btn btn-ghost" href="<?= m_url('cetak.php') ?>" target="_blank">Cetak Laporan</a>
    </div>
  </div>

  <?php if ($summary['games']): ?>
  <div class="score-strip">
    <?php foreach ($summary['games'] as $g => $gData): ?>
    <div class="score-chip <?= $gData['winner'] === 0 ? 'sc-tie' : '' ?>">
      <div>
        <div class="sc-label"><?= game_label($g) ?></div>
        <div class="sc-score"><?= (int)$gData['points'][1] ?> – <?= (int)$gData['points'][2] ?></div>
      </div>
      <?php if ($gData['winner'] > 0): ?>
        <span class="sc-winner"><?= e($gData['winner'] === 1 ? $match['player1'] : $match['player2']) ?></span>
      <?php else: ?>
        <span class="sc-winner">Berlangsung</span>
      <?php endif; ?>
    </div>
    <?php endforeach; ?>
    <?php if ($summary['unfinished'] > 0): ?>
    <div class="score-chip sc-tie">
      <div>
        <div class="sc-label">Rally tanpa hasil</div>
        <div class="sc-score"><?= (int)$summary['unfinished'] ?></div>
      </div>
      <span class="sc-winner">Perlu dilengkapi</span>
    </div>
    <?php endif; ?>
  </div>
  <?php endif; ?>
</section>

<!-- ==== Kartu statistik ==== -->
<div class="stat-grid">
  <div class="stat-card" style="--accent:var(--brand-500)">
    <div class="stat-label">Total Rally</div>
    <div class="stat-value" data-countup="<?= (int)$summary['total_rallies'] ?>"><?= fmt_num($summary['total_rallies']) ?></div>
    <div class="stat-sub"><?= $summary['unfinished'] > 0 ? (int)$summary['unfinished'] . ' rally belum berakhir' : 'semua rally memiliki hasil' ?></div>
  </div>

  <div class="stat-card" style="--accent:var(--p1)">
    <div class="stat-label">Total Pukulan</div>
    <div class="stat-value" data-countup="<?= (int)$summary['total_shots'] ?>"><?= fmt_num($summary['total_shots']) ?></div>
    <div class="stat-pair">
      <div class="sp sp-p1"><span class="sp-label"><?= e($match['player1']) ?></span><span class="sp-num"><?= fmt_num($p1['shots']) ?></span></div>
      <div class="sp sp-p2"><span class="sp-label"><?= e($match['player2']) ?></span><span class="sp-num"><?= fmt_num($p2['shots']) ?></span></div>
    </div>
  </div>

  <div class="stat-card" style="--accent:#22A55B">
    <div class="stat-label">Poin</div>
    <div class="stat-value"><?= fmt_num($p1['points']) ?> <small>: <?= fmt_num($p2['points']) ?></small></div>
    <div class="stat-pair">
      <div class="sp sp-p1"><span class="sp-label">via WS</span><span class="sp-num"><?= (int)$p1['points_ws'] ?></span></div>
      <div class="sp sp-p2"><span class="sp-label">via WS</span><span class="sp-num"><?= (int)$p2['points_ws'] ?></span></div>
    </div>
  </div>

  <div class="stat-card" style="--accent:#22A55B">
    <div class="stat-label">Winning Shot</div>
    <div class="stat-value" data-countup="<?= (int)($p1['ws'] + $p2['ws']) ?>"><?= fmt_num($p1['ws'] + $p2['ws']) ?></div>
    <div class="stat-pair">
      <div class="sp sp-p1"><span class="sp-label"><?= e($match['player1']) ?></span><span class="sp-num"><?= (int)$p1['ws'] ?></span></div>
      <div class="sp sp-p2"><span class="sp-label"><?= e($match['player2']) ?></span><span class="sp-num"><?= (int)$p2['ws'] ?></span></div>
    </div>
  </div>

  <div class="stat-card" style="--accent:#E15B5B">
    <div class="stat-label">Unforced Error</div>
    <div class="stat-value" data-countup="<?= (int)($p1['ue'] + $p2['ue']) ?>"><?= fmt_num($p1['ue'] + $p2['ue']) ?></div>
    <div class="stat-pair">
      <div class="sp sp-p1"><span class="sp-label"><?= e($match['player1']) ?></span><span class="sp-num"><?= (int)$p1['ue'] ?></span></div>
      <div class="sp sp-p2"><span class="sp-label"><?= e($match['player2']) ?></span><span class="sp-num"><?= (int)$p2['ue'] ?></span></div>
    </div>
  </div>

  <div class="stat-card" style="--accent:#E8A23C">
    <div class="stat-label">Forced Error</div>
    <div class="stat-value" data-countup="<?= (int)($p1['fe'] + $p2['fe']) ?>"><?= fmt_num($p1['fe'] + $p2['fe']) ?></div>
    <div class="stat-pair">
      <div class="sp sp-p1"><span class="sp-label"><?= e($match['player1']) ?></span><span class="sp-num"><?= (int)$p1['fe'] ?></span></div>
      <div class="sp sp-p2"><span class="sp-label"><?= e($match['player2']) ?></span><span class="sp-num"><?= (int)$p2['fe'] ?></span></div>
    </div>
  </div>

  <div class="stat-card" style="--accent:#7C8FA5">
    <div class="stat-label">Rally Terpanjang</div>
    <div class="stat-value" data-countup="<?= (int)$summary['longest_rally'] ?>"><?= fmt_num($summary['longest_rally']) ?></div>
    <div class="stat-sub">pukulan dalam satu rally</div>
  </div>
</div>

<!-- ==== Grafik ==== -->
<div class="chart-grid">
  <div class="card chart-card">
    <div class="card-head">
      <span class="card-title">Total Pukulan per Teknik</span>
      <div class="legend-chips"><span class="lc lc-p1"><?= e($match['player1']) ?></span><span class="lc lc-p2"><?= e($match['player2']) ?></span></div>
    </div>
    <div class="chart-box"><canvas id="chartTech"></canvas></div>
  </div>

  <div class="card chart-card half">
    <div class="card-head">
      <span class="card-title">Jenis Hasil — <?= e($match['player1']) ?></span>
    </div>
    <div class="chart-box" style="height:264px"><canvas id="chartHasilP1"></canvas></div>
  </div>

  <div class="card chart-card half">
    <div class="card-head">
      <span class="card-title">Jenis Hasil — <?= e($match['player2']) ?></span>
    </div>
    <div class="chart-box" style="height:264px"><canvas id="chartHasilP2"></canvas></div>
  </div>

  <div class="card chart-card half">
    <div class="card-head">
      <span class="card-title">Poin per Babak</span>
      <div class="legend-chips"><span class="lc lc-p1"><?= e($match['player1']) ?></span><span class="lc lc-p2"><?= e($match['player2']) ?></span></div>
    </div>
    <div class="chart-box" style="height:300px"><canvas id="chartBabak"></canvas></div>
  </div>

  <div class="card chart-card half">
    <div class="card-head">
      <span class="card-title">Posisi Pukulan Penentu Point</span>
      <div class="legend-chips"><span class="lc lc-p1"><?= e($match['player1']) ?></span><span class="lc lc-p2"><?= e($match['player2']) ?></span></div>
    </div>
    <div class="chart-box"><canvas id="chartPosisi"></canvas></div>
  </div>

  <div class="card chart-card">
    <div class="card-head">
      <span class="card-title">Teknik Penentu Point</span>
      <div class="legend-chips"><span class="lc lc-p1"><?= e($match['player1']) ?></span><span class="lc lc-p2"><?= e($match['player2']) ?></span></div>
    </div>
    <div class="chart-box"><canvas id="chartPenentu"></canvas></div>
  </div>
</div>

<script>
<?php
$recapAll = [1 => $analyzer->recap(1), 2 => $analyzer->recap(2)];
$shots1 = $shots2 = [];
foreach (tech_list() as $t) {
    $shots1[] = (int)$recapAll[1]['techs'][$t['code']]['jumlah'];
    $shots2[] = (int)$recapAll[2]['techs'][$t['code']]['jumlah'];
}
$gameKeys = array_keys($summary['games']);
?>
window.SS_DASH = <?= json_encode([
    'n1' => $match['player1'],
    'n2' => $match['player2'],
    'labels' => array_map(function ($t) { return $t['code']; }, tech_list()),
    'shots1' => $shots1,
    'shots2' => $shots2,
    'penentu1' => $analyzer->penentuCounts(1),
    'penentu2' => $analyzer->penentuCounts(2),
    'hasil1' => [$p1['ws'], $p1['ue'], $p1['fe']],
    'hasil2' => [$p2['ws'], $p2['ue'], $p2['fe']],
    'babakLabels' => array_map('game_label', $gameKeys),
    'babakP1' => array_map(function ($g) use ($summary) { return (int)$summary['games'][$g]['points'][1]; }, $gameKeys),
    'babakP2' => array_map(function ($g) use ($summary) { return (int)$summary['games'][$g]['points'][2]; }, $gameKeys),
    'posisiLabels' => positions(),
    'posisi1' => array_values($analyzer->positionDistribution(1)),
    'posisi2' => array_values($analyzer->positionDistribution(2)),
], JSON_UNESCAPED_UNICODE) ?>;
</script>

<?php endif; ?>

<?php require __DIR__ . '/app/views/footer.php'; ?>
