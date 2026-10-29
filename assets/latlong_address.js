const addressForm = document.getElementById('address-form');
const addressMessage = document.getElementById('address-message');
const addressResults = document.getElementById('address-results');
const detail = document.getElementById('address-detail');
const nearbyMessage = document.getElementById('nearby-message');
const nearbyRows = document.getElementById('nearby-rows');
const subslsItems = new Map();
function selectSubSLS(id, zoom = false, latlng = null) {
  const selected = subslsItems.get(id);
  if (!selected) return;
  for (const [key, item] of subslsItems) {
    const active = key === id;
    item.row.classList.toggle('selected-subsls', active);
    item.button.setAttribute('aria-pressed', String(active));
    item.shape.setStyle({color:active?'#168064':'#1466aa',weight:active?3:2,fillOpacity:active?0.2:0.07});
  }
  addressMap.closePopup();
  selected.shape.bringToFront();
  if (zoom) {
    addressMap.stop();
    addressMap.invalidateSize();
    addressMap.fitBounds(selected.shape.getBounds(), {padding:[30,30], maxZoom:19, animate:false});
    document.getElementById('address-map').scrollIntoView({behavior:'smooth',block:'center'});
  }
  selected.shape.openPopup(latlng || selected.shape.getBounds().getCenter());
  if (pin) pin.bringToFront();
}
let addressMap, polygons, pin, radiusLayer, nearbyController, generation = 0;
async function request(data, signal) {
  const response = await fetch(location.pathname, {method:'POST', body:new URLSearchParams(data), signal});
  let json;
  try { json = await response.json(); } catch (_) { throw new Error('Server tidak memberikan respons yang valid. Coba lagi.'); }
  if (!response.ok) throw new Error(json.error || 'Pencarian gagal.');
  return json;
}
function text(tag, value) { const node = document.createElement(tag); node.textContent = value; return node; }
function ensureMap() {
  if (!window.L || !window.turf) throw new Error('Komponen peta gagal dimuat. Muat ulang halaman.');
  if (addressMap) return;
  addressMap = L.map('address-map').setView([-0.5,117.15], 13);
  L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {maxZoom:19,attribution:'&copy; OpenStreetMap contributors'}).addTo(addressMap);
  L.control.scale({imperial:false}).addTo(addressMap);
  addressMap.on('click', event => loadNearby(event.latlng.lat, event.latlng.lng, false));
}
async function loadNearby(lat, lng, recenter = true) {
  if (nearbyController) nearbyController.abort();
  nearbyController = new AbortController(); const myGeneration = ++generation;
  const timer = setTimeout(() => nearbyController && myGeneration === generation && nearbyController.abort(), 30000);
  nearbyMessage.textContent = 'Memuat peta SubSLS sekitar...';
  nearbyRows.replaceChildren(); subslsItems.clear();
  try {
    detail.hidden = false; ensureMap(); addressMap.invalidateSize();
    if (polygons) { addressMap.removeLayer(polygons); polygons = null; }
    if (pin) addressMap.removeLayer(pin);
    if (radiusLayer) addressMap.removeLayer(radiusLayer);
    pin = L.circleMarker([lat,lng], {radius:7,color:'#fff',weight:2,fillColor:'#dc2626',fillOpacity:1}).addTo(addressMap);
    radiusLayer = L.circle([lat,lng], {radius:500,color:'#667085',weight:1,dashArray:'5 5',fill:false,interactive:false}).addTo(addressMap);
    document.getElementById('selected-coordinates').textContent = lat.toFixed(7) + ', ' + lng.toFixed(7);
    if (recenter) addressMap.fitBounds(radiusLayer.getBounds(), {padding:[16,16], maxZoom:16});
    const data = await request({action:'nearby',coordinates:lat + ', ' + lng}, nearbyController.signal);
    if (myGeneration !== generation) return;
    const point = turf.point([lng,lat]); const circle = turf.circle(point, 0.5, {units:'kilometers',steps:64});
    const unique = new Map();
    for (const f of data.features) {
      if (turf.booleanIntersects(circle, f)) unique.set(f.properties.idsubsls, f);
    }
    const features = [...unique.values()].map(feature => ({feature, contains:turf.booleanPointInPolygon(point,feature)}));
    features.sort((a,b) => Number(b.contains)-Number(a.contains) || a.feature.properties.idsubsls.localeCompare(b.feature.properties.idsubsls));
    polygons = L.featureGroup().addTo(addressMap);
    for (const {feature} of features) {
      const p = feature.properties;
      const shape = L.geoJSON(feature, {style:{bubblingMouseEvents:false,color:'#1466aa',weight:2,fillOpacity:0.07},onEachFeature:(_feature, layer) => {
        layer.options.bubblingMouseEvents = false;
        layer.on('click', event => {
          if (event.originalEvent) L.DomEvent.stopPropagation(event.originalEvent);
          selectSubSLS(p.idsubsls, false, event.latlng);
        });
      }}).addTo(polygons);
      const popup = document.createElement('div');
      popup.append(text('strong', p.nmsls + ' - Sub ' + p.kdsubsls),text('div',p.idsubsls),text('div',p.nmdesa));
      shape.bindPopup(popup, {autoPan:false});
      const row = document.createElement('tr');
      row.dataset.subsls = p.idsubsls;
      for (const value of [p.idsubsls,p.nmsls,p.kdsubsls,p.nmdesa,p.nmkec,p.nmkab]) row.append(text('td',value || '-'));
      const cell = document.createElement('td');
      const button = text('button','Lihat'); button.type = 'button'; button.setAttribute('aria-pressed','false');
      button.addEventListener('click', () => selectSubSLS(p.idsubsls, true));
      cell.append(button); row.append(cell); nearbyRows.append(row);
      subslsItems.set(p.idsubsls, {shape,row,button});
    }
    pin.bringToFront();
    nearbyMessage.textContent = features.length ? '' : 'Tidak ada polygon SubSLS dalam radius 500 m pada data peta yang tersedia.';
  } catch (error) {
    if (myGeneration !== generation) return;
    nearbyMessage.textContent = error.name === 'AbortError' ? 'Pencarian terlalu lama. Pilih lokasi lagi untuk mencoba.' : error.message;
  } finally { clearTimeout(timer); }
}
addressForm.addEventListener('submit', async event => {
  event.preventDefault(); const button = document.getElementById('address-submit');
  if (button.disabled) return;
  generation++; if (nearbyController) nearbyController.abort();
  detail.hidden = true; addressResults.replaceChildren(); addressMessage.textContent = 'Mencari alamat...';
  button.disabled = true; button.textContent = 'Mencari...';
  try {
    const data = await request({action:'address',query:document.getElementById('address').value}, AbortSignal.timeout(25000));
    addressMessage.textContent = data.results.length ? 'Pilih lokasi yang sesuai (' + data.results.length + ' hasil).' : 'Alamat tidak ditemukan di Kalimantan Timur. Coba nama jalan lengkap dan kota atau kelurahan di Kalimantan Timur.';
    for (const result of data.results) {
      const choice = text('button',result.label); choice.type = 'button'; choice.className = 'address-choice'; choice.setAttribute('aria-pressed','false');
      choice.addEventListener('click', () => {
        addressResults.querySelectorAll('button').forEach(b => b.setAttribute('aria-pressed','false')); choice.setAttribute('aria-pressed','true');
        document.getElementById('selected-location').textContent = result.label;
        loadNearby(result.lat,result.lng);
      });
      addressResults.append(choice);
    }
  } catch (error) { addressMessage.textContent = error.name === 'TimeoutError' ? 'Layanan alamat terlalu lama merespons. Coba lagi nanti.' : error.message; }
  finally { button.disabled = false; button.textContent = 'Cari'; }
});
