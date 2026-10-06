<?php
// Migrasi 003: kolom style per layer (outline + fill).
// Idempotent: aman dijalankan berulang (cek kolom dulu).
// Default dipilih agar tampilan layer yang sudah ada TIDAK berubah:
// outline ikut kelas, weight 1, opacity 1, fill 0.65, fill on.
//   CLI:     php database/migrate_003.php
//   Browser: http://geomap.test/database/migrate_003.php
// Instalasi baru tidak perlu ini (sudah tercakup di database/schema.sql).
declare(strict_types=1);

$isCli = PHP_SAPI === 'cli';
if ($isCli) {
    chdir(dirname(__DIR__));
}

function out3(string $msg, bool $isCli): void
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

    // kolom => definisi ADD COLUMN (DDL implicit-commit: tanpa transaksi)
    $cols = [
        'outline_color'   => 'CHAR(7) NULL DEFAULT NULL',
        'outline_weight'  => 'DECIMAL(3,1) NOT NULL DEFAULT 1.0',
        'outline_opacity' => 'DECIMAL(3,2) NOT NULL DEFAULT 1.00',
        'fill_opacity'    => 'DECIMAL(3,2) NOT NULL DEFAULT 0.65',
        'fill_enabled'    => 'TINYINT(1) NOT NULL DEFAULT 1',
    ];
    $stmt = $pdo->prepare(
        "SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS
         WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'layers'"
    );
    $stmt->execute();
    $have = array_flip($stmt->fetchAll(PDO::FETCH_COLUMN));
    foreach ($cols as $name => $def) {
        if (isset($have[$name])) {
            out3("kolom layers.{$name} sudah ada (lewati)", $isCli);
        } else {
            $pdo->exec("ALTER TABLE layers ADD COLUMN {$name} {$def}");
            out3("ALTER layers ADD {$name} OK", $isCli);
        }
    }

    out3('MIGRASI 003 SELESAI', $isCli);
} catch (Throwable $e) {
    if (!$isCli) {
        http_response_code(500);
    }
    out3('MIGRASI 003 GAGAL: ' . $e->getMessage(), $isCli);
    exit(1);
}
