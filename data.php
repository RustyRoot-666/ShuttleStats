<?php
/**
 * Halaman DATA RALLY — tabel lengkap dengan filter, urut, halaman, edit & hapus.
 */
require_once __DIR__ . '/app/bootstrap.php';

$match = current_match();
$pageTitle = 'Data Rally';
$activeNav = 'data';

if (!$match) {
    $pageSubtitle = 'Semua entri pukulan per rally';
    require __DIR__ . '/app/views/header.php';
    echo '<div class="card"><div class="empty-state">
        <h3>Belum ada pertandingan</h3>
        <p>Buat pertandingan terlebih dahulu untuk mulai mengolah data.</p>
        <a class="btn btn-primary" href="' . url('pertandingan.php') . '">Buat Pertandingan</a>
      </div></div>';
    require __DIR__ . '/app/views/footer.php';
    exit;
}

$repo = new Repository(Database::pdo());
$matchId = (int)$match['id'];

$filters = [
    'game'     => get_int('game', 0),
    'player'   => get_int('player', 0),
    'result'   => get_str('result', ''),
    'position' => get_str('position', ''),
    'det'      => get_str('det', ''),
    'q'        => get_str('q', ''),
];
$sort = get_str('sort', 'default');
$page = max(1, get_int('page', 1));
$result = $repo->entries($matchId, $filters, $sort, $page, app_config('per_page'));
$usedGames = $repo->usedGames($matchId);

$pageSubtitle = number_format($result['total'], 0, ',', '.') . ' entri · ' . e($match['player1']) . ' vs ' . e($match['player2']);
$pageActions =
    '<a class="btn btn-ghost btn-sm" href="' . m_url('input.php') . '">+ Input Rally</a>' .
    '<a class="btn btn-ghost btn-sm" href="' . m_url('export.php', ['type' => 'xlsx']) . '">Export XLSX</a>' .
    '<a class="btn btn-ghost btn-sm" href="' . m_url('export.php', ['type' => 'csv']) . '">Export CSV</a>';

require __DIR__ . '/app/views/header.php';

$qs = function (array $override) use ($filters, $sort) {
    $params = array_filter(array_merge([
        'game' => $filters['game'], 'player' => $filters['player'],
        'result' => $filters['result'], 'position' => $filters['position'],
        'det' => $filters['det'], 'q' => $filters['q'], 'sort' => $sort,
    ], $override), function ($v) { return $v !== '' && $v !== 0 && $v !== null; });
    $params['m'] = current_match()['id'];
    return m_url('data.php', $params);
};

$sortOptions = [
    'default' => 'Urutan bawaan (babak → rally)',
    'rally_desc' => 'Rally terbaru dulu',
    'total_desc' => 'Total pukulan terbanyak',
    'total_asc' => 'Total pukulan tersedikit',
];
?>

