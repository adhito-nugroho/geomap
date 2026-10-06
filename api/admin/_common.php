<?php
// Guard bersama semua endpoint /api/admin/*.php (JSON):
// metode POST + sesi admin + token CSRF. Semua query di endpoint memakai PDO prepared statement.
declare(strict_types=1);

require_once __DIR__ . '/../../includes/auth.php';

function json_out(array $data, int $code = 200): void
{
    http_response_code($code);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function json_fail(string $msg, int $code = 400): void
{
    json_out(['error' => $msg], $code);
}

/** Validasi awal dan kembalikan input (mendukung body JSON dan form-data upload). */
function admin_input(): array
{
    if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
        json_fail('Metode harus POST.', 405);
    }
    if (!is_admin()) {
        json_fail('Akses ditolak: butuh peran admin.', 403);
    }
    $ct = (string) ($_SERVER['CONTENT_TYPE'] ?? '');
    if (stripos($ct, 'application/json') !== false) {
        $in = json_decode((string) file_get_contents('php://input'), true);
        if (!is_array($in)) {
            json_fail('Body JSON tidak valid.');
        }
    } else {
        $in = $_POST; // dipakai endpoint upload (FormData)
    }
    if (!csrf_check($in['csrf_token'] ?? null)) {
        json_fail('Token keamanan (CSRF) tidak valid.', 403);
    }
    return $in;
}

/** Ambil string trim dengan batas panjang; gagal bila kosong/di luar batas. */
function v_str(array $in, string $key, int $max, bool $required = true): ?string
{
    $v = isset($in[$key]) ? trim((string) $in[$key]) : '';
    if ($v === '') {
        if ($required) {
            json_fail("Field '{$key}' wajib diisi.");
        }
        return null;
    }
    if (mb_strlen($v) > $max) {
        json_fail("Field '{$key}' maksimal {$max} karakter.");
    }
    return $v;
}

/** Ambil integer dalam rentang; gagal bila tidak valid. */
function v_int(array $in, string $key, int $min, int $max): int
{
    $v = $in[$key] ?? null;
    if (is_bool($v)) {
        $v = $v ? 1 : 0;
    }
    if (!is_numeric($v) || (int) $v != $v) {
        json_fail("Field '{$key}' harus bilangan bulat.");
    }
    $v = (int) $v;
    if ($v < $min || $v > $max) {
        json_fail("Field '{$key}' harus antara {$min} dan {$max}.");
    }
    return $v;
}

/** Validasi warna heksadesimal #rrggbb. */
function v_color(array $in, string $key): string
{
    $v = trim((string) ($in[$key] ?? ''));
    if (!preg_match('/^#[0-9a-fA-F]{6}$/', $v)) {
        json_fail("Field '{$key}' harus warna heksadesimal (contoh #1d4ed8).");
    }
    return strtolower($v);
}

/** Pastikan group milik map 1; kembalikan id group. */
function v_group(PDO $pdo, $groupId): int
{
    if (!is_numeric($groupId)) {
        json_fail('Group tidak valid.');
    }
    $stmt = $pdo->prepare('SELECT id FROM layer_groups WHERE id = ? AND map_id = 1 LIMIT 1');
    $stmt->execute([(int) $groupId]);
    if (!$stmt->fetchColumn()) {
        json_fail('Group tidak ditemukan.', 404);
    }
    return (int) $groupId;
}

/** Pastikan layer (aktif/nonaktif) milik map 1; kembalikan baris layer. */
function v_layer(PDO $pdo, $layerId): array
{
    if (!is_numeric($layerId)) {
        json_fail('Layer tidak valid.');
    }
    $stmt = $pdo->prepare(
        'SELECT l.* FROM layers l JOIN layer_groups g ON g.id = l.group_id
         WHERE l.id = ? AND g.map_id = 1 LIMIT 1'
    );
    $stmt->execute([(int) $layerId]);
    $row = $stmt->fetch();
    if (!$row) {
        json_fail('Layer tidak ditemukan.', 404);
    }
    return $row;
}

