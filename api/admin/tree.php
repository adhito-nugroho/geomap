<?php
// GET /api/admin/tree.php -> tree group/layer LENGKAP untuk admin (termasuk nonaktif
// dan file_geojson). Butuh sesi admin. Viewer publik tetap memakai /api/map.php.
declare(strict_types=1);

require_once __DIR__ . '/../../includes/auth.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

if (!is_admin()) {
    http_response_code(403);
    echo json_encode(['error' => 'Akses ditolak: butuh peran admin.']);
    exit;
}

try {
    $pdo = db();

    $stmtGroups = $pdo->prepare(
        'SELECT id, nama, urutan FROM layer_groups WHERE map_id = 1 ORDER BY urutan, id'
    );
    $stmtGroups->execute();
    $stmtLayers = $pdo->prepare(
        'SELECT id, nama, tipe_geom, file_geojson, style_mode, style_field,
                opacity_default, visible_default, aktif, min_zoom, urutan,
                outline_color, outline_weight, outline_opacity, fill_opacity, fill_enabled,
                popup_config
         FROM layers WHERE group_id = ? ORDER BY urutan, id'
    );
    $stmtClasses = $pdo->prepare(
        'SELECT id, nilai, label, warna, outline_warna, urutan
         FROM layer_classes WHERE layer_id = ? ORDER BY urutan, id'
    );

    $tree = [];
    foreach ($stmtGroups->fetchAll() as $g) {
        $stmtLayers->execute([(int) $g['id']]);
        $layers = [];
        foreach ($stmtLayers->fetchAll() as $l) {
            $stmtClasses->execute([(int) $l['id']]);
            $l['visible_default'] = (int) $l['visible_default'];
            $l['opacity_default'] = (int) $l['opacity_default'];
            $l['aktif'] = (int) $l['aktif'];
            $l['min_zoom'] = (int) $l['min_zoom'];
            $l['outline_weight'] = (float) $l['outline_weight'];
            $l['outline_opacity'] = (float) $l['outline_opacity'];
            $l['fill_opacity'] = (float) $l['fill_opacity'];
            $l['fill_enabled'] = (int) $l['fill_enabled'];
            $pc = is_string($l['popup_config'] ?? null) ? json_decode($l['popup_config'], true) : null;
            $l['popup_config'] = (is_array($pc) && is_array($pc['fields'] ?? null)) ? $pc : null;
            $l['classes'] = $stmtClasses->fetchAll();
            $layers[] = $l;
        }
        $tree[] = ['id' => (int) $g['id'], 'nama' => $g['nama'], 'layers' => $layers];
    }
    echo json_encode(['groups' => $tree], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Gagal memuat tree admin.']);
}
