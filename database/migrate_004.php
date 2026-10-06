<?php
// Migrasi 004: kolom popup_config (JSON) per layer untuk format popup identify.
// Idempotent: aman dijalankan berulang (cek kolom dulu).
// Struktur: {"title_field":"WADMKD","fields":[{"key":"ARAHAN_NEW","label":"Arahan",
//   "visible":true,"order":1,"format":"teks","decimals":0,"unit":""}, ...]}
// Kosong/NULL = viewer memakai perilaku lama (tabel mentah minus field teknis).
//   CLI:     php database/migrate_004.php
//   Browser: http://geomap.test/database/migrate_004.php
// Instalasi baru tidak perlu ini (sudah tercakup di database/schema.sql).
declare(strict_types=1);

$isCli = PHP_SAPI === 'cli';
if ($isCli) {
    chdir(dirname(__DIR__));
}

function out4(string $msg, bool $isCli): void
{
    if ($isCli) {
        echo $msg . PHP_EOL;
    } else {
        echo htmlspecialchars($msg, ENT_QUOTES, 'UTF-8') . "<br>\n";
    }
}

try {
    $cfg = require dirname(__DIR__) . '/config.php';
    $cfg['db_host'] = $cfg['db_host'] ?? '127.0.0.1';
    $cfg['db_port'] = $cfg['db_port'] ?? '3306';
    $pdo = new PDO(
        sprintf('mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4', $cfg['db_host'], $cfg['db_port'], $cfg['db_name']),
        $cfg['db_user'],
        $cfg['db_pass'],
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_EMULATE_PREPARES => false]
    );

    $stmt = $pdo->prepare(
        "SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
         WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'layers' AND COLUMN_NAME = 'popup_config'"
    );
    $stmt->execute();
    if ((int) $stmt->fetchColumn() === 0) {
        // DDL MySQL implicit-commit: tanpa transaksi
        $pdo->exec('ALTER TABLE layers ADD COLUMN popup_config TEXT NULL DEFAULT NULL');
        out4('ALTER layers ADD popup_config OK', $isCli);
    } else {
        out4('kolom layers.popup_config sudah ada (lewati)', $isCli);
    }

    out4('MIGRASI 004 SELESAI', $isCli);
} catch (Throwable $e) {
    if (!$isCli) {
        http_response_code(500);
    }
    out4('MIGRASI 004 GAGAL: ' . $e->getMessage(), $isCli);
    exit(1);
}
