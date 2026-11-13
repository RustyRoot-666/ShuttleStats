<?php
/**
 * Halaman DAFTAR PERTANDINGAN — buat, ubah, hapus pertandingan.
 */
require_once __DIR__ . '/app/bootstrap.php';

$repo = new Repository(Database::pdo());
$activeId = active_match_id();

/* ---- Aksi form (non-AJAX, PRG pattern) ---- */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_verify(post('_csrf'))) {
        flash_set('error', 'Sesi kedaluwarsa. Coba lagi.');
        redirect('pertandingan.php');
    }
    $action = post('action');

    if ($action === 'create') {
        $p1 = trim((string)post('player1'));
        $p2 = trim((string)post('player2'));
        $tournament = trim((string)post('tournament'));
        $date = trim((string)post('match_date'));
        $errors = [];
        if ($p1 === '') $errors[] = 'Nama pemain 1 wajib diisi.';
        if ($p2 === '') $errors[] = 'Nama pemain 2 wajib diisi.';
        if (mb_strlen($p1) > 80 || mb_strlen($p2) > 80) $errors[] = 'Nama pemain maksimal 80 karakter.';
        if ($p1 !== '' && $p2 !== '' && strcasecmp($p1, $p2) === 0) $errors[] = 'Nama kedua pemain tidak boleh sama.';
        if ($date !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) $errors[] = 'Format tanggal tidak valid.';
        if ($errors) {
            flash_set('error', implode(' ', $errors));
        } else {
            $id = $repo->createMatch($p1, $p2, $tournament, $date);
            $_SESSION['_active_match'] = $id;
            flash_set('success', 'Pertandingan "' . $p1 . ' vs ' . $p2 . '" berhasil dibuat. Lanjutkan dengan input rally.');
            redirect('input.php', ['m' => $id]);
        }
    }

    if ($action === 'update') {
        $id = post_int('id');
        $match = $repo->getMatch($id);
        $p1 = trim((string)post('player1'));
        $p2 = trim((string)post('player2'));
        if (!$match) {
            flash_set('error', 'Pertandingan tidak ditemukan.');
        } elseif ($p1 === '' || $p2 === '') {
            flash_set('error', 'Kedua nama pemain wajib diisi.');
        } else {
            $repo->updateMatch($id, $p1, $p2, trim((string)post('tournament')), trim((string)post('match_date')));
            flash_set('success', 'Data pertandingan diperbarui.');
        }
        redirect('pertandingan.php');
    }
}

$matches = $repo->listMatches();
$editMatch = null;
if (get_str('edit') !== '') {
    $editMatch = $repo->getMatch(get_int('edit'));
}

$pageTitle = 'Daftar Pertandingan';
$activeNav = 'pertandingan';
$pageSubtitle = 'Kelola pertandingan yang akan diolah datanya';
$pageActions = '<button class="btn btn-primary" data-modal-open="#matchModal">'
    . '<svg viewBox="0 0 24 24" stroke-width="2.4" fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round"><line x1="12" y1="5" x2="12" y2="19"></line><line x1="5" y1="12" x2="19" y2="12"></line></svg>'
    . 'Pertandingan Baru</button>';

require __DIR__ . '/app/views/header.php';
?>

<?php if (!$matches): ?>
<div class="card"><div class="empty-state">
  <div class="es-icon"><svg viewBox="0 0 24 24" stroke-width="2" fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round"><path d="M8 21h8"></path><path d="M12 17v4"></path><path d="M7 4h10v6a5 5 0 0 1-10 0Z"></path><path d="M17 5h3a2 2 0 0 1-2 4h-1"></path><path d="M7 5H4a2 2 0 0 0 2 4h1"></path></svg></div>
  <h3>Belum ada pertandingan</h3>
  <p>Buat pertandingan pertama Anda: isi nama kedua pemain, nama turnamen (opsional), dan tanggal.</p>
  <button class="btn btn-primary btn-lg" data-modal-open="#matchModal">Buat Pertandingan</button>
