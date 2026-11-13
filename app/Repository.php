<?php
/**
 * Lapisan akses data: pertandingan & entri rally.
 */
class Repository
{
    /** @var PDO */
    private $db;

    public function __construct(PDO $db)
    {
        $this->db = $db;
    }

    /* ================= PERTANDINGAN ================= */

    public function listMatches()
    {
        $sql = 'SELECT m.*,
                (SELECT COUNT(*) FROM rally_entries r WHERE r.match_id = m.id) AS entry_count,
                (SELECT COUNT(DISTINCT r.game || \'-\' || r.rally_no) FROM rally_entries r WHERE r.match_id = m.id) AS rally_count
                FROM matches m ORDER BY m.id DESC';
        // SQLite: || menggabung; MySQL pakai CONCAT
        if (Database::driver() === 'mysql') {
            $sql = 'SELECT m.*,
                    (SELECT COUNT(*) FROM rally_entries r WHERE r.match_id = m.id) AS entry_count,
                    (SELECT COUNT(DISTINCT CONCAT(r.game, \'-\', r.rally_no)) FROM rally_entries r WHERE r.match_id = m.id) AS rally_count
                    FROM matches m ORDER BY m.id DESC';
        }
        return $this->db->query($sql)->fetchAll();
    }

    public function latestMatchId()
    {
        $row = $this->db->query('SELECT id FROM matches ORDER BY id DESC LIMIT 1')->fetch();
        return $row ? (int)$row['id'] : 0;
    }

    public function getMatch($id)
    {
        $st = $this->db->prepare('SELECT * FROM matches WHERE id = ?');
        $st->execute([(int)$id]);
        $row = $st->fetch();
        return $row ?: null;
    }

    public function createMatch($player1, $player2, $tournament, $date)
    {
        $st = $this->db->prepare('INSERT INTO matches (player1, player2, tournament, match_date, created_at)
                                  VALUES (?, ?, ?, ?, ?)');
        $st->execute([$player1, $player2, $tournament, $date, date('Y-m-d H:i:s')]);
        return (int)$this->db->lastInsertId();
    }

    public function updateMatch($id, $player1, $player2, $tournament, $date)
    {
        $st = $this->db->prepare('UPDATE matches SET player1 = ?, player2 = ?, tournament = ?, match_date = ? WHERE id = ?');
        $st->execute([$player1, $player2, $tournament, $date, (int)$id]);
    }

    public function deleteMatch($id)
    {
        $st = $this->db->prepare('DELETE FROM rally_entries WHERE match_id = ?');
        $st->execute([(int)$id]);
        $st = $this->db->prepare('DELETE FROM matches WHERE id = ?');
        $st->execute([(int)$id]);
    }

    /* ================= ENTRI RALLY ================= */

    public function countEntries($matchId)
    {
        $st = $this->db->prepare('SELECT COUNT(*) AS c FROM rally_entries WHERE match_id = ?');
        $st->execute([(int)$matchId]);
        return (int)$st->fetch()['c'];
    }

    /** Babak yang benar-benar memiliki data. */
    public function usedGames($matchId)
    {
        $st = $this->db->prepare('SELECT DISTINCT game FROM rally_entries WHERE match_id = ? ORDER BY game');
        $st->execute([(int)$matchId]);
        return array_map('intval', array_column($st->fetchAll(), 'game'));
    }

    public function getEntry($id)
    {
        $st = $this->db->prepare('SELECT * FROM rally_entries WHERE id = ?');
        $st->execute([(int)$id]);
        return $st->fetch() ?: null;
    }

    /**
     * Daftar entri dengan filter + urut + halaman.
     * Filter: game, player_num, result_type, q (teknik penentu / posisi)
     */
    public function entries($matchId, array $filters = [], $sort = 'default', $page = 1, $perPage = 15)
    {
        $where = ['match_id = :match_id'];
        $params = ['match_id' => (int)$matchId];

        if (!empty($filters['game'])) {
            $where[] = 'game = :game';
            $params['game'] = (int)$filters['game'];
        }
        if (!empty($filters['player'])) {
            $where[] = 'player_num = :player';
            $params['player'] = (int)$filters['player'];
        }
        if (!empty($filters['result'])) {
            $where[] = 'result_type = :result';
            $params['result'] = $filters['result'];
        }
        if (!empty($filters['position'])) {
            $where[] = 'position = :position';
            $params['position'] = $filters['position'];
        }
        if (!empty($filters['det'])) {
            $where[] = 'det_technique = :det';
            $params['det'] = $filters['det'];
        }
        if (!empty($filters['q'])) {
            $where[] = '(det_technique LIKE :q OR position LIKE :q OR result_type LIKE :q)';
            $params['q'] = '%' . $filters['q'] . '%';
        }

        $whereSql = implode(' AND ', $where);

        $countSt = $this->db->prepare("SELECT COUNT(*) AS c FROM rally_entries WHERE $whereSql");
        $countSt->execute($params);
        $total = (int)$countSt->fetch()['c'];

        $orderMap = [
            'default'   => 'game ASC, rally_no ASC, player_num ASC',
            'rally_asc' => 'game ASC, rally_no ASC, player_num ASC',
            'rally_desc' => 'game DESC, rally_no DESC, player_num ASC',
            'total_desc' => 'total_shots DESC, game ASC, rally_no ASC',
            'total_asc' => 'total_shots ASC, game ASC, rally_no ASC',
        ];
        $order = isset($orderMap[$sort]) ? $orderMap[$sort] : $orderMap['default'];

        $page = max(1, (int)$page);
        $perPage = max(1, (int)$perPage);
        $offset = ($page - 1) * $perPage;

        $st = $this->db->prepare(
            "SELECT * FROM rally_entries WHERE $whereSql ORDER BY $order LIMIT $perPage OFFSET $offset"
        );
        $st->execute($params);
        $rows = $st->fetchAll();

        return [
            'rows' => $rows,
            'total' => $total,
            'page' => $page,
            'perPage' => $perPage,
            'pages' => (int)ceil($total / $perPage),
        ];
    }

    /** Semua entri satu pertandingan (untuk export). */
    public function allEntries($matchId)
    {
        $st = $this->db->prepare(
            'SELECT * FROM rally_entries WHERE match_id = ? ORDER BY game ASC, rally_no ASC, player_num ASC'
        );
        $st->execute([(int)$matchId]);
        return $st->fetchAll();
    }

    /** Usulan nomor rally berikutnya pada sebuah babak. */
    public function nextRallyNo($matchId, $game)
    {
        $st = $this->db->prepare(
            'SELECT MAX(rally_no) AS mx FROM rally_entries WHERE match_id = ? AND game = ?'
        );
        $st->execute([(int)$matchId, (int)$game]);
        $row = $st->fetch();
        return ($row && $row['mx'] !== null) ? (int)$row['mx'] + 1 : 1;
    }

    /** Entri terakhir (untuk tampilan cepat di halaman input). */
    public function recentEntries($matchId, $limit = 8)
    {
        $st = $this->db->prepare(
            'SELECT * FROM rally_entries WHERE match_id = ? ORDER BY id DESC LIMIT ' . (int)$limit
        );
        $st->execute([(int)$matchId]);
        return array_reverse($st->fetchAll());
    }

    /**
     * Validasi + normalisasi data entri dari array input (form/API).
     * Return [data array, errors array].
     */
    public function sanitizeEntry(array $in)
    {
        $errors = [];
        $data = [];

        $data['match_id'] = (int)(isset($in['match_id']) ? $in['match_id'] : 0);
        if ($data['match_id'] <= 0 || !$this->getMatch($data['match_id'])) {
            $errors[] = 'Pertandingan tidak ditemukan.';
        }

        $data['game'] = clamp_int(isset($in['game']) ? $in['game'] : 1, 1, 3);

        $data['rally_no'] = clamp_int(isset($in['rally_no']) ? $in['rally_no'] : 1, 1, 999);

        $data['player_num'] = clamp_int(isset($in['player_num']) ? $in['player_num'] : 1, 1, 2);

        $total = 0;
        foreach (tech_list() as $t) {
            $key = 't_' . strtolower(str_replace([' ', '.'], ['_', ''], $t['code']));
            // form memakai nama kolom DB langsung (t_smash_fh, dst.)
            $val = isset($in[$t['col']]) ? $in[$t['col']] : (isset($in[$key]) ? $in[$key] : 0);
            $val = clamp_int($val, 0, 999);
            $data[$t['col']] = $val;
            $total += $val;
        }
        $data['total_shots'] = $total;

        $det = isset($in['det_technique']) ? trim((string)$in['det_technique']) : '';
        if ($det !== '' && tech_by_code($det) === null) {
            $errors[] = 'Teknik penentu point tidak valid: ' . $det;
            $det = '';
        }
        $data['det_technique'] = $det !== '' ? $det : null;

        $res = isset($in['result_type']) ? trim((string)$in['result_type']) : '';
        if ($res !== '' && !in_array($res, result_types(), true)) {
            $errors[] = 'Jenis hasil tidak valid.';
            $res = '';
        }
        $data['result_type'] = $res !== '' ? $res : null;

        // Konsistensi: teknik penentu & jenis hasil harus ada bersama
        if ($data['det_technique'] === null && $data['result_type'] !== null) {
            $data['det_technique'] = null;
            $data['result_type'] = null;
        }
        if ($data['det_technique'] !== null && $data['result_type'] === null) {
            $data['result_type'] = null;
            $data['det_technique'] = null;
        }

        $pos = isset($in['position']) ? trim((string)$in['position']) : '';
        $norm = '';
        foreach (positions() as $p) {
            if (strcasecmp($p, $pos) === 0) {
                $norm = $p;
                break;
            }
        }
        if ($pos !== '' && $norm === '') {
            $errors[] = 'Posisi tidak valid: ' . $pos;
        }
        $data['position'] = $norm !== '' ? $norm : null;

        if ($total === 0 && $data['det_technique'] === null) {
            $errors[] = 'Minimal diisi: jumlah pukulan salah satu teknik, atau teknik penentu point.';
        }

        return [$data, $errors];
    }

    public function insertEntry(array $data)
    {
        $cols = ['match_id', 'game', 'rally_no', 'player_num', 'total_shots',
                 'det_technique', 'result_type', 'position', 'created_at'];
        $vals = [$data['match_id'], $data['game'], $data['rally_no'], $data['player_num'],
                 $data['total_shots'], $data['det_technique'], $data['result_type'],
                 $data['position'], date('Y-m-d H:i:s')];
        foreach (tech_list() as $t) {
            $cols[] = $t['col'];
            $vals[] = $data[$t['col']];
        }
        $sql = 'INSERT INTO rally_entries (' . implode(', ', $cols) . ')
                VALUES (' . rtrim(str_repeat('?, ', count($cols)), ', ') . ')';
        $st = $this->db->prepare($sql);
        $st->execute($vals);
        return (int)$this->db->lastInsertId();
    }

    public function updateEntry($id, array $data)
    {
        $sets = ['game = ?', 'rally_no = ?', 'player_num = ?', 'total_shots = ?',
                 'det_technique = ?', 'result_type = ?', 'position = ?'];
        $vals = [$data['game'], $data['rally_no'], $data['player_num'], $data['total_shots'],
                 $data['det_technique'], $data['result_type'], $data['position']];
        foreach (tech_list() as $t) {
            $sets[] = $t['col'] . ' = ?';
            $vals[] = $data[$t['col']];
        }
        $vals[] = (int)$id;
        $st = $this->db->prepare('UPDATE rally_entries SET ' . implode(', ', $sets) . ' WHERE id = ?');
        $st->execute($vals);
    }

    public function deleteEntry($id)
    {
        $st = $this->db->prepare('DELETE FROM rally_entries WHERE id = ?');
        $st->execute([(int)$id]);
    }

    /** Hapus satu rally penuh (kedua pemain) pada babak & nomor tertentu. */
    public function deleteRally($matchId, $game, $rallyNo)
    {
        $st = $this->db->prepare('DELETE FROM rally_entries WHERE match_id = ? AND game = ? AND rally_no = ?');
        $st->execute([(int)$matchId, (int)$game, (int)$rallyNo]);
        return $st->rowCount();
    }

    /** Hapus semua data rally satu pertandingan (metadata pertandingan tetap). */
    public function clearEntries($matchId)
    {
        $st = $this->db->prepare('DELETE FROM rally_entries WHERE match_id = ?');
        $st->execute([(int)$matchId]);
    }

    public function insertManyEntries(array $rows)
    {
        $this->db->beginTransaction();
        try {
            foreach ($rows as $row) {
                $this->insertEntry($row);
            }
            $this->db->commit();
        } catch (Exception $e) {
            $this->db->rollBack();
            throw $e;
        }
    }
}
