<?php
/**
 * Mesin analisis — mereplikasi (dan melengkapi) rumus sheet
 * "Rekapan Pemain 1/2" dan "Dashbord" pada template Excel.
 */
class Analyzer
{
    /** @var PDO */
    private $db;
    private $match;

    public function __construct(PDO $db, array $match)
    {
        $this->db = $db;
        $this->match = $match;
    }

    private function whereGame($game, array &$params)
    {
        if ($game !== null && $game !== '' && (int)$game > 0) {
            $params['game'] = (int)$game;
            return ' AND game = :game';
        }
        return '';
    }

    /**
     * Rekap per teknik untuk satu pemain — persis sheet Rekapan.
     * Return: ['techs' => [kode => [label, jumlah, fe, ue, ws, pfe, pue, pws]], 'totals' => [...]]
     */
    public function recap($playerNum, $game = null)
    {
        $techs = [];
        foreach (tech_list() as $t) {
            $techs[$t['code']] = [
                'label' => $t['label'],
                'jumlah' => 0, 'fe' => 0, 'ue' => 0, 'ws' => 0,
                'pfe' => 0.0, 'pue' => 0.0, 'pws' => 0.0,
            ];
        }

        $params = ['match_id' => (int)$this->match['id'], 'player' => (int)$playerNum];
        $gameSql = $this->whereGame($game, $params);

        // Jumlah pukulan per teknik (SUMIFS)
        $sql = 'SELECT ' . implode(', ', array_map(function ($t) {
            return "COALESCE(SUM({$t['col']}), 0) AS {$t['col']}";
        }, tech_list())) . '
        FROM rally_entries WHERE match_id = :match_id AND player_num = :player' . $gameSql;
        $st = $this->db->prepare($sql);
        $st->execute($params);
        $sums = $st->fetch();
        foreach (tech_list() as $t) {
            $techs[$t['code']]['jumlah'] = (int)$sums[$t['col']];
        }

        // FE / UE / WS per teknik penentu (COUNTIFS)
        $st = $this->db->prepare(
            'SELECT det_technique, result_type, COUNT(*) AS c
             FROM rally_entries
             WHERE match_id = :match_id AND player_num = :player
               AND det_technique IS NOT NULL AND result_type IS NOT NULL' . $gameSql . '
             GROUP BY det_technique, result_type'
        );
        $st->execute($params);
        foreach ($st->fetchAll() as $row) {
            $code = $row['det_technique'];
            if (!isset($techs[$code])) {
                continue;
            }
            if ($row['result_type'] === 'Forced Error')   $techs[$code]['fe'] = (int)$row['c'];
            if ($row['result_type'] === 'Unforced Error') $techs[$code]['ue'] = (int)$row['c'];
            if ($row['result_type'] === 'Winning Shot')   $techs[$code]['ws'] = (int)$row['c'];
        }

        // Persentase (IFERROR(x/y,0))
        $tot = ['jumlah' => 0, 'fe' => 0, 'ue' => 0, 'ws' => 0];
        foreach ($techs as &$t) {
            $t['pfe'] = $t['jumlah'] > 0 ? $t['fe'] / $t['jumlah'] : 0;
            $t['pue'] = $t['jumlah'] > 0 ? $t['ue'] / $t['jumlah'] : 0;
            $t['pws'] = $t['jumlah'] > 0 ? $t['ws'] / $t['jumlah'] : 0;
            $tot['jumlah'] += $t['jumlah'];
            $tot['fe'] += $t['fe'];
            $tot['ue'] += $t['ue'];
            $tot['ws'] += $t['ws'];
        }
        unset($t);

        $tot['pfe'] = $tot['jumlah'] > 0 ? $tot['fe'] / $tot['jumlah'] : 0;
        $tot['pue'] = $tot['jumlah'] > 0 ? $tot['ue'] / $tot['jumlah'] : 0;
        $tot['pws'] = $tot['jumlah'] > 0 ? $tot['ws'] / $tot['jumlah'] : 0;

        return ['techs' => $techs, 'totals' => $tot];
    }

    /** Jumlah "teknik penentu point" per pemain (tabel B dashboard Excel). */
    public function penentuCounts($playerNum, $game = null)
    {
        $out = array_fill(0, count(tech_list()), 0);
        $codes = [];
        foreach (tech_list() as $i => $t) {
            $codes[strtolower($t['code'])] = $i;
        }
        $params = ['match_id' => (int)$this->match['id'], 'player' => (int)$playerNum];
        $gameSql = $this->whereGame($game, $params);
        $st = $this->db->prepare(
            'SELECT det_technique, COUNT(*) AS c FROM rally_entries
             WHERE match_id = :match_id AND player_num = :player
               AND det_technique IS NOT NULL' . $gameSql . '
             GROUP BY det_technique'
        );
        $st->execute($params);
        foreach ($st->fetchAll() as $row) {
            $key = strtolower($row['det_technique']);
            if (isset($codes[$key])) {
                $out[$codes[$key]] = (int)$row['c'];
            }
        }
        return $out;
    }

