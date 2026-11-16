# PES SE 2026

Database lokal: pesse2026. Master wilayah disalin dari dailyse2026; laporan, daftar petugas, dan alokasi dimulai kosong. Mikrodata tidak disertakan.

## Alur petugas
1. Login admin provinsi/kabupaten.
2. Menu **Upload Master Petugas**: download template CSV, isi email,nama,role,satker. ID petugas dibuat otomatis oleh sistem. Role pengawas/pencacah; satker kode kabupaten/kota (contoh 6401).
3. Menu **Upload Alokasi SubSLS**: CSV idsubsls,email_pengawas,email_pencacah. Email wajib sudah terdaftar dengan role dan satker sesuai wilayah.
4. Menu **Alokasi SubSLS / Ganti Petugas**: pilih satker/desa, lalu dropdown email pengawas dan pencacah.

Upload bersifat atomik: satu baris salah membatalkan seluruh file. Upload ulang email petugas yang sama memperbarui nama dan mempertahankan ID serta password; role/satker tidak dapat diubah melalui upload. Viewer hanya membaca. Admin kabupaten dibatasi satkernya.

Login admin/viewer memakai username admin6400/viewer6400 atau kode kabupaten/kota masing-masing. Email kedua akun memakai bps[kode]@bps.go.id. Password semua akun selain admin6400 direset menjadi 123 pada 12 November 2026; password admin6400 tetap. Petugas memakai email sebagai username dengan password awal 123 yang dibuat otomatis saat upload. Upload ulang tidak mengubah password akun yang sudah ada.

## Database
Salinan awal database ada di database.sql (termasuk master wilayah dan akun awal; password tersimpan sebagai hash). Impor melalui MySQL, lalu sesuaikan .env. Fitur Backup Database tersedia bagi superadmin.

## Daily Report PDF
Menu **Daily Report** berada tepat di bawah Weekly Report untuk superadmin dan admin kabupaten. Pilih tanggal data lalu Generate / Download PDF atau Pratinjau PDF. File PDF dua belas halaman dibuat langsung oleh PHP, tanpa layanan eksternal atau cache laporan publik. Admin provinsi melihat delapan kabupaten sampel; admin kabupaten melihat grafik PCL yang dikelompokkan di bawah nama PML di kolom kiri, berdasarkan alokasi SubSLS saat ini. Peta admin kabupaten tetap memakai batas kecamatan wilayahnya.

Data diambil dari snapshot terakhir **per SubSLS** sampai tanggal pilihan (1–30 November 2026, tidak boleh melewati hari ini). Histori tidak dijumlahkan. PDF mencantumkan rentang tanggal sumber dan waktu generate WITA; sampul menggunakan tanggal generate. SubSLS tanpa data diberi catatan dan masuk kategori Belum; PDF tidak dibuat bila seluruh wilayah belum memiliki laporan.

Halaman 1–2 mengikuti sampul dan apresiasi referensi; halaman 3 pie progres umum; halaman 4 stacked bar progres SubSLS; halaman 5 komposisi enam status assignment; halaman 6 Submit + Approve; halaman 7–10 komposisi status Pengecekan Data (Keluarga, Usaha Keluarga, Usaha BKU, Individu); halaman 11 diskusi; halaman 12 penutup. Keempat snapshot pengecekan dipilih per jenis dari tanggal terakhir yang tidak melewati tanggal laporan, tanpa menjumlahkan histori. Belum = semua status selain Open nol. Selesai = Approve sama dengan target positif. Sisanya On-progress. Completed = Approve. Persentase assignment dan progress memakai jumlah seluruh enam status sebagai pembagi. Status selesai manual tidak digunakan untuk rumus laporan ini.

Pemeriksaan logika dan cakupan akun: `php migrations/test_daily_report.php`. Aset latar ada di `assets/daily-report`; catatan sumber tersedia di README direktori tersebut. Contoh PDF lokal berada di `output/pdf` dan tidak disertakan dalam Git atau disajikan melalui HTTP.