<div class="card table-card">
  <form class="toolbar" method="get" action="data.php">
    <input type="hidden" name="m" value="<?= $matchId ?>">
    <input class="input search" type="search" name="q" value="<?= e($filters['q']) ?>" placeholder="Cari teknik / posisi / hasil…" data-debounce-search>
    <select class="select" name="game" data-autosubmit>
      <option value="0">Semua babak</option>
      <?php foreach (games() as $g): ?>
        <option value="<?= $g ?>" <?= $filters['game'] === $g ? 'selected' : '' ?>><?= game_label($g) ?></option>
      <?php endforeach; ?>
    </select>
    <select class="select" name="player" data-autosubmit>
      <option value="0">Semua pemain</option>
      <option value="1" <?= $filters['player'] === 1 ? 'selected' : '' ?>><?= e($match['player1']) ?></option>
      <option value="2" <?= $filters['player'] === 2 ? 'selected' : '' ?>><?= e($match['player2']) ?></option>
    </select>
    <select class="select" name="result" data-autosubmit>
      <option value="">Semua jenis hasil</option>
      <?php foreach (result_types() as $rt): ?>
        <option value="<?= e($rt) ?>" <?= $filters['result'] === $rt ? 'selected' : '' ?>><?= e($rt) ?></option>
      <?php endforeach; ?>
    </select>
    <select class="select" name="position" data-autosubmit>
      <option value="">Semua posisi</option>
      <?php foreach (positions() as $p): ?>
        <option value="<?= e($p) ?>" <?= $filters['position'] === $p ? 'selected' : '' ?>><?= e($p) ?></option>
      <?php endforeach; ?>
    </select>
    <select class="select" name="det" data-autosubmit>
      <option value="">Semua teknik penentu</option>
      <?php foreach (tech_list() as $t): ?>
        <option value="<?= e($t['code']) ?>" <?= $filters['det'] === $t['code'] ? 'selected' : '' ?>><?= e($t['label']) ?></option>
      <?php endforeach; ?>
    </select>
    <div class="toolbar-right">
      <select class="select" name="sort" data-autosubmit>
        <?php foreach ($sortOptions as $k => $label): ?>
          <option value="<?= e($k) ?>" <?= $sort === $k ? 'selected' : '' ?>><?= e($label) ?></option>
        <?php endforeach; ?>
      </select>
      <a class="btn btn-ghost btn-sm" href="<?= m_url('data.php') ?>">Reset</a>
    </div>
  </form>

  <div class="table-wrap">
    <table class="table">
      <thead>
        <tr>
          <th style="width:64px">No</th>
          <th style="width:82px">Babak</th>
          <th style="width:64px" class="num"><a class="sort-link" href="<?= $qs(['sort' => 'rally_desc', 'page' => 1]) ?>">Rally <span class="sort-arrow">▼</span></a></th>
          <th style="width:170px">Pemain</th>
          <th style="width:80px" class="num"><a class="sort-link" href="<?= $qs(['sort' => 'total_desc', 'page' => 1]) ?>">Pukulan <span class="sort-arrow">▼</span></a></th>
          <th>Rincian teknik</th>
          <th style="width:120px">Penentu</th>
          <th style="width:130px">Jenis hasil</th>
          <th style="width:120px">Posisi</th>
          <th style="width:110px"></th>
        </tr>
      </thead>
      <tbody>
        <?php if (!$result['rows']): ?>
        <tr>
          <td colspan="10">
            <div class="empty-state" style="padding:34px 16px">
              <h3>Tidak ada data</h3>
              <p>Belum ada entri yang cocok dengan filter, atau pertandingan ini belum memiliki data rally.</p>
              <a class="btn btn-primary btn-sm" href="<?= m_url('input.php') ?>">Mulai input rally</a>
            </div>
          </td>
        </tr>
        <?php else: $no = ($result['page'] - 1) * $result['perPage']; foreach ($result['rows'] as $r):
            $no++;
            $pname = (int)$r['player_num'] === 1 ? $match['player1'] : $match['player2'];
            $chips = [];
            foreach (tech_list() as $t) {
                if ((int)$r[$t['col']] > 0) {
                    $chips[] = '<span class="trash-chip' . ($r['det_technique'] === $t['code'] ? ' hot' : '') . '">'
                        . e($t['code']) . ' ×' . (int)$r[$t['col']] . '</span>';
                }
            }
            $editPayload = json_encode([
                'id' => (int)$r['id'], 'game' => (int)$r['game'], 'rally_no' => (int)$r['rally_no'],
                'player_num' => (int)$r['player_num'], 'det_technique' => $r['det_technique'],
                'result_type' => $r['result_type'], 'position' => $r['position'],
            ] + array_combine(array_map(function ($t) { return $t['col']; }, tech_list()),
                              array_map(function ($t) use ($r) { return (int)$r[$t['col']]; }, tech_list())), JSON_UNESCAPED_UNICODE);
        ?>
        <tr>
          <td class="cell-muted"><?= $no ?></td>
          <td><span class="badge badge-game"><?= e(game_label($r['game'])) ?></span></td>
          <td class="num"><?= (int)$r['rally_no'] ?></td>
          <td><span class="badge badge-p<?= (int)$r['player_num'] ?>"><?= e($pname) ?></span></td>
          <td class="num"><?= (int)$r['total_shots'] ?></td>
          <td><?php echo $chips ? implode(' ', $chips) : '<span class="cell-muted">—</span>'; ?></td>
          <td><?= $r['det_technique'] !== null ? '<strong>' . e($r['det_technique']) . '</strong>' : '<span class="cell-muted">—</span>' ?></td>
          <td><?php if ($r['result_type'] !== null): ?><span class="badge <?= e(result_badge_class($r['result_type'])) ?>"><?= e($r['result_type']) ?></span><?php else: ?><span class="cell-muted">—</span><?php endif; ?></td>
          <td class="cell-muted cell-nowrap" title="<?= $r['position'] !== null ? e(position_label($r['position'])) : '' ?>"><?= $r['position'] !== null ? e($r['position']) : '—' ?></td>
          <td class="actions-cell">
            <button class="icon-btn" data-edit-entry='<?= e($editPayload) ?>' title="Edit entri">
              <svg viewBox="0 0 24 24" stroke-width="2" fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round"><path d="M12 20h9"></path><path d="M16.5 3.5a2.1 2.1 0 0 1 3 3L7 19l-4 1 1-4Z"></path></svg>
            </button>
            <button class="icon-btn danger" data-delete-entry="<?= (int)$r['id'] ?>" data-label="rally <?= (int)$r['rally_no'] ?> (<?= e($pname) ?>)" title="Hapus baris ini">
              <svg viewBox="0 0 24 24" stroke-width="2" fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round"><polyline points="3 6 5 6 21 6"></polyline><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path></svg>
            </button>
            <button class="icon-btn danger" title="Hapus rally penuh (baris kedua pemain)"
              data-delete-rally='<?= e(json_encode(['match_id' => $matchId, 'game' => (int)$r['game'], 'rally_no' => (int)$r['rally_no'], 'label' => game_label($r['game']) . ' rally ' . (int)$r['rally_no']])) ?>'>
              <svg viewBox="0 0 24 24" stroke-width="2" fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round"><polygon points="12 2 2 7 12 12 22 7 12 2"></polygon><polyline points="2 17 12 22 22 17"></polyline><polyline points="2 12 12 17 22 12"></polyline></svg>
            </button>
          </td>
        </tr>
        <?php endforeach; endif; ?>
      </tbody>
    </table>
  </div>

  <?php if ($result['pages'] > 1): ?>
  <div class="pagination">
    <span class="page-info">Halaman <?= $result['page'] ?> dari <?= $result['pages'] ?> — <?= number_format($result['total'], 0, ',', '.') ?> entri</span>
    <?php
      $renderPage = function ($p, $label = null, $disabled = false, $current = false) use ($qs, $result) {
          if ($disabled) {
              echo '<button class="page-btn" disabled>' . ($label ?: $p) . '</button>';
          } elseif ($current) {
              echo '<button class="page-btn is-current">' . ($label ?: $p) . '</button>';
          } else {
              echo '<a class="page-btn" href="' . $qs(['page' => $p]) . '">' . ($label ?: $p) . '</a>';
          }
      };
      $renderPage($result['page'] - 1, '‹', $result['page'] <= 1);
      $start = max(1, $result['page'] - 2);
      $end = min($result['pages'], $result['page'] + 2);
      if ($start > 1) { $renderPage(1); if ($start > 2) echo '<span class="page-ellipsis">…</span>'; }
      for ($p = $start; $p <= $end; $p++) { $renderPage($p, null, false, $p === $result['page']); }
      if ($end < $result['pages']) { if ($end < $result['pages'] - 1) echo '<span class="page-ellipsis">…</span>'; $renderPage($result['pages']); }
      $renderPage($result['page'] + 1, '›', $result['page'] >= $result['pages']);
    ?>
  </div>
  <?php endif; ?>
