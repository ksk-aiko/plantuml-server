<?php

declare(strict_types=1);

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);

if ($method === 'POST' && $path === '/api/render') {
    header('Content-Type: application/json; charset=UTF-8');
    echo json_encode([
        'status' => 'ok',
        'message' => 'render endpoint placeholder',
        'format' => 'svg',
        'data' => '<svg xmlns="http://www.w3.org/2000/svg" width="220" height="40"><text x="10" y="25">PlantUML MVP</text></svg>'
    ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    exit;
}

header('Content-Type: text/plain; charset=UTF-8');
echo "PlantUML MVP API placeholder\n";
