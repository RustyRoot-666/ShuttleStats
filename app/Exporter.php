<?php
/**
 * Export data pertandingan:
 * 1. XLSX — replika 4 sheet template asli (Input Data, Rekapan Pemain 1,
 *    Rekap pemain 2, Dashbord) berisi nilai hasil olahan.
 * 2. CSV  — data mentah entri rally.
 */
class Exporter
{
    /** @var PDO */
    private $db;
    private $repo;
    private $match;

    public function __construct(PDO $db, array $match)
    {
        $this->db = $db;
        $this->repo = new Repository($db);
        $this->match = $match;
    }

    public function downloadXlsx()
    {
        $writer = new XlsxWriter();
        $safeName = preg_replace('/[^A-Za-z0-9_\-]+/', '_', $this->match['player1'] . '_vs_' . $this->match['player2']);
        $writer->addSheet('Input Data', $this->inputDataRows(), [
            'widths' => [2 => 5, 3 => 9, 4 => 9, 5 => 20] + $this->techWidths(6) + [24 => 7, 25 => 16, 26 => 3, 27 => 15, 28 => 13],
            'freeze' => 10,
        ]);
        $writer->addSheet('Rekapan Pemain 1', $this->rekapRows(1), [
            'widths' => [2 => 24, 3 => 22, 4 => 14, 5 => 13, 6 => 9, 7 => 14, 8 => 9, 9 => 14, 10 => 9, 12 => 18],
        ]);
        $writer->addSheet('Rekap pemain 2', $this->rekapRows(2), [
            'widths' => [2 => 24, 3 => 22, 4 => 14, 5 => 13, 6 => 9, 7 => 14, 8 => 9, 9 => 14, 10 => 9, 12 => 18],
        ]);
        $writer->addSheet('Dashbord', $this->dashboardRows(), [
            'widths' => [2 => 26, 3 => 22, 4 => 16, 5 => 16, 6 => 16],
        ]);
        $writer->download('ShuttleStats_' . $safeName . '_' . date('Ymd') . '.xlsx');
    }

    public function downloadCsv()
    {
        $entries = $this->repo->allEntries($this->match['id']);

        header('Content-Type: text/csv; charset=UTF-8');
        $safeName = preg_replace('/[^A-Za-z0-9_\-]+/', '_', $this->match['player1'] . '_vs_' . $this->match['player2']);
        header('Content-Disposition: attachment; filename="ShuttleStats_' . $safeName . '_data.csv"');

        $out = fopen('php://output', 'w');
        fwrite($out, "\xEF\xBB\xBF"); // BOM agar Excel membaca UTF-8

        $header = ['NO', 'Babak', 'No. Rally / Point', 'Pemain'];
        foreach (tech_list() as $t) {
            $header[] = $t['code'];
        }
        $header = array_merge($header, ['Total Pukulan', 'Teknik Penentu Point', 'Jenis Hasil', 'Posisi']);
        fputcsv($out, $header, ';');

        $no = 0;
        foreach ($entries as $r) {
            $no++;
            $row = [$no, game_label($r['game']), (int)$r['rally_no'],
                    (int)$r['player_num'] === 1 ? $this->match['player1'] : $this->match['player2']];
            foreach (tech_list() as $t) {
                $row[] = (int)$r[$t['col']];
            }
            $row = array_merge($row, [
                (int)$r['total_shots'],
                $r['det_technique'] !== null ? $r['det_technique'] : '',
                $r['result_type'] !== null ? $r['result_type'] : '',
                $r['position'] !== null ? $r['position'] : '',
            ]);
            fputcsv($out, $row, ';');
        }
        fclose($out);
        exit;
    }

    /* ================= sheet builders ================= */

    private function techWidths($startCol)
    {
        $w = [];
        $i = 0;
        foreach (tech_list() as $t) {
            $w[$startCol + $i] = 7;
            $i++;
        }
        return $w;
    }

