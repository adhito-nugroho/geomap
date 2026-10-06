<?php
// Admin Fase 4: CRUD group/layer/kelas, upload GeoJSON, style kategori otomatis,
// reorder drag & drop, pengaturan peta. Login memakai admin/login.php (Fase 1).
declare(strict_types=1);
require_once __DIR__ . '/../includes/auth.php';
require_admin();

$pdo = db();
$map = $pdo->query('SELECT id, judul, center_lat, center_lng, zoom, basemap_default FROM maps WHERE id = 1')->fetch();
$user = current_user();
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Admin — WebGIS Perhutanan Sosial</title>
<link rel="icon" href="data:image/svg+xml,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 100 100'><text y='.9em' font-size='90'>🌲</text></svg>">
<script src="https://cdn.tailwindcss.com"></script>
<script src="https://cdn.jsdelivr.net/npm/sortablejs@1.15.2/Sortable.min.js"></script>
<!-- Konversi ZIP Shapefile di browser: JSZip (baca zip) + shpjs (parse) + proj4 (reproyeksi) -->
<script src="https://cdn.jsdelivr.net/npm/jszip@3.10.1/dist/jszip.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/shpjs@6.1.0/dist/shp.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/proj4@2.11.0/dist/proj4.js"></script>
<script src="https://unpkg.com/alpinejs@3.13.5/dist/cdn.min.js" defer></script>
</head>
<body class="min-h-screen bg-gray-100" x-data="adminApp()" x-init="load(); loadMapForm()">
<header class="bg-emerald-900 text-white px-4 py-3 flex flex-wrap items-center justify-between gap-2">
  <div class="flex items-center gap-2">
    <span class="bg-white rounded-xl p-1 shrink-0">
      <img src="../assets/logo.png" alt="Logo CDK Wilayah Bojonegoro" class="w-7 h-7 rounded-lg object-cover" onerror="this.parentElement.style.display='none'">
    </span>
    <h1 class="font-bold">Admin WebGIS <span class="font-normal text-emerald-200">/ CDK Wilayah Bojonegoro</span></h1>
  </div>
  <div class="flex items-center gap-3 text-sm">
    <a href="../index.php" class="underline">Lihat viewer</a>
    <span><?= e($user['username']) ?> (<?= e($user['role']) ?>)</span>
    <form method="post" action="logout.php">
      <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
      <button class="bg-emerald-700 hover:bg-emerald-600 rounded px-3 py-1">Keluar</button>
    </form>
  </div>
</header>

<main class="max-w-5xl mx-auto p-4 space-y-4">
  <!-- Pesan sukses/error -->
  <div x-show="msg" :class="msgErr ? 'bg-red-50 border-red-200 text-red-700' : 'bg-emerald-50 border-emerald-200 text-emerald-800'"
       class="rounded border px-3 py-2 text-sm" x-text="msg"></div>

  <!-- Pengaturan peta -->
  <section class="bg-white rounded shadow p-4">
    <h2 class="font-semibold mb-2">Pengaturan peta</h2>
    <div class="grid sm:grid-cols-2 gap-3 text-sm">
      <label class="sm:col-span-2 block">Judul
        <input x-model="mapForm.judul" class="mt-1 w-full border rounded px-2 py-1.5"></label>
      <label class="block">Center lat (-90…90)
        <input x-model="mapForm.center_lat" class="mt-1 w-full border rounded px-2 py-1.5"></label>
      <label class="block">Center lng (-180…180)
        <input x-model="mapForm.center_lng" class="mt-1 w-full border rounded px-2 py-1.5"></label>
      <label class="block">Zoom (0…19)
        <input type="number" min="0" max="19" x-model="mapForm.zoom" class="mt-1 w-full border rounded px-2 py-1.5"></label>
      <label class="block">Basemap default
        <select x-model="mapForm.basemap_default" class="mt-1 w-full border rounded px-2 py-1.5">
          <option value="osm">OpenStreetMap</option>
          <option value="esri">Esri Satelit</option>
          <option value="hot">OSM Humanitarian</option>
        </select></label>
    </div>
    <button @click="saveMap()" class="mt-3 bg-emerald-700 text-white rounded px-4 py-1.5 text-sm">Simpan pengaturan</button>
  </section>

  <!-- Group & layer -->
  <section class="bg-white rounded shadow p-4">
    <div class="flex items-center justify-between mb-2">
      <h2 class="font-semibold">Group &amp; layer <span class="font-normal text-xs text-gray-500">(geser ↕ untuk urutan)</span></h2>
      <button @click="addGroup()" class="bg-emerald-700 text-white rounded px-3 py-1 text-sm">+ Group</button>
    </div>
    <div x-show="orderNote" class="text-xs text-gray-500 mb-2" x-text="orderNote"></div>
    <div id="group-list" class="space-y-3">
      <template x-for="g in groups" :key="g.id">
        <div class="border rounded" :data-gid="g.id">
          <div class="flex items-center gap-2 bg-gray-50 px-2 py-1.5 rounded-t">
            <span class="drag-handle cursor-move text-gray-400" title="Geser group">↕</span>
            <template x-if="!g.editing">
              <span class="font-semibold text-sm flex-1" x-text="g.nama"></span>
            </template>
            <template x-if="g.editing">
              <input x-model="g.nama" @keydown.enter="saveGroup(g)" class="flex-1 border rounded px-2 py-1 text-sm">
            </template>
            <template x-if="!g.editing">
              <button @click="g.editing = true" class="text-xs text-emerald-700 underline">Ubah</button>
            </template>
            <template x-if="g.editing">
              <span class="flex gap-1">
                <button @click="saveGroup(g)" class="text-xs bg-emerald-700 text-white rounded px-2 py-0.5">Simpan</button>
                <button @click="refresh()" class="text-xs underline">Batal</button>
              </span>
            </template>
            <button @click="openEditor(null, g.id)" class="text-xs text-emerald-700 underline">+ Layer</button>
            <button @click="delGroup(g)" class="text-xs text-red-600 underline">Hapus</button>
          </div>
          <ul class="layer-list divide-y" :data-gid="g.id">
            <template x-for="l in g.layers" :key="l.id">
              <li class="flex items-center gap-2 px-2 py-1.5 text-sm" :data-lid="l.id">
                <span class="drag-handle cursor-move text-gray-400" title="Geser layer">↕</span>
                <span class="flex-1">
                  <span class="font-medium" x-text="l.nama"></span>
                  <span class="ml-1 text-xs text-gray-500" x-text="'(' + l.style_mode + (l.style_field ? ':' + l.style_field : '') + ')'"></span>
                  <span x-show="l.visible_default !== 1" class="ml-1 text-xs bg-gray-200 rounded px-1">off</span>
                  <span x-show="l.aktif !== 1" class="ml-1 text-xs bg-red-200 rounded px-1">nonaktif</span>
                </span>
                <button @click="openEditor(l.id)" class="text-xs text-emerald-700 underline">Edit</button>
                <button @click="delLayer(l)" class="text-xs text-red-600 underline">Hapus</button>
              </li>
            </template>
            <li x-show="g.layers.length === 0" class="px-2 py-1.5 text-xs text-gray-400">Belum ada layer.</li>
          </ul>
        </div>
      </template>
    </div>
  </section>
