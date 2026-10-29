(() => {
  'use strict';
  const form = document.getElementById('batch-form');
  const fileInput = document.getElementById('batch-file');
  const start = document.getElementById('batch-start');
  const cancel = document.getElementById('batch-cancel');
  const download = document.getElementById('batch-download');
  const message = document.getElementById('batch-message');
  const progress = document.getElementById('batch-progress');
  const headers = ['latlong','kode subsls','kode SLS','Nama SLS','Kode Desa','Nama Desa','Kode Kecamatan','nama Kecamatan','Kode Kabupaten','Nama Kabupaten','Keterangan'];
  let controller, output = null;
  function ready() {
    if (!window.XLSX || !window.turf) throw new Error('Komponen Excel/peta belum termuat. Periksa internet lalu muat ulang halaman.');
  }
  function exportFile(data, name) {
    const sheet = XLSX.utils.aoa_to_sheet(data);
    // Explicit strings preserve leading zeroes and prevent formula injection.
    for (const key of Object.keys(sheet)) if (!key.startsWith('!')) sheet[key] = {t:'s',v:String(sheet[key].v ?? '')};
    sheet['!cols'] = data[0].map((_, i) => ({wch:i === 0 ? 46 : i === 10 ? 65 : i === 1 ? 21 : 24}));
    sheet['!autofilter'] = {ref:sheet['!ref']};
    const book = XLSX.utils.book_new(); XLSX.utils.book_append_sheet(book, sheet, 'Latlong');
    XLSX.writeFile(book, name, {compression:true});
  }
  document.getElementById('batch-template').addEventListener('click', () => {
    try { ready(); exportFile([['latlong'],['-0.4741982982343076, 117.15640378196456']], 'template_ceklatlong.xlsx'); }
    catch (error) { message.textContent = error.message; }
  });
  download.addEventListener('click', () => {
    try { if (output) exportFile(output, 'hasil_ceklatlong_' + new Date().toISOString().replace(/[-:]/g,'').slice(0,15).replace('T','_') + '.xlsx'); }
    catch (error) { message.textContent = error.message; }
  });
  cancel.addEventListener('click', () => controller?.abort());
  fileInput.addEventListener('change', () => { output = null; download.hidden = true; message.textContent = ''; progress.hidden = true; });
  async function candidates(values, signal) {
    const timeout = setTimeout(() => controller.abort(), 60000);
    try {
      const response = await fetch(location.pathname, {method:'POST',body:new URLSearchParams({action:'batch',values:JSON.stringify(values)}),signal});
      let result;
      try { result = await response.json(); } catch (_) { throw new Error('Respons server tidak valid. Coba file lebih kecil atau hubungi pengelola.'); }
      if (!response.ok) throw new Error(result.error || 'Pengecekan gagal.');
      if (!Array.isArray(result.items) || result.items.length !== values.length) throw new Error('Hasil server tidak lengkap.');
      return result;
    } finally { clearTimeout(timeout); }
  }
  form.addEventListener('submit', async event => {
    event.preventDefault(); if (start.disabled) return;
    output = null; download.hidden = true; controller = new AbortController();
    start.disabled = true; fileInput.disabled = true; cancel.hidden = false; progress.hidden = false; progress.value = 0;
    message.textContent = 'Membaca Excel...';
    try {
      ready(); const file = fileInput.files[0];
      if (!file || !/\.xlsx$/i.test(file.name)) throw new Error('Pilih file .xlsx dari template.');
      if (file.size > 10 * 1024 * 1024) throw new Error('Maksimal 10 MB per file. Pecah file menjadi beberapa bagian.');
      const book = XLSX.read(await file.arrayBuffer(), {type:'array',sheetRows:10002});
      const sheet = book.Sheets[book.SheetNames[0]];
      if (!sheet || !sheet['!ref']) throw new Error('Sheet pertama kosong.');
      const range = XLSX.utils.decode_range(sheet['!fullref'] || sheet['!ref']);
      if (range.e.r > 10000) throw new Error('Maksimal 10.000 baris data per file. Pecah file menjadi beberapa bagian.');
      if (String(sheet.A1?.v ?? '').trim().toLowerCase() !== 'latlong') throw new Error('Header A1 pada sheet pertama harus latlong.');
      const values = [];
      for (let r = 1; r <= range.e.r; r++) {
        const cell = sheet['A' + (r+1)];
        if (cell?.f) throw new Error('Baris ' + (r+1) + ': gunakan nilai latlong, bukan rumus Excel.');
        values.push(String(cell?.v ?? ''));
      }
      if (!values.length) throw new Error('Belum ada data koordinat.');
      const resultRows = [headers]; let found = 0, notFound = 0, invalid = 0, overlap = 0;
      for (let offset = 0; offset < values.length; offset += 25) {
        controller.signal.throwIfAborted();
        const chunk = values.slice(offset,offset+25);
        const batch = await candidates(chunk,controller.signal);
        for (let i = 0; i < chunk.length; i++) {
          const item = batch.items[i]; const raw = chunk[i];
          if (item.error) { invalid++; resultRows.push([raw,...Array(9).fill(''),item.error]); continue; }
          const point = turf.point([item.lng,item.lat]); const matches = new Map();
          for (const key of item.candidates) {
            const feature = batch.features[key];
            if (!feature) throw new Error('Data polygon tidak lengkap.');
            if (turf.booleanPointInPolygon(point,feature)) matches.set(feature.properties.idsubsls,feature.properties);
          }
          if (!matches.size) { notFound++; resultRows.push([raw,...Array(9).fill(''),'Tidak ditemukan dalam polygon SubSLS yang tersedia.']); continue; }
          found++; if (matches.size > 1) overlap++;
          for (const [id,p] of [...matches].sort((a,b) => a[0].localeCompare(b[0]))) {
            resultRows.push([raw,id,p.kdsls,p.nmsls,id.slice(7,10),p.nmdesa,id.slice(4,7),p.nmkec,id.slice(0,4),p.nmkab,
              matches.size > 1 ? 'Lebih dari satu SubSLS (batas/tumpang tindih); semua kecocokan ditampilkan.' : 'Ditemukan']);
          }
        }
        const done = offset + chunk.length; progress.value = Math.floor(done / values.length * 100);
        message.textContent = 'Diproses ' + done.toLocaleString('id-ID') + ' / ' + values.length.toLocaleString('id-ID') + ' baris (' + progress.value + '%).';
        await new Promise(resolve => setTimeout(resolve, 100));
      }
      controller.signal.throwIfAborted(); output = resultRows; download.hidden = false;
      message.textContent = 'Selesai. ' + found + ' baris ditemukan, ' + notFound + ' tidak ditemukan, ' + invalid + ' tidak valid. ' + overlap + ' baris memiliki lebih dari satu kecocokan. Hasil Excel siap diunduh.';
    } catch (error) {
      message.textContent = error.name === 'AbortError' ? 'Proses dibatalkan atau melewati batas waktu. Tidak ada hasil parsial yang dianggap selesai; silakan proses ulang.' : error.message;
    } finally { start.disabled = false; fileInput.disabled = false; cancel.hidden = true; controller = null; }
  });
})();
