<?php
// Migrasi 002: min_zoom per layer + tabel layer_popup_fields.
// Idempotent: aman dijalankan berulang (cek kolom/tabel dulu).
//   CLI:     php database/migrate_002.php
//   Browser: http://geomap.test/database/migrate_002.php
// Instalasi baru tidak perlu ini (sudah tercakup di database/schema.sql).
declare(strict_types=1);

$isCli = PHP_SAPI === 'cli';
if ($isCli) {
    chdir(dirname(__DIR__));
}

function out2(string $msg, bool $isCli): void
{
    if ($isCli) {
        echo $msg . PHP_EOL;
    } else {
        echo htmlspecialchars($msg, ENT_QUOTES, 'UTF-8') . "<br>\n";
    }
}

try {
    $cfg = require dirname(__DIR__) . '/config.php';
    $pdo = new PDO(
        sprintf('mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4', $cfg['db_host'], $cfg['db_port'], $cfg['db_name']),
        $cfg['db_user'],
        $cfg['db_pass'],
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_EMULATE_PREPARES => false]
    );

    // 1. Kolom layers.min_zoom (0 = selalu dimuat)
    $stmt = $pdo->prepare(
        "SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
         WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'layers' AND COLUMN_NAME = 'min_zoom'"
    );
    $stmt->execute();
    if ((int) $stmt->fetchColumn() === 0) {
        // DDL MySQL implicit-commit: tanpa transaksi (lihat migrate.php)
        $pdo->exec('ALTER TABLE layers ADD COLUMN min_zoom TINYINT UNSIGNED NOT NULL DEFAULT 0');
        out2('ALTER layers ADD min_zoom OK', $isCli);
    } else {
        out2('kolom layers.min_zoom sudah ada (lewati)', $isCli);
    }

    // 2. Tabel layer_popup_fields (CREATE IF NOT EXISTS = idempotent)
    $pdo->exec(
        "CREATE TABLE IF NOT EXISTS layer_popup_fields (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            layer_id INT UNSIGNED NOT NULL,
            field VARCHAR(100) NOT NULL,
            urutan INT NOT NULL DEFAULT 0,
            CONSTRAINT fk_popup_layer FOREIGN KEY (layer_id)
                REFERENCES layers (id) ON DELETE CASCADE ON UPDATE CASCADE,
            UNIQUE KEY uq_popup_layer_field (layer_id, field),
            KEY idx_popup_layer_urutan (layer_id, urutan)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
    );
    out2('tabel layer_popup_fields OK', $isCli);

    out2('MIGRASI 002 SELESAI', $isCli);
} catch (Throwable $e) {
    if (!$isCli) {
        http_response_code(500);
    }
    out2('MIGRASI 002 GAGAL: ' . $e->getMessage(), $isCli);
    exit(1);
}
