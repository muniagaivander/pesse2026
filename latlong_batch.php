<?php
// Bounded candidate lookup only; exact polygon tests run in the browser with Turf.
function latlong_batch_candidates($raw): array
{
    if (!is_string($raw) || strlen($raw) > 10000) throw new InvalidArgumentException('Batch koordinat tidak valid.');
    $values = json_decode($raw, true);
    if (!is_array($values) || !array_is_list($values) || count($values) < 1 || count($values) > 25) {
        throw new InvalidArgumentException('Satu permintaan hanya dapat memproses 1 sampai 25 baris.');
    }
    $items = [];
    foreach ($values as $value) {
        $item = ['lat' => null, 'lng' => null, 'candidates' => [], 'error' => ''];
        if (!is_string($value) || strlen($value) > 120 || !preg_match('/^\s*([+-]?\d+(?:\.\d+)?)\s*,\s*([+-]?\d+(?:\.\d+)?)\s*$/D', $value, $m)) {
            $item['error'] = 'Format latlong tidak valid; gunakan latitude, longitude.';
        } else {
            $item['lat'] = (float)$m[1]; $item['lng'] = (float)$m[2];
            if (abs($item['lat']) > 90 || abs($item['lng']) > 180) $item['error'] = 'Koordinat di luar rentang latitude/longitude.';
        }
        $items[] = $item;
    }
    $dir = __DIR__ . '/cache/latlong';
    $index = json_decode(file_get_contents($dir . '/index.json'), true, 512, JSON_THROW_ON_ERROR);
    $handle = fopen($dir . '/features.jsonl', 'rb');
    if (!$handle) throw new RuntimeException('Indeks polygon tidak dapat dibaca.');
    $features = []; $bytes = 0;
    try {
        foreach ($index['rows'] as $row) {
            foreach ($items as &$item) {
                if ($item['error'] !== '' || $item['lng'] < $row[0] || $item['lat'] < $row[1] || $item['lng'] > $row[2] || $item['lat'] > $row[3]) continue;
                $key = (string)$row[4];
                if (!isset($features[$key])) {
                    $bytes += $row[5];
                    if ($bytes > 16 * 1024 * 1024) throw new RuntimeException('Batch polygon terlalu besar.');
                    if (fseek($handle, $row[4]) !== 0) throw new RuntimeException('Indeks polygon tidak valid.');
                    $json = stream_get_contents($handle, $row[5]);
                    if ($json === false || strlen($json) !== $row[5]) throw new RuntimeException('Data polygon tidak lengkap.');
                    $features[$key] = json_decode($json, true, 512, JSON_THROW_ON_ERROR);
                }
                $item['candidates'][] = $key;
            }
            unset($item);
        }
    } finally { fclose($handle); }
    return ['items' => $items, 'features' => (object)$features];
}
