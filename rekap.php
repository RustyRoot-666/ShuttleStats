<?php
/**
 * Halaman REKAP PEMAIN — replika sheet "Rekapan Pemain 1/2" template Excel,
 * ditambah filter per babak.
 */
require_once __DIR__ . '/app/bootstrap.php';

$match = current_match();
$pageTitle = 'Rekap Pemain';
$activeNav = 'rekap';

if (!$match) {
    $pageSubtitle = 'Rekapitulasi per teknik: jumlah pukulan, Forced Error, Unforced Error, Winning Shot';
    require __DIR__ . '/app/views/header.php';
    echo '<div class="card"><div class="empty-state">
        <h3>Belum ada pertandingan</h3>
        <p>Buat pertandingan terlebih dahulu untuk melihat rekap.</p>
        <a class="btn btn-primary" href="' . url('pertandingan.php') . '">Buat Pertandingan</a>
      </div></div>';
    require __DIR__ . '/app/views/footer.php';
    exit;
}

$repo = new Repository(Database::pdo());
$analyzer = new Analyzer(Database::pdo(), $match);
$matchId = (int)$match['id'];

$gameFilter = get_int('game', 0);
$usedGames = $repo->usedGames($matchId);
$recaps = [
    1 => $analyzer->recap(1, $gameFilter),
    2 => $analyzer->recap(2, $gameFilter),
];

$entriesCount = $repo->countEntries($matchId);

$pageSubtitle = e($match['player1']) . ' vs ' . e($match['player2'])
    . ' — perhitungan mengikuti rumus sheet Rekapan pada template Excel';
$pageActions =
    '<a class="btn btn-ghost btn-sm" href="' . m_url('export.php', ['type' => 'xlsx']) . '">Export XLSX</a>' .
    '<a class="btn btn-ghost btn-sm" href="' . m_url('cetak.php') . '" target="_blank">Cetak</a>';

require __DIR__ . '/app/views/header.php';

function recap_table($match, $recap, $playerNum, $gameFilter)
{
    $name = $playerNum === 1 ? $match['player1'] : $match['player2'];
    $tot = $recap['totals'];
    ?>
    <div class="card">
      <div class="card-head">
        <span class="card-title">
          <span class="dot-p<?= $playerNum ?>"></span>
          Rekapan Individu — <?= e($name) ?>
        </span>
        <span class="badge badge-muted"><?= $gameFilter > 0 ? game_label($gameFilter) : 'Gabungan semua babak' ?></span>
      </div>
      <div class="table-wrap">
        <table class="table">
          <thead>
            <tr>
              <th>Teknik</th>
              <th style="width:96px" class="num">Jumlah Pukulan</th>
              <th style="width:86px" class="num">Forced Error</th>
              <th style="width:112px">% FE</th>
              <th style="width:96px" class="num">Unforced Error</th>
              <th style="width:112px">% UE</th>
              <th style="width:90px" class="num">Winning Shot</th>
              <th style="width:112px">% WS</th>
              <th style="width:96px">Kode</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach (tech_list() as $t):
                $d = $recap['techs'][$t['code']];
                $pctBar = function ($val) {
                    return '<span class="pct-bar"><i style="width:' . min(100, round($val * 100)) . '%"></i></span>';
                };
            ?>
            <tr>
              <td class="cell-strong"><?= e($t['label']) ?></td>
              <td class="num"><?= (int)$d['jumlah'] ?></td>
              <td class="num"><?= (int)$d['fe'] ?></td>
              <td class="pct-cell"><?= fmt_pct($d['pfe']) ?><?= $d['fe'] > 0 ? '<span class="pct-bar"><i class="bar-fe" style="width:' . min(100, round($d['pfe'] * 100)) . '%"></i></span>' : '' ?></td>
              <td class="num"><?= (int)$d['ue'] ?></td>
              <td class="pct-cell"><?= fmt_pct($d['pue']) ?><?= $d['ue'] > 0 ? '<span class="pct-bar"><i class="bar-ue" style="width:' . min(100, round($d['pue'] * 100)) . '%"></i></span>' : '' ?></td>
              <td class="num"><?= (int)$d['ws'] ?></td>
              <td class="pct-cell"><?= fmt_pct($d['pws']) ?><?= $d['ws'] > 0 ? '<span class="pct-bar"><i class="bar-ws" style="width:' . min(100, round($d['pws'] * 100)) . '%"></i></span>' : '' ?></td>
              <td class="cell-muted kode-cell"><?= e($t['code']) ?></td>
            </tr>
            <?php endforeach; ?>
            <tr class="total-row">
              <td>JUMLAH</td>
              <td class="num"><?= (int)$tot['jumlah'] ?></td>
              <td class="num"><?= (int)$tot['fe'] ?></td>
              <td class="pct-cell"><?= fmt_pct($tot['pfe']) ?></td>
              <td class="num"><?= (int)$tot['ue'] ?></td>
              <td class="pct-cell"><?= fmt_pct($tot['pue']) ?></td>
              <td class="num"><?= (int)$tot['ws'] ?></td>
              <td class="pct-cell"><?= fmt_pct($tot['pws']) ?></td>
              <td></td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>
    <?php
}
?>

<?php if ($entriesCount === 0): ?>
<div class="card"><div class="empty-state">
  <h3>Belum ada data rally</h3>
  <p>Rekap akan terisi otomatis setelah Anda menginput data rally atau mengimport file Excel.</p>
  <a class="btn btn-primary" href="<?= m_url('input.php') ?>">Mulai input rally</a>
  <a class="btn btn-ghost" href="<?= url('import.php') ?>" style="margin-left:8px">Import Excel</a>
</div></div>
<?php else: ?>

<div class="card" style="margin-bottom:18px">
  <div class="card-body tight">
    <div style="display:flex;gap:12px;align-items:center;flex-wrap:wrap;padding:6px 8px">
      <span style="font-size:12.5px;font-weight:700;color:var(--muted)">TAMPILKAN:</span>
      <div class="segmented">
        <a href="<?= m_url('rekap.php') ?>"><span class="btn btn-ghost btn-sm" style="border:none;<?= $gameFilter === 0 ? 'color:var(--brand-700);background:var(--brand-100)' : '' ?>">Semua Babak</span></a>
        <?php foreach (games() as $g): ?>
          <?php if (in_array($g, $usedGames, true) || $gameFilter === $g): ?>
          <a href="<?= m_url('rekap.php', ['game' => $g]) ?>"><span class="btn btn-ghost btn-sm" style="border:none;<?= $gameFilter === $g ? 'color:var(--brand-700);background:var(--brand-100)' : '' ?>">Babak <?= $g ?></span></a>
          <?php endif; ?>
        <?php endforeach; ?>
      </div>
      <span class="cell-muted" style="font-size:12px;margin-left:auto">% dihitung terhadap jumlah pukulan teknik tersebut (sesuai rumus %FE = FE / Jumlah Pukulan)</span>
    </div>
  </div>
</div>

<?php recap_table($match, $recaps[1], 1, $gameFilter); ?>
<?php recap_table($match, $recaps[2], 2, $gameFilter); ?>

<?php endif; ?>

<?php require __DIR__ . '/app/views/footer.php'; ?>