</div>

<!-- ===== Modal edit entri ===== -->
<div class="modal-backdrop" id="editModal">
  <div class="modal modal-lg">
    <div class="modal-head">
      <span class="modal-title">Edit Entri Rally</span>
      <button class="icon-btn" data-modal-close>✕</button>
    </div>
    <form id="editForm">
      <div class="modal-body">
        <input type="hidden" name="id" id="editId">
        <div class="form-grid" style="margin-bottom:14px">
          <div class="field">
            <label>Babak</label>
            <select class="select" name="game" id="editGame">
              <?php foreach (games() as $g): ?><option value="<?= $g ?>">Babak <?= $g ?></option><?php endforeach; ?>
            </select>
          </div>
          <div class="field">
            <label>No. Rally</label>
            <input class="input" type="number" min="1" max="999" name="rally_no" id="editRallyNo">
          </div>
          <div class="field">
            <label>Pemain</label>
            <select class="select" name="player_num" id="editPlayerNum">
              <option value="1"><?= e($match['player1']) ?></option>
              <option value="2"><?= e($match['player2']) ?></option>
            </select>
          </div>
        </div>
        <p style="font-size:12.5px;font-weight:700;color:var(--muted);text-transform:uppercase;letter-spacing:.05em;margin:4px 0 8px">Jumlah pukulan per teknik</p>
        <div class="pp-grid" style="padding:0">
          <?php foreach (tech_list() as $t): ?>
          <div class="pp-field">
            <div class="pp-label"><span><?= e($t['code']) ?></span></div>
            <div class="counter">
              <button type="button" data-minus>−</button>
              <input type="number" min="0" max="999" value="0" id="edit_<?= e($t['col']) ?>" name="<?= e($t['col']) ?>" inputmode="numeric">
              <button type="button" data-plus>+</button>
            </div>
          </div>
          <?php endforeach; ?>
        </div>
        <div class="form-grid" style="margin-top:14px">
          <div class="field">
            <label>Teknik penentu</label>
            <select class="select" name="det_technique" id="edit_det_technique">
              <option value="">— tidak ada —</option>
              <?php foreach (tech_list() as $t): ?><option value="<?= e($t['code']) ?>"><?= e($t['label']) ?></option><?php endforeach; ?>
            </select>
          </div>
          <div class="field">
            <label>Jenis hasil</label>
            <select class="select" name="result_type" id="edit_result_type">
              <option value="">— tidak ada —</option>
              <?php foreach (result_types() as $rt): ?><option value="<?= e($rt) ?>"><?= e($rt) ?></option><?php endforeach; ?>
            </select>
          </div>
          <div class="field">
            <label>Posisi</label>
            <select class="select" name="position" id="edit_position">
              <option value="">— tidak ada —</option>
              <?php foreach (positions() as $p): ?><option value="<?= e($p) ?>"><?= e($p) ?></option><?php endforeach; ?>
            </select>
          </div>
        </div>
      </div>
      <div class="modal-foot">
        <button type="button" class="btn btn-ghost" data-modal-close>Batal</button>
        <button type="submit" class="btn btn-primary">Simpan Perubahan</button>
      </div>
    </form>
  </div>
</div>

<script>window.SS_TECH_COLS = <?= json_encode(array_map(function ($t) { return $t['col']; }, tech_list())) ?>;</script>

<?php require __DIR__ . '/app/views/footer.php'; ?>
