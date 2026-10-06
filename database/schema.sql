-- ============================================================
-- WebGIS Peta Persetujuan Perhutanan Sosial - SKEMA DATABASE
-- Idempotent: aman dijalankan berulang (IF NOT EXISTS + UNIQUE key).
-- Geometri TIDAK disimpan di MySQL, hanya metadata + style.
-- File GeoJSON fisik ada di /storage/geojson/
-- ============================================================

CREATE TABLE IF NOT EXISTS users (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    role ENUM('admin','viewer') NOT NULL DEFAULT 'viewer',
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS maps (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    judul VARCHAR(200) NOT NULL,
    center_lat DECIMAL(10,7) NOT NULL DEFAULT -7.4000000,
    center_lng DECIMAL(10,7) NOT NULL DEFAULT 109.2000000,
    zoom TINYINT UNSIGNED NOT NULL DEFAULT 9,
    basemap_default VARCHAR(50) NOT NULL DEFAULT 'osm',
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS layer_groups (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    map_id INT UNSIGNED NOT NULL,
    nama VARCHAR(100) NOT NULL,
    urutan INT NOT NULL DEFAULT 0,
    CONSTRAINT fk_groups_map FOREIGN KEY (map_id)
        REFERENCES maps (id) ON DELETE CASCADE ON UPDATE CASCADE,
    UNIQUE KEY uq_groups_map_nama (map_id, nama),
    KEY idx_groups_map_urutan (map_id, urutan)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS layers (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    group_id INT UNSIGNED NOT NULL,
    nama VARCHAR(100) NOT NULL,
    tipe_geom ENUM('polygon','line','point') NOT NULL DEFAULT 'polygon',
    file_geojson VARCHAR(255) NOT NULL,
    style_mode ENUM('single','categorized') NOT NULL DEFAULT 'single',
    style_field VARCHAR(100) NULL DEFAULT NULL,
    opacity_default TINYINT UNSIGNED NOT NULL DEFAULT 100,
    visible_default TINYINT(1) NOT NULL DEFAULT 1,
    urutan INT NOT NULL DEFAULT 0,
    aktif TINYINT(1) NOT NULL DEFAULT 1,
    -- Tambahan migrasi 002: layer dimuat & digambar mulai zoom ini (0 = selalu)
    min_zoom TINYINT UNSIGNED NOT NULL DEFAULT 0,
    -- Tambahan migrasi 003: style garis & isi per layer.
    -- Default = tampilan lama (outline ikut kelas, weight 1, opacity 1, fill 0.65, fill on).
    -- outline_color NULL = pakai outline_warna kelas; fill_enabled 0 = tanpa isi.
    outline_color CHAR(7) NULL DEFAULT NULL,
    outline_weight DECIMAL(3,1) NOT NULL DEFAULT 1.0,
    outline_opacity DECIMAL(3,2) NOT NULL DEFAULT 1.00,
    fill_opacity DECIMAL(3,2) NOT NULL DEFAULT 0.65,
    fill_enabled TINYINT(1) NOT NULL DEFAULT 1,
    -- Tambahan migrasi 004: format popup identify per layer (JSON, NULL = perilaku lama).
    popup_config TEXT NULL DEFAULT NULL,
    CONSTRAINT fk_layers_group FOREIGN KEY (group_id)
        REFERENCES layer_groups (id) ON DELETE CASCADE ON UPDATE CASCADE,
    UNIQUE KEY uq_layers_group_nama (group_id, nama),
    KEY idx_layers_group_urutan (group_id, urutan)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS layer_classes (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    layer_id INT UNSIGNED NOT NULL,
    nilai VARCHAR(100) NOT NULL,
    label VARCHAR(150) NOT NULL,
    warna CHAR(7) NOT NULL DEFAULT '#3388ff',
    outline_warna CHAR(7) NOT NULL DEFAULT '#ffffff',
    urutan INT NOT NULL DEFAULT 0,
    CONSTRAINT fk_classes_layer FOREIGN KEY (layer_id)
        REFERENCES layers (id) ON DELETE CASCADE ON UPDATE CASCADE,
    UNIQUE KEY uq_classes_layer_nilai (layer_id, nilai),
    KEY idx_classes_layer_urutan (layer_id, urutan)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tambahan migrasi 002: kolom atribut yang BOLEH disajikan di popup/GeoJSON.
-- Baris kosong = semua kolom disajikan (kompatibel mundur). Kolom lain dibuang
-- saat disajikan lewat /api/geojson.php; file asli tidak diubah.
CREATE TABLE IF NOT EXISTS layer_popup_fields (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    layer_id INT UNSIGNED NOT NULL,
    field VARCHAR(100) NOT NULL,
    urutan INT NOT NULL DEFAULT 0,
    CONSTRAINT fk_popup_layer FOREIGN KEY (layer_id)
        REFERENCES layers (id) ON DELETE CASCADE ON UPDATE CASCADE,
    UNIQUE KEY uq_popup_layer_field (layer_id, field),
    KEY idx_popup_layer_urutan (layer_id, urutan)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
