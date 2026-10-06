<?php
// POST /api/admin/class_auto.php {csrf_token, layer_id, field}
// Baca nilai unik field dari file GeoJSON lalu buat baris layer_classes yang belum ada
// (warna default berbeda-beda, bisa diubah admin). Peringatan bila nilai unik > 30.
declare(strict_types=1);
require_once __DIR__ . '/_common.php';

/** Warna default berbeda per indeks (HSL diputar, bukan warna legenda baku). */
function palette_color(int $i): string
{
    $h = ($i * 137) % 360;
    $s = 0.70;
    $l = 0.50;
    $c = (1 - abs(2 * $l - 1)) * $s;
    $x = $c * (1 - abs(fmod($h / 60, 2) - 1));
    $m = $l - $c / 2;
    if ($h < 60) {
        [$r, $g, $b] = [$c, $x, 0];
    } elseif ($h < 120) {
        [$r, $g, $b] = [$x, $c, 0];
    } elseif ($h < 180) {
        [$r, $g, $b] = [0, $c, $x];
    } elseif ($h < 240) {
        [$r, $g, $b] = [0, $x, $c];
    } elseif ($h < 300) {
        [$r, $g, $b] = [$x, 0, $c];
    } else {
        [$r, $g, $b] = [$c, 0, $x];
    }
    return sprintf('#%02x%02x%02x', (int) round(($r + $m) * 255), (int) round(($g + $m) * 255), (int) round(($b + $m) * 255));
}

try {
    $in = admin_input();
    $pdo = db();
    $layer = v_layer($pdo, $in['layer_id'] ?? null);
    $field = v_str($in, 'field', 100);

    $path = geojson_path((string) $layer['file_geojson']);
    if ($path === null) {
        json_fail('File GeoJSON layer belum ada. Unggah dulu via form upload.');
    }
    $gj = json_decode((string) file_get_contents($path), true);
    if (!is_array($gj) || !is_array($gj['features'] ?? null) || count($gj['features']) === 0) {
        json_fail('File GeoJSON layer kosong/rusak. Unggah ulang.');
    }

    // Kumpulkan nilai unik (string), abaikan null/kosong
    $uniq = [];
    foreach ($gj['features'] as $ft) {
        $props = $ft['properties'] ?? null;
        if (!is_array($props) || !array_key_exists($field, $props)) {
            continue;
        }
        $v = $props[$field];
        if ($v === null || $v === '') {
            continue;
        }
        if (is_bool($v)) {
            $v = $v ? 'true' : 'false';
        } elseif (!is_scalar($v)) {
            continue;
        }
        $uniq[(string) $v] = true;
    }
    $values = array_keys($uniq);
    sort($values, SORT_NATURAL | SORT_FLAG_CASE);
    if (count($values) === 0) {
        json_fail("Field '{$field}' tidak ditemukan/tidak bernilai di fitur mana pun.");
    }
    if (count($values) > 100) {
        json_fail('Nilai unik melebihi 100 (' . count($values) . '). Pilih field dengan kategori lebih sedikit.');
    }
    $warning = count($values) > 30
        ? 'Nilai unik ' . count($values) . ' melebihi 30: legenda akan sangat panjang.' : null;

    $stmtMax = $pdo->prepare('SELECT COALESCE(MAX(urutan), 0) FROM layer_classes WHERE layer_id = ?');
    $stmtMax->execute([(int) $layer['id']]);
    $urut = (int) $stmtMax->fetchColumn();
    $stmtIns = $pdo->prepare(
        'INSERT IGNORE INTO layer_classes (layer_id, nilai, label, warna, outline_warna, urutan)
         VALUES (?, ?, ?, ?, ?, ?)'
    );
    $added = 0;
    foreach ($values as $i => $v) {
        $stmtIns->execute([(int) $layer['id'], $v, $v, palette_color($i), '#ffffff', $urut + $i + 1]);
        $added += $stmtIns->rowCount();
    }

    $stmt = $pdo->prepare(
        'SELECT id, nilai, label, warna, outline_warna, urutan
         FROM layer_classes WHERE layer_id = ? ORDER BY urutan, id'
    );
    $stmt->execute([(int) $layer['id']]);
    json_out(['ok' => true, 'added' => $added, 'total_values' => count($values), 'warning' => $warning, 'classes' => $stmt->fetchAll()]);
} catch (Throwable $e) {
    json_fail('Gagal membuat kelas otomatis.', 500);
}
