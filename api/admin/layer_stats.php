<?php
// GET /api/admin/layer_stats.php?layer_id=N -> statistik file GeoJSON layer saat ini
// (ukuran, fitur, vertex, presisi, bbox, kolom + kontribusi ukuran, peringatan
// bertingkat) + daftar kolom popup yang sedang dipilih. Butuh sesi admin.
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
    require_once __DIR__ . '/_common.php';
    $pdo = db();

    $layerId = $_GET['layer_id'] ?? null;
    if (!is_numeric($layerId)) {
        http_response_code(400);
        echo json_encode(['error' => 'Layer tidak valid.']);
        exit;
    }
    // v_layer() memanggil json_fail (format admin) bila tak ada
    $layer = v_layer($pdo, $layerId);

    $path = geojson_path((string) $layer['file_geojson']);
    if ($path === null) {
        http_response_code(404);
        echo json_encode(['error' => 'File GeoJSON layer belum ada. Unggah dulu.']);
        exit;
    }
    $size = filesize($path);
    $raw = (string) file_get_contents($path);
    $gj = json_decode($raw, true);
    if (!is_array($gj) || !is_array($gj['features'] ?? null)) {
        http_response_code(500);
        echo json_encode(['error' => 'File GeoJSON rusak. Unggah ulang.']);
        exit;
    }

    $stats = geojson_stats($gj, (int) $size, substr($raw, 0, 500000));

    $stmt = $pdo->prepare(
        'SELECT field FROM layer_popup_fields WHERE layer_id = ? ORDER BY urutan, id'
    );
    $stmt->execute([(int) $layer['id']]);
    $stats['popup_selected'] = $stmt->fetchAll(PDO::FETCH_COLUMN);

    echo json_encode($stats, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Gagal menghitung statistik.']);
}
