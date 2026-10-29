<?php
require_once __DIR__.'/bootstrap.php';
function pes_csv(array $headers): array {
 if (($_FILES['csv']['error'] ?? UPLOAD_ERR_NO_FILE)!==UPLOAD_ERR_OK) throw new RuntimeException('Pilih file CSV yang valid.');
 $f=fopen($_FILES['csv']['tmp_name'],'r'); $line=fgets($f); rewind($f); $sep=substr_count($line,';')>substr_count($line,',')?';':',';
 $head=fgetcsv($f,0,$sep,'"',''); $head=array_map(fn($s)=>trim($s,"\xEF\xBB\xBF \t\r\n"),$head ?: []);
 if ($head!==$headers) throw new RuntimeException('Header CSV harus: '.implode(',',$headers));
 $rows=[]; $n=1;
 while(($r=fgetcsv($f,0,$sep,'"',''))!==false) { $n++; if ($r===[null]) continue; if(count($r)!==count($head)) throw new RuntimeException("Baris $n: jumlah kolom salah."); $rows[$n]=array_combine($head,array_map('trim',$r)); }
 fclose($f); if(!$rows) throw new RuntimeException('CSV kosong.'); return $rows;
}
function pes_template(array $headers,string $name): void {
 header('Content-Type: text/csv; charset=utf-8'); header('Content-Disposition: attachment; filename="'.$name.'.csv"'); $f=fopen('php://output','w'); fputcsv($f,$headers,',','"',''); fclose($f); exit;
}
function pes_scope(array $user,string $kab): void {
 if(in_array($user['role'],['admin_kab','viewer_kab'],true) && $user['kab_id']!==$kab) throw new RuntimeException('Wilayah di luar satker Anda.');
}
function pes_allocate(array $user,string $id,string $pengawas,string $pencacah): void {
 $q=db()->prepare('SELECT kc.kab_id FROM pes_sample_subsls ms JOIN pes_sample_sls s ON s.id=ms.sls_id JOIN pes_sample_desa d ON d.id=s.desa_id JOIN pes_sample_kec kc ON kc.id=d.kec_id WHERE ms.id=?'); $q->execute([$id]); $kab=$q->fetchColumn();
 if(!$kab) throw new RuntimeException('ID SubSLS tidak ditemukan atau bukan sampel PES: '.$id); pes_scope($user,$kab);
 foreach(['pengawas'=>$pengawas,'pencacah'=>$pencacah] as $role=>$email) {
 if($email==='') throw new RuntimeException('Email '.$role.' wajib diisi.');
 $q=db()->prepare('SELECT p.idpetugas FROM petugas p JOIN users u ON u.id=p.user_id WHERE p.email=? AND p.role=? AND p.satker=? AND u.active=1'); $q->execute([$email,$role,$kab]);
 if(!$q->fetchColumn()) throw new RuntimeException("Email $role $email belum terdaftar, tidak aktif, atau role/satker tidak sesuai.");
 }
 db()->prepare('UPDATE master_subsls SET pengawas_email=?,pencacah_email=? WHERE id=?')->execute([$pengawas,$pencacah,$id]);
 db()->prepare('UPDATE daily_status SET pengawas_email=?,pencacah_email=? WHERE subsls_id=?')->execute([$pengawas,$pencacah,$id]);
}
