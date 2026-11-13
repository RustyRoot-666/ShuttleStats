-- ============================================================
-- ShuttleStats — Skema MySQL (opsional, bila tidak memakai SQLite)
-- Import lewat phpMyAdmin atau: mysql -u root -p < mysql_schema.sql
-- Lalu ubah app/config.php: 'driver' => 'mysql'
-- ============================================================

CREATE DATABASE IF NOT EXISTS shuttlestats CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE shuttlestats;

CREATE TABLE IF NOT EXISTS matches (
    id INT AUTO_INCREMENT PRIMARY KEY,
    player1 VARCHAR(80) NOT NULL,
    player2 VARCHAR(80) NOT NULL,
    tournament VARCHAR(120) NOT NULL DEFAULT '',
    match_date DATE NOT NULL DEFAULT '1970-01-01',
    created_at DATETIME NOT NULL
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS rally_entries (
    id INT AUTO_INCREMENT PRIMARY KEY,
    match_id INT NOT NULL,
    game INT NOT NULL DEFAULT 1,
    rally_no INT NOT NULL DEFAULT 1,
    player_num INT NOT NULL DEFAULT 1,
    t_servis INT NOT NULL DEFAULT 0,
    t_net_fh INT NOT NULL DEFAULT 0,
    t_net_bh INT NOT NULL DEFAULT 0,
    t_net_kill_fh INT NOT NULL DEFAULT 0,
    t_net_kill_bh INT NOT NULL DEFAULT 0,
    t_u_clear_fh INT NOT NULL DEFAULT 0,
    t_u_clear_bh INT NOT NULL DEFAULT 0,
    t_drive_fh INT NOT NULL DEFAULT 0,
    t_drive_bh INT NOT NULL DEFAULT 0,
    t_defend_fh INT NOT NULL DEFAULT 0,
    t_defend_bh INT NOT NULL DEFAULT 0,
    t_defend_body INT NOT NULL DEFAULT 0,
    t_smash_fh INT NOT NULL DEFAULT 0,
    t_smash_bh INT NOT NULL DEFAULT 0,
    t_dropshot_fh INT NOT NULL DEFAULT 0,
    t_dropshot_bh INT NOT NULL DEFAULT 0,
    t_lob_fh INT NOT NULL DEFAULT 0,
    t_lob_bh INT NOT NULL DEFAULT 0,
    total_shots INT NOT NULL DEFAULT 0,
    det_technique VARCHAR(20) NULL,
    result_type VARCHAR(20) NULL,
    position VARCHAR(20) NULL,
    created_at DATETIME NOT NULL,
    INDEX idx_entries_match (match_id, game, rally_no),
    INDEX idx_entries_result (match_id, result_type),
    CONSTRAINT fk_entries_match FOREIGN KEY (match_id) REFERENCES matches(id) ON DELETE CASCADE
) ENGINE=InnoDB;