    private function inputDataRows()
    {
        $rows = [];
        $m = $this->match;

        // baris 2 (index 1): judul
        $rows[1] = [1 => null, 2 => ['v' => 'INPUT DATA - PUKULAN & HASIL PER RALLY', 's' => XlsxWriter::S_TITLE]];

        // baris 4-7 (index 3-6): metadata
        $rows[3] = [2 => ['v' => 'Nama Pemain 1', 's' => XlsxWriter::S_META], 4 => $m['player1']];
        $rows[4] = [2 => ['v' => 'Nama Pemain 2', 's' => XlsxWriter::S_META], 4 => $m['player2']];
        $rows[5] = [2 => ['v' => 'Turnamen', 's' => XlsxWriter::S_META],      4 => (string)$m['tournament']];
        $rows[6] = [2 => ['v' => 'Tanggal', 's' => XlsxWriter::S_META],       4 => (string)$m['match_date']];

        // baris 9 (index 8): header
        $head = ['NO', 'Babak', 'No. Rally / Point', 'Pemain'];
        foreach (tech_list() as $t) {
            $head[] = $t['code'];
        }
        $head = array_merge($head, ['Total Pukulan', 'Teknik Penentu Point', null, 'Jenis Hasil', 'Posisi']);
        $row9 = [];
        foreach ($head as $i => $h) {
            if ($h === null) continue;
            $row9[$i + 2] = ['v' => $h, 's' => XlsxWriter::S_HEADER];
        }
        $rows[8] = $row9;

        // baris 11+ (index 10+): data
        $entries = $this->repo->allEntries($this->match['id']);
        $merges = ['B9:C9'];
        $merges[] = 'Y9:Z9';
        $this->pendingMerges = [];
        $rIdx = 10;
        $no = 0;
        foreach ($entries as $r) {
            $no++;
            $row = [];
            $row[2] = ['v' => $no, 's' => XlsxWriter::S_CELL_C];
            $row[3] = ['v' => game_label($r['game']), 's' => XlsxWriter::S_CELL_C];
            $row[4] = ['v' => (int)$r['rally_no'], 's' => XlsxWriter::S_CELL_C];
            $row[5] = ['v' => (int)$r['player_num'] === 1 ? $this->match['player1'] : $this->match['player2'], 's' => XlsxWriter::S_CELL];
            $c = 6;
            foreach (tech_list() as $t) {
                $v = (int)$r[$t['col']];
                $row[$c] = $v > 0 ? ['v' => $v, 's' => XlsxWriter::S_CELL_C] : ['v' => null, 's' => XlsxWriter::S_CELL_C];
                $c++;
            }
            $row[24] = ['v' => (int)$r['total_shots'], 's' => XlsxWriter::S_CELL_C];
            if ($r['det_technique'] !== null) {
                $row[25] = ['v' => $r['det_technique'], 's' => XlsxWriter::S_CELL];
            }
            if ($r['result_type'] !== null) {
                $row[27] = ['v' => $r['result_type'], 's' => XlsxWriter::S_CELL];
            }
            if ($r['position'] !== null) {
                $row[28] = ['v' => $r['position'], 's' => XlsxWriter::S_CELL];
            }
            $rows[$rIdx] = $row;
            $this->pendingMerges[] = 'Y' . ($rIdx + 1) . ':Z' . ($rIdx + 1);
            $rIdx++;
        }
        return $rows;
    }

    /** @var array merge Y:Z per baris data Input Data */
    private $pendingMerges = [];

    public function inputDataMerges()
    {
        return $this->pendingMerges;
    }

    private function rekapRows($playerNum)
    {
        $analyzer = new Analyzer($this->db, $this->match);
        $recap = $analyzer->recap($playerNum);
        $name = $playerNum === 1 ? $this->match['player1'] : $this->match['player2'];

        $rows = [];
        $H = XlsxWriter::S_HEADER;
        $C = XlsxWriter::S_CELL;
        $CC = XlsxWriter::S_CELL_C;
        $T = XlsxWriter::S_TOTAL;

        $rows[2] = [2 => ['v' => 'Rekapan Individu Pemain ' . $playerNum, 's' => XlsxWriter::S_TITLE]];
        $rows[3] = [2 => ['v' => 'Nama Pemain', 's' => XlsxWriter::S_META], 4 => $name];
        $rows[5] = [2 => ['v' => 'Detail per Teknik dari Pemain ' . $playerNum, 's' => XlsxWriter::S_SUB]];

        $rows[6] = [
            2 => ['v' => 'Teknik', 's' => $H],
            4 => ['v' => 'Jumlah Pukulan', 's' => $H],
            5 => ['v' => 'Forced Error', 's' => $H],
            6 => ['v' => '% FE', 's' => $H],
            7 => ['v' => 'Unforced Error', 's' => $H],
            8 => ['v' => '%UE', 's' => $H],
            9 => ['v' => 'Winning Shot', 's' => $H],
            10 => ['v' => '%WS', 's' => $H],
            12 => ['v' => 'Kode di Input Data', 's' => $H],
        ];

        $rows[8] = [2 => ['v' => 'GABUNGAN SEMUA BABAK 1-3', 's' => XlsxWriter::S_SUB]];

        $rIdx = 9;
        foreach (tech_list() as $t) {
            $d = $recap['techs'][$t['code']];
            $rows[$rIdx] = [
                2 => ['v' => $t['label'], 's' => $C],
                4 => ['v' => $d['jumlah'], 's' => $CC],
                5 => ['v' => $d['fe'], 's' => $CC],
                6 => ['v' => round($d['pfe'], 4), 's' => XlsxWriter::S_PCT],
                7 => ['v' => $d['ue'], 's' => $CC],
                8 => ['v' => round($d['pue'], 4), 's' => XlsxWriter::S_PCT],
                9 => ['v' => $d['ws'], 's' => $CC],
                10 => ['v' => round($d['pws'], 4), 's' => XlsxWriter::S_PCT],
                12 => ['v' => $t['code'], 's' => $C],
            ];
            $rIdx++;
        }

        $tot = $recap['totals'];
        $rows[$rIdx] = [
            2 => ['v' => 'JUMLAH', 's' => $T],
            4 => ['v' => $tot['jumlah'], 's' => $T],
            5 => ['v' => $tot['fe'], 's' => $T],
            6 => ['v' => round($tot['pfe'], 4), 's' => XlsxWriter::S_PCT_TOT],
            7 => ['v' => $tot['ue'], 's' => $T],
            8 => ['v' => round($tot['pue'], 4), 's' => XlsxWriter::S_PCT_TOT],
            9 => ['v' => $tot['ws'], 's' => $T],
            10 => ['v' => round($tot['pws'], 4), 's' => XlsxWriter::S_PCT_TOT],
        ];
        return $rows;
    }

