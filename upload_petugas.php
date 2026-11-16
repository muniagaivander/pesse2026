<?php
require __DIR__.'/layout.php'; require __DIR__.'/pes_master.php';
$user=require_role(['superadmin','admin_kab']);
$headers=['email','nama','role','satker'];
if(isset($_GET['template'])) pes_template($headers,'template_master_petugas');
$error=null;
if($_SERVER['REQUEST_METHOD']==='POST') {
 pes_check_csrf();
 try {
 $rows=pes_csv($headers); db()->beginTransaction(); $seen=[];
 foreach($rows as $line=>$r) {
 try {
 $email=normalize_email($r['email']); $name=$r['nama']; $role=strtolower($r['role']); $kab=$r['satker'];
 if(!filter_var($email,FILTER_VALIDATE_EMAIL) || strlen($email)>150 || $name==='' || strlen($name)>150 || !in_array($role,['pengawas','pencacah'],true)) throw new RuntimeException('Email, nama, atau role tidak valid.');
 if(isset($seen[$email])) throw new RuntimeException('Email berulang di file.'); $seen[$email]=true;
 pes_scope($user,$kab); $satker=pes_satker($kab); if($kab==='6400') throw new RuntimeException('Petugas harus memiliki satker kabupaten/kota.');
 $q=db()->prepare('SELECT * FROM petugas WHERE email=?'); $q->execute([$email]); $old=$q->fetch();
 if($old) {
 $id=$old['idpetugas'];
 pes_scope($user,$old['satker']);
 if($old['email']!==$email || $old['role']!==$role || $old['satker']!==$kab) throw new RuntimeException('Email sudah terdaftar; role dan satker harus sama. Nama dapat diperbarui.');
 db()->prepare('UPDATE petugas SET nama=? WHERE idpetugas=?')->execute([$name,$id]); db()->prepare('UPDATE users SET name=? WHERE id=?')->execute([$name,$old['user_id']]);
 } else {
 $id='P'.$kab.'-'.strtoupper(bin2hex(random_bytes(8)));
 $q=db()->prepare('SELECT id FROM users WHERE email=? OR username=?'); $q->execute([$email,$email]); if($q->fetchColumn()) throw new RuntimeException('Email sudah digunakan akun lain.');
 db()->prepare('INSERT INTO users(username,email,password_hash,role,kab_id,name,satker) VALUES(?,?,?,?,?,?,?)')->execute([$email,$email,password_hash('123',PASSWORD_DEFAULT),$role,$kab,$name,$satker]); $uid=db()->lastInsertId();
 db()->prepare('INSERT INTO petugas(idpetugas,user_id,email,nama,role,satker) VALUES(?,?,?,?,?,?)')->execute([$id,$uid,$email,$name,$role,$kab]);
 }
 } catch(Throwable $e) { throw new RuntimeException('Baris '.$line.': '.$e->getMessage()); }
 }
 db()->commit(); flash('success',count($rows).' petugas berhasil disimpan.'); redirect('petugas.php');
 } catch(Throwable $e) { if(db()->inTransaction()) db()->rollBack(); $error=$e->getMessage().' Tidak ada perubahan disimpan.'; }
}
render_header('Upload Master Petugas');
?>
<?php if($error): ?><div class="alert alert-danger"><?=e($error)?></div><?php endif ?>
<div class="card"><div class="card-body">
<p>Upload daftar petugas sebelum mengisi alokasi SubSLS. Gunakan CSV UTF-8 dari Excel. ID petugas dibuat otomatis oleh sistem.</p>
<a class="btn btn-outline-success mb-3" href="?template=1">Download Template CSV</a>
<form method="post" enctype="multipart/form-data"><input type="hidden" name="csrf" value="<?=e(pes_csrf())?>"><div class="form-group"><label>File master petugas</label><input class="form-control-file" type="file" name="csv" accept=".csv" required></div><button class="btn btn-primary">Upload Petugas</button></form>
<table class="table table-bordered mt-4"><tr><th>Kolom</th><th>Isi</th></tr><tr><td>email</td><td>Email unik petugas</td></tr><tr><td>nama</td><td>Nama lengkap</td></tr><tr><td>role</td><td>pengawas atau pencacah</td></tr><tr><td>satker</td><td>Kode satker, contoh 6401 (BPS Kabupaten Paser)</td></tr></table>
<p>Upload ulang email yang sudah terdaftar hanya memperbarui nama; ID petugas tetap sama. Login petugas memakai email sebagai username dengan password awal 123. Upload ulang tidak mengubah password akun yang sudah ada.</p>
</div></div><?php render_footer(); ?>
