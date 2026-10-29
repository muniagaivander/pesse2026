<?php
// Public boundary lookup: intentionally independent of login and respondent data.
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store');
    try {
        $action = $_POST['action'] ?? 'coordinates';
        if (!is_string($action) || !in_array($action, ['coordinates', 'address', 'nearby', 'batch'], true)) throw new InvalidArgumentException('Permintaan tidak valid.');
        if ($action === 'batch') {
            require_once __DIR__ . '/latlong_batch.php';
            echo json_encode(latlong_batch_candidates($_POST['values'] ?? ''), JSON_THROW_ON_ERROR);
            exit;
        }
        if ($action === 'address') {
            require_once __DIR__ . '/latlong_geocoder.php';
            echo json_encode(latlong_search_address($_POST['query'] ?? ''), JSON_THROW_ON_ERROR);
            exit;
        }
        $raw = $_POST['coordinates'] ?? '';
        if (!is_string($raw) || strlen($raw) > 120 || !preg_match('/^\s*([+-]?\d+(?:\.\d+)?)\s*,\s*([+-]?\d+(?:\.\d+)?)\s*$/D', $raw, $matches)) {
            throw new InvalidArgumentException('Masukkan latitude, longitude. Contoh: -0.4804208, 117.1638711');
        }
        $lat = (float)$matches[1]; $lng = (float)$matches[2];
        if (abs($lat) > 90 || abs($lng) > 180) throw new InvalidArgumentException('Latitude harus -90 sampai 90 dan longitude -180 sampai 180.');
        $padLat = $action === 'nearby' ? 0.5 / 110.5 : 0;
        $padLng = $action === 'nearby' ? 0.5 / (110.5 * max(0.01, cos(deg2rad($lat)))) : 0;
        $directory = __DIR__ . '/cache/latlong';
        if (!is_readable($directory . '/index.json') || !is_readable($directory . '/features.jsonl')) {
            throw new RuntimeException('Peta pencarian belum tersedia. Hubungi pengelola aplikasi.');
        }
        $index = json_decode(file_get_contents($directory . '/index.json'), true, 512, JSON_THROW_ON_ERROR);
        $handle = fopen($directory . '/features.jsonl', 'rb');
        if (!$handle) throw new RuntimeException('Peta pencarian tidak dapat dibaca.');
        $features = [];
        try {
            foreach ($index['rows'] as $row) {
                if ($lng + $padLng < $row[0] || $lat + $padLat < $row[1] || $lng - $padLng > $row[2] || $lat - $padLat > $row[3]) continue;
                if (fseek($handle, $row[4]) !== 0) throw new RuntimeException('Indeks peta tidak dapat dibaca.');
                $feature = stream_get_contents($handle, $row[5]);
                if ($feature === false || strlen($feature) !== $row[5]) throw new RuntimeException('Data peta tidak lengkap.');
                $features[] = json_decode($feature, true, 512, JSON_THROW_ON_ERROR);
            }
        } finally { fclose($handle); }
        echo json_encode(['lat' => $lat, 'lng' => $lng, 'features' => $features], JSON_THROW_ON_ERROR);
    } catch (InvalidArgumentException $error) {
        http_response_code(422);
        echo json_encode(['error' => $error->getMessage()]);
    } catch (Throwable $error) {
        http_response_code($error->getCode() === 429 ? 429 : 503);
        error_log('Cek latlong: ' . $error->getMessage());
        $message = ($action ?? '') === 'address'
            ? 'Pencarian alamat gagal. Hubungi pengelola untuk memeriksa log PHP dan kelengkapan file latlong_geocoder.php.'
            : 'Peta pencarian belum tersedia atau tidak dapat dibaca. Hubungi pengelola aplikasi.';
        if ($error instanceof LatlongSearchException) $message = $error->getMessage();
        echo json_encode(['error' => $message]);
    }
    exit;
}
$mode = in_array($_GET['mode'] ?? '', ['coordinates', 'address'], true) ? $_GET['mode'] : 'home';
?>
<!doctype html>
<html lang="id">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Cek Koordinat SubSLS | BPS Kalimantan Timur</title>
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css">
<style>
*{box-sizing:border-box}body{margin:0;background:#f4f6f8;color:#17212b;font:15px/1.5 Arial,sans-serif}header{background:#fff;border-bottom:1px solid #d9e0e6;padding:18px 24px}header span{color:#546579;font-size:13px}h1{font-size:23px;margin:0 0 2px}main{max-width:960px;margin:24px auto;padding:0 18px}label{display:block;font-weight:700;margin-bottom:6px}.input-row{display:flex;gap:8px}input{min-width:0;flex:1;border:1px solid #a9b7c5;border-radius:6px;font:inherit;padding:12px}button{border:0;border-radius:6px;background:#1466aa;color:#fff;font:700 15px Arial;padding:12px 20px;cursor:pointer}button:disabled{opacity:.65;cursor:wait}#message{margin:16px 0;color:#405367}#message.error{color:#b42318}.result{background:#fff;border:1px solid #d4dde5;border-left:4px solid #168064;border-radius:6px;padding:16px;margin:12px 0}h2{font-size:19px;margin:0 0 8px}dl{display:grid;grid-template-columns:130px 1fr;gap:5px 12px;margin:0}dt{color:#526170}dd{margin:0;overflow-wrap:anywhere}#map{height:360px;border:1px solid #d4dde5;border-radius:6px;margin-top:20px}#map[hidden]{display:none}footer{font-size:12px;color:#596978;margin:20px 0}.note{font-size:13px;color:#596978}@media(max-width:480px){header{padding:16px 18px}h1{font-size:21px}.input-row{flex-wrap:wrap}input{flex-basis:100%}button{width:100%}dl{grid-template-columns:100px 1fr}#map{height:300px}}
dd.full-region-code{font-size:17px;font-weight:700}
.home-choices{display:flex;gap:16px;flex-wrap:wrap;margin:28px 0}.home-choices a{flex:1;min-width:200px;padding:24px;border:1px solid #cbd5e1;border-top:4px solid #1466aa;border-radius:6px;background:white;color:#174b77;text-decoration:none;font-size:20px;font-weight:bold}.home-choices a:last-child{border-top-color:#168064;color:#14654e}.back{display:inline-block;margin-bottom:18px;color:#1466aa;text-decoration:none;font-weight:700}.address-choice{display:block;width:100%;text-align:left;background:white;color:#17212b;border:1px solid #ccd6e0;margin:8px 0;font-weight:400;line-height:1.5}.address-choice:hover,.address-choice[aria-pressed="true"]{background:#e8f2fc;border-color:#1466aa}.table-scroll{overflow:auto;background:white}table{width:100%;border-collapse:collapse;font-size:14px}th,td{padding:10px;text-align:left;border-bottom:1px solid #dbe3eb;white-space:nowrap}th{background:#e8f2fc}tr.contains{background:#eaf7ee}td button{padding:5px 8px;font-size:13px}.map-title{margin-top:20px}.source{font-size:12px;color:#526170}#address-map{height:440px;border:1px solid #cbd5e1;border-radius:6px}.map-legend{font-size:13px;margin:10px 0}.map-legend span{display:inline-block;margin-right:16px}.found{color:#168064}.around{color:#1466aa}#selected-location{overflow-wrap:anywhere}@media(max-width:480px){#address-map{height:350px}.home-choices{gap:10px}}
</style>
<style>tr.selected-subsls{background:#d9f1e5}tr.selected-subsls td:first-child{box-shadow:inset 4px 0 #168064}tr.selected-subsls button{background:#168064}</style>
</head>
<body>
<header><h1><?= $mode === 'home' ? 'Temukan SubSLS' : ($mode === 'address' ? 'Cari Alamat' : 'Cek Koordinat SubSLS') ?></h1><span>BPS Provinsi Kalimantan Timur</span></header>
<main>
<?php if ($mode === 'home'): ?>
<nav class="home-choices" aria-label="Pilihan pencarian"><a href="?mode=coordinates">Cek Koordinat</a><a href="?mode=address">Cari Alamat</a></nav>
<?php else: ?>
<a class="back" href="?mode=home">&larr; Kembali ke Home</a>
<?php endif; ?>
<?php if ($mode === 'coordinates'): ?>
<form id="lookup">
<label for="coordinates">Latitude, Longitude</label>
<div class="input-row"><input id="coordinates" name="coordinates" type="text" required maxlength="120" placeholder="-0.48042080709974655, 117.16387105186992" autocomplete="off"><button id="submit" type="submit">Cek SubSLS</button></div>
</form>
<p id="message" role="status" aria-live="polite"></p>
<div id="results"></div>
<div id="map" hidden aria-label="Lokasi koordinat dan batas SubSLS"></div>
<p class="note">Hasil mengacu pada batas polygon SubSLS yang tersedia. Titik pada perbatasan atau wilayah tumpang tindih dapat memiliki lebih dari satu hasil.</p>
<section class="batch-section" aria-labelledby="batch-title">
<h2 id="batch-title">Cek Latlong via Excel</h2>
<button type="button" id="batch-template">Download Template Excel</button>
<p class="note">Satu kolom latlong pada sheet pertama. Maksimal 10.000 baris / 10 MB (.xlsx). Koordinat dikirim ke aplikasi ini untuk pengecekan; file Excel tidak disimpan di server.</p>
<form id="batch-form"><label for="batch-file">Upload Template Pengecekan Wilayah Latlong dibawah</label><input type="file" id="batch-file" accept=".xlsx" required>
<div class="batch-actions"><button type="submit" id="batch-start">Proses Excel</button><button type="button" id="batch-cancel" hidden>Batal</button></div></form>
<progress id="batch-progress" value="0" max="100" hidden aria-label="Progres pengecekan koordinat"></progress>
<p id="batch-message" role="status" aria-live="polite"></p>
<button type="button" id="batch-download" hidden>Download Hasil Excel</button>
</section>
<?php elseif ($mode === 'address'): ?>
<form id="address-form"><label for="address">Alamat atau nama tempat</label><div class="input-row"><input id="address" name="query" required minlength="3" maxlength="200" placeholder="Jl. Elang, Samarinda" autocomplete="off"><button id="address-submit" type="submit">Cari</button></div></form>
<p class="source">Pencarian: Photon / <a href="https://www.openstreetmap.org/copyright" target="_blank" rel="noopener">&copy; OpenStreetMap contributors</a>. Kata pencarian dikirim ke Photon. Jangan masukkan data pribadi atau rahasia.</p>
<p id="address-message" role="status" aria-live="polite"></p>
<div id="address-results" aria-label="Hasil pencarian alamat"></div>
<section id="address-detail" hidden>
<h2 class="map-title" id="selected-location"></h2><p id="selected-coordinates"></p>
<div id="address-map" aria-label="Lokasi alamat dan SubSLS sekitar"></div>
<p class="note">Hasil alamat adalah perkiraan lokasi. Klik peta untuk menyesuaikan titik. Batas SubSLS tersedia untuk Kalimantan Timur.</p>
<p id="nearby-message" role="status" aria-live="polite"></p>
<h2>Daftar SubSLS Sekitar</h2>
<div class="table-scroll"><table><thead><tr><th>Kode SubSLS</th><th>SLS</th><th>SubSLS</th><th>Desa/Kelurahan</th><th>Kecamatan</th><th>Kabupaten/Kota</th><th>Peta</th></tr></thead><tbody id="nearby-rows"></tbody></table></div>
</section>
<?php endif; ?>
<footer>&copy; 2026 Tim SPBE BPS Provinsi Kalimantan Timur</footer>
</main>
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
<script src="https://cdn.jsdelivr.net/npm/@turf/turf@7.2.0/turf.min.js"></script>
<?php if ($mode === 'coordinates'): ?>
<style>.batch-section{margin-top:28px;padding-top:20px;border-top:1px solid #cbd5e1}.batch-actions{display:flex;gap:10px;margin:14px 0}#batch-file{max-width:100%;background:white}#batch-progress{width:100%;height:20px}#batch-cancel{background:#526170}</style>
<script src="https://cdn.sheetjs.com/xlsx-0.20.3/package/dist/xlsx.full.min.js"></script>
<script src="assets/latlong_batch.js?v=1"></script>
<?php endif; ?>
<?php if ($mode === 'coordinates'): ?>
<script>
const form = document.getElementById('lookup');
const message = document.getElementById('message');
const results = document.getElementById('results');
const button = document.getElementById('submit');
let map, overlay;
function showResult(feature) {
  const p = feature.properties;
  const regionId = String(p.idsubsls || '');
  const codes = /^\d{16}$/.test(regionId)
    ? [regionId.slice(0, 2), regionId.slice(2, 4), regionId.slice(4, 7), regionId.slice(7, 10), regionId.slice(10, 14), regionId.slice(14, 16)]
    : ['', '', '', '', String(p.kdsls || ''), String(p.kdsubsls || '')];
  const slsName = String(p.nmsls || 'SLS').replace(/^RT\s*0*(\d+)$/i, (_, number) => 'RT ' + Number(number));
  const withCode = (code, name) => (code ? code + '-' : '') + (name || '-');
  const article = document.createElement('article'); article.className = 'result';
  const title = document.createElement('h2'); title.textContent = '(' + codes[4] + codes[5] + ') ' + slsName + ' - Sub ' + codes[5];
  article.append(title);
  const list = document.createElement('dl');
  for (const [label, value] of [
    ['Provinsi', withCode(codes[0], p.nmprov)],
    ['Kabupaten', withCode(codes[1], p.nmkab)],
    ['Kecamatan', withCode(codes[2], p.nmkec)],
    ['Desa/Kelurahan', withCode(codes[3], p.nmdesa)],
    ['SLS', withCode(codes[4], slsName)],
    ['SubSLS', withCode(codes[5], 'Sub ' + codes[5])],
    ['Kode Lengkap', regionId],
    ['Kode Lengkap (-)', codes.join('-')]
  ]) {
    const dt = document.createElement('dt'); dt.textContent = label;
    const dd = document.createElement('dd'); dd.textContent = value || '-';
    if (label === 'Kode Lengkap' || label === 'Kode Lengkap (-)') dd.className = 'full-region-code';
    list.append(dt, dd);
  }
  article.append(list); results.append(article);
}
form.addEventListener('submit', async event => {
  event.preventDefault(); if (button.disabled) return;
  button.disabled = true; button.textContent = 'Mencari...';
  message.className = ''; message.textContent = 'Memeriksa koordinat...'; results.replaceChildren();
  document.getElementById('map').hidden = true;
  try {
    if (!window.turf) throw new Error('Komponen pencarian gagal dimuat. Muat ulang halaman.');
    const response = await fetch(location.pathname, {method:'POST', body:new FormData(form), signal:AbortSignal.timeout(30000)});
    const data = await response.json();
    if (!response.ok) throw new Error(data.error || 'Pencarian gagal. Coba kembali.');
    const point = turf.point([data.lng, data.lat]);
    const matches = data.features.filter(feature => turf.booleanPointInPolygon(point, feature));
    message.textContent = matches.length ? matches.length + ' SubSLS ditemukan.' : 'Koordinat tidak ditemukan dalam wilayah SubSLS pada peta.';
    matches.forEach(showResult);
    if (window.L) {
      document.getElementById('map').hidden = false;
      if (!map) {
        map = L.map('map');
        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {maxZoom:19, attribution:'&copy; OpenStreetMap contributors'}).addTo(map);
        L.control.scale({imperial:false}).addTo(map);
      }
      map.invalidateSize(); if (overlay) map.removeLayer(overlay);
      overlay = L.featureGroup().addTo(map);
      if (matches.length) L.geoJSON(matches, {style:{color:'#1466aa',weight:2,fillOpacity:.12}}).addTo(overlay);
      L.circleMarker([data.lat,data.lng],{radius:7,color:'#fff',weight:2,fillColor:'#dc2626',fillOpacity:1}).addTo(overlay);
      if (matches.length) map.fitBounds(overlay.getBounds(), {padding:[24,24],maxZoom:17});
      else map.setView([data.lat,data.lng], 13);
    }
  } catch (error) {
    message.className = 'error'; message.textContent = error.name === 'TimeoutError' ? 'Pencarian terlalu lama. Silakan coba lagi.' : error.message;
  } finally { button.disabled = false; button.textContent = 'Cek SubSLS'; }
});
</script>
<?php elseif ($mode === 'address'): ?>
<script src="assets/latlong_address.js?v=4"></script>
<?php endif; ?>
</body>
</html>
