<?php
/**
 * ShuttleStats — Sistem Analisis Pertandingan Bulutangkis
 * Konfigurasi aplikasi.
 *
 * Basis data default: SQLite (tanpa konfigurasi apa pun).
 * Untuk MySQL: ubah 'driver' menjadi 'mysql' dan isi kredensial.
 */

define('APP_NAME', 'ShuttleStats');
define('APP_TAGLINE', 'Sistem Analisis Pertandingan Bulutangkis');
define('APP_VERSION', '1.0.0');

return [
    'db' => [
        // 'sqlite' (disarankan — langsung jalan) atau 'mysql'
        'driver' => getenv('DB_DRIVER') ?: 'sqlite',

        // --- SQLite ---
        // Di Railway: set DB_PATH=/data/shuttlestats.sqlite (lokasi Volume)
        'sqlite_path' => getenv('DB_PATH') ?: __DIR__ . '/../data/shuttlestats.sqlite',

        // --- MySQL (dipakai jika driver = 'mysql') ---
        'mysql' => [
            'host'     => getenv('MYSQLHOST') ?: 'localhost',
            'port'     => (int)(getenv('MYSQLPORT') ?: 3306),
            'dbname'   => getenv('MYSQLDATABASE') ?: 'shuttlestats',
            'username' => getenv('MYSQLUSER') ?: 'root',
            'password' => getenv('MYSQLPASSWORD') ?: '',
            'charset'  => 'utf8mb4',
        ],
    ],

    // Baris per halaman pada tabel data
    'per_page' => 15,

    // Batas ukuran file import (bytes) — 5 MB
    'upload_max' => 5 * 1024 * 1024,

    // Buat pertandingan contoh otomatis saat pertama kali dijalankan
    'seed_demo' => getenv('SEED_DEMO') !== false ? filter_var(getenv('SEED_DEMO'), FILTER_VALIDATE_BOOLEAN) : true,

    // Tampilkan error PHP (true saat pengembangan, false di produksi)
    'debug' => getenv('APP_DEBUG') !== false ? filter_var(getenv('APP_DEBUG'), FILTER_VALIDATE_BOOLEAN) : true,

    // Zona waktu aplikasi
    'timezone' => 'Asia/Jakarta',
];
