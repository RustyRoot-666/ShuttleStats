<?php
/**
 * Endpoint JSON untuk interaksi AJAX.
 * Semua request POST wajib membawa token CSRF (header X-CSRF-Token).
 */
require_once __DIR__ . '/app/bootstrap.php';

header('Cache-Control: no-store');

$action = get_str('action');
$isPost = ($_SERVER['REQUEST_METHOD'] === 'POST');

if ($isPost) {
    $token = isset($_SERVER['HTTP_X_CSRF_TOKEN']) ? $_SERVER['HTTP_X_CSRF_TOKEN'] : post('_csrf');
    if (!csrf_verify($token)) {
        http_json(['ok' => false, 'message' => 'Sesi kedaluwarsa atau token keamanan tidak valid. Muat ulang halaman.'], 419);
    }
}

$repo = new Repository(Database::pdo());

switch ($action) {

    /* ---- Simpan satu rally (dua baris pemain) dari halaman Input ---- */
    case 'save_rally':
        if (!$isPost) http_json(['ok' => false, 'message' => 'Metode tidak valid.'], 405);

        $match = current_match();
        if (!$match) {
            http_json(['ok' => false, 'message' => 'Tidak ada pertandingan aktif. Buat dulu di menu Daftar Pertandingan.']);
        }
        $matchId = (int)$match['id'];
        $game = clamp_int(post('game', 1), 1, 3);
        $rallyNo = clamp_int(post('rally_no', 1), 1, 999);
        $detPlayer = clamp_int(post('det_player', 0), 0, 2);
        $detTech = trim((string)post('det_technique'));
        $resultType = trim((string)post('result_type'));
        $position = trim((string)post('position'));

        // Validasi kombinasi penentu point
        $hasDet = ($detPlayer > 0 && $detTech !== '' && $resultType !== '');
        if ($detPlayer > 0 && !$hasDet && ($detTech !== '' || $resultType !== '')) {
            http_json(['ok' => false, 'message' => 'Penentu point belum lengkap: isi pemain, teknik, dan jenis hasil (atau kosongkan semuanya).']);
        }
        if ($hasDet && tech_by_code($detTech) === null) {
            http_json(['ok' => false, 'message' => 'Teknik penentu point tidak valid.']);
        }
        if ($hasDet && !in_array($resultType, result_types(), true)) {
            http_json(['ok' => false, 'message' => 'Jenis hasil tidak valid.']);
        }
        $positionOk = '';
        if ($position !== '') {
            foreach (positions() as $p) {
                if (strcasecmp($p, $position) === 0) { $positionOk = $p; break; }
            }
            if ($positionOk === '') {
                http_json(['ok' => false, 'message' => 'Posisi tidak valid.']);
            }
        }

        $inserted = 0;
        foreach ([1, 2] as $pn) {
            $prefix = 'p' . $pn;
            $shotsPost = post($prefix);
            if (!is_array($shotsPost)) {
                $shotsPost = [];
            }
            $shots = [];
            $total = 0;
            foreach (tech_list() as $t) {
                $v = clamp_int(isset($shotsPost[$t['col']]) ? $shotsPost[$t['col']] : 0, 0, 999);
                $shots[$t['col']] = $v;
                $total += $v;
            }
            $isDet = ($hasDet && $detPlayer === $pn);
            if ($total === 0 && !$isDet) {
                continue; // pemain tanpa pukulan & tanpa penentu → tidak disimpan
            }
            $data = [
                'match_id' => $matchId,
                'game' => $game,
                'rally_no' => $rallyNo,
                'player_num' => $pn,
                'det_technique' => $isDet ? $detTech : null,
                'result_type' => $isDet ? $resultType : null,
                'position' => $isDet ? ($positionOk !== '' ? $positionOk : null) : null,
            ] + $shots;
            $data['total_shots'] = $total;
            $repo->insertEntry($data);
            $inserted++;
        }

        if ($inserted === 0) {
            http_json(['ok' => false, 'message' => 'Form kosong: isi jumlah pukulan minimal satu teknik.']);
        }

        ob_start();
        render_recent_entries($repo, $match, $matchId);
        $html = ob_get_clean();

        http_json([
            'ok' => true,
            'message' => 'Rally ' . $rallyNo . ' (' . game_label($game) . ') tersimpan — ' . $inserted . ' baris data.',
            'next_rally_no' => $repo->nextRallyNo($matchId, $game),
            'recent_html' => $html,
        ]);
        break;

    /* ---- Edit satu entri dari halaman Data ---- */
    case 'update_entry':
        if (!$isPost) http_json(['ok' => false, 'message' => 'Metode tidak valid.'], 405);
        $id = post_int('id');
        $entry = $repo->getEntry($id);
        if (!$entry) {
            http_json(['ok' => false, 'message' => 'Entri tidak ditemukan.']);
        }
        $input = [
            'match_id' => $entry['match_id'],
            'game' => post('game', $entry['game']),
            'rally_no' => post('rally_no', $entry['rally_no']),
            'player_num' => post('player_num', $entry['player_num']),
            'det_technique' => post('det_technique'),
            'result_type' => post('result_type'),
            'position' => post('position'),
        ];
        foreach (tech_list() as $t) {
            $input[$t['col']] = post($t['col'], 0);
        }
        list($data, $errors) = $repo->sanitizeEntry($input);
        if ($errors) {
            http_json(['ok' => false, 'message' => implode(' ', $errors)]);
        }
        $repo->updateEntry($id, $data);
        http_json(['ok' => true, 'message' => 'Entri #' . $id . ' berhasil diperbarui.']);
        break;

    case 'delete_entry':
        if (!$isPost) http_json(['ok' => false, 'message' => 'Metode tidak valid.'], 405);
        $id = post_int('id');
        $entry = $repo->getEntry($id);
        if (!$entry) http_json(['ok' => false, 'message' => 'Entri tidak ditemukan.']);
        $repo->deleteEntry($id);
        http_json(['ok' => true, 'message' => 'Entri dihapus.']);
        break;

    case 'delete_rally':
        if (!$isPost) http_json(['ok' => false, 'message' => 'Metode tidak valid.'], 405);
        $matchId = post_int('match_id');
        $game = post_int('game');
        $rallyNo = post_int('rally_no');
        $n = $repo->deleteRally($matchId, $game, $rallyNo);
        http_json(['ok' => true, 'message' => $n . ' baris rally dihapus.']);
        break;

    case 'delete_match':
        if (!$isPost) http_json(['ok' => false, 'message' => 'Metode tidak valid.'], 405);
        $id = post_int('id');
        $match = $repo->getMatch($id);
        if (!$match) http_json(['ok' => false, 'message' => 'Pertandingan tidak ditemukan.']);
        $repo->deleteMatch($id);
        http_json(['ok' => true, 'message' => 'Pertandingan dihapus.', 'redirect' => url('pertandingan.php')]);
        break;

    case 'clear_entries':
        if (!$isPost) http_json(['ok' => false, 'message' => 'Metode tidak valid.'], 405);
        $id = post_int('id');
        $match = $repo->getMatch($id);
        if (!$match) http_json(['ok' => false, 'message' => 'Pertandingan tidak ditemukan.']);
        $repo->clearEntries($id);
        http_json(['ok' => true, 'message' => 'Semua data rally pertandingan ini dikosongkan.']);
        break;

    default:
        http_json(['ok' => false, 'message' => 'Aksi tidak dikenal.'], 400);
}

/* ===== render partial: tabel entri terbaru (dipakai save_rally) ===== */
function render_recent_entries(Repository $repo, array $match, $matchId)
{
    $rows = $repo->recentEntries($matchId, 8);
    include __DIR__ . '/app/views/_recent_entries.php';
}
