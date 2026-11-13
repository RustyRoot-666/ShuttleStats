<?php
/**
 * LAPORAN CETAK — tampilan siap cetak (Ctrl+P) berisi seluruh hasil olahan.
 */
require_once __DIR__ . '/app/bootstrap.php';

$match = current_match();

if (!$match) {
    http_response_code(404);
    echo '<h1 style="font-family:sans-serif;padding:40px">Pertandingan tidak ditemukan.</h1>';
    exit;
}

$repo = new Repository(Database::pdo());
$analyzer = new Analyzer(Database::pdo(), $match);
$matchId = (int)$match['id'];
$summary = $analyzer->summary();
$recaps = [1 => $analyzer->recap(1), 2 => $analyzer->recap(2)];
$penentu = [1 => $analyzer->penentuCounts(1), 2 => $analyzer->penentuCounts(2)];
$entriesCount = $repo->countEntries($matchId);

function rekap_print_table($match, $recap, $playerNum)
{
    $name = $playerNum === 1 ? $match['player1'] : $match['player2'];
    $tot = $recap['totals'];
    ?>
    <table class="table">
      <thead>
        <tr>
          <th style="border-bottom:2px solid #17322B">Teknik — <?= e($name) ?></th>
          <th>Jumlah</th><th>FE</th><th>%FE</th><th>UE</th><th>%UE</th><th>WS</th><th>%WS</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach (tech_list() as $t): $d = $recap['techs'][$t['code']]; ?>
        <tr>
          <td><?= e($t['label']) ?></td>
          <td class="num"><?= (int)$d['jumlah'] ?></td>
          <td class="num"><?= (int)$d['fe'] ?></td>
          <td class="num"><?= fmt_pct($d['pfe']) ?></td>
          <td class="num"><?= (int)$d['ue'] ?></td>
          <td class="num"><?= fmt_pct($d['pue']) ?></td>
          <td class="num"><?= (int)$d['ws'] ?></td>
          <td class="num"><?= fmt_pct($d['pws']) ?></td>
        </tr>
        <?php endforeach; ?>
        <tr class="total-row">
          <td>JUMLAH</td>
          <td class="num"><?= (int)$tot['jumlah'] ?></td>
          <td class="num"><?= (int)$tot['fe'] ?></td>
          <td class="num"><?= fmt_pct($tot['pfe']) ?></td>
          <td class="num"><?= (int)$tot['ue'] ?></td>
          <td class="num"><?= fmt_pct($tot['pue']) ?></td>
          <td class="num"><?= (int)$tot['ws'] ?></td>
          <td class="num"><?= fmt_pct($tot['pws']) ?></td>
        </tr>
      </tbody>
    </table>
    <?php
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Laporan — <?= e($match['player1']) ?> vs <?= e($match['player2']) ?></title>
<link rel="stylesheet" href="<?= url('assets/css/app.css') ?>">
<style>
  body { background: #fff; }
  .print-page { max-width: 900px; margin: 0 auto; padding: 34px 26px 60px; }
  .print-head { border-bottom: 3px solid #17322B; padding-bottom: 14px; margin-bottom: 22px; display: flex; justify-content: space-between; align-items: flex-end; gap: 14px; }
  .print-head h1 { font-size: 21px; }
  .print-head .ph-sub { color: #5A6B60; font-size: 13px; margin-top: 4px; }
  .print-section { margin-bottom: 26px; }
  .print-section > h2 { font-size: 15px; border-left: 4px solid #1AA04E; padding-left: 9px; margin-bottom: 10px; }
  .print-tools { position: fixed; top: 14px; right: 14px; display: flex; gap: 8px; }
  .table th { background: #F0F5F0; border-bottom: 1.5px solid #9DB5A5; }
  .table td, .table th { padding: 6px 9px; font-size: 12px; }
  .kv { display: grid; grid-template-columns: repeat(auto-fit, minmax(190px, 1fr)); gap: 10px; }
  .kv .kvi { border: 1px solid #D7E0D8; border-radius: 9px; padding: 10px 13px; }
  .kv .kvi b { display: block; font-size: 20px; }
  .kv .kvi span { font-size: 11px; text-transform: uppercase; letter-spacing: .05em; color: #77857C; font-weight: 700; }
  .score-line { font-size: 14px; margin: 4px 0 0; }
  @media print { .print-tools { display: none; } .print-page { padding: 0; } }
</style>
</head>
<body>

<div class="print-tools no-print">
  <button class="btn btn-primary" onclick="window.print()">Cetak / Simpan PDF</button>
  <a class="btn btn-ghost" href="<?= m_url('index.php') ?>">← Kembali</a>
</div>

<div class="print-page">
  <div class="print-head">
    <div>
      <h1>Laporan Analisis Pertandingan Bulutangkis</h1>
      <div class="ph-sub">
        <strong><?= e($match['player1']) ?></strong> vs <strong><?= e($match['player2']) ?></strong>
        <?= $match['tournament'] !== '' ? ' · ' . e($match['tournament']) : '' ?>
        <?= $match['match_date'] !== '' ? ' · ' . e(fmt_date_id($match['match_date'])) : '' ?>
      </div>
    </div>
    <div style="text-align:right;font-size:11px;color:#77857C">
      <?= e(APP_NAME) ?> v<?= e(APP_VERSION) ?><br>Dicetak: <?= date('d/m/Y H:i') ?>
    </div>
  </div>

  <?php if ($entriesCount === 0): ?>
    <p style="padding:30px;text-align:center;color:#77857C">Belum ada data rally pada pertandingan ini.</p>
  <?php else: ?>

  <div class="print-section">
    <h2>Ringkasan Pertandingan</h2>
    <div class="kv">
      <div class="kvi"><span>Total Rally</span><b><?= fmt_num($summary['total_rallies']) ?></b></div>
      <div class="kvi"><span>Total Pukulan</span><b><?= fmt_num($summary['total_shots']) ?></b></div>
      <div class="kvi"><span>Rata-rata Pukulan / Rally</span><b><?= number_format($summary['avg_rally_len'], 1, ',', '.') ?></b></div>
      <div class="kvi"><span>Rally Terpanjang</span><b><?= fmt_num($summary['longest_rally']) ?></b></div>
    </div>
    <p class="score-line" style="margin-top:12px">
      <?php foreach ($summary['games'] as $g => $gData): ?>
        <?= e(game_label($g)) ?>:
        <strong><?= (int)$gData['points'][1] ?>–<?= (int)$gData['points'][2] ?></strong>
        (<?= $gData['winner'] > 0 ? e($gData['winner'] === 1 ? $match['player1'] : $match['player2']) : 'berlangsung' ?>) &nbsp;
      <?php endforeach; ?>
    </p>
    <table class="table" style="margin-top:10px">
      <thead>
        <tr><th>Ringkasan</th><th class="num">Total Pukulan</th><th class="num">Forced Error</th><th class="num">Unforced Error</th><th class="num">Winning Shot</th><th class="num">Poin</th></tr>
      </thead>
      <tbody>
        <?php foreach ([1, 2] as $p): $d = $summary['players'][$p]; ?>
        <tr>
          <td class="cell-strong"><?= e($d['name']) ?></td>
          <td class="num"><?= fmt_num($d['shots']) ?></td>
          <td class="num"><?= fmt_num($d['fe']) ?></td>
          <td class="num"><?= fmt_num($d['ue']) ?></td>
          <td class="num"><?= fmt_num($d['ws']) ?></td>
          <td class="num"><?= fmt_num($d['points']) ?></td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>

  <div class="print-section">
    <h2>Rekapan Individu — Gabungan Semua Babak</h2>
    <?php rekap_print_table($match, $recaps[1], 1); ?>
    <div style="height:18px"></div>
    <?php rekap_print_table($match, $recaps[2], 2); ?>
  </div>

  <div class="print-section">
    <h2>Teknik Penentu Point</h2>
    <table class="table">
      <thead>
        <tr><th>Teknik</th><th class="num"><?= e($match['player1']) ?></th><th class="num"><?= e($match['player2']) ?></th></tr>
      </thead>
      <tbody>
        <?php foreach (tech_list() as $i => $t): ?>
        <tr>
          <td><?= e($t['label']) ?></td>
          <td class="num"><?= (int)$penentu[1][$i] ?></td>
          <td class="num"><?= (int)$penentu[2][$i] ?></td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>

  <div class="print-section">
    <h2>Catatan</h2>
    <p style="font-size:12px;color:#5A6B60">
      %FE / %UE / %WS dihitung terhadap jumlah pukulan teknik terkait (mengikuti rumus sheet Rekapan template
      <em>Input Data Task</em>: <code>=IFERROR(FE/Jumlah;0)</code>). Poin dihitung dari penentu point:
      pukulan <em>Winning Shot</em> milik pemain, atau error (<em>Unforced/Forced Error</em>) milik lawan.
    </p>
  </div>
  <?php endif; ?>
</div>

</body>
</html>
