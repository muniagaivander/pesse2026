<?php
// Target lapangan berbeda dari periode penyimpanan laporan (1–30 November).
const PES_FIELDWORK_END = '2026-11-27';

function pes_fieldwork_schedule(?string $date = null): array
{
    $tz = new DateTimeZone('Asia/Makassar');
    $start = new DateTimeImmutable('2026-11-01', $tz);
    $end = new DateTimeImmutable(PES_FIELDWORK_END, $tz);
    $asOf = new DateTimeImmutable($date ?? 'today', $tz);
    $asOf = $asOf->setTime(0, 0);
    $duration = (int)$start->diff($end)->days + 1;
    $day = $asOf < $start ? 0 : min($duration, (int)$start->diff($asOf)->days + 1);
    $remaining = $asOf < $start ? $duration : max(0, (int)$asOf->diff($end)->format('%r%a'));
    return ['day' => $day, 'duration' => $duration, 'target' => $day / $duration * 100, 'remaining' => $remaining];
}

function pes_monitor_metrics(int $allocation, int $done, array $schedule): array
{
    $ideal = $allocation * $schedule['target'] / 100;
    $achievement = $ideal > 0 ? $done / $ideal * 100 : null;
    $progress = $allocation > 0 ? $done / $allocation * 100 : null;
    $status = $achievement === null ? 'Belum ada target' : ($achievement >= 100 ? 'Sesuai' : ($achievement >= 70 ? 'Waspada' : 'Kritis'));
    $remaining = max(0, $allocation - $done);
    return ['ideal' => $ideal, 'achievement' => $achievement, 'progress' => $progress, 'status' => $status,
        'color' => ['Sesuai'=>'#15803d', 'Waspada'=>'#facc15', 'Kritis'=>'#dc2626'][$status] ?? '#64748b',
        'remaining' => $remaining, 'per_day' => $schedule['remaining'] > 0 ? $remaining / $schedule['remaining'] : null];
}
