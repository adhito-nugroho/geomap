<?php
// POST /api/admin/layer_delete.php {csrf_token, id}
// Hapus layer (+kelas via kaskade). File GeoJSON ikut dihapus HANYA bila tak dipakai layer lain.
declare(strict_types=1);
require_once __DIR__ . '/_common.php';

try {
    $in = admin_input();
    $pdo = db();
    $id = v_int($in, 'id', 1, PHP_INT_MAX);
    $layer = v_layer($pdo, $id);
    $file = (string) $layer['file_geojson'];

    $stmt = $pdo->prepare('DELETE FROM layers WHERE id = ?');
    $stmt->execute([$id]);

    $deletedFile = false;
    $stmt = $pdo->prepare('SELECT COUNT(*) FROM layers WHERE file_geojson = ?');
    $stmt->execute([$file]);
    if ((int) $stmt->fetchColumn() === 0 && ($p = geojson_path($file)) !== null) {
        @unlink($p);
        $deletedFile = true;
    }
    json_out(['ok' => true, 'deleted_file' => $deletedFile]);
} catch (Throwable $e) {
    json_fail('Gagal menghapus layer.', 500);
}