    private function dashboardRows()
    {
        $analyzer = new Analyzer($this->db, $this->match);
        $summary = $analyzer->summary();
        $H = XlsxWriter::S_HEADER;
        $C = XlsxWriter::S_CELL;
        $CC = XlsxWriter::S_CELL_C;

        $rows = [];
        $rows[1] = [2 => ['v' => 'DASHBOARD ANALISIS PERTANDINGAN BULUTANGKIS', 's' => XlsxWriter::S_TITLE]];
        $rows[2] = [2 => ['v' => 'Pemain 1: ' . $this->match['player1'] . '   vs   Pemain 2: ' . $this->match['player2']
            . ($this->match['tournament'] !== '' ? ' | Turnamen: ' . $this->match['tournament'] : ''), 's' => XlsxWriter::S_BOLD]];
        $rows[3] = [2 => ['v' => 'Diekspor dari ' . APP_NAME . ' pada ' . date('d/m/Y H:i') . ' — semua angka merupakan nilai hasil olahan.', 's' => 10]];

        // Ringkasan
        $rows[5] = [
            2 => ['v' => 'Ringkasan', 's' => $H],
            3 => ['v' => 'Total Pukulan', 's' => $H],
            4 => ['v' => 'Forced Error', 's' => $H],
            5 => ['v' => 'Unforced Error', 's' => $H],
            6 => ['v' => 'Winning Shot', 's' => $H],
        ];
        foreach ([1, 2] as $p) {
            $d = $summary['players'][$p];
            $rows[5 + $p] = [
                2 => ['v' => $d['name'], 's' => $C],
                3 => ['v' => $d['shots'], 's' => $CC],
                4 => ['v' => $d['fe'], 's' => $CC],
                5 => ['v' => $d['ue'], 's' => $CC],
                6 => ['v' => $d['ws'], 's' => $CC],
            ];
        }

        // A. Total pukulan per teknik
        $recap = [1 => $analyzer->recap(1), 2 => $analyzer->recap(2)];
        $r = 14;
        $rows[$r] = [2 => ['v' => 'A. Total pukulan per teknik', 's' => XlsxWriter::S_SUB]];
        $rows[$r + 1] = [
            2 => ['v' => 'Teknik', 's' => $H],
            3 => ['v' => $this->match['player1'], 's' => $H],
            4 => ['v' => $this->match['player2'], 's' => $H],
        ];
        $i = $r + 2;
        foreach (tech_list() as $t) {
            $rows[$i] = [
                2 => ['v' => $t['label'], 's' => $C],
                3 => ['v' => $recap[1]['techs'][$t['code']]['jumlah'], 's' => $CC],
                4 => ['v' => $recap[2]['techs'][$t['code']]['jumlah'], 's' => $CC],
            ];
            $i++;
        }

        // B. Teknik penentu point
        $rows[$i + 1] = [2 => ['v' => 'B. Teknik penentu point', 's' => XlsxWriter::S_SUB]];
        $rows[$i + 2] = [
            2 => ['v' => 'Teknik', 's' => $H],
            3 => ['v' => $this->match['player1'], 's' => $H],
            4 => ['v' => $this->match['player2'], 's' => $H],
        ];
        $penentu = [1 => $analyzer->penentuCounts(1), 2 => $analyzer->penentuCounts(2)];
        $j = $i + 3;
        foreach (tech_list() as $idx => $t) {
            $rows[$j] = [
                2 => ['v' => $t['label'], 's' => $C],
                3 => ['v' => $penentu[1][$idx], 's' => $CC],
                4 => ['v' => $penentu[2][$idx], 's' => $CC],
            ];
            $j++;
        }

        // C. Jenis hasil
        $rows[$j + 1] = [2 => ['v' => 'C. Jenis hasil', 's' => XlsxWriter::S_SUB]];
        $rows[$j + 2] = [
            2 => ['v' => 'Jenis Hasil', 's' => $H],
            3 => ['v' => $this->match['player1'], 's' => $H],
            4 => ['v' => $this->match['player2'], 's' => $H],
        ];
        $jenis = [
            ['Winning Shot',  $summary['players'][1]['ws'], $summary['players'][2]['ws']],
            ['Unforced Error', $summary['players'][1]['ue'], $summary['players'][2]['ue']],
            ['Forced Error',  $summary['players'][1]['fe'], $summary['players'][2]['fe']],
        ];
        $k = $j + 3;
        foreach ($jenis as $row) {
            $rows[$k] = [
                2 => ['v' => $row[0], 's' => $C],
                3 => ['v' => $row[1], 's' => $CC],
                4 => ['v' => $row[2], 's' => $CC],
            ];
            $k++;
        }
        return $rows;
    }
}
