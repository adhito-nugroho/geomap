# WebGIS Peta Persetujuan Perhutanan Sosial

Viewer WebGIS internal Cabang Dinas Kehutanan. PHP native + MySQL + Leaflet, tanpa build step.

## Status: FASE 1 SELESAI

Fase 1 mencakup: struktur folder, `database/schema.sql`, `migrate.php`, seeder, login admin.
Viewer peta (Fase 2), kontrol peta (Fase 3), CRUD admin (Fase 4), ukur/export/bagikan (Fase 5) BELUM dikerjakan.

## Struktur folder (Fase 1)

```
geomap/
  admin/
    index.php      dasbor ringkasan (CRUD penuh = Fase 4)
    login.php      login admin (sesi + CSRF)
    logout.php     logout (POST + CSRF)
  api/             endpoint JSON (Fase 2-4)
  assets/          css/js tambahan bila perlu (Fase 2+)
  database/
    schema.sql     skema idempotent
    seed.php       data awal group/layer/kelas + user
  includes/
    auth.php       sesi, login, require_admin
    db.php         PDO singleton (prepared statement)
    helpers.php    e(), csrf, redirect
  storage/geojson/ file GeoJSON + .htaccess (tanpa eksekusi PHP)
  config.php       kredensial lokal (TIDAK di-commit)
  config.example.php contoh konfigurasi
  migrate.php      runner migrasi (CLI + browser)
  index.php        placeholder viewer (asli = Fase 2)
```

## Cara setup di Laragon

1. Letakkan folder ini di `D:\laragon\www\geomap` (atau clone repo ke sana).
2. Pastikan Apache + MySQL jalan di Laragon. PHP 8.x dengan ekstensi `pdo_mysql` aktif.
3. Salin konfigurasi:
   - Duplikat `config.example.php` menjadi `config.php`, sesuaikan `db_user`/`db_pass` bila MySQL memakai password.
   - Isi `seed_admin_password` / `seed_viewer_password` di `config.php` lokal
     (tidak di-commit; alternatif via env `GEOMAP_ADMIN_PASSWORD`).
4. Jalankan migrasi (salah satu):
   - CLI: `php migrate.php` dari dalam folder `geomap`
   - Browser: `http://geomap.test/migrate.php` atau `http://localhost/geomap/migrate.php`
   - Aman diulang: migrasi idempotent.
5. Login admin: `http://geomap.test/admin/login.php` dengan username `admin` /
   `viewer` dan password yang Anda isi di `config.php`.
6. Verifikasi: halaman `admin/index.php` menampilkan count tabel + tree group/layer.

## Cara import GeoJSON (Fase 1: manual)

1. Sederhanakan dulu file besar (target &lt; 5 MB, presisi 5-6 desimal) — tool otomatis = Fase 5.
2. Copy file `.geojson` ke `storage/geojson/` dengan nama huruf-kecil-underscore, contoh `persetujuan_ps_april26.geojson`.
3. Daftarkan metadata di tabel `layers` (via SQL untuk sementara; form admin = Fase 4):
   ```sql
   INSERT INTO layers (group_id, nama, tipe_geom, file_geojson, style_mode, style_field, visible_default, urutan, aktif)
   VALUES (1, 'Layer Baru', 'polygon', 'layer_baru.geojson', 'single', NULL, 1, 99, 1);
   ```
4. Tambahkan warna di `layer_classes` (wajib — JS dilarang hardcode warna).

## Cara menambah layer baru (ringkas)

Sama seperti import di atas + isi `layer_classes` sesuai `style_mode`:
- `single`: 1 baris dengan `nilai='__single__'`.
- `categorized`: 1 baris per nilai unik field `style_field`.

## Cloudflare Tunnel

1. Jalankan tunnel ke Apache lokal, contoh: `cloudflared tunnel --url http://localhost:80`
2. Aplikasi tetap bekerja karena memakai path relatif; `base_url` di `config.php` opsional untuk fitur bagikan link (Fase 5).
3. Pastikan cookie sesi `Lax` + HTTPS dari Cloudflare — tidak ada perubahan kode yang diperlukan.

## Keamanan (Fase 1)

- Semua query memakai PDO prepared statement (`ATTR_EMULATE_PREPARES=false`).
- Password `password_hash`/`password_verify`, session regenerate saat login.
- CSRF token di login + logout (dan semua POST admin di Fase 4).
- Output di-escape via `e()` (`htmlspecialchars`).
- `storage/geojson/.htaccess` memblokir eksekusi PHP di folder data.

## Asumsi Fase 1

- Fokus peta awal Banyumas Raya (-7.4, 109.2, zoom 9); bisa diubah di tabel `maps`.
- Field kategori `Kawasan Hutan` diasumsikan bernama `zona` (nilai: HK, HSAL, HL, HPT, HP, HPK, APL, Danau, Tubuh Air). Bisa diganti di admin Fase 4.
- GeoJSON di `storage/geojson/` masih CONTOH KECIL (1-2 poligon per file) agar migrasi + Fase 2 bisa dites; ganti dengan data asli yang sudah disederhanakan.
- `require_login()` mengarahkan ke path relatif `login.php` di folder `admin/` (works untuk `geomap.test` maupun subfolder `localhost/geomap`).

## Daftar periksa keamanan sebelum dibuka publik (Cloudflare Tunnel)

Jalankan semua poin ini di server produksi sebelum URL disebar:

- [ ] **Ganti password admin seed.** Password `seed_*_password` di `config.php` hanya untuk instalasi awal. Setelah login pertama, ganti via SQL langsung (belum ada UI ganti password).
- [ ] **Sesi & cookie.** `includes/helpers.php` sudah mengatur `cookie_httponly=true`, `cookie_samesite=Lax`, dan regenerate ID saat login. Pastikan situs diakses via **HTTPS** (Cloudflare).
- [ ] **CSRF di semua POST admin.** Login, logout, dan seluruh endpoint `api/admin/*.php` memvalidasi `csrf_token` sesi. Jangan menonaktifkannya.
- [ ] **Folder upload tidak mengeksekusi PHP.** `storage/geojson/.htaccess` memblokir eksekusi script di folder data — pastikan file `.htaccess` ikut ter-upload ke server.
- [ ] **`display_errors off` di produksi.** Di `php.ini` server: `display_errors=Off`, `log_errors=On`. API sudah mengembalikan pesan error generik.
- [ ] **`config.php` tidak ikut commit.** Sudah ada di `.gitignore`; verifikasi dengan `git ls-files config.php` (harus kosong).
- [ ] **Batasi `/admin`.** Minimal: password kuat + ganti berkala. Opsional: batasi IP via `.htaccess` di folder `admin/`, atau nonaktifkan user `viewer` bila tak dipakai.
- [ ] **Uji ulang pasca-deploy:** login salah tertolak, endpoint admin tanpa login mengembalikan 403, dan `api/geojson.php?layer=abc` mengembalikan 400.
