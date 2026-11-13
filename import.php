<?php
/**
 * Halaman IMPORT — unggah file Excel (.xlsx) / CSV format template Input Data.
 */
require_once __DIR__ . '/app/bootstrap.php';

$repo = new Repository(Database::pdo());
$allMatches = $repo->listMatches();
$report = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && post('action') === 'import') {
    if (!csrf_verify(post('_csrf'))) {
        flash_set('error', 'Sesi kedaluwarsa. Coba lagi.');
        redirect('import.php');
    }
    $mode = post('mode') === 'append' ? 'append' : 'new';
    $targetId = post_int('target_match');

    if (!isset($_FILES['file']) || $_FILES['file']['error'] !== UPLOAD_ERR_OK) {
        flash_set('error', 'Gagal mengunggah file. Pastikan ukuran file di bawah ' . round(app_config('upload_max') / 1048576) . ' MB.');
        redirect('import.php');
    }
    if ($_FILES['file']['size'] > app_config('upload_max')) {
        flash_set('error', 'Ukuran file melebihi batas ' . round(app_config('upload_max') / 1048576) . ' MB.');
        redirect('import.php');
    }

    $importer = new Importer($repo);
    $report = $importer->import($_FILES['file']['tmp_name'], $_FILES['file']['name'], $mode, $targetId);

    if ($report['ok']) {
        $_SESSION['_active_match'] = $report['match_id'];
        if ($report['inserted'] > 0) {
            flash_set('success', 'Import berhasil: ' . $report['inserted'] . ' baris data ditambahkan.');
        }
    }
}

$pageTitle = 'Import Excel';
$activeNav = 'import';
$pageSubtitle = 'Muat data dari file template "Input Data Task" (.xlsx) atau CSV dengan kolom yang sama';
$pageActions = '<a class="btn btn-ghost btn-sm" href="' . url('export.php', ['type' => 'template']) . '">Unduh Template Kosong</a>';

require __DIR__ . '/app/views/header.php';
?>

<?php if ($report): ?>
<div class="card" style="margin-bottom:18px">
  <div class="card-head">
    <span class="card-title">Hasil Import</span>
    <?php if ($report['ok']): ?>
      <a class="btn btn-primary btn-sm" href="<?= url('index.php', ['m' => $report['match_id']]) ?>">Buka Dashboard →</a>
    <?php endif; ?>
  </div>
  <div class="card-body">
    <?php if ($report['ok']): ?>
      <div class="callout c-ok" style="margin-bottom:12px">
        <svg viewBox="0 0 24 24" stroke-width="2.4" fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round"><path d="M20 6 9 17l-5-5"></path></svg>
        <div>
          <strong><?= number_format($report['inserted'], 0, ',', '.') ?> baris data</strong> berhasil diimport ke
          <strong><?= e($report['match_name']) ?></strong>
          <?php if ($report['skipped'] > 0): ?> · <?= (int)$report['skipped'] ?> baris dilewati<?php endif; ?>.
        </div>
      </div>
    <?php else: ?>
      <div class="callout c-danger" style="margin-bottom:12px">
        <svg viewBox="0 0 24 24" stroke-width="2.4" fill="none" stroke="currentColor" stroke-linecap="round"><line x1="12" y1="8" x2="12" y2="14"></line><line x1="12" y1="17" x2="12.01" y2="17"></line><circle cx="12" cy="12" r="10"></circle></svg>
        <div><strong>Import gagal.</strong> Periksa pesan berikut lalu perbaiki file Anda.</div>
      </div>
    <?php endif; ?>

    <?php if ($report['errors']): ?>
      <ul class="report-list">
        <?php foreach ($report['errors'] as $err): ?><li class="r-err"><?= e($err) ?></li><?php endforeach; ?>
      </ul>
    <?php endif; ?>
    <?php if ($report['warnings']): ?>
      <p style="font-size:12.5px;font-weight:700;color:var(--muted);text-transform:uppercase;letter-spacing:.05em;margin:12px 0 6px">Catatan</p>
      <ul class="report-list">
        <?php foreach (array_slice($report['warnings'], 0, 25) as $w): ?><li><?= e($w) ?></li><?php endforeach; ?>
        <?php if (count($report['warnings']) > 25): ?><li class="cell-muted">… dan <?= count($report['warnings']) - 25 ?> catatan lain.</li><?php endif; ?>
      </ul>
    <?php endif; ?>
  </div>
</div>
<?php endif; ?>

