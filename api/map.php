<?php
// GET /api/map.php?id=1 -> config peta + tree group/layer + kelas legenda.
// Semua warna/kelas berasal dari database; JS dilarang meng-hardcode.
declare(strict_types=1);

require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/helpers.php'; // untuk hidden_fields() (popup identify)

header('Content-Type: application/json; charset=utf-8');
// Config bisa berubah dari admin: jangan cache di browser
header('Cache-Control: no-store');

try {
    // Validasi id peta (default 1)
    $mapId = 1;
    if (isset($_GET['id'])) {
        if (!ctype_digit((string) $_GET['id'])) {
            http_response_code(400);
            echo json_encode(['error' => 'Parameter id tidak valid.']);
            exit;
        }
        $mapId = (int) $_GET['id'];
    }

    $pdo = db();

    $stmtMap = $pdo->prepare(
        'SELECT id, judul, center_lat, center_lng, zoom, basemap_default
         FROM maps WHERE id = ? LIMIT 1'
    );
    $stmtMap->execute([$mapId]);
    $map = $stmtMap->fetch();
    if (!$map) {
        http_response_code(404);
        echo json_encode(['error' => 'Peta tidak ditemukan.']);
        exit;
    }

    // Groups milik peta, terurut
    $stmtGroups = $pdo->prepare(
        'SELECT id, nama, urutan FROM layer_groups WHERE map_id = ? ORDER BY urutan, id'
    );
    $stmtGroups->execute([$mapId]);
    $groups = $stmtGroups->fetchAll();

    // Layers aktif per group, terurut
    $stmtLayers = $pdo->prepare(
        'SELECT id, nama, tipe_geom, style_mode, style_field,
                opacity_default, visible_default, min_zoom, urutan,
                outline_color, outline_weight, outline_opacity, fill_opacity, fill_enabled,
                popup_config
         FROM layers WHERE group_id = ? AND aktif = 1 ORDER BY urutan, id'
    );
    // Kelas legenda per layer, terurut
    $stmtClasses = $pdo->prepare(
        'SELECT nilai, label, warna, outline_warna, urutan
         FROM layer_classes WHERE layer_id = ? ORDER BY urutan, id'
    );

    $tree = [];
    foreach ($groups as $g) {
        $stmtLayers->execute([(int) $g['id']]);
        $layers = [];
        foreach ($stmtLayers->fetchAll() as $l) {
            $stmtClasses->execute([(int) $l['id']]);
            $l['visible_default'] = (int) $l['visible_default'];
            $l['opacity_default'] = (int) $l['opacity_default'];
            $l['min_zoom'] = (int) $l['min_zoom'];
            $l['outline_weight'] = (float) $l['outline_weight'];
            $l['outline_opacity'] = (float) $l['outline_opacity'];
            $l['fill_opacity'] = (float) $l['fill_opacity'];
            $l['fill_enabled'] = (int) $l['fill_enabled'];
            // popup_config: teruskan hanya bila JSON valid berisi daftar fields
            $pc = is_string($l['popup_config'] ?? null) ? json_decode($l['popup_config'], true) : null;
            $l['popup_config'] = (is_array($pc) && is_array($pc['fields'] ?? null)) ? $pc : null;
            $l['classes'] = $stmtClasses->fetchAll();
            $layers[] = $l;
        }
        $tree[] = [
            'id'     => (int) $g['id'],
            'nama'   => $g['nama'],
            'layers' => $layers,
        ];
    }

    echo json_encode([
        'map'    => [
            'id'              => (int) $map['id'],
            'judul'           => $map['judul'],
            'center_lat'      => (float) $map['center_lat'],
            'center_lng'      => (float) $map['center_lng'],
            'zoom'            => (int) $map['zoom'],
            'basemap_default' => $map['basemap_default'],
        ],
        'groups' => $tree,
        // Daftar field teknis yang disembunyikan di popup identify (dikonfigurasi di includes/helpers.php)
        'hidden_fields' => hidden_fields(),
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
} catch (Throwable $e) {
    http_response_code(500);
    // Pesan error generik agar tidak membocorkan detail server
    echo json_encode(['error' => 'Gagal memuat konfigurasi peta.']);
}
