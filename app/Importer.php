<?php
/**
 * Import file Excel (.xlsx) / CSV dengan format template
 * "Input Data Task" ke dalam database.
 */
class Importer
{
    /** @var Repository */
    private $repo;

    public function __construct(Repository $repo)
    {
        $this->repo = $repo;
    }

    /**
     * @param string $path          path file sementara
     * @param string $originalName  nama file asli
     * @param string $mode          'new' (buat pertandingan baru) | 'append' (tambah ke pertandingan)
     * @param int    $targetMatchId dipakai bila mode = append
     * @return array laporan ['ok','match_id','match_name','inserted','skipped','warnings','errors']
     */
    public function import($path, $originalName, $mode = 'new', $targetMatchId = 0)
    {
        $report = [
            'ok' => false, 'match_id' => 0, 'match_name' => '',
            'inserted' => 0, 'skipped' => 0, 'warnings' => [], 'errors' => [],
        ];

        $ext = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
        if ($ext === 'csv') {
            $parsed = $this->parseCsv($path);
        } elseif ($ext === 'xlsx') {
            $parsed = $this->parseXlsx($path);
        } else {
            $report['errors'][] = 'Format file tidak didukung. Gunakan .xlsx atau .csv.';
            return $report;
        }

        if ($parsed === null) {
            $report['errors'] = array_merge($report['errors'], $this->lastErrors);
            return $report;
        }

        $meta = $parsed['meta'];
        $rows = $parsed['rows'];

        // ---- tentukan pertandingan tujuan ----
        if ($mode === 'append') {
            $match = $this->repo->getMatch($targetMatchId);
            if (!$match) {
                $report['errors'][] = 'Pertandingan tujuan tidak ditemukan.';
                return $report;
            }
        } else {
            $p1 = $meta['player1'] !== '' ? $meta['player1'] : 'Pemain 1';
            $p2 = $meta['player2'] !== '' ? $meta['player2'] : 'Pemain 2';
            $matchId = $this->repo->createMatch($p1, $p2, $meta['tournament'], $meta['date']);
            $match = $this->repo->getMatch($matchId);
            $report['warnings'][] = 'Pertandingan baru dibuat: ' . $p1 . ' vs ' . $p2 . '.';
        }
        $report['match_id'] = (int)$match['id'];

        // pemetaan nama pemain file → nomor pemain tujuan
        $nameMap = [];
        $fileP = [$meta['player1'], $meta['player2']];
        foreach ([1, 2] as $slot) {
            $file = trim((string)$fileP[$slot - 1]);
            if ($file === '') {
                continue;
            }
            if (strcasecmp($file, $match['player1']) === 0) {
                $nameMap[strtolower($file)] = 1;
            } elseif (strcasecmp($file, $match['player2']) === 0) {
                $nameMap[strtolower($file)] = 2;
            } else {
                // posisi file (p1/p2) mengikuti posisi pertandingan tujuan
                $nameMap[strtolower($file)] = $slot;
                if ($mode === 'append') {
                    $report['warnings'][] = 'Nama "' . $file . '" tidak sama dengan "' . $match['player' . $slot]
                        . '" — dipetakan berdasarkan urutan (Pemain ' . $slot . ').';
                }
            }
        }
        $nameMap[strtolower($match['player1'])] = 1;
        $nameMap[strtolower($match['player2'])] = 2;

        // ---- proses baris ----
        $insert = [];
        $seq = 0;
        foreach ($rows as $i => $r) {
            $lineNo = $r['_line'];
            $empty = true;
            foreach (tech_list() as $t) {
                if (!empty($r['shots'][$t['col']])) {
                    $empty = false;
                    break;
                }
            }
            if ($empty && $r['player'] === '' && $r['det'] === null && $r['result'] === null) {
                continue; // baris kosong murni — abaikan tanpa menghitung
            }

            $seq++;
            // nama pemain → nomor
            $playerNum = 0;
            if ($r['player'] !== '') {
                $key = strtolower(trim($r['player']));
                if (isset($nameMap[$key])) {
                    $playerNum = $nameMap[$key];
                }
            }
            if ($playerNum === 0) {
                $report['skipped']++;
                $report['warnings'][] = 'Baris ' . $lineNo . ' dilewati: nama pemain "' . $r['player'] . '" tidak dikenali.';
                continue;
            }

            $data = [
                'match_id'   => (int)$match['id'],
                'game'       => $r['game'] > 0 ? $r['game'] : 1,
                'rally_no'   => $r['rally_no'] > 0 ? $r['rally_no'] : $seq,
                'player_num' => $playerNum,
                'det_technique' => $r['det'],
                'result_type'   => $r['result'],
                'position'      => $r['position'],
            ];
            $total = 0;
            foreach (tech_list() as $t) {
                $v = clamp_int(isset($r['shots'][$t['col']]) ? $r['shots'][$t['col']] : 0, 0, 999);
                $data[$t['col']] = $v;
                $total += $v;
            }
            $data['total_shots'] = $total;

            if ($total === 0 && $data['det_technique'] === null) {
                $report['skipped']++;
                continue;
            }
            $insert[] = $data;
        }

        if (!$insert && !$report['errors']) {
            $report['errors'][] = 'Tidak ada baris data valid yang ditemukan di file.';
            // Mode 'new': pertandingan yang barusan dibuat ikut dihapus agar
            // tidak menyisakan pertandingan kosong di daftar.
            if ($mode === 'new' && (int)$match['id'] > 0) {
                $this->repo->deleteMatch((int)$match['id']);
                $report['match_id'] = 0;
                $report['warnings'] = array_values(array_filter($report['warnings'], function ($w) {
                    return strpos($w, 'Pertandingan baru dibuat') !== 0;
                }));
            }
            return $report;
        }

        $this->repo->insertManyEntries($insert);
        $report['inserted'] = count($insert);
        $report['ok'] = true;
        $report['match_name'] = $match['player1'] . ' vs ' . $match['player2'];
        return $report;
    }

