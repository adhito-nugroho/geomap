<?php
// Contoh konfigurasi. Salin menjadi config.php lalu sesuaikan.
// config.php TIDAK boleh di-commit (sudah ada di .gitignore).

return [
    // Database MySQL (Laragon default: host 127.0.0.1, user root, password kosong)
    'db_host' => '127.0.0.1',
    'db_port' => '3306',
    'db_name' => 'geomap',
    'db_user' => 'root',
    'db_pass' => '',

    // Base URL publik, contoh: http://geomap.test atau http://localhost/geomap
    // Dipakai untuk fitur "bagikan link". Boleh dikosongkan.
    'base_url' => '',

    // User-Agent untuk proxy Nominatim. PENTING: Nominatim menolak (403) beberapa
    // format UA bertanda kurung/kontak tertentu — yang terbukti lolos: "NamaApp/versi".
    // Operator disarankan menambahkan email kontak asli di belakangnya lalu uji via
    // /api/search.php?q=... (contoh: 'WebGIS-Perhutanan-Sosial/1.0 (nama@instansi.go.id)').
    'nominatim_user_agent' => 'WebGIS-Perhutanan-Sosial/1.0',

    // Password awal untuk seeder (migrate.php). WAJIB diisi di config.php lokal.
    // Kosongkan = user tersebut tidak dibuat. Bisa juga via env:
    // GEOMAP_ADMIN_PASSWORD / GEOMAP_VIEWER_PASSWORD. Ganti setelah login pertama.
    'seed_admin_password'  => '',
    'seed_viewer_password' => '',
];