## Pengecekan Data
Menu di bawah Dashboard berisi Keluarga, Usaha (Keluarga), Usaha (BKU), dan Individu. Tampilan dapat dipilih per SubSLS atau per petugas (PCL/PML), mengikuti alokasi saat ini. Cakupan akun tetap mengikuti kabupaten atau penugasan petugas.

Hanya akun aktif **admin6400** yang dapat membuka tab Upload, mengunduh template, dan mengunggah data. Pembatasan diperiksa di server, termasuk permintaan langsung. Template XLSX memakai sheet Rekap dan header persis seperti Excel sumber (`tanggal`, `subsls_id`, kemudian kolom hitungan), dengan 160 ID sampel terisi. Isi seluruh hitungan dengan bilangan bulat, termasuk 0 untuk nihil.

Admin kabupaten/kota mendapatkan tombol **Unduh** di posisi tab Upload. Sekali klik menghasilkan satu XLSX dengan empat sheet sesuai jenis data, tanggal terbaru masing-masing jenis, dan seluruh SubSLS sampel dalam kabupaten akun. Filter tabel tidak membatasi isi unduhan; wilayah selalu diperiksa dari akun aktif di database. Angka disimpan sebagai sel numerik dan ID SubSLS sebagai teks agar tetap utuh.

Setiap file berisi snapshot lengkap untuk satu tanggal November 2026, tidak melebihi hari ini. Dapat mengunggah satu sampai empat jenis sekaligus; jika satu file tidak valid, semuanya dibatalkan. Upload ulang jenis/tanggal yang sama memperbarui data tanggal itu. Histori tanggal lain tetap disimpan. Setiap tab menampilkan tanggal terbaru yang tersedia untuk jenis tersebut, tanpa menjumlahkan histori. Tanggal data ditampilkan di atas tabel.

Untuk database lama jalankan `php migrations/setup_data_checks.php --apply`. Skema tabel `data_check_rows` dan audit `data_check_uploads` juga disertakan dalam database.sql. Impor awal opsional melalui argumen `--keluarga=FILE.xlsx`, `--usaha_keluarga=FILE.xlsx`, `--usaha_bku=FILE.xlsx`, dan `--individu=FILE.xlsx`, menggunakan validasi yang sama dengan web.

Uji regresi data awal 12 November: `php migrations/test_data_checks.php` memeriksa total, batas akun/wilayah, template, dan pemilihan snapshot terakhir. Perubahan histori pengujian di-rollback.

## Validasi
Seluruh file PHP lulus pemeriksaan sintaks. Uji HTTP mencakup login 22 akun, halaman utama, upload master, upload alokasi, dropdown, validasi role/satker, rollback upload, pembatasan viewer, serta CSRF menu baru. Data pengujian sudah dihapus.


## Sampel dan periode PES
Kegiatan berlangsung 1–30 November 2026. Kolom `master_subsls.status_sampel` menyimpan Ya/Tidak. Daftar 160 ID resmi tersimpan di `migrations/sample_ids.txt`; seluruh 17.039 master wilayah tetap disimpan.

Jumlah sampel per kabupaten/kota: 6401=17, 6402=15, 6403=18, 6404=14, 6409=22, 6471=26, 6472=27, 6474=21. Menu Master Wilayah menampilkan status dan pencarian, dengan sampel Ya sebagai tampilan awal.

Semua kueri operasional membaca view `pes_sample_*` sehingga hanya sampel masuk filter, alokasi, template, status, rekap, ekspor, dan dashboard. Peta tetap menampilkan geometri nonsampel dengan warna abu-abu tanpa interaksi. Impor master wilayah tidak mengubah status sampel yang sudah ada; wilayah baru default Tidak.

Migrasi database lama: jalankan `php migrations/apply_samples.php` setelah backup. Backup SQL aplikasi menyertakan definisi view dari `migrations/sample_views.sql`. Migrasi tidak menghapus laporan/alokasi lama; view laporan harian membatasi tanggal November dan ID sampel. Target harian progres dihitung merata 100% / 30 hari.