    /** @var array */
    private $lastErrors = [];

    /* ================= XLSX ================= */

    private function parseXlsx($path)
    {
        try {
            $reader = new XlsxReader($path);
        } catch (Exception $e) {
            $this->lastErrors[] = $e->getMessage();
            return null;
        }

        $found = $reader->findSheet('input data');
        if ($found === null) {
            $found = $reader->findSheet('input');
        }
        if ($found === null) {
            // fallback: sheet pertama yang punya header "Babak"
            foreach ($reader->sheetNames() as $name) {
                $rows = $reader->sheet($name);
                if ($this->looksLikeInputSheet($rows)) {
                    $found = [$name, $rows];
                    break;
                }
            }
        }
        if ($found === null) {
            $this->lastErrors[] = 'Sheet "Input Data" tidak ditemukan di dalam file Excel.';
            return null;
        }

        $sheet = $found[1];

        // Metadata: D4/D5/D6/D7 (kolom D = 4)
        $meta = [
            'player1'    => trim((string)XlsxReader::val($sheet, 4, 4)),
            'player2'    => trim((string)XlsxReader::val($sheet, 5, 4)),
            'tournament' => trim((string)XlsxReader::val($sheet, 6, 4)),
            'date'       => $this->normalizeDate(XlsxReader::val($sheet, 7, 4)),
        ];

        return ['meta' => $meta, 'rows' => $this->rowsFromGrid($sheet, 11, $this->xlsxColumns())];
    }

    /** Kolom template: B=NO, C=Babak, D=Rally, E=Pemain, F..W teknik, X total, Y det, AA hasil, AB posisi */
    private function xlsxColumns()
    {
        return [
            'game'    => 3,
            'rally'   => 4,
            'player'  => 5,
            'techs'   => 6,   // F..W (6..23)
            'det'     => 25,  // Y
            'result'  => 27,  // AA
            'position'=> 28,  // AB
        ];
    }

