<?php
/**
 * PhpZip — utilitas baca/tulis arsip ZIP tanpa ketergantungan ekstensi server.
 *
 * Mengapa kelas ini ada:
 * Sebagian instalasi PHP (mis. XAMPP dengan ekstensi zip yang belum aktif)
 * tidak memiliki class ZipArchive. ShuttleStats harus tetap bisa:
 *   1. MENULIS file .xlsx → build() menulis entri "stored" (tanpa kompresi)
 *      memakai pack() + crc32() saja — 100% PHP murni, tanpa ekstensi apa pun,
 *      tanpa file sementara.
 *   2. MEMBACA file .xlsx → readEntries() memakai ZipArchive bila tersedia,
 *      dan otomatis beralih ke pembaca murni PHP bila tidak. Pembaca murni
 *      mendukung entri "stored" dan "deflate" (via gzinflate — zlib aktif
 *      secara bawaan pada PHP Windows, termasuk XAMPP).
 *
 * Catatan: define('PHPZIP_FORCE_PURE', true) sebelum pemakaian akan memaksa
 * mode murni PHP (berguna untuk pengujian atau server dengan ZipArchive
 * bermasalah).
 */
class PhpZip
{
    /* ================= tulis ================= */

    /**
     * Buat arsip ZIP (string biner) dari daftar [nama => isi].
     * Entri ditulis tanpa kompresi (stored) sehingga tidak butuh ekstensi.
     *
     * @param array $files
     * @return string
     */
    public static function build(array $files)
    {
        $now = getdate();
        $dosTime = ($now['hours'] << 11) | ($now['minutes'] << 5) | (((int)$now['seconds']) >> 1);
        $dosDate = (($now['year'] - 1980) << 9) | ($now['mon'] << 5) | $now['mday'];
        if ($now['year'] < 1980) {
            $dosDate = (1 << 5) | 1; // 1980-01-01
            $dosTime = 0;
        }

        $out     = '';
        $central = '';
        $offset  = 0;
        $count   = 0;

        foreach ($files as $name => $data) {
            $name = (string)$name;
            $data = (string)$data;
            $crc  = crc32($data);
            if ($crc < 0) {
                $crc += 4294967296; // pack('V') membutuhkan nilai unsigned
            }
            $size = strlen($data);
            $nlen = strlen($name);

            // --- local file header (30 byte) + nama + isi ---
            $out .= "PK\x03\x04"
                . pack('vvvvvVVVvv',
                    0x0014,   // version needed to extract: 2.0
                    0x0000,   // general purpose flag
                    0x0000,   // method: 0 = stored (tanpa kompresi)
                    $dosTime,
                    $dosDate,
                    $crc,
                    $size,    // compressed size
                    $size,    // uncompressed size
                    $nlen,
                    0)        // extra field length
                . $name . $data;

            // --- central directory header (46 byte) + nama ---
            $central .= "PK\x01\x02"
                . pack('vvvvvvVVVvvvvvVV',
                    0x0014,   // version made by (MS-DOS)
                    0x0014,   // version needed to extract
                    0x0000,   // flag
                    0x0000,   // method: stored
                    $dosTime,
                    $dosDate,
                    $crc,
                    $size,    // compressed size
                    $size,    // uncompressed size
                    $nlen,
                    0,        // extra field length
                    0,        // file comment length
                    0,        // disk number start
                    0,        // internal file attributes
                    0,        // external file attributes
                    $offset)  // offset local header
                . $name;

            $offset += 30 + $nlen + $size;
            $count++;
        }

        // --- end of central directory record (22 byte) ---
        $out .= $central;
        $out .= "PK\x05\x06"
            . pack('vvvvVVv',
                0, 0,
                $count, $count,
                strlen($central),
                $offset,
                0);

        return $out;
    }

    /* ================= baca ================= */

    /**
     * Baca seluruh isi arsip → [nama => isi].
     * ZipArchive dipakai bila tersedia; selain itu pembaca murni PHP.
     *
     * @param string $path
     * @return array
     * @throws Exception
     */
    public static function readEntries($path)
    {
        if (!defined('PHPZIP_FORCE_PURE') && class_exists('ZipArchive')) {
            $zip = new ZipArchive();
            if (@$zip->open($path) === true) {
                $entries = [];
                for ($i = 0; $i < $zip->numFiles; $i++) {
                    $name = $zip->getNameIndex($i);
                    $data = $zip->getFromIndex($i);
                    if ($name !== false && $data !== false && substr($name, -1) !== '/') {
                        $entries[$name] = $data;
                    }
                }
                $zip->close();
                if ($entries) {
                    return $entries;
                }
            }
        }
        return self::readPure($path);
    }

