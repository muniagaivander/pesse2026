<?php
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require __DIR__ . '/../data_check.php';
if (!in_array('--apply', $argv, true)) exit("Usage: php migrations/setup_data_checks.php --apply [--keluarga=FILE.xlsx --usaha_keluarga=FILE.xlsx --usaha_bku=FILE.xlsx --individu=FILE.xlsx]\n");
db()->exec(file_get_contents(__DIR__ . '/20261112_data_checks.sql'));
$files = [];
foreach (array_slice($argv, 1) as $arg) {
    if (!str_contains($arg, '=')) continue;
    [$kind, $path] = explode('=', $arg, 2);
    $kind = ltrim($kind, '-');
    dc_definition($kind);
    $files[$kind] = ['name' => basename($path), 'path' => $path];
}
if ($files) {
    $owner = db()->query("SELECT * FROM users WHERE username='admin6400' AND active=1")->fetch();
    if (!$owner) throw new RuntimeException('Akun admin6400 aktif tidak ditemukan.');
    foreach (dc_save_uploads($owner, $files) as $result) echo $result . PHP_EOL;
} else {
    echo "Tabel pengecekan data siap.\n";
}