</div></div>
<?php else: ?>
<div class="match-cards">
  <?php foreach ($matches as $m):
      $isCurrent = (int)$m['id'] === (int)$activeId;
      $entryCount = (int)$m['entry_count'];
      $rallyCount = (int)$m['rally_count'];
  ?>
  <div class="card match-card <?= $isCurrent ? 'is-current' : '' ?>">
    <div class="card-head" style="border-bottom:none;padding-bottom:0">
      <?php if ($isCurrent): ?><span class="current-flag">Sedang aktif</span><?php else: ?><span></span><?php endif; ?>
      <span class="badge badge-muted"><?= e(fmt_date_id($m['match_date'])) ?></span>
    </div>
    <div class="mc-body">
      <div class="mc-names">
        <?= e($m['player1']) ?> <em>vs</em> <?= e($m['player2']) ?>
      </div>
      <div class="mc-meta">
        <?php if ($m['tournament'] !== ''): ?><?= e($m['tournament']) ?><?php else: ?><span class="cell-muted">Tanpa nama turnamen</span><?php endif; ?>
      </div>
      <div class="mc-stats">
        <div class="mc-stat">Rally<strong><?= number_format($rallyCount, 0, ',', '.') ?></strong></div>
        <div class="mc-stat">Baris Data<strong><?= number_format($entryCount, 0, ',', '.') ?></strong></div>
        <div class="mc-stat">Dibuat<strong><?= e(date('d/m/Y', strtotime($m['created_at']))) ?></strong></div>
      </div>
    </div>
    <div class="mc-foot">
      <a class="btn btn-primary btn-sm" href="<?= url('index.php', ['m' => $m['id']]) ?>">Buka Dashboard</a>
      <a class="btn btn-ghost btn-sm" href="<?= url('input.php', ['m' => $m['id']]) ?>">Input</a>
      <a class="btn btn-ghost btn-sm" href="<?= url('pertandingan.php', ['edit' => $m['id']]) ?>">Edit</a>
      <span style="flex:1"></span>
      <button class="btn btn-danger-soft btn-sm" data-delete-match="<?= (int)$m['id'] ?>"
        data-label="<?= e($m['player1'] . ' vs ' . $m['player2']) ?>">Hapus</button>
    </div>
  </div>
  <?php endforeach; ?>
</div>
<?php endif; ?>

<!-- ===== Modal buat/edit pertandingan ===== -->
<div class="modal-backdrop <?= $editMatch ? 'is-open' : '' ?>" id="matchModal">
  <div class="modal">
    <div class="modal-head">
      <span class="modal-title"><?= $editMatch ? 'Edit Pertandingan' : 'Pertandingan Baru' ?></span>
      <button class="icon-btn" data-modal-close>✕</button>
    </div>
    <form method="post" action="pertandingan.php">
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="<?= $editMatch ? 'update' : 'create' ?>">
      <?php if ($editMatch): ?><input type="hidden" name="id" value="<?= (int)$editMatch['id'] ?>"><?php endif; ?>
      <div class="modal-body">
        <div class="form-grid">
          <div class="field">
            <label>Nama Pemain 1 <span class="required-mark">*</span></label>
            <input class="input" type="text" name="player1" maxlength="80" required
                   value="<?= $editMatch ? e($editMatch['player1']) : '' ?>" placeholder="cth. Rizky Pratama">
          </div>
          <div class="field">
            <label>Nama Pemain 2 <span class="required-mark">*</span></label>
            <input class="input" type="text" name="player2" maxlength="80" required
                   value="<?= $editMatch ? e($editMatch['player2']) : '' ?>" placeholder="cth. Bagas Setiawan">
          </div>
          <div class="field">
            <label>Turnamen</label>
            <input class="input" type="text" name="tournament" maxlength="120"
                   value="<?= $editMatch ? e($editMatch['tournament']) : '' ?>" placeholder="cth. Kejuaraan Nasional 2026">
            <span class="hint">Opsional</span>
          </div>
          <div class="field">
            <label>Tanggal</label>
            <input class="input" type="date" name="match_date"
                   value="<?= $editMatch ? e($editMatch['match_date']) : date('Y-m-d') ?>">
          </div>
        </div>
      </div>
      <div class="modal-foot">
        <button type="button" class="btn btn-ghost" data-modal-close>Batal</button>
        <button type="submit" class="btn btn-primary"><?= $editMatch ? 'Simpan Perubahan' : 'Buat Pertandingan' ?></button>
      </div>
    </form>
  </div>
</div>

<?php require __DIR__ . '/app/views/footer.php'; ?>