    private function looksLikeInputSheet(array $sheet)
    {
        foreach ($sheet as $rowIdx => $row) {
            foreach ($row as $val) {
                if (is_string($val) && stripos(trim($val), 'teknik penentu') !== false) {
                    return true;
                }
            }
        }
        return false;
    }

    /* ================= CSV ================= */

    private function parseCsv($path)
    {
        $content = file_get_contents($path);
        if ($content === false) {
            $this->lastErrors[] = 'File tidak dapat dibaca.';
            return null;
        }
        // buang BOM
        $content = preg_replace('/^\xEF\xBB\xBF/', '', $content);
        $lines = preg_split('/\r\n|\r|\n/', trim($content));
        if (!$lines) {
            $this->lastErrors[] = 'File CSV kosong.';
            return null;
        }

        $meta = ['player1' => '', 'player2' => '', 'tournament' => '', 'date' => ''];

        // deteksi pemisah ; atau ,
        $sep = (substr_count($lines[0], ';') >= substr_count($lines[0], ',')) ? ';' : ',';

        $headerIdx = -1;
        foreach ($lines as $i => $line) {
            $cells = str_getcsv($line, $sep);
            foreach ($cells as $c) {
                if (is_string($c) && stripos(trim($c), 'babak') !== false) {
                    $headerIdx = $i;
                    break 2;
                }
            }
        }

        if ($headerIdx === -1) {
            $this->lastErrors[] = 'Header kolom "Babak" tidak ditemukan pada file CSV.';
            return null;
        }

        // baris metadata sebelum header (opsional): "Nama Pemain 1;X"
        for ($i = 0; $i < $headerIdx; $i++) {
            $cells = str_getcsv($lines[$i], $sep);
            if (count($cells) < 2) {
                continue;
            }
            $label = strtolower(trim($cells[0]));
            $value = trim($cells[1]);
            if (strpos($label, 'pemain 1') !== false || strpos($label, 'player 1') !== false) {
                $meta['player1'] = $value;
            } elseif (strpos($label, 'pemain 2') !== false || strpos($label, 'player 2') !== false) {
                $meta['player2'] = $value;
            } elseif (strpos($label, 'turnamen') !== false || strpos($label, 'tournament') !== false) {
                $meta['tournament'] = $value;
            } elseif (strpos($label, 'tanggal') !== false || strpos($label, 'date') !== false) {
                $meta['date'] = $this->normalizeDate($value);
            }
        }

        $header = [];
        foreach (str_getcsv($lines[$headerIdx], $sep) as $h) {
            $header[] = strtolower(trim($h));
        }
        $colMap = $this->csvHeaderMap($header);

        $rows = [];
        for ($i = $headerIdx + 1; $i < count($lines); $i++) {
            if (trim($lines[$i]) === '') {
                continue;
            }
            $cells = str_getcsv($lines[$i], $sep);
            $grid = [1 => $cells]; // baris virtual ber-index 1
            $mapped = $this->mapCsvRow($cells, $colMap);
            $mapped['_line'] = $i + 1;
            $rows[] = $mapped;
        }

        return ['meta' => $meta, 'rows' => $rows];
    }

    /** Peta nama kolom header CSV → posisi. */
    private function csvHeaderMap(array $header)
    {
        $map = [];
        foreach ($header as $idx => $h) {
            $h = trim(preg_replace('/\s+/', ' ', strtolower($h)));
            if ($h === '' ) continue;
            if ($h === 'babak') $map['game'] = $idx;
            elseif (strpos($h, 'rally') !== false || strpos($h, 'point') !== false) $map['rally'] = $idx;
            elseif ($h === 'pemain' || $h === 'player') $map['player'] = $idx;
            elseif ($h === 'no' ) $map['no'] = $idx;
            elseif (strpos($h, 'teknik penentu') !== false) $map['det'] = $idx;
            elseif (strpos($h, 'jenis hasil') !== false) $map['result'] = $idx;
            elseif ($h === 'posisi' || $h === 'position') $map['position'] = $idx;
            elseif (strpos($h, 'total') !== false) $map['total'] = $idx;
            else {
                foreach (tech_list() as $t) {
                    if (strcasecmp($h, $t['code']) === 0) {
                        $map['tech_' . $t['col']] = $idx;
                        break;
                    }
                }
            }
        }
        return $map;
    }