    /**
     * Ringkasan per pemain + analisis tambahan:
     * total pukulan, FE/UE/WS, jumlah rally, poin menang, poin per babak, dll.
     */
    public function summary()
    {
        $mid = (int)$this->match['id'];

        $out = [
            'players' => [1 => [], 2 => []],
            'games' => [],
            'total_rallies' => 0,
            'total_shots' => 0,
            'avg_rally_len' => 0,
            'longest_rally' => 0,
            'unfinished' => 0,
        ];

        foreach ([1, 2] as $p) {
            $params = ['match_id' => $mid, 'player' => $p];
            $st = $this->db->prepare(
                'SELECT COALESCE(SUM(total_shots),0) AS shots,
                        COUNT(*) AS entries,
                        SUM(CASE WHEN result_type = \'Forced Error\' THEN 1 ELSE 0 END) AS fe,
                        SUM(CASE WHEN result_type = \'Unforced Error\' THEN 1 ELSE 0 END) AS ue,
                        SUM(CASE WHEN result_type = \'Winning Shot\' THEN 1 ELSE 0 END) AS ws
                 FROM rally_entries WHERE match_id = :match_id AND player_num = :player'
            );
            $st->execute($params);
            $r = $st->fetch();
            $out['players'][$p] = [
                'name' => $p === 1 ? $this->match['player1'] : $this->match['player2'],
                'shots' => (int)$r['shots'],
                'entries' => (int)$r['entries'],
                'fe' => (int)$r['fe'],
                'ue' => (int)$r['ue'],
                'ws' => (int)$r['ws'],
                'points' => 0,
                'points_ws' => 0,
                'points_opp_err' => 0,
            ];
        }

        // Poin per babak: satu rally = satu poin (model rally-point)
        $st = $this->db->prepare(
            'SELECT game, rally_no, player_num, result_type, total_shots
             FROM rally_entries
             WHERE match_id = :mid
             ORDER BY game ASC, rally_no ASC, player_num ASC'
        );
        $st->execute(['mid' => $mid]);
        $rallies = [];
        foreach ($st->fetchAll() as $row) {
            $key = $row['game'] . '-' . $row['rally_no'];
            if (!isset($rallies[$key])) {
                $rallies[$key] = [
                    'game' => (int)$row['game'],
                    'rally_no' => (int)$row['rally_no'],
                    'shots' => 0,
                    'dets' => [],
                ];
            }
            $rallies[$key]['shots'] += (int)$row['total_shots'];
            if ($row['result_type'] !== null) {
                $rallies[$key]['dets'][] = [(int)$row['player_num'], $row['result_type']];
            }
        }

        $gameAgg = [];
        foreach ($rallies as $r) {
            $g = $r['game'];
            if (!isset($gameAgg[$g])) {
                $gameAgg[$g] = ['rallies' => 0, 'points' => [1 => 0, 2 => 0], 'shots' => 0];
            }
            $gameAgg[$g]['rallies']++;
            $gameAgg[$g]['shots'] += $r['shots'];
            $out['total_rallies']++;
            $out['total_shots'] += $r['shots'];
            $out['longest_rally'] = max($out['longest_rally'], $r['shots']);

            if (count($r['dets']) === 0) {
                $out['unfinished']++;
                continue;
            }
            // pemenang: WS langsung; jika >1 det, WS menang
            $winner = null; $via = null;
            foreach ($r['dets'] as $det) {
                if ($det[1] === 'Winning Shot') {
                    $winner = $det[0];
                    $via = 'ws';
                    break;
                }
            }
            if ($winner === null) {
                $first = $r['dets'][0];
                $winner = 3 - $first[0]; // lawan dari pemain yang error
                $via = 'opp_err';
            }
            $gameAgg[$g]['points'][$winner]++;
            $out['players'][$winner]['points']++;
            if ($via === 'ws') {
                $out['players'][$winner]['points_ws']++;
            } else {
                $out['players'][$winner]['points_opp_err']++;
            }
        }

        ksort($gameAgg);
        foreach ($gameAgg as $g => $agg) {
            $gameAgg[$g]['winner'] = $agg['points'][1] === $agg['points'][2] ? 0
                : ($agg['points'][1] > $agg['points'][2] ? 1 : 2);
            $gameAgg[$g]['avg_shots'] = $agg['rallies'] > 0 ? $agg['shots'] / $agg['rallies'] : 0;
        }
        $out['games'] = $gameAgg;
        $out['avg_rally_len'] = $out['total_rallies'] > 0 ? $out['total_shots'] / $out['total_rallies'] : 0;

        return $out;
    }

    /** Distribusi posisi pukulan penentu per pemain. */
    public function positionDistribution($playerNum)
    {
        $result = array_fill_keys(positions(), 0);
        $st = $this->db->prepare(
            'SELECT position, COUNT(*) AS c FROM rally_entries
             WHERE match_id = :mid AND player_num = :p AND position IS NOT NULL
             GROUP BY position'
        );
        $st->execute(['mid' => (int)$this->match['id'], 'p' => (int)$playerNum]);
        foreach ($st->fetchAll() as $row) {
            if (array_key_exists($row['position'], $result)) {
                $result[$row['position']] = (int)$row['c'];
            } else {
                $result[$row['position']] = (int)$row['c'];
            }
        }
        return $result;
    }
}
