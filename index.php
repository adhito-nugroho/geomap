<?php
// Viewer publik Fase 3: peta + panel + legenda + lazy load (Fase 2) ditambah
// slider opacity, filter layers, identify popup, search Nominatim, kontrol zoom
// kanan bawah, scale bar, dropdown skala, minimap.
// Warna/legenda/daftar hidden-field 100% dari /api/map.php (database).
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Peta Persetujuan Perhutanan Sosial</title>
<link rel="icon" href="data:image/svg+xml,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 100 100'><text y='.9em' font-size='90'>🌲</text></svg>">
<script src="https://cdn.tailwindcss.com"></script>
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css">
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
<link rel="stylesheet" href="https://unpkg.com/leaflet-minimap@3.6.1/dist/Control.MiniMap.min.css">
<script src="https://unpkg.com/leaflet-minimap@3.6.1/dist/Control.MiniMap.min.js"></script>
<script src="https://unpkg.com/alpinejs@3.13.5/dist/cdn.min.js" defer></script>
<style>
  /* Tinggi header sebagai variabel tunggal: 56px desktop, 52px mobile */
  :root { --header-h: 56px; }
  @media (max-width: 639.98px) { :root { --header-h: 52px; } }
  /* Top bar: tinggi dari variabel, gradien + garis bawah + bayangan */
  header.topbar {
    height: var(--header-h);
    overflow: hidden;
    background: linear-gradient(to right, #064e3b, #047857);
    border-bottom: 1px solid rgba(255, 255, 255, .1);
    box-shadow: 0 2px 8px rgba(0, 0, 0, .25);
    position: sticky;
    top: 0;
    z-index: 20; /* di atas panel (10) & kontrol Leaflet, di bawah modal (30) */
  }
  /* Area peta mengisi sisa viewport di bawah header (flex-1 sebagai fallback) */
  #map-wrap { height: calc(100dvh - var(--header-h)); }
  /* Peta mengisi seluruh ruang di bawah top bar */
  #map { position: absolute; inset: 0; z-index: 0; }
  /* Teks branding dua baris: nowrap + ellipsis; baris 2 hanya >=640px */
  .brand-title { font-size: 16px; font-weight: 700; color: #fff; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
  .brand-sub { font-size: 12px; color: #a7f3d0; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
  @media (max-width: 639.98px) { .brand-sub { display: none; } }
  /* Logo: 40px desktop / 36px mobile, rasio asli tanpa distorsi */
  .brand-logo { height: 40px; width: auto; object-fit: contain; }
  @media (max-width: 639.98px) { .brand-logo { height: 36px; } }
  /* Tombol menu kanan: hover putih transparan + fokus keyboard jelas */
  .topbtn { display: inline-flex; align-items: center; gap: .375rem; padding: .45rem .7rem; border-radius: .5rem; font-size: .875rem; color: #fff; background: transparent; border: 0; cursor: pointer; white-space: nowrap; }
  .topbtn:hover { background: rgba(255, 255, 255, .12); }
  .topbtn:focus-visible { outline: 2px solid #fff; outline-offset: 2px; }
  .loginbtn { background: #fff; color: #064e3b; font-weight: 600; }
  .loginbtn:hover { background: #ecfdf5; }
  .loginbtn:focus-visible { outline: 2px solid #064e3b; outline-offset: 2px; }
  /* Swatch legenda: warnanya diisi via JS dari database (bukan hardcode CSS) */
  .legend-swatch { width: 18px; height: 14px; border: 1px solid #9ca3af; flex-shrink: 0; }
  /* Tabel popup identify (gaya sendiri agar tidak tergantung JIT Tailwind di dalam popup) */
  .identify-table { border-collapse: collapse; font-size: 12px; table-layout: fixed; max-width: 100%; }
  .identify-table th, .identify-table td { border: 1px solid #d1d5db; padding: 3px 6px; text-align: left; vertical-align: top; overflow-wrap: anywhere; word-break: break-word; }
  .identify-table th { background: #f3f4f6; white-space: nowrap; }
  .identify-title { font-weight: bold; font-size: 13px; margin-bottom: 4px; overflow-wrap: anywhere; }
  /* Kontrol basemap (kanan atas) digeser ke bawah kotak pencarian */
  .leaflet-top.leaflet-right .leaflet-control-layers { margin-top: 3.5rem; }
  /* Kontrol kiri bawah (skala, dropdown skala, minimap) mengalah pada panel layer */
  #map-wrap .leaflet-bottom.leaflet-left { left: 0; transition: left .2s ease; }
  #map-wrap.panel-open .leaflet-bottom.leaflet-left { left: 316px; }
  @media (max-width: 767px) {
    #map-wrap.panel-open .leaflet-bottom.leaflet-left { left: 0; }
  }
  input[type="range"] { accent-color: #047857; }
  /* Toolbar vertikal kanan (ukur, export, bagikan, cetak) */
  .map-toolbar { position: absolute; right: .5rem; top: 7.5rem; z-index: 10; display: flex; flex-direction: column; gap: .375rem; }
  .map-toolbar button { width: 40px; height: 40px; border-radius: .5rem; background: #fff; border: 1px solid #e5e7eb; box-shadow: 0 1px 4px rgba(0,0,0,.25); font-size: 18px; line-height: 1; display: flex; align-items: center; justify-content: center; cursor: pointer; color: #111827; }
  .map-toolbar button:hover { background: #f3f4f6; }
  .map-toolbar button.active { background: #047857; color: #fff; border-color: #047857; }
  .map-toolbar button:focus-visible { outline: 2px solid #047857; outline-offset: 2px; }
  /* Panel hasil ukur + toast + loading */
  .measure-panel { position: absolute; bottom: 1rem; left: 50%; transform: translateX(-50%); z-index: 10; }
  .spinner { width: 36px; height: 36px; border-radius: 50%; border: 4px solid #d1fae5; border-top-color: #047857; animation: spin 0.9s linear infinite; }
  @keyframes spin { to { transform: rotate(360deg); } }
  /* Target sentuh >=40px di mobile untuk kontrol Leaflet juga */
  @media (max-width: 767px) {
    .leaflet-bar a { width: 40px !important; height: 40px !important; line-height: 40px !important; }
    .map-toolbar { top: auto; bottom: 3rem; right: 50%; transform: translateX(50%); flex-direction: row; }
  }
  /* Cetak: hanya peta + header + legenda + atribusi + skala */
  #print-header, #print-legend { display: none; }
  @media print {
    aside, #map-search, .map-toolbar, .measure-panel, #toast,
    .leaflet-control-zoom, .map-tools, .leaflet-control-minimap,
    .leaflet-control-layers, .scale-select { display: none !important; }
    #print-header, #print-legend { display: block !important; }
    #map-wrap { height: auto; }
    body { overflow: visible; }
    .legend-swatch { -webkit-print-color-adjust: exact; print-color-adjust: exact; }
  }
</style>
</head>
<body class="h-screen flex flex-col overflow-hidden" x-data="webgis()">
<!-- Alpine v3 otomatis memanggil method init() di x-data, jadi atribut pemicu
     ganda sudah dihapus (dulu menyebabkan init jalan dua kali) -->

<!-- ===== Top bar: logo + judul di kiri; info, home, login di kanan ===== -->
<header class="topbar text-white flex items-center justify-between px-3 shrink-0">
  <div class="flex items-center gap-2 min-w-0">
    <!-- Logo dalam kotak putih; disembunyikan bila file tak ada -->
    <div class="bg-white rounded-xl p-1 shrink-0">
      <img src="assets/logo.png" alt="Logo CDK Wilayah Bojonegoro" class="brand-logo rounded-lg" onerror="this.parentElement.style.display='none'">
    </div>
    <div class="min-w-0 leading-tight">
      <!-- Baris 1: judul dinamis dari tabel maps. Baris 2: instansi (statis, diizinkan). -->
      <h1 class="brand-title" x-text="mapTitle">Memuat…</h1>
      <p class="brand-sub">CDK Wilayah Bojonegoro · Dishut Prov. Jatim</p>
    </div>
  </div>
  <nav class="flex items-center gap-1 sm:gap-2 shrink-0" aria-label="Menu peta">
    <button class="topbtn" @click="infoOpen = true" title="Info peta" aria-label="Info peta">ⓘ <span class="hidden sm:inline">Info</span></button>
    <button class="topbtn" @click="goHome()" title="Kembali ke tampilan awal" aria-label="Kembali ke tampilan awal">⌂ <span class="hidden sm:inline">Home</span></button>
    <a class="topbtn loginbtn" href="admin/login.php" title="Login admin" aria-label="Login admin">👤 <span class="hidden sm:inline">Login</span></a>
  </nav>
</header>

<!-- ===== Area peta + panel layer ===== -->
<div id="map-wrap" class="relative flex-1" :class="{ 'panel-open': panelOpen }">

  <div id="map"></div>

  <!-- Kotak pencarian lokasi (kanan atas peta, Nominatim via /api/search.php) -->
  <div id="map-search" class="absolute top-2 right-2 z-10 w-64 max-w-[70vw]">
    <input x-model="searchQuery" @input="onSearchInput()" @keydown.escape="clearSearch()"
           placeholder="Search by location name" autocomplete="off"
           class="w-full rounded shadow-lg border px-3 py-2 text-sm bg-white">
    <ul x-show="searchResults.length > 0" class="mt-1 bg-white rounded shadow-lg text-sm max-h-56 overflow-y-auto">
      <template x-for="(r, i) in searchResults" :key="i">
        <li><button @click="goResult(r)" class="w-full text-left px-3 py-2 hover:bg-emerald-50 border-b last:border-0">
          <span class="block truncate" x-text="r.display_name"></span>
        </button></li>
      </template>
    </ul>
    <p x-show="searchNote" class="mt-1 text-xs bg-white/90 rounded px-2 py-1 shadow" x-text="searchNote"></p>
  </div>

  <!-- Toolbar kanan: ukur, export, bagikan, cetak -->
  <div class="map-toolbar" role="toolbar" aria-label="Alat peta">
    <button @click="startMeasure('distance')" :class="{ 'active': measure.active && measure.mode === 'distance' }"
            title="Ukur jarak" aria-label="Ukur jarak">📏</button>
    <button @click="startMeasure('area')" :class="{ 'active': measure.active && measure.mode === 'area' }"
            title="Ukur luas" aria-label="Ukur luas">▦</button>
    <button @click="exportOpen = true" title="Export GeoJSON layer aktif" aria-label="Export GeoJSON">⤓</button>
    <button @click="shareLink()" title="Bagikan tautan peta" aria-label="Bagikan tautan">🔗</button>
    <button @click="doPrint()" title="Cetak peta" aria-label="Cetak peta">🖨</button>
  </div>

  <!-- Panel hasil ukur -->
  <div x-show="measure.active || measure.result" class="measure-panel bg-white rounded shadow-lg px-3 py-2 text-sm max-w-[92vw]" x-transition>
    <p class="font-medium" x-text="measure.active ? 'Klik peta untuk menambah titik (klik ganda / Selesai untuk mengakhiri)…' : measure.result"></p>
    <p class="text-xs text-gray-500 italic">Hasil ukur bersifat indikatif.</p>
    <div class="flex gap-2 mt-1.5">
      <button x-show="measure.active" @click="measureFinish()" class="bg-emerald-700 text-white rounded px-3 py-1.5 min-h-[40px]">Selesai</button>
      <button @click="measureClear()" class="bg-gray-200 rounded px-3 py-1.5 min-h-[40px]">Hapus</button>
    </div>
  </div>

  <!-- Dialog export GeoJSON -->
  <div x-show="exportOpen" class="absolute inset-0 z-30 flex items-center justify-center bg-black/40 p-4"
       @click.self="exportOpen = false" x-transition>
    <div class="bg-white rounded shadow-xl p-4 max-w-sm w-full text-sm">
      <h3 class="font-bold mb-1">Export GeoJSON</h3>
      <p class="text-gray-600 mb-2">Pilih layer yang sedang tampil:</p>
      <template x-if="exportLayers().length === 0">
        <p class="text-gray-500">Tidak ada layer aktif. Centang dulu layer di panel kiri.</p>
      </template>
      <ul class="space-y-1 max-h-48 overflow-y-auto mb-2">
        <template x-for="l in exportLayers()" :key="l.id">
          <li><label class="flex items-center gap-2 cursor-pointer border rounded px-2 py-1.5">
            <input type="radio" name="exp-layer" :value="l.id" x-model="exportId" class="accent-emerald-700">
            <span x-text="l.nama"></span>
          </label></li>
        </template>
      </ul>
      <p class="text-xs text-amber-700 bg-amber-50 border border-amber-200 rounded px-2 py-1 mb-3">Data ini versi tampilan (disederhanakan, kolom terbatas), bukan data resmi untuk perhitungan luas atau dokumen legal.</p>
      <div class="flex gap-2">
        <button @click="downloadExport()" :disabled="!exportId" class="bg-emerald-700 text-white rounded px-3 py-1.5 min-h-[40px] disabled:opacity-50">Unduh</button>
        <button @click="exportOpen = false" class="bg-gray-200 rounded px-3 py-1.5 min-h-[40px]">Batal</button>
      </div>
    </div>
  </div>

  <!-- Toast kecil -->
  <div id="toast" x-show="toastMsg" x-transition
       class="absolute bottom-16 left-1/2 -translate-x-1/2 z-20 bg-emerald-900 text-white text-sm rounded px-3 py-2 shadow" x-text="toastMsg"></div>

  <!-- Overlay loading saat data peta dimuat -->
  <div x-show="bootLoading" class="absolute inset-0 z-20 bg-white/80 flex flex-col items-center justify-center gap-3">
    <div class="spinner" role="status" aria-label="Memuat"></div>
    <p class="text-sm text-gray-600" x-text="loadingMsg">Memuat…</p>
  </div>

  <!-- Kepala + legenda khusus cetak -->
  <div id="print-header">
    <h1 style="font-size:18px;font-weight:bold;" x-text="mapTitle"></h1>
    <p style="font-size:12px;">Tanggal cetak: <span id="print-date"></span> · Skala tampilan: <span id="print-scale"></span></p>
  </div>
  <div id="print-legend"></div>

  <!-- Panel kiri (drawer di layar kecil): judul + tree group/layer + legenda -->
  <aside x-show="panelOpen" x-transition
         class="absolute top-2 left-2 bottom-2 w-[300px] max-w-[85vw] bg-white rounded shadow-lg z-10 flex flex-col overflow-hidden">
    <div class="flex items-center justify-between px-3 py-2 border-b bg-gray-50">
      <h2 class="font-semibold text-sm">Layers</h2>
      <button @click="panelOpen = false" title="Tutup panel" class="px-2 py-0.5 rounded hover:bg-gray-200">✕</button>
    </div>

    <!-- Filter layers (client-side, berdasarkan nama) -->
    <div class="px-3 py-2 border-b">
      <input x-model="filterText" placeholder="Filter layers" autocomplete="off"
             class="w-full border rounded px-2 py-1.5 text-sm">
    </div>

    <div class="flex-1 overflow-y-auto p-2 pb-6 space-y-3 text-sm">
      <!-- Pesan error bila API gagal -->
      <template x-if="error">
        <div class="rounded bg-red-50 border border-red-200 text-red-700 text-xs px-2 py-1" x-text="error"></div>
      </template>

      <!-- Tree group -> layer -->
      <template x-for="g in groups" :key="g.id">
        <section x-show="groupMatch(g)">
          <h3 class="font-semibold text-gray-700 px-1 mb-1" x-text="g.nama"></h3>
          <ul class="space-y-2">
            <template x-for="l in g.layers" :key="l.id">
              <li x-show="layerMatch(l)" class="border rounded px-2 py-1.5">
                <label class="flex items-center gap-2 cursor-pointer">
                  <input type="checkbox" x-model="l.checked" @change="toggleLayer(l)"
                         class="w-4 h-4 accent-emerald-700">
                  <span class="font-medium flex-1" x-text="l.nama"></span>
                  <span x-show="(l.min_zoom ?? 0) > 0" class="text-xs text-gray-500 tabular-nums" title="Layer tampil mulai zoom ini" x-text="'≥z' + l.min_zoom"></span>
                  <span x-show="l.checked && curZoom < (l.min_zoom ?? 0)" class="text-xs text-amber-600" x-text="'· tampil mulai zoom ' + l.min_zoom"></span>
                  <span x-show="l.loading" class="text-xs text-emerald-700">Memuat…</span>
                </label>
                <!-- Error per layer (terlihat di panel, bukan hanya console) -->
                <p x-show="l.loadError" class="text-xs text-red-600 pl-6 mt-1" x-text="l.loadError"></p>
                <!-- Slider opacity per layer (fill + garis) -->
                <div class="flex items-center gap-2 pl-6 mt-1.5 text-xs text-gray-600">
                  <input type="range" min="0" max="100" step="1" title="Opacity layer"
                         x-model.number="l.opacity" @input="applyOpacity(l)" class="flex-1 h-1">
                  <span class="w-12 text-right tabular-nums" x-text="l.opacity + ' %'"></span>
                </div>
                <!-- Legenda otomatis dari layer_classes (database) -->
                <ul class="mt-1.5 space-y-1 pl-6">
                  <template x-for="c in l.classes" :key="c.nilai">
                    <li class="flex items-center gap-2 text-xs text-gray-600">
                      <span class="legend-swatch" :style="'background:' + c.warna"></span>
                      <span x-text="c.label"></span>
                    </li>
                  </template>
                </ul>
              </li>
            </template>
          </ul>
        </section>
      </template>
    </div>
  </aside>

  <!-- Tombol buka panel saat collapsed -->
  <button x-show="!panelOpen" @click="panelOpen = true" x-transition
          class="absolute top-2 left-2 z-10 bg-white rounded shadow-lg px-3 py-2 text-sm font-semibold hover:bg-gray-100">
    ☰ Layers
  </button>

  <!-- Modal info peta -->
  <div x-show="infoOpen" class="absolute inset-0 z-30 flex items-center justify-center bg-black/40 p-4"
       @click.self="infoOpen = false" x-transition>
    <div class="bg-white rounded shadow-xl p-4 max-w-sm w-full text-sm">
      <h3 class="font-bold mb-1" x-text="mapTitle"></h3>
      <p class="text-gray-600">Viewer internal Cabang Dinas Kehutanan — Peta Persetujuan Perhutanan Sosial.</p>
      <p class="text-gray-600 mt-1">Layer aktif: <b x-text="activeCount()"></b> dari <b x-text="totalCount()"></b>.</p>
      <button @click="infoOpen = false" class="mt-3 bg-emerald-700 text-white rounded px-3 py-1.5 w-full">Tutup</button>
    </div>
  </div>
</div>

<script>
// State Alpine + logika Leaflet. Tidak ada warna hardcode: semua dari API.
function webgis() {
  // Objek Leaflet di luar state reaktif Alpine (closure, bukan properti x-data)
  // agar drag/zoom/render ribuan fitur tidak lewat Proxy Alpine (berat di HP).
  let _map = null;
  const _leaflets = {};
  let _locateLayer = null;
  let _measureLayer = null;
  let _scaleSelect = null;
  return {
    homeView: null,
    mapTitle: 'Memuat…',
    groups: [],       // tree dari /api/map.php, tiap layer dapat flag checked/loading
    panelOpen: window.innerWidth >= 768,
    infoOpen: false,
    error: '',
    fittedOnce: false, // fitBounds otomatis hanya sekali, saat peta masih di pusat default
    curZoom: 0,       // zoom peta saat ini (reaktif untuk catatan min_zoom di panel)
    paneOrder: {},    // id layer -> indeks urutan tree (untuk z-index pane)
    paneTotal: 0,
    filterText: '',    // filter layers (client-side)
    hiddenFields: [],  // field teknis popup identify, dari database (lowercase)
    searchQuery: '',
    searchResults: [],
    searchNote: '',
    searchTimer: null,
    scaleText: '',
    currentBase: 'osm',
    // Loading overlay: tampil selama konfigurasi + layer awal dimuat
    bootLoading: true,
    loadingMsg: 'Memuat konfigurasi…',
    loadTotal: 0,
    loadDone: 0,
    toastMsg: '',
    toastTimer: null,
    exportOpen: false,
    exportId: null,
    // Ukur manual (tanpa library tambahan): polyline jarak + polygon luas geodesik
    measure: { active: false, mode: null, points: [] },

    async init() {
      // Guard ganda: cegah inisialisasi dua kali (pernah terjadi via x-init + auto-init).
      if (this._started) return;
      this._started = true;
      // Muat konfigurasi + tree + legenda dari database
      let cfg;
      try {
        const res = await fetch('api/map.php?id=1');
        if (!res.ok) throw new Error('HTTP ' + res.status);
        cfg = await res.json();
      } catch (e) {
        this.error = 'Gagal memuat konfigurasi peta. Pastikan migrate.php sudah dijalankan.';
        this.bootLoading = false;
        return;
      }
      this.mapTitle = cfg.map.judul || 'Peta Persetujuan Perhutanan Sosial';
      document.title = this.mapTitle;
      this.homeView = { lat: cfg.map.center_lat, lng: cfg.map.center_lng, zoom: cfg.map.zoom };
      this.hiddenFields = (cfg.hidden_fields || []).map((s) => String(s).toLowerCase());
      // Pulihkan state dari URL hash (fitur bagikan); nilai invalid diabaikan
      const hh = this.parseHash();
      if (hh.c) {
        const p = hh.c.split(',').map(Number);
        if (p.length === 3 && p.every(Number.isFinite)
            && p[0] >= -90 && p[0] <= 90 && p[1] >= -180 && p[1] <= 180
            && p[2] >= 0 && p[2] <= 19) {
          this.homeView = { lat: p[0], lng: p[1], zoom: Math.round(p[2]) };
        }
      }
      const baseList = ['osm', 'esri', 'hot'];
      this.currentBase = baseList.includes(hh.b) ? hh.b : null;
      if (!this.currentBase) {
        const d = cfg.map.basemap_default;
        this.currentBase = (d === 'esri' || d === 'hot') ? d : 'osm';
      }

      // Basemap: OSM default; Esri satelit + OSM Humanitarian sebagai opsi
      const osm = L.tileLayer('https://tile.openstreetmap.org/{z}/{x}/{y}.png',
        { maxZoom: 19, attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a>' });
      const esri = L.tileLayer('https://server.arcgisonline.com/ArcGIS/rest/services/World_Imagery/MapServer/tile/{z}/{y}/{x}',
        { maxZoom: 19, attribution: 'Imagery &copy; Esri & contributors' });
      const hot = L.tileLayer('https://{s}.tile.openstreetmap.fr/hot/{z}/{x}/{y}.png',
        { maxZoom: 19, attribution: '&copy; OpenStreetMap contributors, Tiles style by HOT' });
      const basemaps = { 'OpenStreetMap': osm, 'Esri Satelit': esri, 'OSM Humanitarian': hot };

      // Guard instance peta: jika sudah ada, hentikan (anti "Map container is already initialized")
      if (_map) return;
      _map = L.map('map', {
        center: [this.homeView.lat, this.homeView.lng],
        zoom: this.homeView.zoom,
        zoomControl: false, // diganti kontrol zoom kustom kanan bawah (Fase 3)
        preferCanvas: true, // performa untuk poligon besar
        layers: [(this.currentBase === 'esri' ? esri : (this.currentBase === 'hot' ? hot : osm))],
      });
      L.control.layers(basemaps).addTo(_map);
      L.control.zoom({ position: 'bottomright' }).addTo(_map);
      // Lacak basemap aktif untuk state bagikan (nama -> kunci)
      const baseNameKeys = { 'OpenStreetMap': 'osm', 'Esri Satelit': 'esri', 'OSM Humanitarian': 'hot' };
      _map.on('baselayerchange', (e) => {
        this.currentBase = baseNameKeys[e.name] || 'osm';
        this.saveHash();
      });
      // Simpan state ke hash setiap peta digeser
      _map.on('moveend', () => this.saveHash());

      // Tombol locate me / zoom extent / fullscreen (kanan bawah, vanilla Leaflet)
      const self = this;
      const ToolBtns = L.Control.extend({
        onAdd() {
          const d = L.DomUtil.create('div', 'leaflet-bar leaflet-control map-tools');
          const mk = (label, title, fn) => {
            const b = L.DomUtil.create('button', '', d);
            b.type = 'button'; b.title = title; b.innerHTML = label;
            b.style.cssText = 'width:30px;height:30px;line-height:30px;font-size:15px;background:#fff;cursor:pointer;display:block;border:0;border-bottom:1px solid #ccc;';
            b.addEventListener('click', (ev) => { ev.stopPropagation(); fn(); });
            return b;
          };
          mk('◎', 'Lokasi saya', () => self.locateMe());
          const last = mk('⛶', 'Layar penuh', () => self.toggleFullscreen());
          last.style.borderBottom = '0';
          // Tombol extent disisipkan di tengah
          const ext = L.DomUtil.create('button', '', d);
          ext.type = 'button'; ext.title = 'Zoom ke layer aktif'; ext.innerHTML = '⤢';
          ext.style.cssText = 'width:30px;height:30px;line-height:30px;font-size:15px;background:#fff;cursor:pointer;display:block;border:0;border-bottom:1px solid #ccc;';
          ext.addEventListener('click', (ev) => { ev.stopPropagation(); self.zoomExtent(); });
          d.insertBefore(ext, last);
          return d;
        }
      });
      _map.addControl(new ToolBtns({ position: 'bottomright' }));

      // Scale bar metrik + dropdown skala + minimap (kiri bawah)
      L.control.scale({ metric: true, imperial: false, position: 'bottomleft' }).addTo(_map);
      this.initScaleControl();
      if (L.Control && L.Control.MiniMap) {
        const osm2 = L.tileLayer('https://tile.openstreetmap.org/{z}/{x}/{y}.png', {});
        _map.addControl(new L.Control.MiniMap(osm2, {
          position: 'bottomleft', width: 140, height: 140, toggleDisplay: true,
        }));
      }
      document.addEventListener('fullscreenchange', () => {
        if (_map) setTimeout(() => _map.invalidateSize(), 200);
      });
      // min_zoom: saat zoom berubah, muat/gambar layer yang masuk rentang
      _map.on('zoomend', () => {
        this.curZoom = _map.getZoom();
        for (const g of this.groups) {
          for (const l of g.layers) {
            if (l.checked) this.toggleLayer(l);
          }
        }
      });
      this.curZoom = _map.getZoom();

      // Siapkan tree + centang default dari visible_default, lalu lazy-load yang aktif.
      // Sekaligus peta urutan tree -> z-index pane (atas panel = atas tumpukan).
      this.groups = cfg.groups || [];
      this.paneOrder = {};
      this.paneTotal = 0;
      for (const g of this.groups) {
        for (const l of g.layers) {
          l.checked = l.visible_default === 1;
          l.loading = false;
          l.loadError = '';
          l.loaded = false; // flag reaktif: GeoJSON sudah di-fetch (untuk template)
          l.opacity = (l.opacity_default ?? 100);
          l.min_zoom = (l.min_zoom ?? 0);
          this.paneOrder[l.id] = this.paneTotal++;
        }
      }
      // Terapkan layer + opacity dari hash bagikan (menimpa default DB)
      if (hh.l !== undefined) {
        const wanted = {};
        for (const item of hh.l.split(',')) {
          const [idS, opS] = item.split(':');
          const id = parseInt(idS, 10);
          const op = parseInt(opS, 10);
          if (Number.isInteger(id)) wanted[id] = Number.isInteger(op) ? Math.min(100, Math.max(0, op)) : 100;
        }
        for (const g of this.groups) {
          for (const l of g.layers) {
            if (l.id in wanted) {
              l.checked = true;
              l.opacity = wanted[l.id];
            } else {
              l.checked = false;
            }
          }
        }
      }
      // Hitung beban loading awal untuk overlay
      this.loadTotal = 0;
      for (const g of this.groups) {
        for (const l of g.layers) {
          if (l.checked) this.loadTotal++;
        }
      }
      this.loadDone = 0;
      this.loadingMsg = this.loadTotal > 0 ? ('Memuat layer 0/' + this.loadTotal + '…') : 'Menyiapkan peta…';
      // Muat paralel (bukan sekuensial) agar latensi tak dijumlahkan.
      // maybeFit ditahan selama batch agar fitBounds mencakup SEMUA layer awal.
      this._batchLoading = true;
      try {
        const jobs = [];
        for (const g of this.groups) {
          for (const l of g.layers) {
            if (l.checked) jobs.push(this.toggleLayer(l));
          }
        }
        await Promise.allSettled(jobs);
      } finally {
        this._batchLoading = false;
      }
      this.maybeFit();
      this.bootLoading = false;
    },

    // Cari kelas warna untuk satu fitur berdasarkan style_field (data dari DB).
    // Pencocokan case-insensitive + trim, supaya "PERHUTANAN SOSIAL" cocok "Perhutanan Sosial".
    styleFor(layer, feature) {
      const classes = layer.classes || [];
      let cls = null;
      if (layer.style_mode === 'categorized' && layer.style_field) {
        const raw = feature.properties ? String(feature.properties[layer.style_field] ?? '') : '';
        const v = raw.trim().toLowerCase();
        cls = (v === '') ? null
          : (classes.find(c => String(c.nilai ?? '').trim().toLowerCase() === v) || null);
      } else {
        cls = classes.find(c => String(c.nilai ?? '').trim() === '__single__') || classes[0] || null;
      }
      // Fallback netral bila atribut tidak cocok kelas mana pun (bukan warna legenda)
      const fill = cls ? cls.warna : '#9ca3af';
      // Outline layer (admin) diutamakan; kosong = ikut outline kelas
      const edge = layer.outline_color || (cls && cls.outline_warna) || '#ffffff';
      const num = (v, d) => {
        const n = parseFloat(v);
        return Number.isFinite(n) ? n : d;
      };
      const f = num(layer.opacity ?? layer.opacity_default ?? 100, 100) / 100;
      const fillOn = (layer.fill_enabled ?? 1) == 1;
      return {
        fillColor: fill,
        color: edge,
        weight: num(layer.outline_weight ?? 1, 1),
        fillOpacity: fillOn ? num(layer.fill_opacity ?? 0.65, 0.65) * f : 0,
        opacity: num(layer.outline_opacity ?? 1, 1) * f,
      };
    },

    // Terapkan opacity slider (throttle rAF agar slider berat tidak redraw berlebih)
    applyOpacity(layer) {
      if (layer._opRaf) return;
      layer._opRaf = requestAnimationFrame(() => {
        layer._opRaf = null;
        this.applyOpacityNow(layer);
        this.saveHash();
      });
    },
    applyOpacityNow(layer) {
      const gl = _leaflets[layer.id];
      if (!gl) return;
      const num = (v, d) => {
        const n = parseFloat(v);
        return Number.isFinite(n) ? n : d;
      };
      const f = num(layer.opacity ?? 100, 100) / 100;
      const fillOn = (layer.fill_enabled ?? 1) == 1;
      gl.setStyle({
        fillOpacity: fillOn ? num(layer.fill_opacity ?? 0.65, 0.65) * f : 0,
        opacity: num(layer.outline_opacity ?? 1, 1) * f,
      });
    },

    // Filter layers client-side berdasarkan nama
    layerMatch(l) {
      const q = this.filterText.trim().toLowerCase();
      return q === '' || l.nama.toLowerCase().includes(q);
    },
    groupMatch(g) {
      return g.layers.some((l) => this.layerMatch(l));
    },

    // Escape HTML untuk popup identify (data GeoJSON tidak boleh merender HTML mentah)
    esc(s) {
      return String(s ?? '').replace(/[&<>"']/g, (c) => (
        { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]
      ));
    },

    // Tabel atribut popup identify; field teknis disembunyikan (daftar dari database).
    // Bila layer punya popup_config: tampilkan judul + label/format/satuan sesuai
    // konfigurasi; nilai field style_field (categorized) dapat kotak warna kelas.
    // Semua teks di-escape; tanpa config -> perilaku lama.
    popupHtml(props, layer) {
      const cfg = (layer && layer.popup_config && Array.isArray(layer.popup_config.fields)
        && layer.popup_config.fields.length > 0) ? layer.popup_config : null;
      if (!cfg) {
        const rows = Object.entries(props || {})
          .filter(([k]) => !this.hiddenFields.includes(String(k).toLowerCase()));
        if (rows.length === 0) return '<i>Tidak ada atribut tampilan.</i>';
        return '<table class="identify-table"><tbody>' + rows.map(([k, v]) => (
          '<tr><th>' + this.esc(k) + '</th><td>'
          + this.esc(typeof v === 'object' ? JSON.stringify(v) : v) + '</td></tr>'
        )).join('') + '</tbody></table>';
      }
      const P = props || {};
      let h = '';
      const tv = cfg.title_field ? P[cfg.title_field] : undefined;
      if (tv !== undefined && tv !== null && tv !== '') {
        h += '<div class="identify-title">' + this.esc(String(tv)) + '</div>';
      }
      const fields = [...cfg.fields]
        .filter((f) => f && f.visible !== false)
        .sort((a, b) => ((a.order ?? 0) - (b.order ?? 0)));
      // Warna kelas untuk penanda field style (categorized), dari layer_classes
      let swatch = null;
      if (layer && layer.style_mode === 'categorized' && layer.style_field && P[layer.style_field] !== undefined) {
        const v = String(P[layer.style_field] ?? '').trim().toLowerCase();
        const cls = (layer.classes || []).find((c) => String(c.nilai ?? '').trim().toLowerCase() === v);
        if (cls) swatch = { field: layer.style_field, color: cls.warna };
      }
      h += '<table class="identify-table"><tbody>';
      for (const f of fields) {
        if (!(f.key in P)) continue;
        let v = P[f.key];
        if (v !== null && typeof v === 'object') v = JSON.stringify(v);
        if ((f.format || 'teks') === 'angka') {
          const n = Number(String(v).replace(/\s/g, '').replace(',', '.'));
          if (Number.isFinite(n)) {
            const d = Math.min(10, Math.max(0, f.decimals ?? 2));
            v = n.toLocaleString('id-ID', { minimumFractionDigits: d, maximumFractionDigits: d });
          } else {
            v = String(v ?? '');
          }
        } else {
          v = String(v ?? '');
        }
        if (f.unit) v = v + ' ' + f.unit;
        let cell = this.esc(v);
        if (swatch && String(f.key) === String(swatch.field)) {
          // Warna dari DB sudah divalidasi heksadesimal saat simpan; lapis pengaman di sini
          const col = /^#[0-9a-fA-F]{6}$/.test(swatch.color) ? swatch.color : '#9ca3af';
          cell = '<span class="legend-swatch" style="background:' + col
            + ';display:inline-block;vertical-align:middle;"></span> ' + cell;
        }
        h += '<tr><th>' + this.esc(f.label || f.key) + '</th><td>' + cell + '</td></tr>';
      }
      return h + '</tbody></table>';
    },

    // Layer aktif = dicentang DAN zoom peta sudah mencapai min_zoom (dari DB)
    layerActive(layer) {
      return layer.checked && _map && _map.getZoom() >= (layer.min_zoom ?? 0);
    },

    // Toggle visibilitas; fetch GeoJSON hanya saat pertama kali dibutuhkan (lazy).
    // Di bawah min_zoom: tidak di-fetch maupun digambar.
    async toggleLayer(layer) {
      if (layer.loading) return; // cegah fetch ganda saat zoom cepat
      if (this.layerActive(layer) && !_leaflets[layer.id]) {
        layer.loading = true;
        layer.loadError = '';
        try {
          const res = await fetch('api/geojson.php?layer=' + layer.id);
          if (!res.ok) throw new Error('HTTP ' + res.status);
          const gj = await res.json();
          _leaflets[layer.id] = L.geoJSON(gj, {
            pane: this.paneFor(layer),
            style: (f) => this.styleFor(layer, f),
            // Identify: konten popup dibangun malas (lazy) saat dibuka,
            // agar ribuan string HTML tak dirakit + disimpan di memori saat load
            onEachFeature: (f, ly) => {
              ly.bindPopup(() => this.popupHtml(f.properties, layer), { maxWidth: Math.min(300, window.innerWidth - 48) });
            },
          });
          layer.loaded = true; // flag reaktif untuk template (exportLayers)
        } catch (e) {
          layer.checked = false;
          layer.loadError = 'Gagal memuat layer. Centang ulang untuk mencoba lagi.';
          this.error = 'Gagal memuat layer ' + layer.nama + '.';
        } finally {
          layer.loading = false;
        }
      }
      this.reorder();
      this.maybeFit();
      this.bumpBoot();
      this.saveHash();
    },
    // Kemajuan overlay loading awal (dipanggil tiap toggleLayer selesai)
    bumpBoot() {
      if (!this.bootLoading) return;
      this.loadDone++;
      if (this.loadDone >= this.loadTotal) {
        this.bootLoading = false;
      } else {
        this.loadingMsg = 'Memuat layer ' + this.loadDone + '/' + this.loadTotal + '…';
      }
    },

    // Fallback viewport: setelah layer pertama dimuat, jika peta masih di pusat
    // default (dari tabel maps), zoom ke gabungan bounds layer yang aktif.
    // Tidak ada koordinat hardcode; pusat default dibaca dari homeView (DB).
    maybeFit() {
      if (this.fittedOnce || !_map || !this.homeView) return;
      if (this._batchLoading) return; // tunggu batch awal selesai agar bounds lengkap
      const c = _map.getCenter();
      const atHome = Math.abs(c.lat - this.homeView.lat) < 1e-6
        && Math.abs(c.lng - this.homeView.lng) < 1e-6
        && _map.getZoom() === this.homeView.zoom;
      if (!atHome) { this.fittedOnce = true; return; } // pengguna sudah menggeser: jangan ganggu
      const bounds = L.latLngBounds([]);
      let any = false;
      for (const g of this.groups) {
        for (const l of g.layers) {
          const gl = _leaflets[l.id];
          if (gl && l.checked) { bounds.extend(gl.getBounds()); any = true; }
        }
      }
      if (any && bounds.isValid()) {
        this.fittedOnce = true;
        _map.fitBounds(bounds, { padding: [24, 24] });
      }
    },

    // Pane Leaflet per layer: z-index dari urutan tree (atas panel = atas tumpukan).
    // Deterministik terhadap urutan load maupun hasil drag & drop admin (perlu refresh).
    paneFor(layer) {
      const name = 'pane-layer-' + layer.id;
      if (!_map.getPane(name)) _map.createPane(name);
      const idx = this.paneOrder[layer.id] ?? 0;
      _map.getPane(name).style.zIndex = 400 + (this.paneTotal - idx);
      return name;
    },

    // Susun ulang z-order sesuai urutan tree (atas tree = atas peta).
    // Layer di bawah min_zoom disembunyikan walau dicentang.
    reorder() {
      if (!_map) return;
      const ordered = [];
      for (const g of this.groups) {
        for (const l of g.layers) {
          const gl = _leaflets[l.id];
          if (!gl) continue;
          if (this.layerActive(l)) ordered.push(gl);
          else if (_map.hasLayer(gl)) _map.removeLayer(gl);
        }
      }
      // Urutan tumpukan diatur pane (z-index), bukan urutan addTo.
      ordered.forEach((gl) => {
        if (!_map.hasLayer(gl)) gl.addTo(_map);
      });
    },

    // ---- Pencarian lokasi (Nominatim via /api/search.php, dibatasi Indonesia) ----
    onSearchInput() {
      clearTimeout(this.searchTimer);
      // Batalkan request sebelumnya agar respons basi tak menimpa hasil terbaru
      if (this._searchCtl) {
        try { this._searchCtl.abort(); } catch (e) { /* abaikan */ }
        this._searchCtl = null;
      }
      const q = this.searchQuery.trim();
      if (q.length < 3) { this.searchResults = []; this.searchNote = ''; return; }
      this.searchNote = 'Mencari…';
      this.searchTimer = setTimeout(async () => {
        // Batas 15 detik agar UI tak menggantung bila server lambat
        const ctl = new AbortController();
        this._searchCtl = ctl;
        const kill = setTimeout(() => ctl.abort(), 15000);
        try {
          const res = await fetch('api/search.php?q=' + encodeURIComponent(q), { signal: ctl.signal });
          clearTimeout(kill);
          if (this._searchCtl !== ctl) return; // sudah digantikan ketikan baru
          let data = null;
          try {
            data = await res.json();
          } catch (e) {
            data = null;
          }
          if (!res.ok) {
            this.searchNote = (data && data.error) || ('Pencarian gagal (HTTP ' + res.status + ').');
            this.searchResults = [];
            return;
          }
          if (!Array.isArray(data)) {
            this.searchNote = 'Respons server tidak valid (HTTP 200).';
            this.searchResults = [];
            return;
          }
          this.searchResults = data;
          this.searchNote = data.length ? '' : 'Tidak ditemukan di Indonesia.';
        } catch (e) {
          clearTimeout(kill);
          if (this._searchCtl !== ctl) return; // digantikan, diam saja
          this.searchNote = (e && e.name === 'AbortError')
            ? 'Pencarian kehabisan waktu. Periksa koneksi lalu coba lagi.'
            : 'Pencarian gagal.';
          this.searchResults = [];
        } finally {
          if (this._searchCtl === ctl) this._searchCtl = null;
        }
      }, 400);
    },
    goResult(r) {
      this.searchResults = [];
      this.searchNote = '';
      if (!_map) return;
      if (r.boundingbox) {
        // Nominatim: boundingbox = [selatan, utara, barat, timur]
        const s = +r.boundingbox[0], n = +r.boundingbox[1], w = +r.boundingbox[2], e = +r.boundingbox[3];
        _map.fitBounds([[s, w], [n, e]], { padding: [24, 24] });
      } else {
        _map.setView([+r.lat, +r.lon], 14);
      }
    },
    clearSearch() {
      this.searchQuery = '';
      this.searchResults = [];
      this.searchNote = '';
    },

    // ---- Kontrol kanan bawah ----
    locateMe() {
      if (!navigator.geolocation) { this.error = 'Geolokasi tidak didukung browser ini.'; return; }
      if (_locateLayer) { _map.removeLayer(_locateLayer); _locateLayer = null; }
      _map.locate({ setView: true, maxZoom: 14 });
      _map.once('locationfound', (e) => {
        _locateLayer = L.circleMarker(e.latlng, {
          radius: 8, color: '#16a34a', fillColor: '#4ade80', fillOpacity: 0.9,
        }).addTo(_map).bindPopup('Lokasi Anda').openPopup();
      });
      _map.once('locationerror', () => {
        this.error = 'Tidak dapat memperoleh lokasi Anda.';
      });
    },
    zoomExtent() {
      if (!_map) return;
      const bounds = L.latLngBounds([]);
      let any = false;
      for (const g of this.groups) {
        for (const l of g.layers) {
          const gl = _leaflets[l.id];
          if (gl && l.checked) { bounds.extend(gl.getBounds()); any = true; }
        }
      }
      if (any && bounds.isValid()) _map.fitBounds(bounds, { padding: [24, 24] });
      else this.goHome();
    },
    toggleFullscreen() {
      const el = document.getElementById('map-wrap');
      if (!document.fullscreenElement) { if (el.requestFullscreen) el.requestFullscreen(); }
      else if (document.exitFullscreen) document.exitFullscreen();
      setTimeout(() => { if (_map) _map.invalidateSize(); }, 300);
    },

    // ---- Dropdown skala (96 dpi): N = meterPerPixel / 0.0002645833 ----
    zoomForScale(n) {
      const lat = _map.getCenter().lat * Math.PI / 180;
      const z = Math.log2(156543.03392 * Math.cos(lat) / (n * 0.0002645833));
      return Math.max(0, Math.min(19, Math.round(z)));
    },
    updateScaleLabel() {
      const sel = _scaleSelect;
      if (!sel || !_map) return;
      const lat = _map.getCenter().lat * Math.PI / 180;
      const n = Math.round(156543.03392 * Math.cos(lat) / Math.pow(2, _map.getZoom()) / 0.0002645833);
      this.scaleText = '1:' + n.toLocaleString('id-ID');
      sel.innerHTML = '';
      const cur = document.createElement('option');
      cur.textContent = '≈ ' + this.scaleText;
      cur.disabled = true; cur.selected = true;
      sel.appendChild(cur);
      for (const p of [10000, 25000, 50000, 72224, 100000, 250000]) {
        const o = document.createElement('option');
        o.value = String(p);
        o.textContent = '1:' + p.toLocaleString('id-ID');
        sel.appendChild(o);
      }
    },
    initScaleControl() {
      const div = L.DomUtil.create('div', 'leaflet-bar leaflet-control scale-select');
      div.style.background = '#fff';
      div.style.padding = '2px 4px';
      const sel = L.DomUtil.create('select', '', div);
      sel.title = 'Skala peta';
      sel.style.fontSize = '12px';
      sel.style.maxWidth = '130px';
      _scaleSelect = sel;
      sel.addEventListener('change', () => {
        const n = parseFloat(sel.value);
        if (n > 0 && _map) _map.setZoom(this.zoomForScale(n));
      });
      L.DomEvent.disableClickPropagation(div);
      L.DomEvent.disableScrollPropagation(div);
      const C = L.Control.extend({ onAdd: () => div });
      _map.addControl(new C({ position: 'bottomleft' }));
      _map.on('moveend zoomend', () => this.updateScaleLabel());
      this.updateScaleLabel();
    },

    // ---- Toast kecil ----
    toast(msg) {
      this.toastMsg = msg;
      clearTimeout(this.toastTimer);
      this.toastTimer = setTimeout(() => { this.toastMsg = ''; }, 3500);
    },

    // ---- Bagikan link: state di URL hash ----
    parseHash() {
      const h = {};
      try {
        const s = (location.hash || '').replace(/^#/, '');
        for (const part of s.split('&')) {
          if (!part) continue;
          const i = part.indexOf('=');
          if (i > 0) h[part.slice(0, i)] = decodeURIComponent(part.slice(i + 1));
        }
      } catch (e) { /* abaikan hash rusak */ }
      return h;
    },
    saveHash() {
      if (!_map) return;
      const c = _map.getCenter();
      const parts = ['c=' + c.lat.toFixed(5) + ',' + c.lng.toFixed(5) + ',' + _map.getZoom(),
        'b=' + this.currentBase];
      const ll = [];
      for (const g of this.groups) {
        for (const l of g.layers) {
          if (l.checked) ll.push(l.id + ':' + (l.opacity ?? 100));
        }
      }
      if (ll.length) parts.push('l=' + ll.join(','));
      history.replaceState(null, '', '#' + parts.join('&'));
    },
    shareLink() {
      this.saveHash();
      const url = location.href;
      const done = () => this.toast('Tautan peta disalin.');
      if (navigator.clipboard && navigator.clipboard.writeText) {
        navigator.clipboard.writeText(url).then(done).catch(() => this.shareFallback(url));
      } else {
        this.shareFallback(url);
      }
    },
    shareFallback(url) {
      try {
        const ta = document.createElement('textarea');
        ta.value = url;
        ta.style.position = 'fixed';
        ta.style.opacity = '0';
        document.body.appendChild(ta);
        ta.select();
        if (document.execCommand('copy')) {
          document.body.removeChild(ta);
          this.toast('Tautan peta disalin.');
          return;
        }
        document.body.removeChild(ta);
      } catch (e) { /* lanjut ke prompt manual */ }
      const manual = prompt('Salin tautan peta ini:', url);
      if (manual !== null) this.toast('Tautan siap dibagikan.');
    },

    // ---- Ukur manual: jarak (haversine) + luas geodesik (spherical excess) ----
    // Tanpa library tambahan: hanya butuh dua operasi ini.
    hav(a, b) {
      const R = 6378137;
      const dLa = (b.lat - a.lat) * Math.PI / 180;
      const dLo = (b.lng - a.lng) * Math.PI / 180;
      const s = Math.sin(dLa / 2) * Math.sin(dLa / 2)
        + Math.cos(a.lat * Math.PI / 180) * Math.cos(b.lat * Math.PI / 180)
        * Math.sin(dLo / 2) * Math.sin(dLo / 2);
      return 2 * R * Math.asin(Math.sqrt(s));
    },
    geodesicArea(latlngs) {
      const R = 6378137;
      const d2r = Math.PI / 180;
      let area = 0;
      if (latlngs.length > 2) {
        for (let i = 0; i < latlngs.length; i++) {
          const p1 = latlngs[i], p2 = latlngs[(i + 1) % latlngs.length];
          area += (p2.lng - p1.lng) * d2r * (2 + Math.sin(p1.lat * d2r) + Math.sin(p2.lat * d2r));
        }
        area = area * R * R / 2;
      }
      return Math.abs(area);
    },
    fmtDist(m) {
      return m < 1000
        ? m.toLocaleString('id-ID', { maximumFractionDigits: 1 }) + ' m'
        : (m / 1000).toLocaleString('id-ID', { maximumFractionDigits: 2 }) + ' km';
    },
    fmtArea(m2) {
      if (m2 < 10000) return m2.toLocaleString('id-ID', { maximumFractionDigits: 1 }) + ' m²';
      if (m2 < 1000000) {
        return (m2 / 10000).toLocaleString('id-ID', { maximumFractionDigits: 2 }) + ' ha'
          + ' (' + m2.toLocaleString('id-ID', { maximumFractionDigits: 0 }) + ' m²)';
      }
      return (m2 / 1000000).toLocaleString('id-ID', { maximumFractionDigits: 2 }) + ' km²';
    },
    startMeasure(mode) {
      if (this.measure.active && this.measure.mode === mode) {
        this.measureClear();
        return;
      }
      this.measureClear();
      this.measure.active = true;
      this.measure.mode = mode;
      this.measure.result = '';
      if (!_measureLayer) {
        _measureLayer = L.layerGroup([], { pane: 'markerPane' }).addTo(_map);
      }
      _map.getContainer().style.cursor = 'crosshair';
      _map.doubleClickZoom.disable();
      this._mClick = (e) => this.measureAdd(e.latlng);
      this._mDbl = () => this.measureFinish();
      this._mEsc = (e) => { if (e.key === 'Escape') this.measureClear(); };
      _map.on('click', this._mClick);
      _map.on('dblclick', this._mDbl);
      document.addEventListener('keydown', this._mEsc);
    },
    measureAdd(ll) {
      this.measure.points.push(ll);
      this.measureDraw();
    },
    measureDraw() {
      const pts = this.measure.points;
      _measureLayer.clearLayers();
      if (pts.length === 0) return;
      // interactive:false agar garis hasil ukur tidak menghalangi klik popup layer di bawahnya
      const opts = { color: '#e11d48', weight: 3, pane: 'markerPane', interactive: false };
      if (this.measure.mode === 'area' && pts.length >= 3) {
        L.polygon(pts, { ...opts, fillOpacity: 0.15 }).addTo(_measureLayer);
      } else if (pts.length >= 2 || this.measure.mode === 'distance') {
        L.polyline(pts, opts).addTo(_measureLayer);
      }
      L.circleMarker(pts[pts.length - 1], { radius: 4, color: '#e11d48', fillColor: '#fff', fillOpacity: 1, pane: 'markerPane', interactive: false }).addTo(_measureLayer);
      this.measure.result = this.measureText();
    },
    measureText() {
      const pts = this.measure.points;
      if (this.measure.mode === 'distance') {
        let total = 0;
        for (let i = 1; i < pts.length; i++) total += this.hav(pts[i - 1], pts[i]);
        return 'Jarak: ' + this.fmtDist(total);
      }
      if (pts.length < 3) return 'Tambahkan minimal 3 titik untuk luas…';
      return 'Luas: ' + this.fmtArea(this.geodesicArea(pts));
    },
    measureFinish() {
      if (!this.measure.active || this.measure.points.length === 0) {
        this.measureClear();
        return;
      }
      this.measure.result = this.measureText();
      this.measureStop();
    },
    measureStop() {
      this.measure.active = false;
      _map.getContainer().style.cursor = '';
      if (_map.doubleClickZoom) _map.doubleClickZoom.enable();
      if (this._mClick) _map.off('click', this._mClick);
      if (this._mDbl) _map.off('dblclick', this._mDbl);
      if (this._mEsc) document.removeEventListener('keydown', this._mEsc);
      this._mClick = this._mDbl = this._mEsc = null;
    },
    measureClear() {
      this.measureStop();
      this.measure.points = [];
      this.measure.result = '';
      if (_measureLayer) _measureLayer.clearLayers();
    },

    // ---- Export GeoJSON layer aktif (versi tampilan dari server) ----
    exportLayers() {
      const out = [];
      for (const g of this.groups) {
        for (const l of g.layers) {
          if (l.checked && l.loaded) out.push(l);
        }
      }
      if (this.exportId && !out.some((l) => l.id === this.exportId)) this.exportId = null;
      if (!this.exportId && out.length === 1) this.exportId = out[0].id;
      return out;
    },
    async downloadExport() {
      const layer = this.exportLayers().find((l) => l.id === this.exportId);
      if (!layer) return;
      try {
        const res = await fetch('api/geojson.php?layer=' + layer.id);
        if (!res.ok) throw new Error('HTTP ' + res.status);
        const blob = await res.blob();
        const a = document.createElement('a');
        a.href = URL.createObjectURL(blob);
        a.download = String(layer.nama).replace(/[^\w\-]+/g, '_') + '.geojson';
        document.body.appendChild(a);
        a.click();
        setTimeout(() => {
          URL.revokeObjectURL(a.href);
          a.remove();
        }, 1000);
        this.exportOpen = false;
        this.toast('Layer ' + layer.nama + ' diunduh.');
      } catch (e) {
        this.toast('Gagal mengunduh layer.');
      }
    },

    // ---- Cetak: window.print + header/legenda khusus cetak ----
    doPrint() {
      this.buildPrintLegend();
      window.print();
    },
    buildPrintLegend() {
      document.getElementById('print-date').textContent =
        new Date().toLocaleDateString('id-ID', { day: 'numeric', month: 'long', year: 'numeric' });
      document.getElementById('print-scale').textContent = this.scaleText || '-';
      let h = '';
      for (const g of this.groups) {
        for (const l of g.layers) {
          if (!l.checked || !l.loaded) continue;
          h += '<div style="margin:6px 0;"><b>' + this.esc(l.nama) + '</b>';
          for (const c of (l.classes || [])) {
            h += '<div><span class="legend-swatch" style="background:' + c.warna + ';display:inline-block;vertical-align:middle;"></span> '
              + this.esc(c.label) + '</div>';
          }
          h += '</div>';
        }
      }
      document.getElementById('print-legend').innerHTML = h || '<i>Tidak ada layer aktif.</i>';
    },

    goHome() {
      if (_map && this.homeView) _map.setView([this.homeView.lat, this.homeView.lng], this.homeView.zoom);
    },
    activeCount() {
      let n = 0;
      for (const g of this.groups) for (const l of g.layers) if (l.checked) n++;
      return n;
    },
    totalCount() {
      let n = 0;
      for (const g of this.groups) n += g.layers.length;
      return n;
    },
  };
}
</script>
</body>
</html>
