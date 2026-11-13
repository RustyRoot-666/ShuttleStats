<?php
/**
 * Pembaca file .xlsx murni PHP (tanpa PhpSpreadsheet / composer).
 * Mendukung: shared strings, inline strings, angka, boolean,
 * tanggal (format builtin & custom), multiple sheet.
 *
 * Return: [namaSheet => [indexBaris(1-based) => [indexKolom(1-based) => nilai]]]
 */
class XlsxReader
{
    /** @var array */
    private $sheets = [];

    /** @var array */
    private $sharedStrings = [];

    /** @var array numFmtId => formatCode */
    private $numFmts = [];

    /** @var array index xf => numFmtId */
    private $cellXfs = [];

    /** @var array nama entri zip => isi (hasil PhpZip::readEntries) */
    private $entries = [];

    /**
     * @param string $path path file .xlsx
     * @throws Exception bila file tidak valid
     */
    public function __construct($path)
    {
        if (!is_string($path) || !is_file($path) || !is_readable($path)) {
            throw new Exception('File Excel tidak ditemukan atau tidak dapat dibaca.');
        }

        // ZipArchive dipakai bila tersedia; bila tidak, PhpZip otomatis
        // beralih ke pembaca murni PHP — ekstensi zip tidak wajib.
        try {
            $this->entries = PhpZip::readEntries($path);
        } catch (Exception $e) {
            throw new Exception('File Excel tidak dapat dibuka (bukan .xlsx yang valid?).');
        }

        $this->loadStyles();
        $this->loadSharedStrings();

        // workbook.xml → daftar sheet (nama + rId)
        $wbXml = $this->file('xl/workbook.xml');
        if ($wbXml === false) {
            throw new Exception('Struktur file Excel tidak dikenali (xl/workbook.xml hilang).');
        }
        $wb = simplexml_load_string($wbXml);
        $sheetDefs = [];
        if (isset($wb->sheets->sheet)) {
            foreach ($wb->sheets->sheet as $s) {
                $rid = '';
                // atribut r:id bersifat namespaced
                $attrs = $s->attributes('http://schemas.openxmlformats.org/officeDocument/2006/relationships', true);
                if (isset($attrs['id'])) {
                    $rid = (string)$attrs['id'];
                }
                $sheetDefs[] = [
                    'name' => (string)$s['name'],
                    'rid'  => $rid,
                ];
            }
        }

        // rels → rId ke path worksheet
        $relsXml = $this->file('xl/_rels/workbook.xml.rels');
        $ridToTarget = [];
        if ($relsXml !== false) {
            $rels = simplexml_load_string($relsXml);
            foreach ($rels->Relationship as $rel) {
                $ridToTarget[(string)$rel['Id']] = (string)$rel['Target'];
            }
        }

        $sheetIndex = 0;
        foreach ($sheetDefs as $def) {
            $sheetIndex++;
            $target = isset($ridToTarget[$def['rid']]) ? $ridToTarget[$def['rid']] : '';
            if ($target === '') {
                // fallback umum: urutan sheet mengikuti penomoran file
                $target = 'worksheets/sheet' . $sheetIndex . '.xml';
            }
            $target = ltrim(str_replace('\\', '/', $target), '/');
            if (strpos($target, 'xl/') !== 0) {
                $target = 'xl/' . $target;
            }
            $xml = $this->file($target);
            if ($xml === false) {
                continue;
            }
            $this->sheets[$def['name']] = $this->parseSheet($xml);
        }
    }

    /** Ambil isi entri zip berdasarkan nama (false bila tidak ada). */
    private function file($name)
    {
        return isset($this->entries[$name]) ? $this->entries[$name] : false;
    }

    public function sheetNames()
    {
        return array_keys($this->sheets);
    }

    /** Ambil sheet sebagai array baris => kolom => nilai. */
    public function sheet($name)
    {
        foreach ($this->sheets as $key => $rows) {
            if (strcasecmp($key, $name) === 0) {
                return $rows;
            }
        }
        return null;
    }

    /** Cari sheet pertama yang namanya mengandung kata kunci. */
    public function findSheet($keyword)
    {
        foreach ($this->sheets as $key => $rows) {
            if (stripos($key, $keyword) !== false) {
                return [$key, $rows];
            }
        }
        return null;
    }

    /* ================= internal ================= */

    private function loadStyles()
    {
        $xml = $this->file('xl/styles.xml');
        if ($xml === false) {
            return;
        }
        $styles = simplexml_load_string($xml);
        // custom numFmts
        if (isset($styles->numFmts->numFmt)) {
            foreach ($styles->numFmts->numFmt as $nf) {
                $this->numFmts[(int)$nf['numFmtId']] = (string)$nf['formatCode'];
            }
        }
        // cellXfs: urutan xf → numFmtId
        if (isset($styles->cellXfs->xf)) {
            foreach ($styles->cellXfs->xf as $xf) {
                $this->cellXfs[] = (int)$xf['numFmtId'];
            }
        }
    }

