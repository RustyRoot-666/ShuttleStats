<?php
/**
 * Halaman INPUT RALLY — pencatatan pukulan per rally untuk kedua pemain.
 */
require_once __DIR__ . '/app/bootstrap.php';

$match = current_match();
$pageTitle = 'Input Rally';
$activeNav = 'input';

if (!$match) {
    $pageSubtitle = 'Pencatatan notasi pukulan per rally';
    require __DIR__ . '/app/views/header.php';
    echo '<div class="card"><div class="empty-state">
        <div class="es-icon"><svg viewBox="0 0 24 24" stroke-width="2" fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round"><path d="M12 20h9"></path><path d="M16.5 3.5a2.1 2.1 0 0 1 3 3L7 19l-4 1 1-4Z"></path></svg></div>
        <h3>Belum ada pertandingan</h3>
        <p>Buat pertandingan terlebih dahulu, lalu kembali ke halaman ini untuk mulai mencatat rally.</p>
        <a class="btn btn-primary" href="' . url('pertandingan.php') . '">Buat Pertandingan</a>
      </div></div>';
    require __DIR__ . '/app/views/footer.php';
    exit;
}

$repo = new Repository(Database::pdo());
$matchId = (int)$match['id'];
$defaultGame = 1;
$usedGames = $repo->usedGames($matchId);
if ($usedGames) {
    $defaultGame = max($usedGames);
}
$nextRally = $repo->nextRallyNo($matchId, $defaultGame);
$recent = $repo->recentEntries($matchId, 8);

$pageSubtitle = 'Catat jumlah pukulan kedua pemain per rally — rekap & dashboard terhitung otomatis';
$pageActions = '<a class="btn btn-ghost btn-sm" href="' . m_url('data.php') . '">Lihat semua data</a>';

require __DIR__ . '/app/views/header.php';

function counter_field($prefix, $tech)
{
    $id = $prefix . '_' . $tech['col'];
    return '<div class="pp-field">'
        . '<div class="pp-label"><span>' . e($tech['code']) . '</span></div>'
        . '<div class="counter">'
        . '<button type="button" data-minus aria-label="Kurangi">−</button>'
        . '<input type="number" min="0" max="999" value="0" id="' . $id . '" name="' . $prefix . '[' . $tech['col'] . ']" inputmode="numeric">'
        . '<button type="button" data-plus aria-label="Tambah">+</button>'
        . '</div></div>';
}
?>