    private function mapCsvRow(array $cells, array $map)
    {
        $g = function ($key) use ($cells, $map) {
            return isset($map[$key]) && isset($cells[$map[$key]]) ? trim($cells[$map[$key]]) : '';
        };
        $row = $this->blankRow();
        $row['_line'] = 0;
        $row['game']    = $this->parseGame($g('game'));
        $row['rally_no']  = (int)$g('rally');
        $row['player']  = $g('player');
        $row['det']     = $this->parseTech($g('det'));
        $row['result']  = $this->parseResult($g('result'));
        $row['position']= $this->parsePosition($g('position'));
        foreach (tech_list() as $t) {
            $v = $g('tech_' . $t['col']);
            $row['shots'][$t['col']] = is_numeric($v) ? (int)$v : 0;
        }
        return $row;
    }

    /* ================= umum ================= */

    private function rowsFromGrid(array $sheet, $startRow, array $cols)
    {
        $rows = [];
        ksort($sheet);
        foreach ($sheet as $rowIdx => $row) {
            if ($rowIdx < $startRow) {
                continue;
            }
            $r = $this->blankRow();
            $r['_line'] = $rowIdx;

            $r['game']     = $this->parseGame(XlsxReader::val($sheet, $rowIdx, $cols['game']));
            $r['rally_no'] = (int)XlsxReader::val($sheet, $rowIdx, $cols['rally']);
            $r['player']   = trim((string)XlsxReader::val($sheet, $rowIdx, $cols['player']));

            $i = 0;
            foreach (tech_list() as $t) {
                $v = XlsxReader::val($sheet, $rowIdx, $cols['techs'] + $i);
                $r['shots'][$t['col']] = is_numeric($v) ? (int)$v : 0;
                $i++;
            }
            $r['det']      = $this->parseTech(XlsxReader::val($sheet, $rowIdx, $cols['det']));
            $r['result']   = $this->parseResult(XlsxReader::val($sheet, $rowIdx, $cols['result']));
            $r['position'] = $this->parsePosition(XlsxReader::val($sheet, $rowIdx, $cols['position']));

            $rows[] = $r;
        }
        return $rows;
    }

    private function blankRow()
    {
        $shots = [];
        foreach (tech_list() as $t) {
            $shots[$t['col']] = 0;
        }
        return [
            '_line' => 0, 'game' => 0, 'rally_no' => 0, 'player' => '',
            'shots' => $shots, 'det' => null, 'result' => null, 'position' => null,
        ];
    }

    private function parseGame($v)
    {
        if (is_int($v) || is_float($v)) {
            return clamp_int($v, 0, 3);
        }
        $s = strtolower(trim((string)$v));
        if ($s === '') return 0;
        if (preg_match('/(\d)/', $s, $m)) {
            return clamp_int($m[1], 0, 3);
        }
        return 0;
    }

    private function parseTech($v)
    {
        $s = trim((string)$v);
        if ($s === '') return null;
        $t = tech_by_code($s);
        return $t ? $t['code'] : null;
    }

    private function parseResult($v)
    {
        $s = strtolower(trim((string)$v));
        foreach (result_types() as $rt) {
            if (strtolower($rt) === $s) {
                return $rt;
            }
        }
        return null;
    }

    private function parsePosition($v)
    {
        $s = strtolower(trim((string)$v));
        foreach (positions() as $p) {
            if (strtolower($p) === $s) {
                return $p;
            }
        }
        return null;
    }

    private function normalizeDate($v)
    {
        if ($v === null) return '';
        $s = trim((string)$v);
        if ($s === '') return '';
        if (preg_match('/^\d{4}-\d{2}-\d{2}/', $s)) {
            return substr($s, 0, 10);
        }
        $ts = strtotime($s);
        if ($ts !== false) {
            return date('Y-m-d', $ts);
        }
        return '';
    }
}
