<?php
/**
 * Penulis file .xlsx murni PHP (tanpa PhpSpreadsheet / composer).
 * Menghasilkan paket xlsx minimal yang valid: inline strings,
 * style (bold, header, border, persen), lebar kolom, merge cells.
 */
class XlsxWriter
{
    /** @var array nama sheet => sheetData XML */
    private $sheets = [];

    /** @var array nama sheet => XML tambahan (cols, mergeCells) */
    private $sheetExtras = [];

    /** Indeks style yang tersedia (lihat stylesXml) */
    const S_DEFAULT = 0;
    const S_TITLE   = 1;   // judul besar, bold
    const S_BOLD    = 2;   // bold saja
    const S_HEADER  = 3;   // header tabel: bold, putih, fill gelap, tengah, border
    const S_CELL    = 4;   // border tipis
    const S_CELL_C  = 5;   // border tipis + center
    const S_TOTAL   = 6;   // total: bold, fill terang, border
    const S_PCT     = 7;   // border + format 0,0%
    const S_PCT_TOT = 8;   // total + format persen
    const S_META    = 9;   // label metadata: bold
    const S_SUB     = 10;  // sub judul section: bold + fill sangat terang

    /**
     * Tambah sheet.
     * @param string $name
     * @param array  $rows  baris data: kunci array = nomor baris - 1 (0 = baris 1);
     *                      setiap baris = array nilai dengan kunci = nomor kolom
     *                      1-BASED (1 = kolom A, 2 = kolom B, dst. — sel kosong
     *                      boleh dilewati). Nilai: string|number|null|array{v:mixed,s:int}
     * @param array  $opts  ['widths'=>[...], 'merges'=>['Y11:Z11',...], 'autofilter'=>'A9:AB100']
     */
    public function addSheet($name, array $rows, array $opts = [])
    {
        $this->sheets[$name] = $this->sheetDataXml($rows);
        $this->sheetExtras[$name] = $opts;
    }

    public function download($filename)
    {
        $xml = $this->build();
        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment; filename="' . rawurlencode($filename) . '"');
        header('Content-Length: ' . strlen($xml));
        header('Cache-Control: max-age=0');
        echo $xml;
        exit;
    }

    public function save($path)
    {
        $xml = $this->build();
        file_put_contents($path, $xml);
        return strlen($xml);
    }

    /* ================= build package ================= */

    private function build()
    {
        $files = [
            '[Content_Types].xml'        => $this->contentTypesXml(),
            '_rels/.rels'                => $this->rootRelsXml(),
            'xl/workbook.xml'            => $this->workbookXml(),
            'xl/_rels/workbook.xml.rels' => $this->workbookRelsXml(),
            'xl/styles.xml'              => $this->stylesXml(),
        ];
        $i = 1;
        foreach ($this->sheets as $name => $dataXml) {
            $files['xl/worksheets/sheet' . $i . '.xml'] = $this->worksheetXml($name, $dataXml);
            $i++;
        }

        // Arsip ZIP dibuat murni PHP (PhpZip) — tidak membutuhkan
        // ekstensi ZipArchive maupun file sementara di server.
        return PhpZip::build($files);
    }

    private function contentTypesXml()
    {
        $xml  = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>' . "\n";
        $xml .= '<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">';
        $xml .= '<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>';
        $xml .= '<Default Extension="xml" ContentType="application/xml"/>';
        $xml .= '<Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>';
        $xml .= '<Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/>';
        $n = count($this->sheets);
        for ($i = 1; $i <= $n; $i++) {
            $xml .= '<Override PartName="/xl/worksheets/sheet' . $i . '.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>';
        }
        $xml .= '</Types>';
        return $xml;
    }

    private function rootRelsXml()
    {
        $xml  = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>' . "\n";
        $xml .= '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">';
        $xml .= '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/>';
        $xml .= '</Relationships>';
        return $xml;
    }

    private function workbookXml()
    {
        $xml  = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>' . "\n";
        $xml .= '<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" ';
        $xml .= 'xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">';
        $xml .= '<sheets>';
        $i = 1;
        foreach ($this->sheets as $name => $rows) {
            $xml .= '<sheet name="' . self::xmlAttr($name) . '" sheetId="' . $i . '" r:id="rId' . $i . '"/>';
            $i++;
        }
        $xml .= '</sheets></workbook>';
        return $xml;
    }

    private function workbookRelsXml()
    {
        $xml  = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>' . "\n";
        $xml .= '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">';
        $i = 1;
        foreach ($this->sheets as $name => $rows) {
            $xml .= '<Relationship Id="rId' . $i . '" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet' . $i . '.xml"/>';
            $i++;
        }
        $xml .= '<Relationship Id="rId' . ($i) . '" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/>';
        $xml .= '</Relationships>';
        return $xml;
    }

