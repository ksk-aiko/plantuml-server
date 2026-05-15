<?php

declare(strict_types=1);

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);

if ($method === 'POST' && $path === '/api/render') {
    $rawBody = file_get_contents('php://input');
    $payload = json_decode($rawBody !== false ? $rawBody : '', true);

    if (!is_array($payload)) {
        http_response_code(400);
        header('Content-Type: application/json; charset=UTF-8');
        echo json_encode(['error' => 'invalid_json'], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        exit;
    }

    $uml = isset($payload['uml']) ? (string) $payload['uml'] : '';
    $format = strtolower(isset($payload['format']) ? (string) $payload['format'] : 'svg');
    $contentTypes = [
        'svg' => 'image/svg+xml',
        'png' => 'image/png',
        'txt' => 'text/plain; charset=UTF-8',
    ];

    if (!array_key_exists($format, $contentTypes)) {
        http_response_code(400);
        header('Content-Type: application/json; charset=UTF-8');
        echo json_encode(['error' => 'unsupported_format'], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        exit;
    }

    $targetUrl = 'http://plantuml:8080/' . $format;
    $context = stream_context_create([
        'http' => [
            'method' => 'POST',
            // Added: pass PlantUML source directly to upstream renderer
            'header' => "Content-Type: text/plain; charset=UTF-8\r\n",
            'content' => $uml,
            'timeout' => 10,
            'ignore_errors' => true,
        ],
    ]);

    $upstreamBody = @file_get_contents($targetUrl, false, $context);
    if ($upstreamBody === false) {
        http_response_code(502);
        header('Content-Type: application/json; charset=UTF-8');
        echo json_encode(['error' => 'upstream_unreachable'], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        exit;
    }

    $statusCode = 200;
    $upstreamType = $contentTypes[$format];
    if (isset($http_response_header) && is_array($http_response_header)) {
        foreach ($http_response_header as $line) {
            if (preg_match('/^HTTP\/\S+\s+(\d+)/', $line, $matches) === 1) {
                $statusCode = (int) $matches[1];
            }
            if (stripos($line, 'Content-Type:') === 0) {
                $upstreamType = trim(substr($line, strlen('Content-Type:')));
            }
        }
    }

    http_response_code($statusCode);
    header('Content-Type: ' . $upstreamType);
    echo $upstreamBody;
    exit;
}

header('Content-Type: text/plain; charset=UTF-8');
echo "PlantUML MVP API placeholder\n";
