<?php
// GET /api/geojson.php?layer=ID -> melayani GeoJSON dari /storage/geojson/.
// - Kolom atribut di luar pilihan admin (layer_popup_fields) dibuang; file asli tak diubah.
// - Koordinat dibulatkan ke 5 desimal saat disajikan (hemat transfer).
// - Header: application/json + ETag (dari output olahan) + Cache-Control + gzip.
declare(strict_types=1);

require_once __DIR__ . '/../includes/db.php';

/** Bulatkan semua angka koordinat ke 5 desimal (rekursif, semua kedalaman + GeometryCollection). */
function round_coords(&$node): void
{
    if (!is_array($node)) {
        return;
    }
    if (isset($node['type'], $node['coordinates']) && is_array($node['coordinates'])) {
        round_pos($node['coordinates']);
        return;
    }
    if (isset($node['type']) && $node['type'] === 'GeometryCollection' && is_array($node['geometries'] ?? null)) {
        foreach ($node['geometries'] as &$g) {
            round_coords($g);
        }
        unset($g);
    }
}

function round_pos(&$c): void
{
    if (is_array($c) && isset($c[0], $c[1]) && is_numeric($c[0]) && is_numeric($c[1]) && !is_array($c[0])) {
        $c[0] = round((float) $c[0], 5);
        $c[1] = round((float) $c[1], 5);
        return;
    }
    if (is_array($c)) {
        foreach ($c as &$s) {
            round_pos($s);
        }
        unset($s);
    }
}

try {
    // Validasi id layer: hanya digit
    if (!isset($_GET['layer']) || !ctype_digit((string) $_GET['layer'])) {
        http_response_code(400);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['error' => 'Parameter layer tidak valid.']);
        exit;
    }
    $layerId = (int) $_GET['layer'];

    $pdo = db();
    $stmt = $pdo->prepare(
        'SELECT file_geojson FROM layers WHERE id = ? AND aktif = 1 LIMIT 1'
    );
    $stmt->execute([$layerId]);
    $file = $stmt->fetchColumn();
    if (!$file) {
        http_response_code(404);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['error' => 'Layer tidak ditemukan / tidak aktif.']);
        exit;
    }

    // Whitelist nama file + cegah path traversal (realpath harus di dalam storage/geojson)
    if (!preg_match('/^[a-z0-9_\-]+\.geojson$/i', $file)) {
        http_response_code(500);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['error' => 'Nama file layer tidak valid.']);
        exit;
    }
    $baseDir = realpath(__DIR__ . '/../storage/geojson');
    $path = realpath($baseDir . DIRECTORY_SEPARATOR . $file);
    if ($path === false || strpos($path, $baseDir) !== 0 || !is_file($path)) {
        http_response_code(404);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['error' => 'File GeoJSON tidak ditemukan.']);
        exit;
    }

    $gj = json_decode((string) file_get_contents($path), true);
    if (!is_array($gj) || !is_array($gj['features'] ?? null)) {
        http_response_code(500);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['error' => 'File GeoJSON rusak.']);
        exit;
    }

    // Potong kolom di luar pilihan admin (baris kosong = sajikan semua, kompatibel mundur)
    $stmt = $pdo->prepare(
        'SELECT field FROM layer_popup_fields WHERE layer_id = ? ORDER BY urutan, id'
    );
    $stmt->execute([$layerId]);
    $allowed = $stmt->fetchAll(PDO::FETCH_COLUMN);
    if (!empty($allowed)) {
        $keep = array_flip($allowed);
        foreach ($gj['features'] as &$ft) {
            if (isset($ft['properties']) && is_array($ft['properties'])) {
                $np = [];
                foreach ($allowed as $k) {
                    if (array_key_exists($k, $ft['properties'])) {
                        $np[$k] = $ft['properties'][$k];
                    }
                }
                $ft['properties'] = $np;
            }
        }
        unset($ft);
    }

    // Bulatkan koordinat ke 5 desimal
    foreach ($gj['features'] as &$ft) {
        if (isset($ft['geometry'])) {
            round_coords($ft['geometry']);
        }
    }
    unset($ft);

    $data = json_encode($gj, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    if ($data === false) {
        http_response_code(500);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['error' => 'Gagal menyusun GeoJSON.']);
        exit;
    }

    // ETag dari output olahan -> dukung 304 Not Modified
    $etag = '"' . md5($data) . '"';
    $lastMod = gmdate('D, d M Y H:i:s', filemtime($path)) . ' GMT';
    header('ETag: ' . $etag);
    header('Last-Modified: ' . $lastMod);
    if (trim($_SERVER['HTTP_IF_NONE_MATCH'] ?? '') === $etag) {
        http_response_code(304);
        exit;
    }

    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: public, max-age=3600');

    // Kompresi gzip bila klien mendukung (menghemat transfer poligon besar)
    $enc = $_SERVER['HTTP_ACCEPT_ENCODING'] ?? '';
    if (stripos($enc, 'gzip') !== false && function_exists('gzencode')) {
        header('Content-Encoding: gzip');
        echo gzencode($data, 6);
    } else {
        echo $data;
    }
} catch (Throwable $e) {
    http_response_code(500);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['error' => 'Gagal melayani GeoJSON.']);
}