    private function loadSharedStrings()
    {
        $xml = $this->file('xl/sharedStrings.xml');
        if ($xml === false) {
            return;
        }
        $ss = simplexml_load_string($xml);
        if (!isset($ss->si)) {
            return;
        }
        foreach ($ss->si as $si) {
            $text = '';
            if (isset($si->t)) {
                $text = (string)$si->t;
            } elseif (isset($si->r)) {
                // rich text: gabung semua <r><t>
                foreach ($si->r as $r) {
                    if (isset($r->t)) {
                        $text .= (string)$r->t;
                    }
                }
            }
            $this->sharedStrings[] = $text;
        }
    }

    private function parseSheet($xml)
    {
        $result = [];
        $sheet = simplexml_load_string($xml);
        if (!isset($sheet->sheetData->row)) {
            return $result;
        }
        foreach ($sheet->sheetData->row as $rowEl) {
            $rowIdx = (int)$rowEl['r'];
            if ($rowIdx <= 0) {
                continue;
            }
            $cells = [];
            if (isset($rowEl->c)) {
                foreach ($rowEl->c as $c) {
                    $ref = (string)$c['r'];
                    $colIdx = self::colIndex($ref);
                    if ($colIdx <= 0) {
                        continue;
                    }
                    $value = $this->cellValue($c);
                    if ($value !== null) {
                        $cells[$colIdx] = $value;
                    }
                }
            }
            if ($cells) {
                $result[$rowIdx] = $cells;
            }
        }
        return $result;
    }

    private function cellValue(SimpleXMLElement $c)
    {
        $type = (string)$c['t'];
        $styleIdx = isset($c['s']) ? (int)$c['s'] : -1;

        if ($type === 'inlineStr') {
            $text = '';
            if (isset($c->is->t)) {
                $text = (string)$c->is->t;
            } elseif (isset($c->is->r)) {
                foreach ($c->is->r as $r) {
                    if (isset($r->t)) {
                        $text .= (string)$r->t;
                    }
                }
            }
            return $text;
        }

        if ($type === 's') {
            $idx = isset($c->v) ? (int)$c->v : -1;
            return isset($this->sharedStrings[$idx]) ? $this->sharedStrings[$idx] : null;
        }

        if ($type === 'b') {
            return isset($c->v) && (string)$c->v === '1' ? 'TRUE' : 'FALSE';
        }

        // t="str" (hasil formula) atau tanpa t (angka)
        if (!isset($c->v)) {
            return null;
        }
        $raw = (string)$c->v;
        if ($raw === '') {
            return null;
        }

        if ($type === 'str' || $type === 'e') {
            return $raw;
        }

        // angka — cek apakah tanggal menurut format
        if ($this->isDateFormat($styleIdx)) {
            return $this->excelDateToString((float)$raw);
        }

        if (preg_match('/^-?\d+$/', $raw)) {
            $int = (int)$raw;
            // hindari float presisi
            return (string)$int === $raw ? $int : (float)$raw;
        }
        return (float)$raw;
    }

    private function isDateFormat($styleIdx)
    {
        if ($styleIdx < 0 || !isset($this->cellXfs[$styleIdx])) {
            return false;
        }
        $fmtId = $this->cellXfs[$styleIdx];
        if (isset($this->numFmts[$fmtId])) {
            $code = $this->numFmts[$fmtId];
        } elseif ($fmtId >= 14 && $fmtId <= 22) {
            return true; // builtin date/time
        } elseif ($fmtId >= 45 && $fmtId <= 47) {
            return true;
        } else {
            return false;
        }
        $code = preg_replace('/\[[^\]]*\]|"[^"]*"|\\\\./', '', $code);
        return preg_match('/[dmyhs]/i', $code) === 1;
    }

    private function excelDateToString($serial)
    {
        $serial = (float)$serial;
        if ($serial < 1 || $serial > 60000) {
            return (string)$serial;
        }
        $unix = ($serial - 25569) * 86400;
        $hasTime = abs($serial - round($serial)) > 0.0000001;
        return gmdate($hasTime ? 'Y-m-d H:i:s' : 'Y-m-d', (int)round($unix));
    }

    /**
     * "BC12" → index kolom (A=1). Return 0 bila tidak ada huruf.
     */
    public static function colIndex($ref)
    {
        if (!preg_match('/^([A-Z]+)/i', trim((string)$ref), $m)) {
            return 0;
        }
        $letters = strtoupper($m[1]);
        $idx = 0;
        $len = strlen($letters);
        for ($i = 0; $i < $len; $i++) {
            $idx = $idx * 26 + (ord($letters[$i]) - 64);
        }
        return $idx;
    }

    /** Index kolom (1-based) → huruf. */
    public static function colLetter($idx)
    {
        $idx = (int)$idx;
        $letters = '';
        while ($idx > 0) {
            $mod = ($idx - 1) % 26;
            $letters = chr(65 + $mod) . $letters;
            $idx = (int)(($idx - $mod - 1) / 26);
        }
        return $letters;
    }

    /** Ambil nilai sel [baris][kolom] dengan aman. */
    public static function val(array $sheet, $row, $col)
    {
        return isset($sheet[$row][$col]) ? $sheet[$row][$col] : null;
    }
}
