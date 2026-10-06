<?php
// POST /api/admin/popup_config.php {csrf_token, layer_id, title_field, fields:[...]}
// Simpan format popup identify per layer (kolom layers.popup_config, JSON).
// fields[]: {key, label?, visible?, order?, format?: teks|angka, decimals?, unit?}
declare(strict_types=1);
require_once __DIR__ . '/_common.php';

try {
    $in = admin_input();
    $pdo = db();
    $layer = v_layer($pdo, $in['layer_id'] ?? null);

    $title = trim((string) ($in['title_field'] ?? ''));
    if (mb_strlen($title) > 100) {
        json_fail('title_field maksimal 100 karakter.');
    }
    $fields = $in['fields'] ?? [];
    if (!is_array($fields)) {
        json_fail('Format fields tidak valid.');
    }
    if (count($fields) > 200) {
        json_fail('Maksimal 200 field.');
    }
    $clean = [];
    foreach ($fields as $f) {
        if (!is_array($f)) {
            json_fail('Format field tidak valid.');
        }
        $key = trim((string) ($f['key'] ?? ''));
        if ($key === '' || mb_strlen($key) > 100) {
            json_fail('Key field wajib 1-100 karakter.');
        }
        $label = trim((string) ($f['label'] ?? ''));
        if ($label === '') {
            $label = $key;
        }
        if (mb_strlen($label) > 150) {
            json_fail('Label maksimal 150 karakter.');
        }
        $format = strtolower(trim((string) ($f['format'] ?? 'teks')));
        if (!in_array($format, ['teks', 'angka'], true)) {
            json_fail("Format field '{$key}' harus teks atau angka.");
        }
        $dec = $f['decimals'] ?? 2;
        if (!is_numeric($dec) || (int) $dec != $dec || $dec < 0 || $dec > 10) {
            json_fail("Desimal field '{$key}' harus 0-10.");
        }
        $unit = trim((string) ($f['unit'] ?? ''));
        if (mb_strlen($unit) > 20) {
            json_fail("Satuan field '{$key}' maksimal 20 karakter.");
        }
        $clean[] = [
            'key' => $key,
            'label' => $label,
            'visible' => !empty($f['visible']),
            'order' => isset($f['order']) && is_numeric($f['order']) ? (int) $f['order'] : 0,
            'format' => $format,
            'decimals' => (int) $dec,
            'unit' => $unit,
        ];
    }

    $json = json_encode(
        ['title_field' => $title, 'fields' => $clean],
        JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
    );
    $stmt = $pdo->prepare('UPDATE layers SET popup_config = ? WHERE id = ?');
    $stmt->execute([$json, (int) $layer['id']]);
    json_out(['ok' => true, 'fields' => count($clean)]);
} catch (Throwable $e) {
    json_fail('Gagal menyimpan format popup.', 500);
}