    /**
     * Pembaca ZIP murni PHP (tanpa ZipArchive).
     * Membaca central directory untuk nama/ukuran/offset tiap entri,
     * lalu memotong data langsung dari local header. Mendukung stored
     * (metode 0) dan deflate (metode 8).
     *
     * @param string $path
     * @return array
     * @throws Exception
     */
    private static function readPure($path)
    {
        $bin = @file_get_contents($path);
        if ($bin === false) {
            throw new Exception('File tidak dapat dibaca.');
        }
        $len = strlen($bin);
        if ($len < 22) {
            throw new Exception('File terlalu kecil untuk sebuah arsip ZIP.');
        }

        // End of central directory dicari dari belakang (komentar maks. 64 KB).
        $tail = substr($bin, -65557);
        $eocd = strrpos($tail, "PK\x05\x06");
        if ($eocd === false) {
            throw new Exception('File bukan arsip ZIP yang valid.');
        }
        $eocd += $len - strlen($tail);

        $e = unpack('vdisk/vcddisk/vhere/vtotal/Vcdsize/Vcdoffset/vcomment',
            substr($bin, $eocd + 4, 18));
        if (!is_array($e) || $e['total'] == 0xFFFF || $e['cdsize'] == 0xFFFFFFFF || $e['cdoffset'] == 0xFFFFFFFF) {
            throw new Exception('Arsip ZIP64 tidak didukung pembaca murni PHP.');
        }

        $entries = [];
        $pos = (int)$e['cdoffset'];
        for ($i = 0; $i < $e['total']; $i++) {
            if ($pos + 46 > $len || substr($bin, $pos, 4) !== "PK\x01\x02") {
                break;
            }
            $h = unpack(
                'vmadeby/vneedby/vflag/vmethod/vmtime/vmdate/Vcrc/Vcsize/Vusize/vnlen/velen/vclen/vdiskstart/viattr/Veattr/Voffset',
                substr($bin, $pos + 4, 42));
            if (!is_array($h)) {
                break;
            }
            $name = substr($bin, $pos + 46, $h['nlen']);
            $pos += 46 + $h['nlen'] + $h['elen'] + $h['clen'];

            if ($name === false || $name === '' || substr($name, -1) === '/') {
                continue; // entri direktori — dilewati
            }
            if ($h['csize'] == 0xFFFFFFFF || $h['usize'] == 0xFFFFFFFF || $h['offset'] == 0xFFFFFFFF) {
                throw new Exception('Arsip ZIP64 tidak didukung pembaca murni PHP.');
            }

            // Header lokal: panjang nama/extra field boleh berbeda dengan central directory.
            if ($h['offset'] + 30 > $len) {
                continue;
            }
            $lh = unpack('vversion/vflag/vmethod/vmtime/vmdate/Vcrc/Vcsize/Vusize/vnlen/velen',
                substr($bin, $h['offset'] + 4, 26));
            if (!is_array($lh)) {
                continue;
            }
            $dataStart = $h['offset'] + 30 + $lh['nlen'] + $lh['elen'];
            if ($dataStart + $h['csize'] > $len) {
                continue;
            }
            $data = substr($bin, $dataStart, $h['csize']);

            if ((int)$h['method'] === 0) {
                $entries[$name] = $data;
            } elseif ((int)$h['method'] === 8) {
                if (!function_exists('gzinflate')) {
                    throw new Exception('File terkompresi tetapi fungsi gzinflate (zlib) tidak aktif di server. Aktifkan ekstensi zip atau zlib pada php.ini lalu restart Apache.');
                }
                $raw = gzinflate($data);
                if ($raw === false) {
                    throw new Exception('Entri arsip ZIP gagal didekompres.');
                }
                $entries[$name] = $raw;
            } else {
                throw new Exception('Metode kompresi ZIP tidak didukung (metode ' . (int)$h['method'] . ').');
            }
        }

        if (!$entries) {
            throw new Exception('Arsip ZIP kosong atau tidak dapat dibaca.');
        }
        return $entries;
    }
}
