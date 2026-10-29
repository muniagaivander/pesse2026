<?php
if(PHP_SAPI!=='cli') exit;
require __DIR__.'/../bootstrap.php';
$ids=file(__DIR__.'/sample_ids.txt',FILE_IGNORE_NEW_LINES|FILE_SKIP_EMPTY_LINES);
$all=db()->query('SELECT id FROM master_subsls')->fetchAll(PDO::FETCH_COLUMN);
echo json_encode(['count'=>count($ids),'unique'=>count(array_unique($ids)),'missing'=>array_values(array_diff($ids,$all)),'by_kab'=>array_count_values(array_map(fn($s)=>substr($s,0,4),$ids))],JSON_PRETTY_PRINT);