</main>

<!-- Modal editor layer -->
<div x-show="ed.open" class="fixed inset-0 z-30 bg-black/40 p-4 overflow-y-auto" x-transition>
  <div class="bg-white rounded shadow-xl max-w-2xl mx-auto p-4 space-y-4 text-sm" @click.away="">
    <div class="flex items-center justify-between">
      <h3 class="font-bold" x-text="ed.id ? 'Edit layer' : 'Layer baru'"></h3>
      <button @click="ed.open = false" class="px-2 py-0.5 rounded hover:bg-gray-200">✕</button>
    </div>

    <!-- Metadata -->
    <div class="grid sm:grid-cols-2 gap-3">
      <label class="block">Nama layer
        <input x-model="ed.nama" class="mt-1 w-full border rounded px-2 py-1.5"></label>
      <label class="block">Group
        <select x-model="ed.group_id" class="mt-1 w-full border rounded px-2 py-1.5">
          <template x-for="g in groups" :key="g.id">
            <option :value="g.id" x-text="g.nama"></option>
          </template>
        </select></label>
      <label class="block">Tipe geometri
        <select x-model="ed.tipe_geom" class="mt-1 w-full border rounded px-2 py-1.5">
          <option value="polygon">polygon</option>
          <option value="line">line</option>
          <option value="point">point</option>
        </select></label>
      <label class="block">Opacity default (0–100)
        <input type="number" min="0" max="100" x-model="ed.opacity_default" class="mt-1 w-full border rounded px-2 py-1.5"></label>
      <label class="block">Min zoom (0–19, 0 = selalu)
        <input type="number" min="0" max="19" x-model="ed.min_zoom" class="mt-1 w-full border rounded px-2 py-1.5"></label>
      <label class="block">Outline color (kosong = ikut kelas)
        <input x-model="ed.outline_color" placeholder="#475569" class="mt-1 w-full border rounded px-2 py-1.5 font-mono"></label>
      <label class="block">Outline weight (0.3–4)
        <input type="number" min="0.3" max="4" step="0.1" x-model="ed.outline_weight" class="mt-1 w-full border rounded px-2 py-1.5"></label>
      <label class="block">Outline opacity (0–1)
        <input type="number" min="0" max="1" step="0.05" x-model="ed.outline_opacity" class="mt-1 w-full border rounded px-2 py-1.5"></label>
      <label class="block">Fill opacity (0–1)
        <input type="number" min="0" max="1" step="0.05" x-model="ed.fill_opacity" class="mt-1 w-full border rounded px-2 py-1.5"></label>
      <label class="flex items-center gap-2"><input type="checkbox" x-model="ed.fill_enabled" class="w-4 h-4"> Fill (isi poligon)</label>
      <label class="block">Style
        <select x-model="ed.style_mode" class="mt-1 w-full border rounded px-2 py-1.5">
          <option value="single">single (1 warna)</option>
          <option value="categorized">categorized (per field)</option>
        </select></label>
      <label class="block" x-show="ed.style_mode === 'categorized'">Style field
        <input x-model="ed.style_field" list="prop-list" placeholder="mis. skema" class="mt-1 w-full border rounded px-2 py-1.5">
        <datalist id="prop-list">
          <template x-for="p in ed.props" :key="p"><option :value="p"></option></template>
        </datalist></label>
      <label class="flex items-center gap-2"><input type="checkbox" x-model="ed.visible_default" class="w-4 h-4"> Visible default</label>
      <label class="flex items-center gap-2"><input type="checkbox" x-model="ed.aktif" class="w-4 h-4"> Aktif</label>
    </div>
    <div class="flex gap-2">
      <button @click="saveLayer()" class="bg-emerald-700 text-white rounded px-4 py-1.5">Simpan layer</button>
      <button x-show="ed.id" @click="delLayer({id: ed.id, nama: ed.nama})" class="text-red-600 underline">Hapus layer</button>
    </div>

    <!-- Upload GeoJSON -->
    <div x-show="ed.id" class="border-t pt-3 space-y-2">
      <h4 class="font-semibold">Upload GeoJSON</h4>
      <p class="text-xs text-gray-500">File saat ini: <code x-text="ed.file_geojson"></code></p>
      <div class="flex flex-wrap items-center gap-2">
        <input type="file" id="up-file" accept=".geojson,.json" class="text-xs">
        <button @click="uploadGeo()" class="bg-emerald-700 text-white rounded px-3 py-1.5">Upload</button>
      </div>
      <!-- Konversi ZIP Shapefile di browser, dikirim sebagai .geojson ke endpoint yang sama -->
      <div class="border-t pt-2 mt-1 space-y-2">
        <h4 class="font-semibold">atau konversi ZIP Shapefile <span class="font-normal text-xs text-gray-500">(di browser)</span></h4>
        <div class="flex flex-wrap items-center gap-2">
          <input type="file" id="zip-file" accept=".zip" class="text-xs">
          <button @click="convertZip()" :disabled="zc.busy" class="bg-emerald-700 text-white rounded px-3 py-1.5 disabled:opacity-50" x-text="zc.busy ? 'Mengonversi…' : 'Konversi'"></button>
        </div>
        <p x-show="zc.msg" :class="zc.msgErr ? 'text-red-600' : 'text-emerald-700'" class="text-xs" x-text="zc.msg"></p>
        <p x-show="zc.prjWarn" class="text-xs text-amber-700" x-text="zc.prjWarn"></p>
        <div x-show="zc.geojson" class="text-xs bg-gray-50 border rounded p-2 space-y-1">
          <p>Fitur: <b x-text="zc.features"></b> · Ukuran hasil: <b x-text="zc.sizeKb"></b> · <span x-text="zc.crsNote"></span></p>
          <p>BBox: <code x-text="zc.bbox"></code></p>
          <p>Field: <code x-text="zc.fields"></code></p>
          <p x-show="zc.sizeWarn" class="text-amber-700" x-text="zc.sizeWarn"></p>
          <div class="flex gap-2">
            <button @click="sendShp()" class="bg-emerald-700 text-white rounded px-3 py-1.5">Kirim ke server</button>
            <button @click="resetZc()" class="underline">Batal</button>
          </div>
        </div>
      </div>
      <div x-show="ed.upload" class="text-xs bg-gray-50 border rounded p-2 space-y-1">
        <p>Fitur: <b x-text="ed.upload.features"></b> · Vertex: <b x-text="ed.upload.vertices"></b>
           · Presisi rata-rata: <b x-text="ed.upload.precision + ' desimal'"></b> ·
           Ukuran: <b :class="sizeColor(ed.upload.size_level)" x-text="ed.upload.size"></b></p>
        <p>BBox: <code x-text="ed.upload.bbox"></code></p>
        <p>Properti: <code x-text="ed.upload.props"></code></p>
        <p x-show="ed.upload.warning" :class="sizeColor(ed.upload.size_level)" x-text="ed.upload.warning"></p>
        <div x-show="ed.upload.columns && ed.upload.columns.length" class="overflow-x-auto">
          <table class="w-full border min-w-[320px]">
            <thead class="bg-gray-100"><tr>
              <th class="border px-1 py-0.5 text-left">Kolom</th>
              <th class="border px-1 py-0.5 text-right">Byte</th>
              <th class="border px-1 py-0.5 text-right">%</th>
            </tr></thead>
            <tbody>
              <template x-for="c in ed.upload.columns" :key="c.name">
                <tr><td class="border px-1 py-0.5" x-text="c.name"></td>
                    <td class="border px-1 py-0.5 text-right tabular-nums" x-text="c.bytes"></td>
                    <td class="border px-1 py-0.5 text-right tabular-nums" x-text="c.pct + ' %'"></td></tr>
              </template>
            </tbody>
          </table>
        </div>
      </div>
    </div>

    <!-- Kolom atribut popup: hanya yang dicentang yang disajikan viewer -->
    <div x-show="ed.id" class="border-t pt-3 space-y-2">
      <h4 class="font-semibold">Atribut yang disertakan di popup</h4>
      <p class="text-xs text-gray-500">Kosongkan semua = sajikan semua kolom. Kolom lain dibuang saat disajikan (file asli tidak diubah).</p>
      <div class="flex flex-wrap items-center gap-2">
        <button @click="loadStats()" class="bg-gray-200 rounded px-3 py-1.5">Muat kolom dari file</button>
        <button @click="savePopup()" class="bg-emerald-700 text-white rounded px-3 py-1.5">Simpan pilihan</button>
      </div>
      <div x-show="ed.popupCols.length" class="text-xs grid sm:grid-cols-2 gap-1 max-h-40 overflow-y-auto border rounded p-2">
        <template x-for="c in ed.popupCols" :key="c.name">
          <label class="flex items-center gap-2 cursor-pointer">
            <input type="checkbox" :value="c.name" x-model="ed.popupSel" class="w-4 h-4 accent-emerald-700">
            <span class="flex-1 truncate" x-text="c.name"></span>
            <span class="text-gray-400 tabular-nums" x-text="c.pct + ' %'"></span>
          </label>
        </template>
      </div>
      <p x-show="ed.popupSel.length" class="text-xs text-gray-500">Dipilih: <code x-text="ed.popupSel.join(', ')"></code></p>
    </div>

    <!-- Style kategori otomatis -->
    <div x-show="ed.id && (ed.style_mode === 'categorized' || singleClass())" class="border-t pt-3 space-y-2">
      <h4 class="font-semibold">Kelas legenda</h4>
      <div x-show="ed.style_mode === 'categorized'" class="flex flex-wrap items-center gap-2">
        <button @click="autoClasses()" class="bg-emerald-700 text-white rounded px-3 py-1.5">Buat kelas dari field</button>
        <span class="text-xs text-gray-500">membaca nilai unik <code x-text="ed.style_field || '(isi style field dulu)'"></code></span>
      </div>
      <p x-show="ed.classWarn" class="text-xs text-amber-700" x-text="ed.classWarn"></p>
      <!-- Scroll horizontal di HP agar kolom tabel tidak remuk -->
      <div class="overflow-x-auto">
      <table class="w-full text-xs border min-w-[560px]">
        <thead class="bg-gray-50">
          <tr><th class="border px-1 py-1">Warna</th><th class="border px-1 py-1">Outline</th>
              <th class="border px-1 py-1 text-left">Nilai</th><th class="border px-1 py-1 text-left">Label</th>
              <th class="border px-1 py-1">Urut</th><th class="border px-1 py-1"></th></tr>
        </thead>
        <tbody>
          <template x-for="c in shownClasses()" :key="c.id">
            <tr>
              <td class="border px-1 py-1 text-center"><input type="color" x-model="c.warna" @change="saveClass(c)"></td>
              <td class="border px-1 py-1 text-center"><input type="color" x-model="c.outline_warna" @change="saveClass(c)"></td>
              <td class="border px-1 py-1"><input x-model="c.nilai" @change="saveClass(c)" :readonly="ed.style_mode !== 'categorized'" :class="ed.style_mode !== 'categorized' ? 'bg-gray-100 text-gray-500' : ''" class="w-full border rounded px-1"></td>
              <td class="border px-1 py-1"><input x-model="c.label" @change="saveClass(c)" class="w-full border rounded px-1"></td>
              <td class="border px-1 py-1"><input type="number" min="0" x-model.number="c.urutan" @change="saveClass(c)" class="w-14 border rounded px-1"></td>
              <td class="border px-1 py-1 text-center"><button x-show="ed.style_mode === 'categorized'" @click="delClass(c)" class="text-red-600">✕</button></td>
            </tr>
          </template>
          <tr x-show="ed.style_mode === 'categorized'">
            <td class="border px-1 py-1 text-center"><input type="color" x-model="newClass.warna"></td>
            <td class="border px-1 py-1 text-center"><input type="color" x-model="newClass.outline_warna"></td>
            <td class="border px-1 py-1"><input x-model="newClass.nilai" placeholder="nilai" class="w-full border rounded px-1"></td>
            <td class="border px-1 py-1"><input x-model="newClass.label" placeholder="label" class="w-full border rounded px-1"></td>
            <td class="border px-1 py-1"><input type="number" min="0" x-model.number="newClass.urutan" class="w-14 border rounded px-1"></td>
            <td class="border px-1 py-1 text-center"><button @click="saveClass(null)" class="text-emerald-700">+ Tambah</button></td>
          </tr>
        </tbody>
      </table>
      </div>
    </div>
  </div>
