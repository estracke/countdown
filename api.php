<?php
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
if ($_SERVER['REQUEST_METHOD'] !== 'POST') { http_response_code(405); exit; }
$incoming = json_decode(file_get_contents('php://input'), true);
if (!is_array($incoming) || !in_array($incoming['mode'] ?? null, ['running','idle'], true)) {
    http_response_code(422); echo json_encode(['error'=>'Invalid command']); exit;
}
$path = __DIR__.'/state.json';
$fp = fopen($path, 'c+');
if (!$fp || !flock($fp, LOCK_EX)) { http_response_code(503); exit; }
rewind($fp);
$prev = json_decode(stream_get_contents($fp), true);
if (!is_array($prev)) $prev = ['mode'=>'idle','stop_signal'=>0,'show_when_seconds'=>180];
$now = (int) round(microtime(true) * 1000);
$next = $prev;
if ($incoming['mode'] === 'running') {
    if (isset($incoming['duration_seconds'])) {
        $seconds = filter_var($incoming['duration_seconds'], FILTER_VALIDATE_INT);
        if ($seconds === false || $seconds <= 0 || $seconds > 31536000) { flock($fp, LOCK_UN); fclose($fp); http_response_code(422); exit; }
        $target = $now + $seconds * 1000;
    } else if (isset($incoming['target_epoch_ms']) && is_numeric($incoming['target_epoch_ms'])) {
        $target = (int)$incoming['target_epoch_ms'];
        if ($target <= $now || $target > $now + 31536000000) { flock($fp, LOCK_UN); fclose($fp); http_response_code(422); exit; }
    } else {
        flock($fp, LOCK_UN); fclose($fp); http_response_code(422); exit;
    }
    $next['mode'] = 'running';
    $next['target_epoch_ms'] = $target;
    $next['target_time'] = gmdate('Y-m-d\TH:i:s', (int)floor($target/1000)).'Z';
    $show = $incoming['show_when_seconds'] ?? '';
    if ($show !== '' && (!is_numeric($show) || $show < 0 || $show > 360000)) {
        flock($fp, LOCK_UN); fclose($fp); http_response_code(422); exit;
    }
    $next['show_when_seconds'] = $show;
} else {
    $next['mode'] = 'idle';
    $next['target_time'] = null;
    $next['target_epoch_ms'] = null;
    $next['stop_signal'] = (int)($prev['stop_signal'] ?? 0) + 1;
    if (array_key_exists('show_when_seconds', $incoming)) {
        $next['show_when_seconds'] = $incoming['show_when_seconds'];
    }
}
$next['revision'] = (int)($prev['revision'] ?? 0) + 1;
rewind($fp);
ftruncate($fp, 0);
fwrite($fp, json_encode($next, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
fflush($fp);
flock($fp, LOCK_UN);
fclose($fp);
$next['server_time_ms'] = (int) round(microtime(true) * 1000);
echo json_encode($next, JSON_UNESCAPED_SLASHES);
