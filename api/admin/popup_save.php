<?php
// POST /api/admin/popup_save.php {csrf_token, layer_id, fields:[...]}
// Ganti daftar kolom yang disajikan di popup/GeoJSON (urutan = urutan array).
// Array kosong = sajikan semua kolom (kompatibel mundur).
declare(strict_types=1);
require_once __DIR__ . '/_common.php';

try {
    $in = admin_input();
    $pdo = db();
    $layer = v_layer($pdo, $in['layer_id'] ?? null);

    $fields = $in['fields'] ?? [];
    if (!is_array($fields)) {
        json_fail('Format fields tidak valid.');
    }
    if (count($fields) > 200) {
        json_fail('Maksimal 200 kolom.');
    }
    $clean = [];
    foreach ($fields as $f) {
        $f = trim((string) $f);
        if ($f === '' || mb_strlen($f) > 100) {
            json_fail('Nama kolom maksimal 100 karakter dan tak boleh kosong.');
        }
        $clean[$f] = true;
    }
    $clean = array_keys($clean);

    $pdo->beginTransaction();
    $stmt = $pdo->prepare('DELETE FROM layer_popup_fields WHERE layer_id = ?');
    $stmt->execute([(int) $layer['id']]);
    $stmt = $pdo->prepare(
        'INSERT INTO layer_popup_fields (layer_id, field, urutan) VALUES (?, ?, ?)'
    );
    foreach ($clean as $i => $f) {
        $stmt->execute([(int) $layer['id'], $f, $i + 1]);
    }
    $pdo->commit();
    json_out(['ok' => true, 'count' => count($clean)]);
} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    json_fail('Gagal menyimpan kolom popup.', 500);
}