/** Resolve path file GeoJSON dengan proteksi traversal (mirror api/geojson.php). */
function geojson_path(string $file): ?string
{
    if (!preg_match('/^[a-z0-9_\-]+\.geojson$/i', $file)) {
        return null;
    }
    $base = realpath(__DIR__ . '/../../storage/geojson');
    if ($base === false) {
        return null;
    }
    $path = realpath($base . DIRECTORY_SEPARATOR . $file);
    if ($path === false || strpos($path, $base) !== 0 || !is_file($path)) {
        return null;
    }
    return $path;
}

/** Tingkat peringatan ukuran file (byte). */
function size_level(int $bytes): array
{
    if ($bytes > 10 * 1048576) {
        return ['merah', 'File melebihi 10 MB: viewer akan lambat. Sederhanakan geometri (simplify) atau pecah menjadi beberapa layer.'];
    }
    if ($bytes > 5 * 1048576) {
        return ['oranye', 'File melebihi 5 MB: pertimbangkan menyederhanakan geometri.'];
    }
    if ($bytes > 2 * 1048576) {
        return ['kuning', 'File melebihi 2 MB: pantau kecepatan muat di viewer.'];
    }
    return ['ok', null];
}

/**
 * Statistik GeoJSON dari array ter-decode + ukuran file.
 * Kembalikan: features, vertices, avg_precision (sampel teks), bbox,
 * columns [{name, bytes, pct}], size_level, size_message.
 */
function geojson_stats(array $gj, int $sizeBytes, string $rawSample): array
{
    $features = $gj['features'];
    $xs = [];
    $ys = [];
    $vertices = 0;
    $colBytes = [];
    $walk = function ($c) use (&$xs, &$ys, &$vertices, &$walk) {
        if (is_array($c) && isset($c[0], $c[1]) && is_numeric($c[0]) && is_numeric($c[1]) && !is_array($c[0])) {
            $xs[] = (float) $c[0];
            $ys[] = (float) $c[1];
            $vertices++;
            return;
        }
        if (is_array($c)) {
            foreach ($c as $s) {
                $walk($s);
            }
        }
    };
    foreach ($features as $ft) {
        if (isset($ft['geometry']['coordinates'])) {
            $walk($ft['geometry']['coordinates']);
        }
        $props = $ft['properties'] ?? null;
        if (is_array($props)) {
            foreach ($props as $k => $v) {
                if (!is_string($k)) {
                    continue;
                }
                // Kontribusi ukuran ≈ byte JSON nilai + nama kunci
                $colBytes[$k] = ($colBytes[$k] ?? 0) + strlen((string) json_encode($v)) + strlen($k);
            }
        }
    }
    // Presisi desimal rata-rata dari sampel teks mentah (mencerminkan presisi file)
    $precSum = 0;
    $precN = 0;
    if (preg_match_all('/-?\d+\.(\d+)/', $rawSample, $m)) {
        foreach ($m[1] as $frac) {
            $precSum += strlen($frac);
            $precN++;
        }
    }
    $cols = [];
    foreach ($colBytes as $name => $bytes) {
        $cols[] = [
            'name' => $name,
            'bytes' => $bytes,
            'pct' => $sizeBytes > 0 ? round($bytes / $sizeBytes * 100, 1) : 0,
        ];
    }
    usort($cols, static fn($a, $b) => $b['bytes'] <=> $a['bytes']);
    [$level, $msg] = size_level($sizeBytes);
    return [
        'features' => count($features),
        'vertices' => $vertices,
        'avg_precision' => $precN > 0 ? round($precSum / $precN, 2) : 0,
        'bbox' => empty($xs) ? null : [
            'minLng' => min($xs), 'minLat' => min($ys),
            'maxLng' => max($xs), 'maxLat' => max($ys),
        ],
        'columns' => $cols,
        'size_bytes' => $sizeBytes,
        'size_level' => $level,
        'size_message' => $msg,
    ];
}
