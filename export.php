<?php
/**
 * Halaman EXPORT — unduh XLSX (replika template), CSV, atau template kosong.
 * Bisa juga dipanggil langsung: export.php?type=xlsx|csv|template&m=ID
 */
require_once __DIR__ . '/app/bootstrap.php';

$type = get_str('type', '');
$match = current_match();

/* ---- Unduhan langsung ---- */
if ($type !== '') {
    try {
        if ($type === 'template') {
            // Template kosong mengikuti format asli
            $fake = [
                'id' => 0,
                'player1' => 'nama pemain 1',
                'player2' => 'nama pemain 2',
                'tournament' => '',
                'match_date' => date('Y-m-d'),
            ];
            $exporter = new Exporter(Database::pdo(), $fake);
            $exporter->downloadXlsx();
            exit;
        }
        if (!$match) {
            flash_set('error', 'Tidak ada pertandingan aktif untuk diexport.');
            redirect('pertandingan.php');
        }
        $exporter = new Exporter(Database::pdo(), $match);
        if ($type === 'xlsx') {
            $exporter->downloadXlsx();
        } elseif ($type === 'csv') {
            $exporter->downloadCsv();
        }
    } catch (Exception $e) {
        // Tampilkan pesan ramah alih-alih halaman fatal error.
        if (!headers_sent()) {
            flash_set('error', 'Export gagal: ' . $e->getMessage());
            redirect('export.php');
        }
    }
    exit;
}

$pageTitle = 'Export Data';
$activeNav = 'export';
$pageSubtitle = 'Unduh hasil olahan data dalam format Excel / CSV';
require __DIR__ . '/app/views/header.php';

$repo = new Repository(Database::pdo());
$allMatches = $repo->listMatches();
?>

<?php if (!$allMatches): ?>
<div class="card"><div class="empty-state">
  <h3>Belum ada data untuk diexport</h3>
  <p>Buat pertandingan dan isi data rally terlebih dahulu.</p>
  <a class="btn btn-primary" href="<?= url('pertandingan.php') ?>">Buat Pertandingan</a>
</div></div>
<?php else: ?>

<?php if ($match): $entriesCount = $repo->countEntries((int)$match['id']); ?>
<div class="card" style="margin-bottom:18px">
  <div class="card-head">
    <span class="card-title">Export Pertandingan Aktif</span>
    <span class="badge badge-muted"><?= e($match['player1'] . ' vs ' . $match['player2']) ?></span>
  </div>
  <div class="card-body" style="display:grid;grid-template-columns:repeat(auto-fit,minmax(260px,1fr));gap:14px">
    <div style="border:1px solid var(--line);border-radius:11px;padding:17px">
      <h3 style="font-size:14.5px;margin-bottom:6px">📊 Excel Lengkap (4 sheet)</h3>
      <p style="font-size:13px;color:var(--text-2)">Replika template asli: <strong>Input Data</strong> (data mentah), <strong>Rekapan Pemain 1</strong>, <strong>Rekap pemain 2</strong>, dan <strong>Dashbord</strong> — berisi nilai hasil olahan (ringkasan, tabel pendukung A/B/C).</p>
      <p class="cell-muted" style="font-size:12px"><?= number_format($entriesCount, 0, ',', '.') ?> baris data rally</p>
      <a class="btn btn-primary" href="<?= m_url('export.php', ['type' => 'xlsx']) ?>">
        <svg viewBox="0 0 24 24" stroke-width="2.2" fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path><polyline points="17 8 12 3 7 8"></polyline><line x1="12" y1="3" x2="12" y2="15"></line></svg>
        Unduh .xlsx
      </a>
    </div>
    <div style="border:1px solid var(--line);border-radius:11px;padding:17px">
      <h3 style="font-size:14.5px;margin-bottom:6px">📄 CSV Data Mentah</h3>
      <p style="font-size:13px;color:var(--text-2)">Semua baris rally (pemisah <code>;</code>, UTF-8). Cocok untuk diolah lebih lanjut di Excel, Python, R, atau tools lain.</p>
      <p class="cell-muted" style="font-size:12px">Kolom sama seperti sheet Input Data</p>
      <a class="btn btn-ghost" href="<?= m_url('export.php', ['type' => 'csv']) ?>">
        <svg viewBox="0 0 24 24" stroke-width="2.2" fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path><polyline points="17 8 12 3 7 8"></polyline><line x1="12" y1="3" x2="12" y2="15"></line></svg>
        Unduh .csv
      </a>
    </div>
  </div>
</div>
<?php endif; ?>

<div class="card">
  <div class="card-head"><span class="card-title">Export Pertandingan Lain</span></div>
  <div class="table-wrap">
    <table class="table">
      <thead>
        <tr><th>Pertandingan</th><th>Turnamen</th><th style="width:90px" class="num">Rally</th><th style="width:180px"></th></tr>
      </thead>
      <tbody>
        <?php foreach ($allMatches as $m): ?>
        <tr>
          <td class="cell-strong"><?= e($m['player1'] . ' vs ' . $m['player2']) ?></td>
          <td class="cell-muted"><?= e($m['tournament'] !== '' ? $m['tournament'] : '—') ?></td>
          <td class="num"><?= (int)$m['rally_count'] ?></td>
          <td class="actions-cell">
            <a class="btn btn-ghost btn-sm" href="<?= url('export.php', ['type' => 'xlsx', 'm' => $m['id']]) ?>">XLSX</a>
            <a class="btn btn-ghost btn-sm" href="<?= url('export.php', ['type' => 'csv', 'm' => $m['id']]) ?>">CSV</a>
          </td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>
<?php endif; ?>

<div class="card">
  <div class="card-head"><span class="card-title">Template &amp; Laporan Cetak</span></div>
  <div class="card-body" style="display:flex;gap:12px;flex-wrap:wrap">
    <a class="btn btn-ghost" href="<?= url('export.php', ['type' => 'template']) ?>">Unduh Template Kosong (.xlsx)</a>
    <?php if ($match): ?><a class="btn btn-ghost" href="<?= m_url('cetak.php') ?>" target="_blank">Buka Laporan Cetak</a><?php endif; ?>
  </div>
</div>

<?php require __DIR__ . '/app/views/footer.php'; ?>
