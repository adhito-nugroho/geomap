<?php
// Migrasi database (idempotent). Bisa dijalankan via CLI maupun browser.
//   CLI:     php migrate.php
//   Browser: http://geomap.test/migrate.php  (atau http://localhost/geomap/migrate.php)
// Menjalankan database/schema.sql lalu database/seed.php.
declare(strict_types=1);

$isCli = PHP_SAPI === 'cli';
if ($isCli) {
    // Izinkan dijalankan dari folder mana pun
    chdir(__DIR__);
}

function out(string $msg, bool $isCli): void
{
    if ($isCli) {
        echo $msg . PHP_EOL;
    } else {
        echo htmlspecialchars($msg, ENT_QUOTES, 'UTF-8') . "<br>\n";
    }
}

try {
    $cfg = require __DIR__ . '/config.php';

    // 1. Sambung TANPA dbname dulu untuk CREATE DATABASE
    $pdoRoot = new PDO(
        sprintf('mysql:host=%s;port=%s;charset=utf8mb4', $cfg['db_host'], $cfg['db_port']),
        $cfg['db_user'],
        $cfg['db_pass'],
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_EMULATE_PREPARES => false]
    );
    $dbName = $cfg['db_name'];
    // Validasi nama database (hanya huruf/angka/underscore) agar aman dari injeksi identifier
    if (!preg_match('/^[A-Za-z0-9_]+$/', $dbName)) {
        throw new RuntimeException('Nama database tidak valid.');
    }
    $pdoRoot->exec("CREATE DATABASE IF NOT EXISTS `{$dbName}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
    out("database `{$dbName}` OK", $isCli);

    // 2. Sambung ke database target
    $pdo = new PDO(
        sprintf('mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4', $cfg['db_host'], $cfg['db_port'], $dbName),
        $cfg['db_user'],
        $cfg['db_pass'],
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_EMULATE_PREPARES => false]
    );

    // 3. Eksekusi schema.sql (pecah per statement; skema ini tanpa stored procedure)
    $schemaFile = __DIR__ . '/database/schema.sql';
    if (!is_file($schemaFile)) {
        throw new RuntimeException('File database/schema.sql tidak ditemukan.');
    }
    $sql = file_get_contents($schemaFile);
    // Buang baris komentar -- agar tidak memecah split
    $lines = explode("\n", $sql);
    $clean = [];
    foreach ($lines as $ln) {
        if (preg_match('/^\s*--/', $ln)) {
            continue;
        }
        $clean[] = $ln;
    }
    $statements = array_filter(array_map('trim', explode(';', implode("\n", $clean))));
    // Catatan: DDL MySQL (CREATE TABLE) menyebabkan implicit commit,
    // jadi TIDAK dibungkus transaksi. Setiap statement dieksekusi satu per satu.
    foreach ($statements as $stmt) {
        $pdo->exec($stmt);
    }
    out('schema.sql OK (' . count($statements) . ' statement)', $isCli);

    // 4. Seed data awal
    require_once __DIR__ . '/database/seed.php';
    foreach (seed_database($pdo) as $line) {
        out($line, $isCli);
    }

    // 5. Ringkasan verifikasi
    foreach (['users', 'maps', 'layer_groups', 'layers', 'layer_classes'] as $t) {
        $n = $pdo->query("SELECT COUNT(*) FROM `{$t}`")->fetchColumn();
        out("count {$t} = {$n}", $isCli);
    }

    out('MIGRASI SELESAI', $isCli);
} catch (Throwable $e) {
    if (isset($pdo) && $pdo instanceof PDO && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    if (!$isCli) {
        http_response_code(500);
    }
    out('MIGRASI GAGAL: ' . $e->getMessage(), $isCli);
    exit(1);
}
