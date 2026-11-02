<?php
header("Content-Type: application/json");
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");

$data = json_decode(file_get_contents("php://input"), true);

file_put_contents(
  "state.json",
  json_encode($data, JSON_PRETTY_PRINT)
);

echo json_encode(["ok" => true]);