</div>

<script>
// Token CSRF dari sesi (dipakai semua POST admin)
const CSRF = <?= json_encode(csrf_token(), JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>;

function adminApp() {
  return {
    groups: [],
    mapForm: { judul: '', center_lat: '', center_lng: '', zoom: 9, basemap_default: 'osm' },
    msg: '', msgErr: false, orderNote: '',
    sortables: [],
    // State konversi ZIP Shapefile (zc = Zip Convert). Hasil konversi disimpan
    // sebagai GeoJSON di memori, dikirim via uploadFile() yang sama.
    zc: { busy: false, msg: '', msgErr: false, fileName: '', geojson: null,
          features: 0, bbox: '', fields: '', sizeKb: '', sizeWarn: '',
          crsNote: '', prjWarn: '' },
    ed: { open: false, id: null, group_id: null, nama: '', tipe_geom: 'polygon',
          opacity_default: 100, visible_default: true, aktif: true, min_zoom: 0,
          outline_color: '', outline_weight: 1, outline_opacity: 1,
          fill_opacity: 0.65, fill_enabled: true,
          style_mode: 'single', style_field: '', file_geojson: '',
          classes: [], props: [], upload: null, classWarn: '',
          popupCols: [], popupSel: [] },
    newClass: { nilai: '', label: '', warna: '#3388ff', outline_warna: '#ffffff', urutan: 99 },

    toast(msg, isErr = false) {
      this.msg = msg; this.msgErr = isErr;
      clearTimeout(this._msgT);
      this._msgT = setTimeout(() => { this.msg = ''; }, 6000);
    },

    async api(path, body) {
      const res = await fetch(path, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ csrf_token: CSRF, ...body }),
      });
      const data = await res.json().catch(() => ({}));
      if (!res.ok) throw new Error(data.error || ('HTTP ' + res.status));
      return data;
    },

    // Muat tree lengkap dari endpoint admin (termasuk layer nonaktif + nama file)
    async load() {
      const res = await fetch('../api/admin/tree.php');
      if (res.status === 403) {
        window.location.href = 'login.php';
        return;
      }
      const cfg = await res.json();
      if (!res.ok) throw new Error(cfg.error || ('HTTP ' + res.status));
      this.groups = (cfg.groups || []).map((g) => ({ ...g, editing: false }));
      this.$nextTick(() => this.initSortables());
    },
    async loadMapForm() {
      const res = await fetch('../api/map.php?id=1');
      const cfg = await res.json();
      this.mapForm = {
        judul: cfg.map.judul, center_lat: cfg.map.center_lat,
        center_lng: cfg.map.center_lng, zoom: cfg.map.zoom,
        basemap_default: cfg.map.basemap_default,
      };
    },
    async refresh() {
      const keepEd = this.ed.open ? this.ed.id : null;
      await this.load();
      if (keepEd) this.syncEditor(keepEd);
    },
    syncEditor(id) {
      for (const g of this.groups) {
        const l = g.layers.find((x) => x.id === id);
        if (l) {
          Object.assign(this.ed, {
            group_id: g.id, nama: l.nama, tipe_geom: l.tipe_geom,
            opacity_default: l.opacity_default, visible_default: l.visible_default === 1,
            aktif: (l.aktif ?? 1) === 1, min_zoom: (l.min_zoom ?? 0),
            outline_color: l.outline_color || '',
            outline_weight: l.outline_weight ?? 1, outline_opacity: l.outline_opacity ?? 1,
            fill_opacity: l.fill_opacity ?? 0.65, fill_enabled: (l.fill_enabled ?? 1) === 1,
            style_mode: l.style_mode,
            style_field: l.style_field || '', file_geojson: l.file_geojson || '',
            classes: JSON.parse(JSON.stringify(l.classes || [])),
          });
          break;
        }
      }
    },

    // ---- Drag & drop (SortableJS) ----
    initSortables() {
      if (typeof Sortable === 'undefined') {
        this.toast('SortableJS gagal dimuat dari CDN: drag & drop nonaktif.', true);
        return;
      }
      this.sortables.forEach((s) => s.destroy());
      this.sortables = [];
      const gl = document.getElementById('group-list');
      if (gl) {
        this.sortables.push(new Sortable(gl, {
          handle: '.drag-handle', animation: 150,
          onEnd: () => this.pushOrder(),
        }));
      }
      document.querySelectorAll('.layer-list').forEach((ul) => {
        this.sortables.push(new Sortable(ul, {
          group: 'layers', handle: '.drag-handle', animation: 150,
          onEnd: () => this.pushOrder(),
        }));
      });
    },
    async pushOrder() {
      try {
        const groups = [...document.querySelectorAll('#group-list > div[data-gid]')]
          .map((d, i) => ({ id: +d.dataset.gid, urutan: i + 1 }));
        const layers = [];
        document.querySelectorAll('.layer-list').forEach((ul) => {
          const gid = +ul.dataset.gid;
          [...ul.querySelectorAll('li[data-lid]')].forEach((li, i) => {
            layers.push({ id: +li.dataset.lid, group_id: gid, urutan: i + 1 });
          });
        });
        await this.api('../api/admin/layer_reorder.php', { groups, layers });
        this.orderNote = 'Urutan tersimpan.';
        await this.refresh(); // selaraskan state dengan DB (viewer ikut urutan ini)
      } catch (e) {
        this.toast(e.message, true);
        await this.refresh();
      }
    },

    // ---- Group ----
    async addGroup() {
      const nama = prompt('Nama group baru:');
      if (nama === null || nama.trim() === '') return;
      try {
        await this.api('../api/admin/group_save.php', { nama: nama.trim() });
        this.toast('Group ditambahkan.');
        await this.refresh();
      } catch (e) { this.toast(e.message, true); }
    },
    async saveGroup(g) {
      try {
        await this.api('../api/admin/group_save.php', { id: g.id, nama: g.nama.trim() });
        g.editing = false;
        this.toast('Group disimpan.');
        await this.refresh();
      } catch (e) { this.toast(e.message, true); }
    },
    async delGroup(g) {
      try {
        await this.api('../api/admin/group_delete.php', { id: g.id });
        this.toast('Group dihapus.');
        await this.refresh();
      } catch (e) {
        // 409 berisi layer_count -> minta konfirmasi eksplisit
        if (/berisi \d+ layer/.test(e.message)) {
          if (confirm(e.message + ' Lanjutkan?')) {
            try {
              await this.api('../api/admin/group_delete.php', { id: g.id, confirm: 1 });
              this.toast('Group beserta isinya dihapus.');
              await this.refresh();
            } catch (e2) { this.toast(e2.message, true); }
          }
          return;
        }
        this.toast(e.message, true);
      }
    },

    // ---- Layer ----
    openEditor(id, presetGroup = null) {
      this.ed.upload = null; this.ed.classWarn = ''; this.ed.props = [];
      this.ed.popupCols = []; this.ed.popupSel = [];
      this.resetZc();
      this.newClass = { nilai: '', label: '', warna: '#3388ff', outline_warna: '#ffffff', urutan: 99 };
      if (id === null) {
        this.ed = { ...this.ed, open: true, id: null, group_id: presetGroup ?? (this.groups[0] ? this.groups[0].id : null),
          nama: '', tipe_geom: 'polygon', opacity_default: 100, visible_default: true, aktif: true, min_zoom: 0,
          outline_color: '', outline_weight: 1, outline_opacity: 1, fill_opacity: 0.65, fill_enabled: true,
          style_mode: 'single', style_field: '', file_geojson: '', classes: [],
          popupCols: [], popupSel: [] };
      } else {
        this.ed.id = id;
        this.syncEditor(id);
        this.ed.open = true;
      }
    },
    async saveLayer() {
      try {
        const data = await this.api('../api/admin/layer_save.php', {
          id: this.ed.id, group_id: this.ed.group_id, nama: this.ed.nama,
          tipe_geom: this.ed.tipe_geom, opacity_default: +this.ed.opacity_default,
          visible_default: this.ed.visible_default ? 1 : 0, aktif: this.ed.aktif ? 1 : 0,
          min_zoom: +this.ed.min_zoom || 0,
          outline_color: this.ed.outline_color, outline_weight: +this.ed.outline_weight,
          outline_opacity: +this.ed.outline_opacity, fill_opacity: +this.ed.fill_opacity,
          fill_enabled: this.ed.fill_enabled ? 1 : 0,
          style_mode: this.ed.style_mode, style_field: this.ed.style_field,
        });
        this.ed.id = data.id;
        this.toast(this.ed.nama ? 'Layer disimpan. Lanjut upload GeoJSON bila perlu.' : 'Layer disimpan.');
        await this.refresh();
        this.syncEditor(data.id);
      } catch (e) { this.toast(e.message, true); }
    },
    async delLayer(l) {
      if (!confirm('Hapus layer "' + l.nama + '"?')) return;
      try {
        await this.api('../api/admin/layer_delete.php', { id: l.id });
        if (this.ed.id === l.id) this.ed.open = false;
        this.toast('Layer dihapus.');
        await this.refresh();
      } catch (e) { this.toast(e.message, true); }
    },

    // ---- Upload (satu File GeoJSON ke endpoint yang sama) ----
    async uploadGeo() {
      const inp = document.getElementById('up-file');
      if (!inp || !inp.files.length) { this.toast('Pilih file .geojson/.json dulu.', true); return; }
      await this.uploadFile(inp.files[0]);
      inp.value = '';
    },
    // Dipakai upload langsung maupun hasil konversi ZIP (tetap validasi server penuh)
    async uploadFile(file) {
      const fd = new FormData();
      fd.append('csrf_token', CSRF);
      fd.append('layer_id', this.ed.id);
      fd.append('file', file);
      try {
        const res = await fetch('../api/admin/layer_upload.php', { method: 'POST', body: fd });
        const data = await res.json().catch(() => ({}));
        if (!res.ok) throw new Error(data.error || ('HTTP ' + res.status));
        const mb = (data.size_bytes / 1048576).toFixed(1);
        this.ed.upload = {
          features: data.features, vertices: data.vertices, precision: data.avg_precision,
          size: mb + ' MB', size_level: data.size_level || 'ok',
          bbox: data.bbox ? [data.bbox.minLng, data.bbox.minLat, data.bbox.maxLng, data.bbox.maxLat].join(', ') : '-',
          props: (data.properties || []).join(', ') || '(tanpa properti)',
          columns: data.columns || [],
          warning: data.warning,
        };
        this.ed.props = data.properties || [];
        this.ed.file_geojson = data.file;
        this.toast('Upload OK: ' + data.features + ' fitur.' + (data.warning ? ' ' + data.warning : ''));
        await this.refresh();
        this.syncEditor(this.ed.id);
        this.ed.file_geojson = data.file;
      } catch (e) { this.toast(e.message, true); }
    },
    // Warna teks tingkat ukuran: ok hijau, kuning, oranye, merah
    sizeColor(level) {
      return { ok: 'text-emerald-700', kuning: 'text-amber-600',
               oranye: 'text-orange-600', merah: 'text-red-600' }[level] || 'text-gray-600';
    },

    // ---- Kolom popup ----
    async loadStats() {
      try {
        const res = await fetch('../api/admin/layer_stats.php?layer_id=' + this.ed.id);
        const data = await res.json().catch(() => ({}));
        if (!res.ok) throw new Error(data.error || ('HTTP ' + res.status));
        this.ed.popupCols = data.columns || [];
        this.ed.popupSel = data.popup_selected || [];
        // Tampilkan ringkasan ukuran juga bila belum ada hasil upload sesi ini
        if (!this.ed.upload) {
          const mb = (data.size_bytes / 1048576).toFixed(1);
          this.ed.upload = {
            features: data.features, vertices: data.vertices, precision: data.avg_precision,
            size: mb + ' MB', size_level: data.size_level || 'ok',
            bbox: data.bbox ? [data.bbox.minLng, data.bbox.minLat, data.bbox.maxLng, data.bbox.maxLat].join(', ') : '-',
            props: (data.columns || []).map((c) => c.name).join(', '),
            columns: data.columns || [], warning: data.size_message,
          };
        }
        this.toast('Kolom dimuat: ' + this.ed.popupCols.length + ' kolom.');
      } catch (e) { this.toast(e.message, true); }
    },
    async savePopup() {
      try {
        const data = await this.api('../api/admin/popup_save.php', {
          layer_id: this.ed.id, fields: this.ed.popupSel,
        });
        this.toast(data.count === 0
          ? 'Pilihan dikosongkan: semua kolom disajikan.'
          : 'Kolom popup disimpan: ' + data.count + ' kolom.');
      } catch (e) { this.toast(e.message, true); }
    },

    // ---- Konversi ZIP Shapefile di browser ----
    // Alur identik dengan yang diuji di Node (tools_pipe_test.js): JSZip untuk
    // daftar isi + .prj, shpjs untuk parse, proj4 untuk reproyeksi bila perlu.
    // Hasil dikirim sebagai .geojson via uploadFile() -> validasi server penuh.
    resetZc() {
      this.zc = { busy: false, msg: '', msgErr: false, fileName: '', geojson: null,
        features: 0, bbox: '', fields: '', sizeKb: '', sizeWarn: '',
        crsNote: '', prjWarn: '' };
    },
    // Pilih .shp/.dbf/.prj (samakan basename bila ada beberapa)
    zcPickFiles(names) {
      const low = names.map((n) => n.toLowerCase());
      const byExt = (e) => names.filter((n, i) => low[i].endsWith(e));
      const shps = byExt('.shp');
      const dbfs = byExt('.dbf');
      const prjs = byExt('.prj');
      const base = (n) => n.replace(/\.[^.]+$/, '').toLowerCase();
      const out = { shpN: shps[0] || null, dbfN: null, prjN: null, shpCount: shps.length };
      if (out.shpN) {
        const b = base(out.shpN);
        out.dbfN = dbfs.find((n) => base(n) === b) || dbfs[0] || null;
        out.prjN = prjs.find((n) => base(n) === b) || prjs[0] || null;
      }
      return out;
    },
    // Dalam rentang derajat (+toleransi debu float) = sudah WGS84
    zcInRange(b) {
      const t = 1e-3;
      return b.minLng >= -180 - t && b.maxLng <= 180 + t && b.minLat >= -90 - t && b.maxLat <= 90 + t;
    },
    // Bulatkan ke 6 desimal (mengecilkan file) — rekursif untuk semua kedalaman
    zcRound(gj) {
      const r = (c) => {
        if (typeof c[0] === 'number') {
          c[0] = +c[0].toFixed(6);
          c[1] = +c[1].toFixed(6);
        } else { c.forEach(r); }
      };
      gj.features.forEach((f) => { if (f.geometry) r(f.geometry.coordinates); });
    },
    // Reproyeksi manual via proj4 (dipakai bila shpjs tidak mengonversi otomatis)
    zcTransform(gj, prjText) {
      const conv = proj4(prjText, 'WGS84');
      const r = (c) => {
        if (typeof c[0] === 'number') {
          const p = conv.forward([c[0], c[1]]);
          c[0] = p[0]; c[1] = p[1];
        } else { c.forEach(r); }
      };
      gj.features.forEach((f) => { if (f.geometry) r(f.geometry.coordinates); });
    },
    zcBbox(gj) {
      const xs = [], ys = [];
      const w = (c) => {
        if (typeof c[0] === 'number') { xs.push(c[0]); ys.push(c[1]); }
        else { c.forEach(w); }
      };
      gj.features.forEach((f) => { if (f.geometry) w(f.geometry.coordinates); });
      return { minLng: Math.min(...xs), minLat: Math.min(...ys), maxLng: Math.max(...xs), maxLat: Math.max(...ys) };
    },
    async convertZip() {
      this.resetZc();
      const s = this.zc;
      if (typeof JSZip === 'undefined' || typeof shp === 'undefined' || typeof proj4 === 'undefined') {
        s.msg = 'Library konversi (JSZip/shpjs/proj4) gagal dimuat dari CDN. Periksa koneksi lalu muat ulang halaman.';
        s.msgErr = true;
        return;
      }
      const inp = document.getElementById('zip-file');
      if (!inp || !inp.files.length) {
        s.msg = 'Pilih file .zip shapefile dulu.';
        s.msgErr = true;
        return;
      }
      s.busy = true;
      try {
        const file = inp.files[0];
        s.fileName = file.name;
        const buf = await file.arrayBuffer();
        const zip = await JSZip.loadAsync(buf);
        const names = Object.keys(zip.files).filter((n) => !zip.files[n].dir);
        const pick = this.zcPickFiles(names);
        if (!pick.shpN || !pick.dbfN) {
          throw new Error('ZIP tidak berisi pasangan .shp + .dbf. Isi ZIP: ' + (names.join(', ') || '(kosong)'));
        }
        if (pick.shpCount > 1) {
          throw new Error('ZIP berisi lebih dari satu shapefile. Pisahkan menjadi satu .shp per ZIP.');
        }
        let gj;
        try {
          gj = await shp(buf);
        } catch (e) {
          throw new Error('Gagal mengurai shapefile. Periksa .shp/.dbf/.prj (' + String((e && e.message) || e).slice(0, 120) + ').');
        }
        if (Array.isArray(gj)) {
          throw new Error('ZIP berisi beberapa layer. Pisahkan menjadi satu .shp per ZIP.');
        }
        if (!gj || gj.type !== 'FeatureCollection' || !Array.isArray(gj.features) || gj.features.length === 0) {
          throw new Error('Hasil konversi kosong atau bukan FeatureCollection.');
        }
        // Perilaku shpjs (terbukti empiris): .prj proyeksi yang dikenali OTOMATIS
        // dikonversi ke WGS84 saat parse. Deteksi via besaran koordinat.
        const prjText = pick.prjN ? await zip.files[pick.prjN].async('string') : '';
        this.zcRound(gj);
        let bbox = this.zcBbox(gj);
        if (this.zcInRange(bbox)) {
          s.crsNote = prjText && prjText.toUpperCase().includes('PROJCS')
            ? 'CRS proyeksi terdeteksi — otomatis dikonversi ke WGS84.'
            : 'CRS WGS84 (lat/lng).';
          if (!prjText) s.prjWarn = 'ZIP tanpa .prj: koordinat diasumsikan sudah WGS84 (lat/lng).';
        } else if (prjText) {
          try {
            this.zcTransform(gj, prjText);
          } catch (e) {
            throw new Error('Gagal membaca proyeksi .prj. Reproyeksikan ke WGS84 dulu lalu ulangi.');
          }
          bbox = this.zcBbox(gj);
          if (!this.zcInRange(bbox)) {
            throw new Error('Hasil konversi di luar rentang wajar. Periksa .prj / reproyeksikan ke WGS84.');
          }
          s.crsNote = 'CRS proyeksi terdeteksi — dikonversi manual ke WGS84.';
        } else {
          throw new Error('Koordinat di luar rentang derajat dan ZIP tanpa .prj. Tambahkan .prj atau pakai data WGS84.');
        }
        this.zcRound(gj);
        bbox = this.zcBbox(gj);
        const keys = gj.features[0].properties ? Object.keys(gj.features[0].properties) : [];
        const sizeB = JSON.stringify(gj).length;
        s.geojson = gj;
        s.features = gj.features.length;
        s.bbox = [bbox.minLng, bbox.minLat, bbox.maxLng, bbox.maxLat].join(', ');
        s.fields = keys.join(', ') || '(tanpa properti)';
        s.sizeKb = (sizeB / 1024).toFixed(0) + ' KB';
        s.sizeWarn = sizeB > 5 * 1048576
          ? 'Hasil konversi melebihi 5 MB: server akan memberi peringatan dan viewer bisa lambat.' : '';
        s.msg = 'Konversi OK. Periksa pratinjau lalu tekan "Kirim ke server".';
        s.msgErr = false;
      } catch (e) {
        s.msg = (e && e.message) || 'Konversi gagal.';
        s.msgErr = true;
      } finally {
        s.busy = false;
      }
    },
    // Kirim hasil konversi sebagai .geojson ke endpoint yang sama (validasi server penuh)
    async sendShp() {
      if (!this.zc.geojson) return;
      const base = (this.zc.fileName || 'konversi').replace(/\.zip$/i, '');
      const file = new File([JSON.stringify(this.zc.geojson)], base + '.geojson', { type: 'application/geo+json' });
      await this.uploadFile(file);
      this.zc.geojson = null;
      const inp = document.getElementById('zip-file');
      if (inp) inp.value = '';
    },

    // ---- Kelas ----
    async autoClasses() {
      if (!this.ed.style_field) { this.toast('Isi style field dulu.', true); return; }
      try {
        const data = await this.api('../api/admin/class_auto.php', { layer_id: this.ed.id, field: this.ed.style_field });
        this.ed.classes = data.classes;
        this.ed.classWarn = data.warning;
        this.toast('Kelas dibuat: +' + data.added + ' baru, total ' + data.classes.length + '.');
        await this.refresh();
        this.syncEditor(this.ed.id);
      } catch (e) { this.toast(e.message, true); }
    },
    async saveClass(c) {
      try {
        const body = c ? { id: c.id, layer_id: this.ed.id, nilai: c.nilai, label: c.label,
                            warna: c.warna, outline_warna: c.outline_warna, urutan: +c.urutan }
                       : { layer_id: this.ed.id, ...this.newClass, urutan: +this.newClass.urutan };
        if (!c && (!body.nilai.trim() || !body.label.trim())) {
          this.toast('Nilai dan label kelas baru wajib diisi.', true);
          return;
        }
        await this.api('../api/admin/class_save.php', body);
        this.toast('Kelas disimpan.');
        await this.refresh();
        this.syncEditor(this.ed.id);
      } catch (e) { this.toast(e.message, true); }
    },
    async delClass(c) {
      if (!confirm('Hapus kelas "' + c.nilai + '"?')) return;
      try {
        await this.api('../api/admin/class_save.php', { id: c.id, delete: 1 });
        this.toast('Kelas dihapus.');
        await this.refresh();
        this.syncEditor(this.ed.id);
      } catch (e) { this.toast(e.message, true); }
    },
    // Baris warna tunggal (mode single) — viewer memakai baris bernilai __single__
    singleClass() {
      return (this.ed.classes || []).find((c) => c.nilai === '__single__') || null;
    },
    // Mode single hanya menampilkan baris tunggalnya agar tak membingungkan
    shownClasses() {
      if (this.ed.style_mode === 'categorized') return this.ed.classes;
      const s = this.singleClass();
      return s ? [s] : [];
    },

    // ---- Peta ----
    async saveMap() {
      try {
        await this.api('../api/admin/map_save.php', { ...this.mapForm });
        this.toast('Pengaturan peta disimpan.');
      } catch (e) { this.toast(e.message, true); }
    },
  };
}
</script>
</body>
</html>
