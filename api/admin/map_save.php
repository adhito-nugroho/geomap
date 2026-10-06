<?php
// POST /api/admin/map_save.php -> ubah pengaturan peta id=1.
declare(strict_types=1);
require_once __DIR__ . '/_common.php';

try {
    $in = admin_input();
    $pdo = db();

    $judul = v_str($in, 'judul', 200);
    $lat = $in['center_lat'] ?? null;
    $lng = $in['center_lng'] ?? null;
    if (!is_numeric($lat) || $lat < -90 || $lat > 90) {
        json_fail('center_lat harus angka -90 sampai 90.');
    }
    if (!is_numeric($lng) || $lng < -180 || $lng > 180) {
        json_fail('center_lng harus angka -180 sampai 180.');
    }
    $zoom = v_int($in, 'zoom', 0, 19);
    $base = strtolower(trim((string) ($in['basemap_default'] ?? '')));
    if (!in_array($base, ['osm', 'esri', 'hot'], true)) {
        json_fail('basemap_default harus osm, esri, atau hot.');
    }

    $stmt = $pdo->prepare(
        'UPDATE maps SET judul = ?, center_lat = ?, center_lng = ?, zoom = ?, basemap_default = ?
         WHERE id = 1'
    );
    $stmt->execute([$judul, (float) $lat, (float) $lng, $zoom, $base]);
    json_out(['ok' => true]);
} catch (Throwable $e) {
    json_fail('Gagal menyimpan pengaturan peta.', 500);
}
