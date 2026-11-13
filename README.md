# ShuttleStats — Sistem Analisis Pertandingan Bulutangkis

Aplikasi web untuk **mengolah data notasi pertandingan bulutangkis** — menggantikan
template Excel *Input Data Task* dengan sistem yang lebih cepat: input rally langsung
di web, rekap otomatis, dan dashboard analisis interaktif.

Dibangun dengan **PHP murni + JavaScript (vanilla + Chart.js) + CSS custom** —
tanpa Composer, tanpa framework, siap dijalankan di XAMPP/Laragon/hosting apa pun.

---

## Fitur

| Menu | Fungsi |
|---|---|
| **Dashboard** | Ringkasan pertandingan, skor per babak, kartu statistik (total rally, pukulan, poin, WS/UE/FE), 6 grafik interaktif: pukulan per teknik, jenis hasil (doughnut per pemain), poin per babak, posisi pukulan penentu, teknik penentu point |
| **Rekap Pemain** | Replika sheet *Rekapan Pemain 1/2* template Excel: Jumlah Pukulan, Forced Error, %FE, Unforced Error, %UE, Winning Shot, %WS per 18 teknik + baris JUMLAH — dengan filter per babak |
| **Input Rally** | Form pencatatan cepat: 18 counter teknik (±) untuk kedua pemain sekaligus + bagian Penentu Point (pemain, teknik, jenis hasil, posisi). Simpan via AJAX, nomor rally otomatis, `Ctrl+Enter` untuk simpan cepat |
| **Data Rally** | Tabel lengkap dengan pencarian, filter (babak/pemain/jenis hasil/posisi/teknik penentu), pengurutan, paginasi, edit (modal), hapus baris, hapus rally penuh |
| **Daftar Pertandingan** | Kelola banyak pertandingan (buat/edit/hapus), pemilih pertandingan aktif di topbar |
| **Import Excel** | Baca file `.xlsx` **persis format template asli** (sheet *Input Data*) atau CSV — mode *buat pertandingan baru* atau *tambahkan ke pertandingan*, dengan laporan hasil import |
| **Export Data** | Unduh XLSX 4 sheet mengikuti struktur template asli (*Input Data*, *Rekapan Pemain 1*, *Rekap pemain 2*, *Dashbord* — berisi nilai hasil olahan), CSV data mentah, dan template kosong |
| **Cetak Laporan** | Laporan siap cetak/PDF: ringkasan, skor, rekap kedua pemain, teknik penentu |

### Kesesuaian dengan template Excel

Perhitungan mengikuti rumus asli template:

- `Jumlah Pukulan` = `SUMIFS` pukulan teknik per pemain
- `FE / UE / WS` = `COUNTIFS` teknik penentu + jenis hasil per pemain
- `%FE / %UE / %WS` = `IFERROR(FE/Jumlah, 0)`
- 18 kode teknik (Servis … LOB BH), Babak 1–3, jenis hasil *Winning Shot / Unforced Error / Forced Error*, posisi *In forecourt / In Midcourt / In Rearcourt / Out / Net / Fault* — identik dengan dropdown template

**Nilai tambah**: skor per babak & pemenang, poin via winning shot vs error lawan,
rata-rata pukulan per rally, rally terpanjang, distribusi posisi, dan filter per babak.

---

## Cara Menjalankan

### XAMPP / Laragon (disarankan)

1. Salin folder `shuttlestats` ke `C:\xampp\htdocs\` (atau folder www Laragon).
2. Pastikan ekstensi PHP aktif: `pdo_sqlite`, `mbstring`, `xml`
   (bawaan XAMPP sudah aktif secara default).
   Ekstensi `zip` **tidak wajib** — baca/tulis file Excel menggunakan fallback
   murni PHP (kelas `app/PhpZip.php`) saat `ZipArchive` tidak tersedia.
3. Buka `http://localhost/shuttlestats/` — database SQLite dibuat **otomatis**
   saat pertama diakses, termasuk satu pertandingan contoh (boleh dihapus).

### PHP built-in server (tanpa XAMPP)

```bash
cd shuttlestats
php -S localhost:8080
# buka http://localhost:8080
```

