<?php
/**
 * Koneksi database (PDO) + pembuatan skema otomatis + seeder data contoh.
 * Mendukung SQLite (default) dan MySQL.
 */
class Database
{
    /** @var PDO */
    private static $pdo = null;

    /** @var bool */
    private static $ready = false;

    public static function pdo()
    {
        if (self::$pdo === null) {
            $cfg = app_config('db');
            if ($cfg['driver'] === 'mysql') {
                $m = $cfg['mysql'];
                $dsn = sprintf(
                    'mysql:host=%s;port=%d;dbname=%s;charset=%s',
                    $m['host'], $m['port'], $m['dbname'], $m['charset']
                );
                self::$pdo = new PDO($dsn, $m['username'], $m['password'], [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                ]);
            } else {
                $path = $cfg['sqlite_path'];
                $dir = dirname($path);
                if (!is_dir($dir)) {
                    mkdir($dir, 0775, true);
                }
                self::$pdo = new PDO('sqlite:' . $path, null, null, [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                ]);
                self::$pdo->exec('PRAGMA journal_mode = WAL');
                self::$pdo->exec('PRAGMA foreign_keys = ON');
            }
        }
        if (!self::$ready) {
            self::install();
            self::$ready = true;
        }
        return self::$pdo;
    }

    public static function driver()
    {
        return app_config('db.driver') === 'mysql' ? 'mysql' : 'sqlite';
    }

    /** Buat tabel bila belum ada. */
    private static function install()
    {
        $mysql = self::driver() === 'mysql';
        $auto  = $mysql ? 'INT AUTO_INCREMENT PRIMARY KEY' : 'INTEGER PRIMARY KEY AUTOINCREMENT';
        $int   = $mysql ? 'INT' : 'INTEGER';

        $techCols = '';
        foreach (tech_list() as $t) {
            $techCols .= ", {$t['col']} {$int} NOT NULL DEFAULT 0";
        }

        $pdo = self::$pdo;
        $pdo->exec("CREATE TABLE IF NOT EXISTS matches (
            id {$auto},
            player1 TEXT NOT NULL,
            player2 TEXT NOT NULL,
            tournament TEXT NOT NULL DEFAULT '',
            match_date TEXT NOT NULL DEFAULT '',
            created_at TEXT NOT NULL
        )");

        $pdo->exec("CREATE TABLE IF NOT EXISTS rally_entries (
            id {$auto},
            match_id {$int} NOT NULL,
            game {$int} NOT NULL DEFAULT 1,
            rally_no {$int} NOT NULL DEFAULT 1,
            player_num {$int} NOT NULL DEFAULT 1{$techCols},
            total_shots {$int} NOT NULL DEFAULT 0,
            det_technique TEXT NULL,
            result_type TEXT NULL,
            position TEXT NULL,
            created_at TEXT NOT NULL
        )");

        $pdo->exec('CREATE INDEX IF NOT EXISTS idx_entries_match ON rally_entries (match_id, game, rally_no)');
        $pdo->exec('CREATE INDEX IF NOT EXISTS idx_entries_result ON rally_entries (match_id, result_type)');

        // Seeder: satu pertandingan contoh agar dashboard langsung terisi.
        if (app_config('seed_demo')) {
            $count = (int)$pdo->query('SELECT COUNT(*) AS c FROM matches')->fetch()['c'];
            if ($count === 0) {
                self::seedDemo();
            }
        }
    }

