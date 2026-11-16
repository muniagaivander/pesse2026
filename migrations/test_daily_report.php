<?php
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
require __DIR__.'/../daily_report_data.php';
require __DIR__.'/../daily_report_pdf.php';
function dr_assert(bool $ok,string $message):void {if(!$ok)throw new RuntimeException($message);echo "PASS $message\n";}
$base=['target'=>10,'open_count'=>10,'draft_count'=>0,'submitted_by_pencacah'=>0,'rejected_by_pengawas'=>0,'pending_count'=>0,'approved_by_pengawas'=>0];
dr_assert(dr_classify($base)==='belum','Open-only means Belum');
foreach(['draft_count','submitted_by_pencacah','rejected_by_pengawas','pending_count','approved_by_pengawas'] as $field){$r=$base;$r[$field]=1;dr_assert(dr_classify($r)==='sedang',"$field activates On-progress");}
$r=$base;$r['approved_by_pengawas']=10;dr_assert(dr_classify($r)==='selesai','Approve equals positive target means Selesai');
$r=$base;$r['target']=0;dr_assert(dr_classify($r)==='belum','Zero target does not mean Selesai');
dr_assert(dr_percent(0,0)===0.0,'Zero denominator is safe');
foreach(['admin6400'=>160,'admin6401'=>17,'admin6472'=>27] as $username=>$sampleCount){
 $q=db()->prepare('SELECT * FROM users WHERE username=?');$q->execute([$username]);$u=$q->fetch();
 $data=dr_report_data($u,'2026-11-12');$t=$data['total'];
 dr_assert($t['subsls']===$sampleCount,"$username sample scope");
 dr_assert($t['belum']+$t['sedang']+$t['selesai']===$t['subsls'],"$username mutually exclusive classifications");
 dr_assert($t['assignments']===array_sum(array_intersect_key($t,status_fields())),"$username six-status denominator");
 dr_assert($t['progress']===$t['submitted_by_pencacah']+$t['approved_by_pengawas'],"$username Submit + Approve");
 dr_assert(array_sum(array_column($data['groups'],'assignments'))===$t['assignments'],"$username regional reconciliation");
 $ids=array_column($data['groups'],'id');$sorted=$ids;sort($sorted);dr_assert($ids===$sorted,"$username ascending region codes");
 if($username!=='admin6400')foreach($ids as $id)dr_assert(str_starts_with($id,$u['kab_id']),"$username cannot include another district");
 dr_assert(str_starts_with(dr_build_pdf($data),'%PDF-1.4'),"$username produces a real PDF");
}
try{dr_report_data(['role'=>'viewer_prov'],'2026-11-12');throw new LogicException('Viewer allowed');}catch(RuntimeException $e){dr_assert(str_contains($e->getMessage(),'tidak diizinkan'),'Viewer rejected');}
echo "All Daily Report checks passed.\n";
