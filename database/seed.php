<?php
// Seeder awal (idempotent). Dipanggil oleh migrate.php.
// PRINSIP: hanya MENAMBAH baris yang belum ada; baris yang sudah ada TIDAK
// PERNAH ditimpa. Ini penting karena migrate.php dijalankan ulang di tiap
// deploy — semua perubahan admin (opacity, warna, urutan, dsb.) harus lestari.
// Warna/kelas legenda HANYA hidup di database, bukan hardcode di JS.
declare(strict_types=1);

function seed_database(PDO $pdo): array
{
    $log = [];

    // ---- 1. Users ----
    // Password TIDAK boleh tertulis di file ter-commit. Diambil dari config.php
    // (gitignored) atau environment variable. Jika kosong, user tersebut di-skip.
    // Lihat 'seed_admin_password' / 'seed_viewer_password' di config.example.php.
    $cfgPath = __DIR__ . '/../config.php';
    $cfg = is_file($cfgPath) ? require $cfgPath : [];
    $defaultUsers = [
        [
            'username' => 'admin',
            'password' => getenv('GEOMAP_ADMIN_PASSWORD') ?: ($cfg['seed_admin_password'] ?? ''),
            'role'     => 'admin',
        ],
        [
            'username' => 'viewer',
            'password' => getenv('GEOMAP_VIEWER_PASSWORD') ?: ($cfg['seed_viewer_password'] ?? ''),
            'role'     => 'viewer',
        ],
    ];
    $stmtUser = $pdo->prepare(
        'INSERT INTO users (username, password_hash, role) VALUES (?, ?, ?)
         ON DUPLICATE KEY UPDATE id = id'
    );
    foreach ($defaultUsers as $u) {
        if ($u['password'] === '') {
            $log[] = 'user: ' . $u['username'] . ' DILEWATI (password kosong; isi seed_*_password di config.php lokal / env)';
            continue;
        }
        $stmtUser->execute([$u['username'], password_hash($u['password'], PASSWORD_DEFAULT), $u['role']]);
        $log[] = 'user: ' . $u['username'] . ' (' . $u['role'] . ') OK';
    }

    // ---- 2. Map utama id=1 ----
    $stmtMap = $pdo->prepare(
        'INSERT INTO maps (id, judul, center_lat, center_lng, zoom, basemap_default)
         VALUES (1, ?, ?, ?, ?, ?)
         ON DUPLICATE KEY UPDATE id = id'
    );
    // Asumsi: fokus awal Banyumas Raya (Jawa Tengah), bisa diubah di admin (Fase 4) / viewer.
    $stmtMap->execute(['Peta Persetujuan Perhutanan Sosial', -7.4000000, 109.2000000, 9, 'osm']);
    $log[] = 'map id=1 OK';

    // ---- 3. Groups ----
    $groups = [
        ['nama' => 'Persetujuan Perhutanan Sosial', 'urutan' => 1],
        ['nama' => 'Rencana & Fungsi KHDPK',         'urutan' => 2],
        ['nama' => 'Administrasi & Kawasan',        'urutan' => 3],
    ];
    $stmtGroup = $pdo->prepare(
        'INSERT INTO layer_groups (map_id, nama, urutan) VALUES (1, ?, ?)
         ON DUPLICATE KEY UPDATE id = id'
    );
    $stmtGroupId = $pdo->prepare('SELECT id FROM layer_groups WHERE map_id = 1 AND nama = ? LIMIT 1');
    $groupIds = [];
    foreach ($groups as $g) {
        $stmtGroup->execute([$g['nama'], $g['urutan']]);
        $stmtGroupId->execute([$g['nama']]);
        $groupIds[$g['nama']] = (int) $stmtGroupId->fetchColumn();
        $log[] = 'group: ' . $g['nama'] . ' OK';
    }

    // ---- 4. Layers ----
    // file_geojson = nama file di /storage/geojson/
    $layers = [
        [
            'group' => 'Persetujuan Perhutanan Sosial', 'nama' => 'Persetujuan_PS_April26',
            'tipe_geom' => 'polygon', 'file_geojson' => 'persetujuan_ps_april26.geojson',
            'style_mode' => 'categorized', 'style_field' => 'skema',
            'opacity_default' => 100, 'visible_default' => 1, 'urutan' => 1, 'aktif' => 1,
        ],
        [
            'group' => 'Persetujuan Perhutanan Sosial', 'nama' => 'MHA_Jalawastu',
            'tipe_geom' => 'polygon', 'file_geojson' => 'mha_jalawastu.geojson',
            'style_mode' => 'single', 'style_field' => null,
            'opacity_default' => 100, 'visible_default' => 1, 'urutan' => 2, 'aktif' => 1,
        ],
        [
            'group' => 'Rencana & Fungsi KHDPK', 'nama' => 'KHDPK_FINAL_DESA',
            'tipe_geom' => 'polygon', 'file_geojson' => 'khdpk_final_desa.geojson',
            'style_mode' => 'single', 'style_field' => null,
            'opacity_default' => 100, 'visible_default' => 1, 'urutan' => 1, 'aktif' => 1,
        ],
        [
            'group' => 'Rencana & Fungsi KHDPK', 'nama' => 'RPKHDPK_AR_4326',
            'tipe_geom' => 'polygon', 'file_geojson' => 'rpkhdpk_ar_4326.geojson',
            'style_mode' => 'categorized', 'style_field' => 'fungsi',
            'opacity_default' => 100, 'visible_default' => 1, 'urutan' => 2, 'aktif' => 1,
        ],
        [
            'group' => 'Administrasi & Kawasan', 'nama' => 'Administrasi_Desa_AR',
            'tipe_geom' => 'polygon', 'file_geojson' => 'administrasi_desa_ar.geojson',
            'style_mode' => 'single', 'style_field' => null,
            'opacity_default' => 100, 'visible_default' => 0, 'urutan' => 1, 'aktif' => 1,
        ],
        [
            'group' => 'Administrasi & Kawasan', 'nama' => 'Kawasan Hutan',
            'tipe_geom' => 'polygon', 'file_geojson' => 'kawasan_hutan.geojson',
            // Asumsi: field penanda zona bernama `zona`. Bisa diganti di admin (Fase 4).
            'style_mode' => 'categorized', 'style_field' => 'zona',
            'opacity_default' => 100, 'visible_default' => 0, 'urutan' => 2, 'aktif' => 1,
        ],
    ];
    $stmtLayer = $pdo->prepare(
        'INSERT INTO layers (group_id, nama, tipe_geom, file_geojson, style_mode, style_field,
             opacity_default, visible_default, urutan, aktif)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
         ON DUPLICATE KEY UPDATE id = id'
    );
    $stmtLayerId = $pdo->prepare('SELECT id FROM layers WHERE group_id = ? AND nama = ? LIMIT 1');
    $layerIds = [];
    foreach ($layers as $l) {
        $gid = $groupIds[$l['group']];
        $stmtLayer->execute([
            $gid, $l['nama'], $l['tipe_geom'], $l['file_geojson'], $l['style_mode'], $l['style_field'],
            $l['opacity_default'], $l['visible_default'], $l['urutan'], $l['aktif'],
        ]);
        $stmtLayerId->execute([$gid, $l['nama']]);
        $layerIds[$l['nama']] = (int) $stmtLayerId->fetchColumn();
        $log[] = 'layer: ' . $l['nama'] . ' OK';
    }

    // ---- 5. Kelas legenda (warna dari DB, bukan hardcode JS) ----
    // [layer, nilai, label, warna fill, outline, urutan]
    $classes = [
        // Persetujuan_PS_April26 (warna WAJIB sesuai spek)
        ['Persetujuan_PS_April26', 'IPHPS', 'IPHPS', '#f59e0b', '#ffffff', 1],
        ['Persetujuan_PS_April26', 'PKK',   'PKK',   '#b91c1c', '#ffffff', 2],
        ['Persetujuan_PS_April26', 'PPHD',  'PPHD',  '#06b6d4', '#ffffff', 3],
        ['Persetujuan_PS_April26', 'PPHKm', 'PPHKm', '#1d4ed8', '#ffffff', 4],
        ['Persetujuan_PS_April26', 'PPHTR', 'PPHTR', '#f43f5e', '#ffffff', 5],
        // RPKHDPK_AR_4326 (warna pilihan sendiri, tersimpan di DB)
        ['RPKHDPK_AR_4326', 'Jasa Lingkungan',   'Jasa Lingkungan',   '#0ea5e9', '#ffffff', 1],
        ['RPKHDPK_AR_4326', 'Penataan',          'Penataan',          '#a855f7', '#ffffff', 2],
        ['RPKHDPK_AR_4326', 'Penggunaan',        'Penggunaan',        '#f59e0b', '#ffffff', 3],
        ['RPKHDPK_AR_4326', 'Perhutanan Sosial', 'Perhutanan Sosial', '#16a34a', '#ffffff', 4],
        ['RPKHDPK_AR_4326', 'Rehabilitasi',      'Rehabilitasi',      '#84cc16', '#ffffff', 5],
        ['RPKHDPK_AR_4326', 'Lain-lain',         'Lain-lain',         '#6b7280', '#ffffff', 6],
        // KHDPK_FINAL_DESA (single)
        ['KHDPK_FINAL_DESA', '__single__', 'KHDPK Desa', '#15803d', '#ffffff', 1],
        // Administrasi_Desa_AR (single)
        ['Administrasi_Desa_AR', '__single__', 'Batas Desa', '#64748b', '#334155', 1],
        // MHA_Jalawastu (single)
        ['MHA_Jalawastu', '__single__', 'MHA Jalawastu', '#eab308', '#713f12', 1],
        // Kawasan Hutan (9 kelas; nilai disederhanakan, label menjelaskan kode lengkap)
        ['Kawasan Hutan', 'HK',       'HK / HSA / CA / SM / TB / TN / TWA / TAHURA (Darat)', '#166534', '#ffffff', 1],
        ['Kawasan Hutan', 'HSAL',     'HSAL / WL / CAL / SML / TNL / TWAL (Laut)',           '#0d9488', '#ffffff', 2],
        ['Kawasan Hutan', 'HL',       'HL (Hutan Lindung)',        '#22c55e', '#ffffff', 3],
        ['Kawasan Hutan', 'HPT',      'HPT (Hutan Produksi Terbatas)', '#eab308', '#ffffff', 4],
        ['Kawasan Hutan', 'HP',       'HP (Hutan Produksi)',       '#f97316', '#ffffff', 5],
        ['Kawasan Hutan', 'HPK',      'HPK (Hutan Produksi Konversi)', '#f43f5e', '#ffffff', 6],
        ['Kawasan Hutan', 'APL',      'APL (Areal Penggunaan Lain)', '#a855f7', '#ffffff', 7],
        ['Kawasan Hutan', 'Danau',    'Danau',                     '#0284c7', '#ffffff', 8],
        ['Kawasan Hutan', 'Tubuh Air','Tubuh Air',                 '#06b6d4', '#ffffff', 9],
    ];
    $stmtClass = $pdo->prepare(
        'INSERT INTO layer_classes (layer_id, nilai, label, warna, outline_warna, urutan)
         VALUES (?, ?, ?, ?, ?, ?)
         ON DUPLICATE KEY UPDATE id = id'
    );
    foreach ($classes as $c) {
        [$layerNama, $nilai, $label, $warna, $outline, $urutan] = $c;
        $stmtClass->execute([$layerIds[$layerNama], $nilai, $label, $warna, $outline, $urutan]);
    }
    $log[] = 'layer_classes: ' . count($classes) . ' baris OK';

    return $log;
}
