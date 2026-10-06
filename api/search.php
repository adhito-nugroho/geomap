<?php
// GET /api/search.php?q=... -> proxy pencarian Nominatim, dibatasi untuk Indonesia.
// Rate limit sederhana: jeda minimal antar request per sesi (kebijakan Nominatim: maks 1 req/detik).
declare(strict_types=1);

require_once __DIR__ . '/../includes/helpers.php'; // session + csrf tidak dipakai di sini, hanya session

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

try {
    $q = trim((string) ($_GET['q'] ?? ''));
    if (mb_strlen($q) < 3 || mb_strlen($q) > 200) {
        http_response_code(400);
        echo json_encode(['error' => 'Kata kunci minimal 3 dan maksimal 200 karakter.']);
        exit;
    }

    // Rate limit per sesi: minimal 1100 ms antar request
    $now = (int) (microtime(true) * 1000);
    $last = (int) ($_SESSION['nominatim_last_ms'] ?? 0);
    if ($now - $last < 1100) {
        http_response_code(429);
        header('Retry-After: 1');
        echo json_encode(['error' => 'Terlalu cepat. Tunggu sebentar lalu coba lagi.']);
        exit;
    }
    $_SESSION['nominatim_last_ms'] = $now;

    $cfg = require __DIR__ . '/../config.php';
    $url = 'https://nominatim.openstreetmap.org/search?'
        . http_build_query([
            'format'          => 'jsonv2',
            'q'               => $q,
            'countrycodes'    => 'id', // batasi Indonesia
            'limit'           => 8,
            'addressdetails'  => 1,
            'accept-language' => 'id',
        ]);

    $ctx = stream_context_create([
        'http' => [
            'method'  => 'GET',
            'header'  => 'User-Agent: ' . ($cfg['nominatim_user_agent'] ?? 'WebGIS-Perhutanan-Sosial/1.0') . "\r\n"
                       . "Accept: application/json\r\n",
            'timeout' => 8,
        ],
    ]);
    $upstream = @file_get_contents($url, false, $ctx);
    if ($upstream === false) {
        http_response_code(502);
        echo json_encode(['error' => 'Layanan pencarian tidak dapat dihubungi.']);
        exit;
    }

    // Teruskan apa adanya bila valid JSON array; kalau tidak, anggap gagal
    $decoded = json_decode($upstream, true);
    if (!is_array($decoded)) {
        http_response_code(502);
        echo json_encode(['error' => 'Respons layanan pencarian tidak valid.']);
        exit;
    }
    echo json_encode($decoded, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Gagal melakukan pencarian.']);
}
