<?php
/**
 * Fungsi bantu umum: escaping, URL, flash message, CSRF, format, dll.
 * Kompatibel PHP 7.4+.
 */

/* -------------------------------------------------------------
 * Konfigurasi & autoload sederhana
 * ------------------------------------------------------------- */
function app_config($key = null, $default = null)
{
    static $config = null;
    if ($config === null) {
        $config = require __DIR__ . '/config.php';
    }
    if ($key === null) {
        return $config;
    }
    $parts = explode('.', $key);
    $value = $config;
    foreach ($parts as $part) {
        if (!is_array($value) || !array_key_exists($part, $value)) {
            return $default;
        }
        $value = $value[$part];
    }
    return $value;
}

function app_path($relative = '')
{
    return __DIR__ . '/..' . ($relative !== '' ? '/' . ltrim($relative, '/') : '');
}

/* -------------------------------------------------------------
 * Output escaping & URL
 * ------------------------------------------------------------- */
function e($value)
{
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

/** URL absolut dari root aplikasi, dengan query string opsional. */
function url($path = '', array $query = [])
{
    $base = rtrim(dirname($_SERVER['SCRIPT_NAME']), '/\\');
    $url = $base . '/' . ltrim($path, '/');
    if ($query) {
        $url .= '?' . http_build_query($query);
    }
    return $url;
}

function redirect($path, array $query = [])
{
    header('Location: ' . url($path, $query));
    exit;
}

/* -------------------------------------------------------------
 * Flash message (sekali tampil setelah redirect)
 * ------------------------------------------------------------- */
function flash_set($type, $message)
{
    $_SESSION['_flash'][] = ['type' => $type, 'message' => $message];
}

function flash_get()
{
    $flash = isset($_SESSION['_flash']) ? $_SESSION['_flash'] : [];
    unset($_SESSION['_flash']);
    return $flash;
}

/* -------------------------------------------------------------
 * CSRF protection
 * ------------------------------------------------------------- */
function csrf_token()
{
    if (empty($_SESSION['_csrf_token'])) {
        $_SESSION['_csrf_token'] = bin2hex(random_bytes(16));
    }
    return $_SESSION['_csrf_token'];
}

function csrf_field()
{
    return '<input type="hidden" name="_csrf" value="' . e(csrf_token()) . '">';
}

function csrf_verify($token)
{
    return is_string($token)
        && !empty($_SESSION['_csrf_token'])
        && hash_equals($_SESSION['_csrf_token'], $token);
}

/* -------------------------------------------------------------
 * Input helper
 * ------------------------------------------------------------- */
function post($key, $default = '')
{
    return isset($_POST[$key]) ? $_POST[$key] : $default;
}

function get_str($key, $default = '')
{
    $v = isset($_GET[$key]) ? $_GET[$key] : $default;
    return is_string($v) ? trim($v) : $default;
}

function get_int($key, $default = 0)
{
    $v = isset($_GET[$key]) ? $_GET[$key] : $default;
    return (int)$v;
}

function post_int($key, $default = 0)
{
    return isset($_POST[$key]) ? (int)$_POST[$key] : $default;
}

/* -------------------------------------------------------------
 * Daftar referensi — HARUS sama dengan template Excel asli
 * ------------------------------------------------------------- */

/**
 * 18 teknik pukulan.
 * 'code'  = kode di sheet Input Data (kolom F..W / kolom Y "Teknik Penentu Point")
 * 'label' = nama panjang di sheet Rekapan
 * 'col'   = nama kolom database
 */
function tech_list()
{
    static $techs = null;
    if ($techs === null) {
        $techs = [
            ['code' => 'Servis',        'label' => 'Servis',              'col' => 't_servis',       'group' => 'Servis'],
            ['code' => 'Net FH',        'label' => 'Net Forehand',        'col' => 't_net_fh',       'group' => 'Net'],
            ['code' => 'Net BH',        'label' => 'Net Backhand',        'col' => 't_net_bh',       'group' => 'Net'],
            ['code' => 'Net Kill FH',   'label' => 'Net Kill Forehand',   'col' => 't_net_kill_fh',  'group' => 'Net Kill'],
            ['code' => 'Net Kill BH',   'label' => 'Net Kill Backhand',   'col' => 't_net_kill_bh',  'group' => 'Net Kill'],
            ['code' => 'U Clear FH',    'label' => 'Underhand Clear FH',  'col' => 't_u_clear_fh',   'group' => 'Underhand Clear'],
            ['code' => 'U Clear BH',    'label' => 'Underhand Clear BH',  'col' => 't_u_clear_bh',   'group' => 'Underhand Clear'],
            ['code' => 'Drive FH',      'label' => 'Drive Forehand',      'col' => 't_drive_fh',     'group' => 'Drive'],
            ['code' => 'Drive BH',      'label' => 'Drive Backhand',      'col' => 't_drive_bh',     'group' => 'Drive'],
            ['code' => 'Defend FH',     'label' => 'Defend Forehand',     'col' => 't_defend_fh',    'group' => 'Defend'],
            ['code' => 'Defend BH',     'label' => 'Defend Backhand',     'col' => 't_defend_bh',    'group' => 'Defend'],
            ['code' => 'Defend Body',   'label' => 'Defend Body',         'col' => 't_defend_body',  'group' => 'Defend'],
            ['code' => 'Smash FH',      'label' => 'Smash Forehand',      'col' => 't_smash_fh',     'group' => 'Smash'],
            ['code' => 'Smash BH',      'label' => 'Smash Backhand',      'col' => 't_smash_bh',     'group' => 'Smash'],
            ['code' => 'Dropshot FH',   'label' => 'Dropshot Forehand',   'col' => 't_dropshot_fh',  'group' => 'Dropshot'],
            ['code' => 'Dropshot BH',   'label' => 'Dropshot Backhand',   'col' => 't_dropshot_bh',  'group' => 'Dropshot'],
            ['code' => 'LOB FH',        'label' => 'Lob Forehand',        'col' => 't_lob_fh',       'group' => 'Lob'],
            ['code' => 'LOB BH',        'label' => 'Lob Backhand',        'col' => 't_lob_bh',       'group' => 'Lob'],
        ];
    }
    return $techs;
}

/** Cari info teknik berdasarkan kode (case-insensitive). */
function tech_by_code($code)
{
    foreach (tech_list() as $t) {
        if (strcasecmp($t['code'], $code) === 0) {
            return $t;
        }
    }
    return null;
}

/** Label pendek untuk tampilan ringkas (tanpa FH/BH). */
function tech_group($code)
{
    $t = tech_by_code($code);
    return $t ? $t['group'] : $code;
}

function result_types()
{
    return ['Winning Shot', 'Unforced Error', 'Forced Error'];
}

function result_badge_class($type)
{
    switch ($type) {
        case 'Winning Shot':  return 'badge-ws';
        case 'Unforced Error': return 'badge-ue';
        case 'Forced Error':  return 'badge-fe';
    }
    return '';
}

function positions()
{
    return ['In forecourt', 'In Midcourt', 'In Rearcourt', 'Out', 'Net', 'Fault'];
}

/** Nama posisi + keterangan Indonesia untuk tooltip/legend. */
function position_label($pos)
{
    $map = [
        'In forecourt' => 'Area depan (forecourt)',
        'In Midcourt'  => 'Area tengah (midcourt)',
        'In Rearcourt' => 'Area belakang (rearcourt)',
        'Out'          => 'Keluar lapangan',
        'Net'          => 'Masuk net',
        'Fault'        => 'Fault',
    ];
    return isset($map[$pos]) ? $map[$pos] : $pos;
}

function games()
{
    return [1, 2, 3];
}

function game_label($n)
{
    return 'Babak ' . (int)$n;
}

/* -------------------------------------------------------------
 * Format angka / tanggal
 * ------------------------------------------------------------- */
function fmt_num($n)
{
    return number_format((float)$n, 0, ',', '.');
}

/** Persen dengan 1 desimal; nilai desimal (0..1) dikali 100. */
function fmt_pct($value, $decimals = 1)
{
    return number_format(((float)$value) * 100, $decimals, ',', '.') . '%';
}

function fmt_date_id($date)
{
    if (empty($date) || $date === '0000-00-00') {
        return '-';
    }
    $ts = strtotime($date);
    if ($ts === false) {
        return $date;
    }
    $bulan = ['', 'Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni',
              'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'];
    return date('j', $ts) . ' ' . $bulan[(int)date('n', $ts)] . ' ' . date('Y', $ts);
}

/* -------------------------------------------------------------
 * Pertandingan aktif (disimpan di session)
 * ------------------------------------------------------------- */
function active_match_id()
{
    // 1) prioritas parameter URL ?m=
    $m = get_int('m', 0);
    if ($m > 0) {
        $_SESSION['_active_match'] = $m;
        return $m;
    }
    // 2) session terakhir
    if (!empty($_SESSION['_active_match'])) {
        return (int)$_SESSION['_active_match'];
    }
    // 3) pertandingan terbaru
    $repo = new Repository(Database::pdo());
    $latest = $repo->latestMatchId();
    if ($latest) {
        $_SESSION['_active_match'] = $latest;
    }
    return $latest ? (int)$latest : 0;
}

/** Pastikan pertandingan aktif valid; jika tidak ada → null. */
function current_match()
{
    static $match = null;
    if ($match === null) {
        $repo = new Repository(Database::pdo());
        $match = $repo->getMatch(active_match_id());
        if (!$match) {
            $match = false;
        }
    }
    return $match ?: null;
}

/** URL dengan menyertakan id pertandingan aktif. */
function m_url($path, array $extra = [])
{
    $match = current_match();
    if ($match && !isset($extra['m'])) {
        $extra['m'] = $match['id'];
    }
    return url($path, $extra);
}

/* -------------------------------------------------------------
 * Lain-lain
 * ------------------------------------------------------------- */
function http_json($data, $status = 200)
{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

function clamp_int($value, $min, $max)
{
    $v = (int)$value;
    return max($min, min($max, $v));
}
