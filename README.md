# PES SE 2026

Database lokal: pesse2026. Master wilayah disalin dari dailyse2026; laporan, daftar petugas, dan alokasi dimulai kosong. Mikrodata tidak disertakan.

## Alur petugas
1. Login admin provinsi/kabupaten.
2. Menu **Upload Master Petugas**: download template CSV, isi idpetugas,email,nama,role,satker. Role pengawas/pencacah; satker kode kabupaten/kota (contoh 6401).
3. Menu **Upload Alokasi SubSLS**: CSV idsubsls,email_pengawas,email_pencacah. Email wajib sudah terdaftar dengan role dan satker sesuai wilayah.
4. Menu **Alokasi SubSLS / Ganti Petugas**: pilih satker/desa, lalu dropdown email pengawas dan pencacah.

Upload bersifat atomik: satu baris salah membatalkan seluruh file. Upload ulang ID petugas yang sama memperbarui nama; email/role/satker tidak dapat diubah melalui upload. Viewer hanya membaca. Admin kabupaten dibatasi satkernya.

Login admin/viewer memakai username admin6400/viewer6400 atau kode kabupaten/kota masing-masing. Email kedua akun memakai bps[kode]@bps.go.id. Petugas memakai email sebagai username dengan password awal 123 yang dibuat otomatis saat upload. Upload ulang tidak mengubah password akun yang sudah ada.

## Database
Salinan awal database ada di database.sql (termasuk master wilayah dan akun awal; password tersimpan sebagai hash). Impor melalui MySQL, lalu sesuaikan .env. Fitur Backup Database tersedia bagi superadmin.

## Validasi
Seluruh file PHP lulus pemeriksaan sintaks. Uji HTTP mencakup login 22 akun, halaman utama, upload master, upload alokasi, dropdown, validasi role/satker, rollback upload, pembatasan viewer, serta CSRF menu baru. Data pengujian sudah dihapus.


## Sampel dan periode PES
Kegiatan berlangsung 1–30 November 2026. Kolom `master_subsls.status_sampel` menyimpan Ya/Tidak. Daftar 160 ID resmi tersimpan di `migrations/sample_ids.txt`; seluruh 17.039 master wilayah tetap disimpan.

Jumlah sampel per kabupaten/kota: 6401=17, 6402=15, 6403=18, 6404=14, 6409=22, 6471=26, 6472=27, 6474=21. Menu Master Wilayah menampilkan status dan pencarian, dengan sampel Ya sebagai tampilan awal.

Semua kueri operasional membaca view `pes_sample_*` sehingga hanya sampel masuk filter, alokasi, template, status, rekap, ekspor, dan dashboard. Peta tetap menampilkan geometri nonsampel dengan warna abu-abu tanpa interaksi. Impor master wilayah tidak mengubah status sampel yang sudah ada; wilayah baru default Tidak.

Migrasi database lama: jalankan `php migrations/apply_samples.php` setelah backup. Backup SQL aplikasi menyertakan definisi view dari `migrations/sample_views.sql`. Migrasi tidak menghapus laporan/alokasi lama; view laporan harian membatasi tanggal November dan ID sampel. Target harian progres dihitung merata 100% / 30 hari.