<form id="rallyForm" autocomplete="off">
  <input type="hidden" name="match_id" value="<?= $matchId ?>">

  <div class="card" style="margin-bottom:16px">
    <div class="rally-form-head">
      <div class="field" style="min-width:180px">
        <label>Babak</label>
        <div class="segmented" role="radiogroup" aria-label="Babak">
          <?php foreach (games() as $g): ?>
          <input type="radio" name="game" value="<?= $g ?>" id="game<?= $g ?>" <?= $g === $defaultGame ? 'checked' : '' ?>>
          <label for="game<?= $g ?>">Babak <?= $g ?></label>
          <?php endforeach; ?>
        </div>
      </div>
      <div class="field" style="max-width:150px">
        <label for="rallyNoInput">No. Rally / Point</label>
        <input class="input" type="number" min="1" max="999" value="<?= $nextRally ?>" id="rallyNoInput" name="rally_no" inputmode="numeric" required>
      </div>
      <div style="margin-left:auto;display:flex;gap:10px;align-items:center">
        <span class="cell-muted" style="font-size:12px">Tekan <strong>Ctrl+Enter</strong> untuk simpan cepat</span>
        <button type="button" class="btn btn-ghost" id="rallyReset">Reset Form</button>
        <button type="submit" class="btn btn-primary btn-lg" id="rallySubmit">
          <svg viewBox="0 0 24 24" stroke-width="2.4" fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round"><path d="M20 6 9 17l-5-5"></path></svg>
          Simpan Rally
        </button>
      </div>
    </div>
  </div>

  <div class="player-panels" style="margin-bottom:16px">
    <?php foreach ([1, 2] as $pn):
        $prefix = 'p' . $pn;
        $pname = $pn === 1 ? $match['player1'] : $match['player2'];
    ?>
    <div class="player-panel" id="panel<?= $pn ?>">
      <div class="pp-head pp-p<?= $pn ?>">
        <span class="pp-name">
          <span class="badge badge-p<?= $pn ?>">Pemain <?= $pn ?></span>
          <?= e($pname) ?>
        </span>
        <span class="pp-total">Total pukulan: <strong>0</strong></span>
      </div>
      <div class="pp-grid">
        <?php foreach (tech_list() as $t) { echo counter_field($prefix, $t); } ?>
      </div>
    </div>
    <?php endforeach; ?>
  </div>

  <div class="card det-section">
    <div class="card-head">
      <span class="card-title">
        <svg width="16" height="16" viewBox="0 0 24 24" stroke-width="2" fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"></circle><line x1="12" y1="8" x2="12" y2="12"></line><line x1="12" y1="16" x2="12.01" y2="16"></line></svg>
        Penentu Point <span class="cell-muted" style="font-weight:400;font-size:12.5px">— isi hanya jika rally ini menghasilkan point</span>
      </span>
    </div>
    <div class="card-body">
      <div class="form-grid" style="grid-template-columns:repeat(auto-fit,minmax(190px,1fr))">
        <div class="field">
          <label>Dilakukan oleh</label>
          <div class="segmented">
            <input type="radio" name="det_player" value="1" id="detPlayerP1" checked>
            <label for="detPlayerP1"><?= e($match['player1']) ?></label>
            <input type="radio" name="det_player" value="2" id="detPlayerP2">
            <label for="detPlayerP2"><?= e($match['player2']) ?></label>
          </div>
        </div>
        <div class="field">
          <label for="detTech">Teknik penentu</label>
          <select class="select" id="detTech" name="det_technique">
            <option value="">— tidak ada —</option>
            <?php foreach (tech_list() as $t): ?>
              <option value="<?= e($t['code']) ?>"><?= e($t['label']) ?> (<?= e($t['code']) ?>)</option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="field">
          <label>Jenis hasil</label>
          <div class="segmented">
            <?php foreach (result_types() as $i => $rt): ?>
            <input type="radio" name="result_type" value="<?= e($rt) ?>" id="rt<?= $i ?>">
            <label for="rt<?= $i ?>"><?= e($rt) ?></label>
            <?php endforeach; ?>
          </div>
        </div>
        <div class="field">
          <label for="detPos">Posisi</label>
          <select class="select" id="detPos" name="position">
            <option value="">— tidak ada —</option>
            <?php foreach (positions() as $p): ?>
              <option value="<?= e($p) ?>"><?= e($p) ?> — <?= e(position_label($p)) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
      </div>
      <p class="hint" style="margin-top:12px;color:var(--muted);font-size:12.5px">
        <strong>Winning Shot</strong> = pukulan yang memenangkan point ·
        <strong>Unforced Error</strong> = kesalahan sendiri tanpa tekanan lawan ·
        <strong>Forced Error</strong> = kesalahan akibat tekanan lawan.
        Posisi mengikuti pilihan pada template asli (In forecourt / Midcourt / Rearcourt / Out / Net / Fault).
      </p>
    </div>
  </div>
</form>

<div class="card" id="recentEntriesWrap" style="margin-top:18px">
  <div class="card-head">
    <span class="card-title">Entri Terakhir</span>
    <a class="btn btn-ghost btn-sm" href="<?= m_url('data.php') ?>">Semua data &amp; filter →</a>
  </div>
  <?php $rows = $recent; include __DIR__ . '/app/views/_recent_entries.php'; ?>
</div>

<?php require __DIR__ . '/app/views/footer.php'; ?>
