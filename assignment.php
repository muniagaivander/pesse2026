<?php
require __DIR__.'/layout.php'; require __DIR__.'/pes_master.php';
$user=require_role(['superadmin','admin_kab','viewer_prov','viewer_kab']); $canEdit=in_array($user['role'],['superadmin','admin_kab'],true);
$kab=in_array($user['role'],['admin_kab','viewer_kab'],true)?$user['kab_id']:trim($_GET['kab_id']??''); $desa=trim($_GET['desa_id']??''); $error=null;
if($_SERVER['REQUEST_METHOD']==='POST') {
 if(!$canEdit) { http_response_code(403); exit('Akses ditolak'); } pes_check_csrf();
 try { db()->beginTransaction(); $ids=$_POST['subsls_id']??[]; if(!is_array($ids)||!$ids) throw new RuntimeException('Tidak ada SubSLS dipilih.');
 foreach($ids as $i=>$id) pes_allocate($user,$id,normalize_email($_POST['pengawas_email'][$i]??''),normalize_email($_POST['pencacah_email'][$i]??''));
 db()->commit(); flash('success','Alokasi berhasil disimpan.'); redirect('assignment.php?'.http_build_query(['kab_id'=>$kab,'desa_id'=>$desa]));
 }catch(Throwable $e){ if(db()->inTransaction()) db()->rollBack(); $error=$e->getMessage(); }
}
$villages=[]; $rows=[]; $staff=[];
if($kab!=='') { pes_scope($user,$kab); $q=db()->prepare('SELECT d.id,d.nmdesa,k.nmkec FROM pes_sample_desa d JOIN pes_sample_kec k ON k.id=d.kec_id WHERE k.kab_id=? ORDER BY k.nmkec,d.nmdesa'); $q->execute([$kab]); $villages=$q->fetchAll();
 $q=db()->prepare('SELECT p.* FROM petugas p JOIN users u ON u.id=p.user_id WHERE p.satker=? AND u.active=1 ORDER BY p.email'); $q->execute([$kab]); $staff=$q->fetchAll();
 if($desa!=='') { $q=db()->prepare('SELECT ms.*,s.nmsls FROM pes_sample_subsls ms JOIN pes_sample_sls s ON s.id=ms.sls_id JOIN pes_sample_desa d ON d.id=s.desa_id JOIN pes_sample_kec k ON k.id=d.kec_id WHERE d.id=? AND k.kab_id=? ORDER BY ms.id'); $q->execute([$desa,$kab]); $rows=$q->fetchAll(); }
}
render_header('Alokasi SubSLS / Ganti Petugas');
?>
<?php if($msg=flash('success')): ?><div class="alert alert-success"><?=e($msg)?></div><?php endif ?>
<?php if($error): ?><div class="alert alert-danger"><?=e($error)?></div><?php endif ?>
<?php if($canEdit): ?><a class="btn btn-success mb-3" href="assignment_template.php">Upload Alokasi SubSLS</a> <a class="btn btn-outline-primary mb-3" href="upload_petugas.php">Upload Master Petugas</a><?php endif ?>
<form method="get" class="card card-body"><div class="row"><div class="col-md-5"><label>Satker</label><select class="form-control" name="kab_id" onchange="this.form.desa_id.value='';this.form.submit()"><option value="">Pilih satker</option><?php foreach(db()->query('SELECT id,nmkab FROM pes_sample_kab ORDER BY id') as $r): if(in_array($user['role'],['admin_kab','viewer_kab'],true)&&$r['id']!==$user['kab_id']) continue; ?><option value="<?=e($r['id'])?>" <?=$kab===$r['id']?'selected':''?>><?=e(pes_satker($r['id']))?></option><?php endforeach ?></select></div><div class="col-md-5"><label>Desa / Kelurahan</label><select class="form-control" name="desa_id" onchange="this.form.submit()"><option value="">Pilih desa</option><?php foreach($villages as $r): ?><option value="<?=e($r['id'])?>" <?=$desa===$r['id']?'selected':''?>><?=e($r['id'].' - '.$r['nmkec'].' / '.$r['nmdesa'])?></option><?php endforeach ?></select></div></div></form>
<?php if($rows): ?><form method="post"><input type="hidden" name="csrf" value="<?=e(pes_csrf())?>"><div class="card"><div class="card-body table-responsive"><table class="table table-bordered"><thead><tr><th>ID SubSLS</th><th>Nama SLS</th><th>Email Pengawas</th><th>Email Pencacah</th></tr></thead><tbody>
<?php foreach($rows as $r): ?><tr><td><?=e($r['id'])?><input type="hidden" name="subsls_id[]" value="<?=e($r['id'])?>"></td><td><?=e($r['nmsls'])?></td><?php foreach(['pengawas','pencacah'] as $role): ?><td><select class="form-control" name="<?=$role?>_email[]" <?=$canEdit?'required':'disabled'?>><option value="">Pilih email <?=$role?></option><?php foreach($staff as $p): if($p['role']!==$role) continue; ?><option value="<?=e($p['email'])?>" <?=$r[$role.'_email']===$p['email']?'selected':''?>><?=e($p['email'].' — '.$p['nama'])?></option><?php endforeach ?></select></td><?php endforeach ?></tr><?php endforeach ?>
</tbody></table></div></div><?php if($canEdit): ?><button class="btn btn-primary mb-3">Simpan Alokasi</button><?php endif ?></form><?php else: ?><div class="alert alert-info">Pilih satker dan desa untuk menampilkan SubSLS.</div><?php endif ?>
<?php render_footer(); ?>