    /** Data contoh: simulasi pertandingan 3 babak dengan profil pemain. */
    public static function seedDemo()
    {
        $pdo = self::$pdo;
        $now = date('Y-m-d H:i:s');

        $techCols = array_map(function ($t) { return $t['col']; }, tech_list());
        // 4 kolom awal + 18 teknik + 5 kolom akhir = 27
        $colCount = 4 + count($techCols) + 5;

        $pdo->beginTransaction();
        try {
            $pdo->prepare('INSERT INTO matches (player1, player2, tournament, match_date, created_at)
                           VALUES (?, ?, ?, ?, ?)')
                ->execute(['Rizky Pratama', 'Bagas Setiawan', 'Kejuaraan Nasional 2026 — Data Contoh', '2026-11-08', $now]);
            $matchId = (int)$pdo->lastInsertId();

        mt_srand(20260101);

        // Profil pukulan (bobot): pemain 1 = agresif, pemain 2 = bertahan
        $profile = [
            1 => ['Servis' => 0, 'Net FH' => 9, 'Net BH' => 4, 'Net Kill FH' => 5, 'Net Kill BH' => 2,
                  'U Clear FH' => 7, 'U Clear BH' => 3, 'Drive FH' => 8, 'Drive BH' => 4,
                  'Defend FH' => 6, 'Defend BH' => 5, 'Defend Body' => 3,
                  'Smash FH' => 14, 'Smash BH' => 2, 'Dropshot FH' => 8, 'Dropshot BH' => 3,
                  'LOB FH' => 5, 'LOB BH' => 2],
            2 => ['Servis' => 0, 'Net FH' => 11, 'Net BH' => 5, 'Net Kill FH' => 3, 'Net Kill BH' => 1,
                  'U Clear FH' => 9, 'U Clear BH' => 4, 'Drive FH' => 5, 'Drive BH' => 3,
                  'Defend FH' => 10, 'Defend BH' => 9, 'Defend Body' => 7,
                  'Smash FH' => 8, 'Smash BH' => 1, 'Dropshot FH' => 10, 'Dropshot BH' => 4,
                  'LOB FH' => 9, 'LOB BH' => 4],
        ];
        $attackTech  = ['Smash FH', 'Smash FH', 'Smash FH', 'Net Kill FH', 'Drive FH', 'Dropshot FH', 'Net Kill BH'];
        $unforcedTech = ['Net FH', 'Dropshot FH', 'Dropshot BH', 'Net BH', 'LOB FH', 'U Clear FH'];
        $forcedTech  = ['Defend FH', 'Defend BH', 'Defend Body', 'LOB BH', 'U Clear BH', 'Net BH'];
        $wsPos  = ['In forecourt', 'In Midcourt', 'In Rearcourt', 'In Midcourt', 'In forecourt'];
        $uePos  = ['Net', 'Net', 'Out', 'Out', 'Fault'];
        $fePos  = ['Net', 'Out', 'In Rearcourt', 'In Midcourt'];

        // Skor per babak (pemenang babak selalu >= 21)
        $games = [
            ['winner' => 1, 'score' => [21, 15]],
            ['winner' => 2, 'score' => [18, 21]],
            ['winner' => 1, 'score' => [21, 17]],
        ];

        $insert = $pdo->prepare(
            'INSERT INTO rally_entries (match_id, game, rally_no, player_num, ' .
            implode(', ', $techCols) .
            ', total_shots, det_technique, result_type, position, created_at)
             VALUES (' . rtrim(str_repeat('?, ', $colCount), ', ') . ')'
        );

        foreach ($games as $gi => $g) {
            $game = $gi + 1;
            $pts = [1 => 0, 2 => 0];
            $target = $g['score'];
            $rallyNo = 0;
            $server = 1;
            while ($pts[1] < $target[0] && $pts[2] < $target[1]) {
                $rallyNo++;
                // pemenang rally — sebar realistis (pemain kuat lebih sering menang)
                $roll = mt_rand(1, 100);
                $strong = $g['winner'];
                $winner = ($roll <= ($strong === 1 ? 54 : 46)) ? $strong : (3 - $strong);
                $loser = 3 - $winner;
                $pts[$winner]++;

                // jenis kemenangan: ~45% WS, ~30% UE lawan, ~25% FE lawan
                $roll2 = mt_rand(1, 100);
                if ($roll2 <= 45) {
                    $outcome = 'ws';
                } elseif ($roll2 <= 75) {
                    $outcome = 'ue';
                } else {
                    $outcome = 'fe';
                }

                // panjang rally
                $len = mt_rand(3, 16) + ($outcome === 'ws' ? 2 : 0);
                if (mt_rand(1, 100) <= 8) {
                    $len = mt_rand(1, 3); // rally pendek
                }

                // distribusi pukulan ke dua pemain bergantian mulai dari server
                $shots = [1 => array_fill_keys(array_map(function ($t) { return $t['code']; }, tech_list()), 0),
                          2 => array_fill_keys(array_map(function ($t) { return $t['code']; }, tech_list()), 0)];
                $cur = $server;
                for ($s = 0; $s < $len; $s++) {
                    $code = self::weightedPick($profile[$cur]);
                    if ($s === 0) {
                        $code = 'Servis';
                    }
                    $shots[$cur][$code]++;
                    $cur = 3 - $cur;
                }

                $rows = [];
                foreach ([1, 2] as $pn) {
                    $total = array_sum($shots[$pn]);
                    $isDet = ($outcome === 'ws' && $pn === $winner)
                          || ($outcome !== 'ws' && $pn === $loser);
                    $det = null; $res = null; $pos = null;
                    if ($isDet) {
                        if ($outcome === 'ws') {
                            // ganti pukulan terakhir pemenang jadi pukulan penentu (menyerang)
                            $det = $attackTech[mt_rand(0, count($attackTech) - 1)];
                            $res = 'Winning Shot';
                            $pos = $wsPos[mt_rand(0, count($wsPos) - 1)];
                        } elseif ($outcome === 'ue') {
                            $det = $unforcedTech[mt_rand(0, count($unforcedTech) - 1)];
                            $res = 'Unforced Error';
                            $pos = $uePos[mt_rand(0, count($uePos) - 1)];
                        } else {
                            $det = $forcedTech[mt_rand(0, count($forcedTech) - 1)];
                            $res = 'Forced Error';
                            $pos = $fePos[mt_rand(0, count($fePos) - 1)];
                        }
                        // pastikan teknik penentu punya minimal 1 hitungan
                        if ($shots[$pn][$det] === 0) {
                            $shots[$pn][$det] = 1;
                        }
                    }
                    $total = array_sum($shots[$pn]);
                    $row = [$matchId, $game, $rallyNo, $pn];
                    foreach (tech_list() as $t) {
                        $row[] = $shots[$pn][$t['code']];
                    }
                    array_push($row, $total, $det, $res, $pos, $now);
                    $insert->execute($row);
                }

                // pemenang rally menjadi server berikutnya (rally point system)
                $server = $winner;
            }
        }

            $pdo->commit();
        } catch (Exception $e) {
            $pdo->rollBack();
            throw $e;
        }
    }

    private static function weightedPick(array $weights)
    {
        $total = array_sum($weights);
        $r = mt_rand(1, max(1, $total));
        foreach ($weights as $key => $w) {
            $r -= $w;
            if ($r <= 0) {
                return $key;
            }
        }
        return array_keys($weights)[0];
    }
}
