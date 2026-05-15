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

if ($method === 'GET' && $path === '/') {
        header('Content-Type: text/html; charset=UTF-8');
        echo <<<'HTML'
<!doctype html>
<html lang="ja">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>PlantUML MVP</title>
    <style>
        :root {
            color-scheme: light;
            --bg: #f4f6fb;
            --panel: #ffffff;
            --text: #1b2735;
            --muted: #5b6675;
            --accent: #1f7a8c;
            --border: #d8deea;
        }
        * { box-sizing: border-box; }
        body {
            margin: 0;
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif;
            color: var(--text);
            background:
                radial-gradient(circle at 100% 0%, #d9edf2 0, transparent 35%),
                radial-gradient(circle at 0% 100%, #e9ecf8 0, transparent 35%),
                var(--bg);
            min-height: 100vh;
        }
        .container {
            max-width: 1080px;
            margin: 24px auto;
            padding: 0 16px;
            display: grid;
            grid-template-columns: 1fr;
            gap: 16px;
        }
        .card {
            background: var(--panel);
            border: 1px solid var(--border);
            border-radius: 14px;
            padding: 16px;
            box-shadow: 0 10px 24px rgba(22, 35, 67, 0.06);
        }
        h1 {
            margin: 0 0 8px;
            font-size: 1.4rem;
        }
        .muted {
            margin: 0;
            color: var(--muted);
            font-size: 0.95rem;
        }
        textarea {
            width: 100%;
            min-height: 220px;
            margin-top: 12px;
            padding: 12px;
            border: 1px solid var(--border);
            border-radius: 10px;
            font-family: ui-monospace, SFMono-Regular, Menlo, Consolas, monospace;
            font-size: 14px;
            line-height: 1.5;
            resize: vertical;
        }
        .controls {
            margin-top: 12px;
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
            align-items: center;
        }
        select, button {
            height: 38px;
            border-radius: 9px;
            border: 1px solid var(--border);
            padding: 0 12px;
            font-size: 14px;
            background: #fff;
        }
        button {
            background: var(--accent);
            border-color: var(--accent);
            color: #fff;
            cursor: pointer;
        }
        .result {
            min-height: 220px;
            display: grid;
            place-items: center;
            border: 1px dashed var(--border);
            border-radius: 10px;
            padding: 12px;
            overflow: auto;
            background: #fff;
        }
        .result img {
            max-width: 100%;
            height: auto;
            display: block;
        }
        .result pre {
            width: 100%;
            margin: 0;
            white-space: pre-wrap;
            font-family: ui-monospace, SFMono-Regular, Menlo, Consolas, monospace;
        }
        .status {
            margin-top: 8px;
            color: var(--muted);
            font-size: 0.9rem;
        }
        @media (min-width: 960px) {
            .container {
                grid-template-columns: 1fr 1fr;
            }
            .hero {
                grid-column: 1 / -1;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <section class="card hero">
            <h1>PlantUML Learning & Rendering Platform (MVP)</h1>
            <p class="muted">Detail Phase 1: HTML/CSS/JavaScript の最小画面</p>
        </section>

        <section class="card">
            <label for="umlInput">PlantUML</label>
            <textarea id="umlInput">@startuml
Alice -> Bob: Hello
@enduml</textarea>
            <div class="controls">
                <label for="format">Format</label>
                <select id="format">
                    <option value="svg">svg</option>
                    <option value="png">png</option>
                    <option value="txt">txt</option>
                </select>
                <button id="renderBtn" type="button">Render</button>
            </div>
            <p class="status" id="status">Ready</p>
        </section>

        <section class="card">
            <div class="result" id="result"></div>
        </section>
    </div>

    <script>
        const umlInput = document.getElementById('umlInput');
        const format = document.getElementById('format');
        const renderBtn = document.getElementById('renderBtn');
        const result = document.getElementById('result');
        const status = document.getElementById('status');

        const render = async () => {
            status.textContent = 'Rendering...';
            result.innerHTML = '';

            try {
                const res = await fetch('/api/render', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ uml: umlInput.value, format: format.value })
                });

                if (!res.ok) {
                    const text = await res.text();
                    throw new Error(text || `HTTP ${res.status}`);
                }

                if (format.value === 'svg') {
                    const svg = await res.text();
                    result.innerHTML = svg;
                } else if (format.value === 'png') {
                    const blob = await res.blob();
                    const url = URL.createObjectURL(blob);
                    const img = document.createElement('img');
                    img.src = url;
                    img.alt = 'Rendered PNG';
                    result.appendChild(img);
                } else {
                    const text = await res.text();
                    const pre = document.createElement('pre');
                    pre.textContent = text;
                    result.appendChild(pre);
                }

                status.textContent = 'Rendered';
            } catch (err) {
                const pre = document.createElement('pre');
                pre.textContent = String(err.message || err);
                result.appendChild(pre);
                status.textContent = 'Failed';
            }
        };

        renderBtn.addEventListener('click', render);
    </script>
</body>
</html>
HTML;
        exit;
}

header('Content-Type: text/plain; charset=UTF-8');
echo "PlantUML MVP API placeholder\n";