    private function worksheetXml($name, $dataXml)
    {
        $opts = isset($this->sheetExtras[$name]) ? $this->sheetExtras[$name] : [];

        $xml  = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>' . "\n";
        $xml .= '<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">';
        $freeze = isset($opts['freeze']) ? (int)$opts['freeze'] : 0;
        if ($freeze > 0) {
            $xml .= '<sheetViews><sheetView workbookViewId="0"><pane ySplit="' . ($freeze - 1)
                . '" topLeftCell="A' . $freeze . '" activePane="bottomLeft" state="frozen"/></sheetView></sheetViews>';
        } else {
            $xml .= '<sheetViews><sheetView workbookViewId="0"/></sheetViews>';
        }

        if (!empty($opts['widths'])) {
            $xml .= '<cols>';
            foreach ($opts['widths'] as $i => $w) {
                $xml .= '<col min="' . $i . '" max="' . $i . '" width="' . $w . '" customWidth="1"/>';
            }
            $xml .= '</cols>';
        }

        $xml .= '<sheetData>' . $dataXml . '</sheetData>';

        if (!empty($opts['merges'])) {
            $xml .= '<mergeCells count="' . count($opts['merges']) . '">';
            foreach ($opts['merges'] as $ref) {
                $xml .= '<mergeCell ref="' . self::xmlAttr($ref) . '"/>';
            }
            $xml .= '</mergeCells>';
        }

        if (!empty($opts['autofilter'])) {
            $xml .= '<autoFilter ref="' . self::xmlAttr($opts['autofilter']) . '"/>';
        }

        $xml .= '<pageMargins left="0.5" right="0.5" top="0.6" bottom="0.6" header="0.3" footer="0.3"/>';
        $xml .= '</worksheet>';
        return $xml;
    }

    private function sheetDataXml(array $rows)
    {
        $xml = '';
        foreach ($rows as $rIdx => $row) {
            if (!is_array($row) || empty($row)) {
                continue;
            }
            $xml .= '<row r="' . ($rIdx + 1) . '">';
            foreach ($row as $cIdx => $cell) {
                $value = $cell;
                $style = null;
                if (is_array($cell)) {
                    $value = isset($cell['v']) ? $cell['v'] : null;
                    $style = isset($cell['s']) ? (int)$cell['s'] : null;
                }
                if ($value === null || $value === '') {
                    continue;
                }
                // kunci kolom 1-based (1 = kolom A) — jaga-jaga dari data tak valid
                if ((int)$cIdx < 1) {
                    throw new Exception('Indeks kolom tidak valid pada baris ' . ($rIdx + 1)
                        . ' — gunakan kunci array 1-based (1 = kolom A).');
                }
                $ref = self::colLetter($cIdx) . ($rIdx + 1);
                $xml .= $this->cellXml($ref, $value, $style);
            }
            $xml .= '</row>';
        }
        return $xml;
    }

    private function cellXml($ref, $value, $style)
    {
        $sAttr = $style !== null ? ' s="' . $style . '"' : '';
        if (is_int($value) || (is_float($value) && !is_nan($value) && !is_infinite($value))) {
            $num = is_float($value) ? self::floatStr($value) : $value;
            return '<c r="' . $ref . '"' . $sAttr . '><v>' . $num . '</v></c>';
        }
        if (is_bool($value)) {
            return '<c r="' . $ref . '"' . $sAttr . ' t="b"><v>' . ($value ? 1 : 0) . '</v></c>';
        }
        // string
        return '<c r="' . $ref . '"' . $sAttr . ' t="inlineStr"><is><t xml:space="preserve">'
            . self::xmlText((string)$value) . '</t></is></c>';
    }

    /* ================= styles ================= */

