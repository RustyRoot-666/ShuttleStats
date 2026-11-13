<?php
/**
 * Partial: tabel entri rally terbaru (dipakai halaman input + api).
 * Variabel: $match (data pertandingan), $rows (entri)
 */
?>
<div class="table-wrap">
  <table class="table">
    <thead>
      <tr>
        <th style="width:70px">Babak</th>
        <th style="width:70px">Rally</th>
        <th>Pemain</th>
        <th style="width:80px" class="num">Pukulan</th>
        <th>Rincian Teknik</th>
        <th>Teknik Penentu</th>
        <th>Jenis Hasil</th>
        <th style="width:60px"></th>
      </tr>
    </thead>
    <tbody>
      <?php if (!$rows): ?>
        <tr><td colspan="8" class="cell-muted" style="text-align:center;padding:26px">Belum ada data rally.</td></tr>
      <?php else: foreach ($rows as $r):
          $pname = (int)$r['player_num'] === 1 ? $match['player1'] : $match['player2'];
          $chips = [];
          foreach (tech_list() as $t) {
              if ((int)$r[$t['col']] > 0) {
                  $chips[] = '<span class="trash-chip' . ($r['det_technique'] === $t['code'] ? ' hot' : '') . '">'
                      . e($t['code']) . ' ×' . (int)$r[$t['col']] . '</span>';
              }
          }
      ?>
      <tr>
        <td><span class="badge badge-game"><?= e(game_label($r['game'])) ?></span></td>
        <td class="num"><?= (int)$r['rally_no'] ?></td>
        <td><span class="badge badge-p<?= (int)$r['player_num'] ?>"><?= e($pname) ?></span></td>
        <td class="num"><?= (int)$r['total_shots'] ?></td>
        <td><?php echo $chips ? implode(' ', $chips) : '<span class="cell-muted">—</span>'; ?></td>
        <td class="cell-nowrap"><?= $r['det_technique'] !== null ? '<strong>' . e($r['det_technique']) . '</strong>' : '<span class="cell-muted">—</span>' ?></td>
        <td><?php if ($r['result_type'] !== null): ?><span class="badge <?= e(result_badge_class($r['result_type'])) ?>"><?= e($r['result_type']) ?></span><?php else: ?><span class="cell-muted">—</span><?php endif; ?></td>
        <td class="actions-cell">
          <button class="icon-btn danger" data-delete-entry="<?= (int)$r['id'] ?>" data-label="rally <?= (int)$r['rally_no'] ?> (<?= e($pname) ?>)" title="Hapus baris ini">
            <svg viewBox="0 0 24 24" stroke-width="2" fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round"><polyline points="3 6 5 6 21 6"></polyline><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path></svg>
          </button>
        </td>
      </tr>
      <?php endforeach; endif; ?>
    </tbody>
  </table>
</div>
