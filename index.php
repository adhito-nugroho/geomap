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
<script src="https://cdn.tailwindcss.com"></script>
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css">
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
<link rel="stylesheet" href="https://unpkg.com/leaflet-minimap@3.6.1/dist/Control.MiniMap.min.css">
<script src="https://unpkg.com/leaflet-minimap@3.6.1/dist/Control.MiniMap.min.js"></script>
<script src="https://unpkg.com/alpinejs@3.13.5/dist/cdn.min.js" defer></script>
<style>
  /* Peta mengisi seluruh ruang di bawah top bar */
  #map { position: absolute; inset: 0; z-index: 0; }
  /* Swatch legenda: warnanya diisi via JS dari database (bukan hardcode CSS) */
  .legend-swatch { width: 18px; height: 14px; border: 1px solid #9ca3af; flex-shrink: 0; }
  /* Tabel popup identify (gaya sendiri agar tidak tergantung JIT Tailwind di dalam popup) */
  .identify-table { border-collapse: collapse; font-size: 12px; }
  .identify-table th, .identify-table td { border: 1px solid #d1d5db; padding: 3px 6px; text-align: left; vertical-align: top; }
  .identify-table th { background: #f3f4f6; white-space: nowrap; }
  /* Kontrol basemap (kanan atas) digeser ke bawah kotak pencarian */
  .leaflet-top.leaflet-right .leaflet-control-layers { margin-top: 56px; }
  /* Kontrol kiri bawah (skala, dropdown skala, minimap) mengalah pada panel layer */
  #map-wrap .leaflet-bottom.leaflet-left { left: 0; transition: left .2s ease; }
  #map-wrap.panel-open .leaflet-bottom.leaflet-left { left: 316px; }
  @media (max-width: 767px) {
    #map-wrap.panel-open .leaflet-bottom.leaflet-left { left: 0; }
  }
  input[type="range"] { accent-color: #047857; }
</style>
</head>
<body class="h-screen flex flex-col overflow-hidden" x-data="webgis()">
<!-- Alpine v3 otomatis memanggil method init() di x-data, jadi atribut pemicu
     ganda sudah dihapus (dulu menyebabkan init jalan dua kali) -->

<!-- ===== Top bar: logo + judul di kiri; info, home, login di kanan ===== -->
<header class="bg-emerald-900 text-white flex items-center justify-between px-3 py-2 z-20 shrink-0">
  <div class="flex items-center gap-2 min-w-0">
    <!-- Branding CDK Wilayah Bojonegoro: logo dalam kotak putih + teks bertingkat -->
    <div class="bg-white rounded-xl p-1 shrink-0">
      <img src="assets/logo.png" alt="Logo CDK Wilayah Bojonegoro" class="w-9 h-9 rounded-lg object-cover" onerror="this.parentElement.style.display='none'">
    </div>
    <div class="min-w-0 leading-tight">
      <p class="font-bold text-sm sm:text-base leading-tight">CDK Wilayah</p>
      <p class="font-bold text-sm sm:text-base leading-tight">Bojonegoro</p>
      <p class="text-[10px] sm:text-[11px] text-emerald-200 leading-tight">DISHUT PROV. JATIM</p>
      <p class="text-[10px] sm:text-[11px] text-emerald-200 truncate leading-tight" x-text="mapTitle">Memuat…</p>
    </div>
  </div>
  <div class="flex items-center gap-1 sm:gap-2">
    <button @click="infoOpen = true" title="Info peta"
            class="px-2 py-1 rounded hover:bg-emerald-700 text-sm">ⓘ <span class="hidden sm:inline">Info</span></button>
    <button @click="goHome()" title="Kembali ke tampilan awal"
            class="px-2 py-1 rounded hover:bg-emerald-700 text-sm">⌂ <span class="hidden sm:inline">Home</span></button>
    <a href="admin/login.php" title="Login admin"
       class="px-2 py-1 rounded hover:bg-emerald-700 text-sm">👤 <span class="hidden sm:inline">Login</span></a>
  </div>
</header>

<!-- ===== Area peta + panel layer ===== -->
<div id="map-wrap" class="relative flex-1" :class="{ 'panel-open': panelOpen }">

  <div id="map"></div>

  <!-- Kotak pencarian lokasi (kanan atas peta, Nominatim via /api/search.php) -->
  <div class="absolute top-2 right-2 z-10 w-64 max-w-[70vw]">
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
  return {
    map: null,
    homeView: null,
    mapTitle: 'Memuat…',
    groups: [],       // tree dari /api/map.php, tiap layer dapat flag checked/loading
    leaflets: {},     // cache id layer -> L.GeoJSON (lazy load, fetch sekali saja)
    panelOpen: window.innerWidth >= 768,
    infoOpen: false,
    error: '',
    fittedOnce: false, // fitBounds otomatis hanya sekali, saat peta masih di pusat default
    filterText: '',    // filter layers (client-side)
    hiddenFields: [],  // field teknis popup identify, dari database (lowercase)
    searchQuery: '',
    searchResults: [],
    searchNote: '',
    searchTimer: null,
    locateLayer: null,
    scaleSelect: null,

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
        return;
      }
      this.mapTitle = cfg.map.judul || 'Peta Persetujuan Perhutanan Sosial';
      document.title = this.mapTitle;
      this.homeView = { lat: cfg.map.center_lat, lng: cfg.map.center_lng, zoom: cfg.map.zoom };
      this.hiddenFields = (cfg.hidden_fields || []).map((s) => String(s).toLowerCase());

      // Basemap: OSM default; Esri satelit + OSM Humanitarian sebagai opsi
      const osm = L.tileLayer('https://tile.openstreetmap.org/{z}/{x}/{y}.png',
        { maxZoom: 19, attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a>' });
      const esri = L.tileLayer('https://server.arcgisonline.com/ArcGIS/rest/services/World_Imagery/MapServer/tile/{z}/{y}/{x}',
        { maxZoom: 19, attribution: 'Imagery &copy; Esri & contributors' });
      const hot = L.tileLayer('https://{s}.tile.openstreetmap.fr/hot/{z}/{x}/{y}.png',
        { maxZoom: 19, attribution: '&copy; OpenStreetMap contributors, Tiles style by HOT' });
      const basemaps = { 'OpenStreetMap': osm, 'Esri Satelit': esri, 'OSM Humanitarian': hot };

      // Guard instance peta: jika sudah ada, hentikan (anti "Map container is already initialized")
      if (this.map) return;
      this.map = L.map('map', {
        center: [this.homeView.lat, this.homeView.lng],
        zoom: this.homeView.zoom,
        zoomControl: false, // diganti kontrol zoom kustom kanan bawah (Fase 3)
        preferCanvas: true, // performa untuk poligon besar
        layers: [(cfg.map.basemap_default === 'esri' ? esri : (cfg.map.basemap_default === 'hot' ? hot : osm))],
      });
      L.control.layers(basemaps).addTo(this.map);
      L.control.zoom({ position: 'bottomright' }).addTo(this.map);

      // Tombol locate me / zoom extent / fullscreen (kanan bawah, vanilla Leaflet)
      const self = this;
      const ToolBtns = L.Control.extend({
        onAdd() {
          const d = L.DomUtil.create('div', 'leaflet-bar leaflet-control');
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
      this.map.addControl(new ToolBtns({ position: 'bottomright' }));

      // Scale bar metrik + dropdown skala + minimap (kiri bawah)
      L.control.scale({ metric: true, imperial: false, position: 'bottomleft' }).addTo(this.map);
      this.initScaleControl();
      if (L.Control && L.Control.MiniMap) {
        const osm2 = L.tileLayer('https://tile.openstreetmap.org/{z}/{x}/{y}.png', {});
        this.map.addControl(new L.Control.MiniMap(osm2, {
          position: 'bottomleft', width: 140, height: 140, toggleDisplay: true,
        }));
      }
      document.addEventListener('fullscreenchange', () => {
        if (this.map) setTimeout(() => this.map.invalidateSize(), 200);
      });
      // min_zoom: saat zoom berubah, muat/gambar layer yang masuk rentang
      this.map.on('zoomend', () => {
        for (const g of this.groups) {
          for (const l of g.layers) {
            if (l.checked) this.toggleLayer(l);
          }
        }
      });

      // Siapkan tree + centang default dari visible_default, lalu lazy-load yang aktif
      this.groups = cfg.groups || [];
      for (const g of this.groups) {
        for (const l of g.layers) {
          l.checked = l.visible_default === 1;
          l.loading = false;
          l.loadError = '';
          l.opacity = (l.opacity_default ?? 100);
          l.min_zoom = (l.min_zoom ?? 0);
        }
      }
      for (const g of this.groups) {
        for (const l of g.layers) {
          if (l.checked) await this.toggleLayer(l);
        }
      }
    },

    // Cari kelas warna untuk satu fitur berdasarkan style_field (data dari DB)
    styleFor(layer, feature) {
      const classes = layer.classes || [];
      let cls = null;
      if (layer.style_mode === 'categorized' && layer.style_field) {
        const v = feature.properties ? String(feature.properties[layer.style_field] ?? '') : '';
        cls = classes.find(c => String(c.nilai) === v) || null;
      } else {
        cls = classes.find(c => c.nilai === '__single__') || classes[0] || null;
      }
      // Fallback netral bila atribut tidak cocok kelas mana pun
      const fill = cls ? cls.warna : '#9ca3af';
      const edge = (cls && cls.outline_warna) ? cls.outline_warna : '#ffffff';
      const f = ((layer.opacity ?? layer.opacity_default ?? 100)) / 100;
      return { fillColor: fill, color: edge, weight: 1, fillOpacity: 0.65 * f, opacity: f };
    },

    // Terapkan opacity slider ke layer yang sudah dimuat (fill + garis)
    applyOpacity(layer) {
      const gl = this.leaflets[layer.id];
      if (!gl) return;
      const f = ((layer.opacity ?? 100)) / 100;
      gl.setStyle({ fillOpacity: 0.65 * f, opacity: f });
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

    // Tabel atribut popup identify; field teknis disembunyikan (daftar dari database)
    popupHtml(props) {
      const rows = Object.entries(props || {})
        .filter(([k]) => !this.hiddenFields.includes(String(k).toLowerCase()));
      if (rows.length === 0) return '<i>Tidak ada atribut tampilan.</i>';
      return '<table class="identify-table"><tbody>' + rows.map(([k, v]) => (
        '<tr><th>' + this.esc(k) + '</th><td>'
        + this.esc(typeof v === 'object' ? JSON.stringify(v) : v) + '</td></tr>'
      )).join('') + '</tbody></table>';
    },

    // Layer aktif = dicentang DAN zoom peta sudah mencapai min_zoom (dari DB)
    layerActive(layer) {
      return layer.checked && this.map && this.map.getZoom() >= (layer.min_zoom ?? 0);
    },

    // Toggle visibilitas; fetch GeoJSON hanya saat pertama kali dibutuhkan (lazy).
    // Di bawah min_zoom: tidak di-fetch maupun digambar.
    async toggleLayer(layer) {
      if (layer.loading) return; // cegah fetch ganda saat zoom cepat
      if (this.layerActive(layer) && !this.leaflets[layer.id]) {
        layer.loading = true;
        layer.loadError = '';
        try {
          const res = await fetch('api/geojson.php?layer=' + layer.id);
          if (!res.ok) throw new Error('HTTP ' + res.status);
          const gj = await res.json();
          this.leaflets[layer.id] = L.geoJSON(gj, {
            style: (f) => this.styleFor(layer, f),
            // Identify: klik fitur = popup tabel atribut
            onEachFeature: (f, ly) => {
              // maxWidth menyesuaikan layar HP agar popup tidak meluap
              ly.bindPopup(this.popupHtml(f.properties), { maxWidth: Math.min(300, window.innerWidth - 48) });
            },
          });
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
    },

    // Fallback viewport: setelah layer pertama dimuat, jika peta masih di pusat
    // default (dari tabel maps), zoom ke gabungan bounds layer yang aktif.
    // Tidak ada koordinat hardcode; pusat default dibaca dari homeView (DB).
    maybeFit() {
      if (this.fittedOnce || !this.map || !this.homeView) return;
      const c = this.map.getCenter();
      const atHome = Math.abs(c.lat - this.homeView.lat) < 1e-6
        && Math.abs(c.lng - this.homeView.lng) < 1e-6
        && this.map.getZoom() === this.homeView.zoom;
      if (!atHome) { this.fittedOnce = true; return; } // pengguna sudah menggeser: jangan ganggu
      const bounds = L.latLngBounds([]);
      let any = false;
      for (const g of this.groups) {
        for (const l of g.layers) {
          const gl = this.leaflets[l.id];
          if (gl && l.checked) { bounds.extend(gl.getBounds()); any = true; }
        }
      }
      if (any && bounds.isValid()) {
        this.fittedOnce = true;
        this.map.fitBounds(bounds, { padding: [24, 24] });
      }
    },

    // Susun ulang z-order sesuai urutan tree (atas tree = atas peta).
    // Layer di bawah min_zoom disembunyikan walau dicentang.
    reorder() {
      if (!this.map) return;
      const ordered = [];
      for (const g of this.groups) {
        for (const l of g.layers) {
          const gl = this.leaflets[l.id];
          if (!gl) continue;
          if (this.layerActive(l)) ordered.push(gl);
          else if (this.map.hasLayer(gl)) this.map.removeLayer(gl);
        }
      }
      ordered.forEach((gl, i) => {
        if (!this.map.hasLayer(gl)) gl.addTo(this.map);
        gl.bringToBack(); // yang terakhir di-bringToBack = paling bawah
      });
    },

    // ---- Pencarian lokasi (Nominatim via /api/search.php, dibatasi Indonesia) ----
    onSearchInput() {
      clearTimeout(this.searchTimer);
      const q = this.searchQuery.trim();
      if (q.length < 3) { this.searchResults = []; this.searchNote = ''; return; }
      this.searchNote = 'Mencari…';
      this.searchTimer = setTimeout(async () => {
        try {
          const res = await fetch('api/search.php?q=' + encodeURIComponent(q));
          const data = await res.json();
          if (!res.ok) { this.searchNote = data.error || 'Pencarian gagal.'; this.searchResults = []; return; }
          this.searchResults = data;
          this.searchNote = data.length ? '' : 'Tidak ditemukan di Indonesia.';
        } catch (e) {
          this.searchNote = 'Pencarian gagal.';
          this.searchResults = [];
        }
      }, 400);
    },
    goResult(r) {
      this.searchResults = [];
      this.searchNote = '';
      if (!this.map) return;
      if (r.boundingbox) {
        // Nominatim: boundingbox = [selatan, utara, barat, timur]
        const s = +r.boundingbox[0], n = +r.boundingbox[1], w = +r.boundingbox[2], e = +r.boundingbox[3];
        this.map.fitBounds([[s, w], [n, e]], { padding: [24, 24] });
      } else {
        this.map.setView([+r.lat, +r.lon], 14);
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
      if (this.locateLayer) { this.map.removeLayer(this.locateLayer); this.locateLayer = null; }
      this.map.locate({ setView: true, maxZoom: 14 });
      this.map.once('locationfound', (e) => {
        this.locateLayer = L.circleMarker(e.latlng, {
          radius: 8, color: '#16a34a', fillColor: '#4ade80', fillOpacity: 0.9,
        }).addTo(this.map).bindPopup('Lokasi Anda').openPopup();
      });
      this.map.once('locationerror', () => {
        this.error = 'Tidak dapat memperoleh lokasi Anda.';
      });
    },
    zoomExtent() {
      if (!this.map) return;
      const bounds = L.latLngBounds([]);
      let any = false;
      for (const g of this.groups) {
        for (const l of g.layers) {
          const gl = this.leaflets[l.id];
          if (gl && l.checked) { bounds.extend(gl.getBounds()); any = true; }
        }
      }
      if (any && bounds.isValid()) this.map.fitBounds(bounds, { padding: [24, 24] });
      else this.goHome();
    },
    toggleFullscreen() {
      const el = document.getElementById('map-wrap');
      if (!document.fullscreenElement) { if (el.requestFullscreen) el.requestFullscreen(); }
      else if (document.exitFullscreen) document.exitFullscreen();
      setTimeout(() => { if (this.map) this.map.invalidateSize(); }, 300);
    },

    // ---- Dropdown skala (96 dpi): N = meterPerPixel / 0.0002645833 ----
    zoomForScale(n) {
      const lat = this.map.getCenter().lat * Math.PI / 180;
      const z = Math.log2(156543.03392 * Math.cos(lat) / (n * 0.0002645833));
      return Math.max(0, Math.min(19, Math.round(z)));
    },
    updateScaleLabel() {
      const sel = this.scaleSelect;
      if (!sel || !this.map) return;
      const lat = this.map.getCenter().lat * Math.PI / 180;
      const n = Math.round(156543.03392 * Math.cos(lat) / Math.pow(2, this.map.getZoom()) / 0.0002645833);
      sel.innerHTML = '';
      const cur = document.createElement('option');
      cur.textContent = '≈ 1:' + n.toLocaleString('id-ID');
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
      const div = L.DomUtil.create('div', 'leaflet-bar leaflet-control');
      div.style.background = '#fff';
      div.style.padding = '2px 4px';
      const sel = L.DomUtil.create('select', '', div);
      sel.title = 'Skala peta';
      sel.style.fontSize = '12px';
      sel.style.maxWidth = '130px';
      this.scaleSelect = sel;
      sel.addEventListener('change', () => {
        const n = parseFloat(sel.value);
        if (n > 0 && this.map) this.map.setZoom(this.zoomForScale(n));
      });
      L.DomEvent.disableClickPropagation(div);
      L.DomEvent.disableScrollPropagation(div);
      const C = L.Control.extend({ onAdd: () => div });
      this.map.addControl(new C({ position: 'bottomleft' }));
      this.map.on('moveend zoomend', () => this.updateScaleLabel());
      this.updateScaleLabel();
    },

    goHome() {
      if (this.map && this.homeView) this.map.setView([this.homeView.lat, this.homeView.lng], this.homeView.zoom);
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