### MySQL (opsional)

1. Import `sql/mysql_schema.sql` lewat phpMyAdmin (membuat database `shuttlestats`).
2. Edit `app/config.php`:
   ```php
   'db' => [
       'driver' => 'mysql',
       'mysql' => [
           'host'     => 'localhost',
           'dbname'   => 'shuttlestats',
           'username' => 'root',
           'password' => '',
           ...
       ],
   ],
   ```

**Kebutuhan server**: PHP **7.4+** (teruji hingga PHP 8.3), ekstensi `pdo_sqlite`
(atau `pdo_mysql`), `mbstring`, `xml`. Ekstensi `zip` opsional (dipakai bila ada
untuk membaca file Excel lebih cepat; tanpa pun semua fitur import/export tetap
jalan). Semua aset (Chart.js, ikon) dibundel lokal — aplikasi jalan tanpa internet.

---

## Struktur Proyek

```
shuttlestats/
├── index.php              Dashboard (grafik)
├── pertandingan.php       CRUD pertandingan
├── input.php              Input rally (form counter 2 pemain)
├── data.php               Tabel data + filter + edit/hapus
├── rekap.php              Rekap per pemain (replika sheet Excel)
├── import.php             Import XLSX/CSV
├── export.php             Export XLSX/CSV/template
├── cetak.php              Laporan cetak
├── api.php                Endpoint JSON (AJAX) + proteksi CSRF
├── app/
│   ├── config.php         Konfigurasi (DB, limit upload, seed, debug)
│   ├── bootstrap.php      Inisialisasi aplikasi
│   ├── Database.php       PDO SQLite/MySQL + skema + seeder
│   ├── Repository.php     Akses data (match & entri rally)
│   ├── Analyzer.php       Mesin rekap & statistik
│   ├── XlsxReader.php     Pembaca .xlsx murni PHP
│   ├── XlsxWriter.php     Penulis .xlsx murni PHP
│   ├── Importer.php       Parser template → database
│   ├── Exporter.php       Database → XLSX/CSV
│   ├── helpers.php        Fungsi bantu (escaping, CSRF, format)
│   └── views/             Layout header/footer/partial
├── assets/
│   ├── css/app.css        Design system custom
│   ├── js/app.js          Interaksi UI (vanilla JS)
│   ├── js/chart.umd.min.js Chart.js 4 (bundel lokal)
│   └── img/favicon.svg
├── data/                  Database SQLite (dilindungi .htaccess)
└── sql/mysql_schema.sql   Skema MySQL opsional
```

---

## Format Import

**XLSX** — struktur template *Input Data Task*:

- Sheet `Input Data`: nama pemain di `D4`/`D5`, turnamen `D6`, tanggal `D7`
- Data rally mulai **baris 11**: kolom Babak, No. Rally, Pemain, 18 kolom teknik
  (Servis … LOB BH), Teknik Penentu Point (kolom Y), Jenis Hasil, Posisi
- Baris kosong dilewati otomatis; nama pemain dicocokkan tanpa membedakan huruf besar/kecil

**CSV** — header: `Babak;No. Rally / Point;Pemain;Servis;Net FH;…;LOB BH;Total Pukulan;Teknik Penentu Point;Jenis Hasil;Posisi`
(pemisah `;` atau `,`). Baris metadata opsional sebelum header:
`Nama Pemain 1;…`, `Nama Pemain 2;…`, `Turnamen;…`, `Tanggal;…`.

## Catatan Keamanan

- Semua input di-escape (XSS) dan query memakai prepared statement (SQL injection).
- Endpoint AJAX dilindungi token CSRF; file import dibatasi 5 MB.
- Folder `data/` diproteksi `.htaccess` (Apache). Untuk Nginx, tambahkan
  `location /shuttlestats/data { deny all; }`.
- Bila dipasang di server publik: matikan `'debug' => true` di `app/config.php`
  dan pertimbangkan menambah autentikasi Basic Auth di folder aplikasi.

## Lisensi

Aplikasi dibuat untuk kebutuhan analisis notasi bulutangkis — bebas digunakan dan
dimodifikasi.