<div class="card">
  <div class="card-head">
    <span class="card-title">Unggah File Data</span>
  </div>
  <div class="card-body">
    <form method="post" action="import.php" enctype="multipart/form-data" id="importForm">
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="import">

      <label class="upload-zone" for="importFile" id="uploadZone">
        <span class="uz-icon"><svg viewBox="0 0 24 24" stroke-width="2" fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path><polyline points="7 9 12 14 17 9"></polyline><line x1="12" y1="14" x2="12" y2="2"></line></svg></span>
        <strong>Klik untuk memilih file</strong> atau seret ke sini<br>
        <span style="font-size:12.5px">Format .xlsx (sheet "Input Data") atau .csv — maksimal <?= round(app_config('upload_max') / 1048576) ?> MB</span>
        <div style="margin-top:10px;font-size:13px;color:var(--brand-700);font-weight:600" id="fileLabel"></div>
      </label>
      <input type="file" id="importFile" name="file" accept=".xlsx,.csv" required style="display:none">

      <div style="margin:18px 0 4px" class="field">
        <label>Mode import</label>
        <div class="segmented">
          <input type="radio" name="mode" value="new" id="modeNew" checked onchange="document.getElementById('appendWrap').style.display='none'">
          <label for="modeNew">Buat pertandingan baru</label>
          <input type="radio" name="mode" value="append" id="modeAppend" onchange="document.getElementById('appendWrap').style.display='block'">
          <label for="modeAppend">Tambah ke pertandingan</label>
        </div>
      </div>

      <div class="field" id="appendWrap" style="display:none;max-width:420px;margin-top:10px">
        <label for="targetMatch">Pertandingan tujuan</label>
        <select class="select" name="target_match" id="targetMatch">
          <?php foreach ($allMatches as $m): ?>
            <option value="<?= (int)$m['id'] ?>"><?= e($m['player1'] . ' vs ' . $m['player2']) ?></option>
          <?php endforeach; ?>
        </select>
        <span class="hint">Data akan ditambahkan setelah baris yang sudah ada.</span>
      </div>

      <div style="margin-top:18px;display:flex;gap:10px">
        <button type="submit" class="btn btn-primary btn-lg">
          <svg viewBox="0 0 24 24" stroke-width="2.2" fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path><polyline points="7 9 12 14 17 9"></polyline><line x1="12" y1="14" x2="12" y2="2"></line></svg>
          Mulai Import
        </button>
      </div>
    </form>
  </div>
</div>

<div class="card">
  <div class="card-head"><span class="card-title">Format file yang diterima</span></div>
  <div class="card-body" style="font-size:13.5px;color:var(--text-2)">
    <p style="margin-bottom:10px"><strong>1. Excel (.xlsx)</strong> — persis template <em>Input Data Task</em>:</p>
    <ul style="margin:0 0 14px 18px;padding:0;line-height:2">
      <li>Sheet <strong>Input Data</strong>: nama pemain di sel <code>D4</code> &amp; <code>D5</code>, turnamen <code>D6</code>, tanggal <code>D7</code>; data rally mulai <strong>baris 11</strong> dengan kolom: Babak, No. Rally, Pemain, 18 kolom teknik (Servis … LOB BH), Teknik Penentu Point (kolom Y), Jenis Hasil, Posisi.</li>
      <li>Baris tanpa nama pemain dikenali otomatis dan dilewati.</li>
    </ul>
    <p style="margin-bottom:10px"><strong>2. CSV</strong> — header dengan kolom: <code>Babak;No. Rally / Point;Pemain;Servis;Net FH;…;LOB BH;Total Pukulan;Teknik Penentu Point;Jenis Hasil;Posisi</code> (pemisah <code>;</code> atau <code>,</code>). Baris metadata opsional sebelum header: <code>Nama Pemain 1;…</code>, <code>Nama Pemain 2;…</code>, <code>Turnamen;…</code>, <code>Tanggal;…</code>.</p>
    <div class="callout" style="margin-top:14px">
      <svg viewBox="0 0 24 24" stroke-width="2" fill="none" stroke="currentColor" stroke-linecap="round"><circle cx="12" cy="12" r="10"></circle><line x1="12" y1="16" x2="12" y2="12"></line><line x1="12" y1="8" x2="12.01" y2="8"></line></svg>
      <div>Nilai yang valid otomatis dipetakan: Babak 1–3 · Jenis Hasil: <em>Winning Shot / Unforced Error / Forced Error</em> · Posisi: <em>In forecourt / In Midcourt / In Rearcourt / Out / Net / Fault</em> · 18 kode teknik mengikuti template.</div>
    </div>
  </div>
</div>

<script>
(function () {
  var input = document.getElementById('importFile');
  var label = document.getElementById('fileLabel');
  var zone = document.getElementById('uploadZone');
  input.addEventListener('change', function () {
    label.textContent = input.files.length ? 'File dipilih: ' + input.files[0].name : '';
  });
  ['dragover', 'dragenter'].forEach(function (ev) {
    zone.addEventListener(ev, function (e) { e.preventDefault(); zone.classList.add('is-drag'); });
  });
  ['dragleave', 'drop'].forEach(function (ev) {
    zone.addEventListener(ev, function (e) { e.preventDefault(); zone.classList.remove('is-drag'); });
  });
  zone.addEventListener('drop', function (e) {
    if (e.dataTransfer.files.length) {
      input.files = e.dataTransfer.files;
      label.textContent = 'File dipilih: ' + input.files[0].name;
    }
  });
})();
</script>

<?php require __DIR__ . '/app/views/footer.php'; ?>
