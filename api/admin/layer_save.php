<?php
// POST /api/admin/layer_save.php -> tambah/ubah metadata layer.
declare(strict_types=1);
require_once __DIR__ . '/_common.php';

try {
    $in = admin_input();
    $pdo = db();

    $groupId = v_group($pdo, $in['group_id'] ?? null);
    $nama = v_str($in, 'nama', 100);
    $tipe = strtolower(trim((string) ($in['tipe_geom'] ?? 'polygon')));
    if (!in_array($tipe, ['polygon', 'line', 'point'], true)) {
        json_fail('tipe_geom harus polygon, line, atau point.');
    }
    $opacity = v_int($in, 'opacity_default', 0, 100);
    $visible = v_int($in, 'visible_default', 0, 1);
    $aktif = v_int($in, 'aktif', 0, 1);
    $minZoom = v_int($in, 'min_zoom', 0, 19);
    $mode = strtolower(trim((string) ($in['style_mode'] ?? 'single')));
    if (!in_array($mode, ['single', 'categorized'], true)) {
        json_fail('style_mode harus single atau categorized.');
    }
    $field = null;
    if ($mode === 'categorized') {
        $field = v_str($in, 'style_field', 100);
    }

    if (!empty($in['id'])) {
        $id = v_int($in, 'id', 1, PHP_INT_MAX);
        v_layer($pdo, $id); // pastikan milik map 1
        $stmt = $pdo->prepare(
            'UPDATE layers SET group_id = ?, nama = ?, tipe_geom = ?, style_mode = ?, style_field = ?,
             opacity_default = ?, visible_default = ?, aktif = ?, min_zoom = ? WHERE id = ?'
        );
        $stmt->execute([$groupId, $nama, $tipe, $mode, $field, $opacity, $visible, $aktif, $minZoom, $id]);
    } else {
        $stmt = $pdo->prepare('SELECT COALESCE(MAX(urutan), 0) + 1 FROM layers WHERE group_id = ?');
        $stmt->execute([$groupId]);
        $urut = (int) $stmt->fetchColumn();
        // File placeholder: admin wajib upload GeoJSON setelahnya (atau timpa via SQL lama)
        $stmt = $pdo->prepare(
            'INSERT INTO layers (group_id, nama, tipe_geom, file_geojson, style_mode, style_field,
             opacity_default, visible_default, urutan, aktif, min_zoom)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
        );
        $stmt->execute([$groupId, $nama, $tipe, 'belum-ada.geojson', $mode, $field, $opacity, $visible, $urut, $aktif, $minZoom]);
        $id = (int) $pdo->lastInsertId();
    }

    // Mode single: pastikan satu baris kelas agar legenda viewer tidak kosong
    if ($mode === 'single') {
        $stmt = $pdo->prepare(
            "INSERT IGNORE INTO layer_classes (layer_id, nilai, label, warna, outline_warna, urutan)
             VALUES (?, '__single__', ?, '#3388ff', '#ffffff', 1)"
        );
        $stmt->execute([$id, $nama]);
    }
    json_out(['ok' => true, 'id' => $id]);
} catch (PDOException $e) {
    if (($e->errorInfo[1] ?? 0) === 1062) {
        json_fail('Nama layer sudah ada di group ini.', 409);
    }
    json_fail('Gagal menyimpan layer.', 500);
} catch (Throwable $e) {
    json_fail('Gagal menyimpan layer.', 500);
}
