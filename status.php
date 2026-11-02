<?php
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
$path = __DIR__.'/state.json';
$handle = fopen($path, 'c+');
if (!$handle) { http_response_code(503); echo json_encode(['error'=>'state unavailable']); exit; }
flock($handle, LOCK_SH);
rewind($handle);
$data = json_decode(stream_get_contents($handle), true);
$now = (int)round(microtime(true) * 1000);
flock($handle, LOCK_UN);
fclose($handle);
if (!is_array($data)) { http_response_code(503); echo json_encode(['error'=>'invalid state']); exit; }
$data['server_time_ms'] = $now;
echo json_encode($data, JSON_UNESCAPED_SLASHES);