    private function stylesXml()
    {
        $border = '<border><left style="thin"><color rgb="FFD4DCE5"/></left><right style="thin"><color rgb="FFD4DCE5"/></right><top style="thin"><color rgb="FFD4DCE5"/></top><bottom style="thin"><color rgb="FFD4DCE5"/></bottom><diagonal/></border>';

        $xml  = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>' . "\n";
        $xml .= '<styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">';
        $xml .= '<numFmts count="1"><numFmt numFmtId="164" formatCode="0.0%"/></numFmts>';
        $xml .= '<fonts count="7">'
            . '<font><sz val="11"/><name val="Calibri"/></font>'                                                 // 0 default
            . '<font><b/><sz val="14"/><color rgb="FF17322B"/><name val="Calibri"/></font>'                      // 1 judul
            . '<font><b/><sz val="11"/><name val="Calibri"/></font>'                                             // 2 bold
            . '<font><b/><sz val="11"/><color rgb="FFFFFFFF"/><name val="Calibri"/></font>'                      // 3 header putih
            . '<font><sz val="11"/><name val="Calibri"/></font>'                                                 // 4
            . '<font><b/><sz val="11"/><color rgb="FF17322B"/><name val="Calibri"/></font>'                      // 5 total
            . '<font><i/><sz val="10"/><color rgb="FF66747E"/><name val="Calibri"/></font>'                      // 6 catatan italic
            . '</fonts>';
        $xml .= '<fills count="5">'
            . '<fill><patternFill patternType="none"/></fill>'
            . '<fill><patternFill patternType="gray125"/></fill>'
            . '<fill><patternFill patternType="solid"><fgColor rgb="FF17322B"/><bgColor indexed="64"/></patternFill></fill>'   // 2 header gelap
            . '<fill><patternFill patternType="solid"><fgColor rgb="FFEAF2EC"/><bgColor indexed="64"/></patternFill></fill>'   // 3 total terang
            . '<fill><patternFill patternType="solid"><fgColor rgb="FFF4F8F5"/><bgColor indexed="64"/></patternFill></fill>'   // 4 sub section
            . '</fills>';
        $xml .= '<borders count="2"><border><left/><right/><top/><bottom/><diagonal/></border>' . $border . '</borders>';
        $xml .= '<cellStyleXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0"/></cellStyleXfs>';
        // cellXfs — urutan = konstanta S_*
        $xml .= '<cellXfs count="11">'
            . '<xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0"/>'                                                   // 0 default
            . '<xf numFmtId="0" fontId="1" fillId="0" borderId="0" xfId="0" applyFont="1"/>'                                     // 1 judul
            . '<xf numFmtId="0" fontId="2" fillId="0" borderId="0" xfId="0" applyFont="1"/>'                                     // 2 bold
            . '<xf numFmtId="0" fontId="3" fillId="2" borderId="1" xfId="0" applyFont="1" applyFill="1" applyBorder="1" applyAlignment="1"><alignment horizontal="center" vertical="center" wrapText="1"/></xf>' // 3 header
            . '<xf numFmtId="0" fontId="0" fillId="0" borderId="1" xfId="0" applyBorder="1"/>'                                   // 4 cell border
            . '<xf numFmtId="0" fontId="0" fillId="0" borderId="1" xfId="0" applyBorder="1" applyAlignment="1"><alignment horizontal="center"/></xf>' // 5 center border
            . '<xf numFmtId="0" fontId="5" fillId="3" borderId="1" xfId="0" applyFont="1" applyFill="1" applyBorder="1"/>'       // 6 total
            . '<xf numFmtId="164" fontId="0" fillId="0" borderId="1" xfId="0" applyBorder="1" applyNumberFormat="1"/>' // 7 pct
            . '<xf numFmtId="164" fontId="5" fillId="3" borderId="1" xfId="0" applyFont="1" applyFill="1" applyBorder="1" applyNumberFormat="1"/>' // 8 pct total
            . '<xf numFmtId="0" fontId="2" fillId="0" borderId="0" xfId="0" applyFont="1"/>'                                     // 9 meta label
            . '<xf numFmtId="0" fontId="2" fillId="4" borderId="0" xfId="0" applyFont="1" applyFill="1"/>'                       // 10 sub section
            . '</cellXfs>';
        $xml .= '<cellStyles count="1"><cellStyle name="Normal" xfId="0" builtinId="0"/></cellStyles>';
        $xml .= '</styleSheet>';
        return $xml;
    }

    /* ================= util ================= */

    /** Index kolom (1-based) → huruf (A, B, …, AA). */
    private static function colLetter($idx)
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

    /**
     * Representasi desimal float tanpa notasi eksponen dan tanpa memotong
     * presisi: dicari jumlah desimal terkecil yang tetap menghasilkan nilai
     * sama saat dibaca kembali (round-trip), lalu nol di belakang dibuang.
     */
    private static function floatStr($f)
    {
        foreach ([10, 12, 14, 16] as $dec) {
            $s = rtrim(rtrim(sprintf('%.0' . $dec . 'F', $f), '0'), '.');
            if ($s === '' || $s === '-') {
                return '0';
            }
            if ((float)$s === $f) {
                return $s;
            }
        }
        return rtrim(rtrim(sprintf('%.16F', $f), '0'), '.');
    }

    private static function xmlText($s)
    {
        return htmlspecialchars($s, ENT_XML1 | ENT_QUOTES, 'UTF-8');
    }

    private static function xmlAttr($s)
    {
        return htmlspecialchars($s, ENT_XML1 | ENT_QUOTES, 'UTF-8');
    }
}
