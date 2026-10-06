<?php
// POST /api/admin/layer_upload.php (FormData: csrf_token, layer_id, file)
// Validasi: ekstensi, MIME, ukuran (tolak >20 MB, peringatan >5 MB), struktur GeoJSON,
// koordinat [lng,lat] dalam rentang wajar. Nama file diacak. Kembalikan jumlah fitur + bbox.
declare(strict_types=1);
require_once __DIR__ . '/_common.php';

const UPLOAD_MAX_BYTES = 20 * 1024 * 1024; // tolak di atas ini
const UPLOAD_WARN_BYTES = 5 * 1024 * 1024;  // peringatan di atas ini

try {
    $in = admin_input();
    $pdo = db();
    $layer = v_layer($pdo, $in['layer_id'] ?? null);
    $layerId = (int) $layer['id'];

    if (!isset($_FILES['file']) || !is_array($_FILES['file'])) {
        json_fail('File tidak ditemukan dalam request.');
    }
    $f = $_FILES['file'];
    if (($f['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
        $msgs = [
            UPLOAD_ERR_INI_SIZE => 'Ukuran melebihi batas server (upload_max_filesize).',
            UPLOAD_ERR_FORM_SIZE => 'Ukuran melebihi batas form.',
            UPLOAD_ERR_PARTIAL => 'File terunggah sebagian, coba lagi.',
            UPLOAD_ERR_NO_FILE => 'Tidak ada file yang dipilih.',
        ];
        json_fail($msgs[$f['error']] ?? 'Gagal membaca file unggahan.');
    }
    $size = (int) ($f['size'] ?? 0);
    if ($size <= 0) {
        json_fail('File kosong.');
    }
    if ($size > UPLOAD_MAX_BYTES) {
        json_fail('File melebihi 20 MB. Sederhanakan dulu (lihat tools Fase 5 / mapshaper).', 413);
    }

    // Ekstensi whitelist
    $ext = strtolower(pathinfo((string) $f['name'], PATHINFO_EXTENSION));
    if (!in_array($ext, ['geojson', 'json'], true)) {
        json_fail('Ekstensi harus .geojson atau .json.');
    }
    // MIME: finfo sebagai sinyal tambahan (browser sering mengirim text/plain/octet-stream
    // untuk .geojson, jadi validasi isi yang menentukan; executable/HTML ditolak tegas)
    $mime = '';
    if (function_exists('finfo_open')) {
        $fi = finfo_open(FILEINFO_MIME_TYPE);
        if ($fi) {
            $mime = strtolower((string) finfo_file($fi, $f['tmp_name']));
            finfo_close($fi);
        }
    }
    if ($mime !== '' && (
        stripos($mime, 'php') !== false || stripos($mime, 'html') !== false
        || stripos($mime, 'executable') !== false || stripos($mime, 'script') !== false
    )) {
        json_fail("Tipe file tidak diizinkan (terdeteksi: {$mime}).");
    }

    $raw = file_get_contents($f['tmp_name']);
    if ($raw === false || $raw === '') {
        json_fail('File tidak dapat dibaca.');
    }
    $gj = json_decode($raw, true);
    if (!is_array($gj) || ($gj['type'] ?? '') !== 'FeatureCollection' || !isset($gj['features']) || !is_array($gj['features'])) {
        json_fail('Bukan GeoJSON FeatureCollection yang valid.');
    }
    if (count($gj['features']) === 0) {
        json_fail('FeatureCollection kosong (0 fitur).');
    }

    // Validasi geometri + rentang koordinat + hitung bbox
    $allowedGeom = ['Point', 'MultiPoint', 'LineString', 'MultiLineString', 'Polygon', 'MultiPolygon'];
    $xs = [];
    $ys = [];
    $checked = 0;
    $walk = function ($c) use (&$xs, &$ys, &$checked, &$walk) {
        if (is_array($c) && isset($c[0], $c[1]) && is_numeric($c[0]) && is_numeric($c[1]) && !is_array($c[0])) {
            $lng = (float) $c[0];
            $lat = (float) $c[1];
            // Wajar global; data dinas umumnya EPSG:4326 Indonesia (lng 90-145, lat -12-8).
            // Toleransi 0,001° untuk debu float pada data WGS84 asli (mis. 180.00000000000006).
            if ($lng < -180.001 || $lng > 180.001 || $lat < -90.001 || $lat > 90.001) {
                json_fail("Koordinat di luar rentang wajar: [{$lng}, {$lat}]. Pastikan [lng, lat] EPSG:4326.");
            }
            $xs[] = $lng;
            $ys[] = $lat;
            $checked++;
            return;
        }
        if (is_array($c)) {
            foreach ($c as $s) {
                $walk($s);
            }
        }
    };
    foreach ($gj['features'] as $i => $ft) {
        if (!is_array($ft) || ($ft['type'] ?? '') !== 'Feature' || !is_array($ft['geometry'] ?? null)) {
            json_fail('Fitur ke-' . ($i + 1) . ' bukan Feature bergeometri.');
        }
        if (!in_array($ft['geometry']['type'] ?? '', $allowedGeom, true)) {
            json_fail('Tipe geometri fitur ke-' . ($i + 1) . ' tidak didukung.');
        }
        $walk($ft['geometry']['coordinates'] ?? null);
    }
    if ($checked === 0) {
        json_fail('Tidak ada koordinat valid di dalam file.');
    }

    // Simpan dengan nama acak; hapus file lama bila tak dipakai layer lain
    $newName = bin2hex(random_bytes(8)) . '.geojson';
    $dest = realpath(__DIR__ . '/../../storage/geojson');
    if ($dest === false) {
        json_fail('Folder penyimpanan tidak ditemukan.', 500);
    }
    $destPath = $dest . DIRECTORY_SEPARATOR . $newName;
    if (!move_uploaded_file($f['tmp_name'], $destPath)) {
        json_fail('Gagal menyimpan file ke storage.', 500);
    }
    @chmod($destPath, 0644);

    $oldFile = (string) $layer['file_geojson'];
    $stmt = $pdo->prepare('UPDATE layers SET file_geojson = ? WHERE id = ?');
    $stmt->execute([$newName, $layerId]);

    $stmt = $pdo->prepare('SELECT COUNT(*) FROM layers WHERE file_geojson = ? AND id <> ?');
    $stmt->execute([$oldFile, $layerId]);
    if ((int) $stmt->fetchColumn() === 0 && ($p = geojson_path($oldFile)) !== null) {
        @unlink($p);
    }

    // Statistik lengkap + peringatan bertingkat (kuning >2MB, oranye >5MB, merah >10MB)
    $stats = geojson_stats($gj, $size, substr($raw, 0, 500000));

    // Daftar properti fitur pertama untuk pemilih style_field
    $firstProps = $gj['features'][0]['properties'] ?? [];
    $props = is_array($firstProps) ? array_values(array_filter(array_keys($firstProps), 'is_string')) : [];

    json_out([
        'ok' => true,
        'file' => $newName,
        'size_bytes' => $size,
        'features' => $stats['features'],
        'vertices' => $stats['vertices'],
        'avg_precision' => $stats['avg_precision'],
        'bbox' => $stats['bbox'],
        'columns' => $stats['columns'],
        'size_level' => $stats['size_level'],
        'size_message' => $stats['size_message'],
        'properties' => $props,
        // Kompatibel mundur dengan UI lama
        'warning' => $stats['size_message'],
    ]);
} catch (Throwable $e) {
    json_fail('Gagal mengunggah GeoJSON.', 500);
}
