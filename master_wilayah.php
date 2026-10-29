<?php
require __DIR__.'/layout.php';
$user=require_role(['superadmin','admin_kab','viewer_prov','viewer_kab']);
$status=in_array($_GET['status_sampel']??'Ya',['Ya','Tidak','Semua'],true)?($_GET['status_sampel']??'Ya'):'Ya';
$search=trim((string)($_GET['q']??'')); $page=max(1,(int)($_GET['page']??1));
$where=[];$params=[];
if($status!=='Semua') {$where[]='ms.status_sampel=?';$params[]=$status;}
if(in_array($user['role'],['admin_kab','viewer_kab'],true)) {$where[]='k.id=?';$params[]=$user['kab_id'];}
if($search!=='') {$where[]='(ms.id LIKE ? OR s.nmsls LIKE ? OR d.nmdesa LIKE ?)';array_push($params,'%'.$search.'%','%'.$search.'%','%'.$search.'%');}
$base=' FROM master_subsls ms JOIN master_sls s ON s.id=ms.sls_id JOIN master_desa d ON d.id=s.desa_id JOIN master_kec kc ON kc.id=d.kec_id JOIN master_kab k ON k.id=kc.kab_id';
$condition=$where?' WHERE '.implode(' AND ',$where):'';
$q=db()->prepare('SELECT COUNT(*)'.$base.$condition);$q->execute($params);$count=(int)$q->fetchColumn();$pages=max(1,(int)ceil($count/100));$page=min($page,$pages);
$q=db()->prepare('SELECT ms.id,ms.status_sampel,s.nmsls,d.nmdesa,kc.nmkec,k.id kab_id,k.nmkab'.$base.$condition.' ORDER BY ms.id LIMIT 100 OFFSET '.(($page-1)*100));$q->execute($params);$rows=$q->fetchAll();
render_header('Master Wilayah SubSLS');
?>
<div class="alert alert-info">Periode PES: <strong>1–30 November 2026</strong>. Hanya SubSLS berstatus sampel <strong>Ya</strong> yang masuk alokasi, filter, dan perhitungan kegiatan. Wilayah nonsampel tetap disimpan di master.</div>
<form method="get" class="card card-body"><div class="row"><div class="col-md-3"><label>Status Sampel</label><select class="form-control" name="status_sampel"><?php foreach(['Ya','Tidak','Semua'] as $v): ?><option <?=$status===$v?'selected':''?>><?=e($v)?></option><?php endforeach ?></select></div><div class="col-md-6"><label>Cari ID SubSLS / SLS / Desa</label><input class="form-control" name="q" value="<?=e($search)?>"></div><div class="col-md-3 align-self-end"><button class="btn btn-primary">Tampilkan</button></div></div></form>
<div class="card"><div class="card-header"><?=number_format($count,0,',','.')?> SubSLS · Halaman <?=$page?> / <?=$pages?></div><div class="card-body table-responsive"><table class="table table-bordered table-sm"><thead><tr><th>ID SubSLS</th><th>Kabupaten/Kota</th><th>Kecamatan</th><th>Desa</th><th>SLS</th><th>Status Sampel</th></tr></thead><tbody><?php foreach($rows as $r): ?><tr><td><?=e($r['id'])?></td><td><?=e($r['kab_id'].' - '.$r['nmkab'])?></td><td><?=e($r['nmkec'])?></td><td><?=e($r['nmdesa'])?></td><td><?=e($r['nmsls'])?></td><td><span class="badge badge-<?=$r['status_sampel']==='Ya'?'success':'secondary'?>"><?=e($r['status_sampel'])?></span></td></tr><?php endforeach ?></tbody></table></div><div class="card-footer"><?php foreach(['Sebelumnya'=>$page-1,'Berikutnya'=>$page+1] as $label=>$n): if($n<1||$n>$pages)continue; ?><a class="btn btn-outline-primary mr-2" href="?<?=e(http_build_query(['status_sampel'=>$status,'q'=>$search,'page'=>$n]))?>"><?=e($label)?></a><?php endforeach ?></div></div>
<?php render_footer(); ?>
