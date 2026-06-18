<?php

declare(strict_types=1);

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
$MAX_UML_BYTES = 100000;
$MAX_TEMP_CONTENT_BYTES = 2000000;

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

    if (strlen($uml) > $MAX_UML_BYTES) {
        http_response_code(413);
        header('Content-Type: application/json; charset=UTF-8');
        echo json_encode(['error' => 'uml_too_large'], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        exit;
    }

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

if (str_starts_with($path, '/api/')) {
    if ($method === 'POST' && $path === '/api/temp-files') {
        $rawBody = file_get_contents('php://input');
        $payload = json_decode($rawBody !== false ? $rawBody : '', true);

        if (!is_array($payload)) {
            http_response_code(400);
            header('Content-Type: application/json; charset=UTF-8');
            echo json_encode(['error' => 'invalid_json'], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
            exit;
        }

        $format = strtolower((string) ($payload['format'] ?? ''));
        $content = (string) ($payload['content'] ?? '');

        if (strlen($content) > $MAX_TEMP_CONTENT_BYTES) {
            http_response_code(413);
            header('Content-Type: application/json; charset=UTF-8');
            echo json_encode(['error' => 'content_too_large'], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
            exit;
        }

        $allowedFormats = ['svg', 'png', 'txt'];
        if (!in_array($format, $allowedFormats, true)) {
            http_response_code(400);
            header('Content-Type: application/json; charset=UTF-8');
            echo json_encode(['error' => 'unsupported_format'], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
            exit;
        }

        if ($content === '') {
            http_response_code(400);
            header('Content-Type: application/json; charset=UTF-8');
            echo json_encode(['error' => 'empty_content'], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
            exit;
        }

        $fileData = $content;
        if ($format === 'png') {
            $decoded = base64_decode($content, true);
            if ($decoded === false) {
                http_response_code(400);
                header('Content-Type: application/json; charset=UTF-8');
                echo json_encode(['error' => 'invalid_base64_png'], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
                exit;
            }
            if (strlen($decoded) > $MAX_TEMP_CONTENT_BYTES) {
                http_response_code(413);
                header('Content-Type: application/json; charset=UTF-8');
                echo json_encode(['error' => 'content_too_large'], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
                exit;
            }
            $fileData = $decoded;
        }

        $tempDir = '/tmp/plantuml_exports';
        if (!is_dir($tempDir) && !mkdir($tempDir, 0700, true)) {
            http_response_code(500);
            header('Content-Type: application/json; charset=UTF-8');
            echo json_encode(['error' => 'temp_dir_create_failed'], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
            exit;
        }

        // Added: generate opaque filename and store file in tmp directory
        $fileId = bin2hex(random_bytes(16));
        $fileName = $fileId . '.' . $format;
        $filePath = $tempDir . '/' . $fileName;

        if (file_put_contents($filePath, $fileData) === false) {
            http_response_code(500);
            header('Content-Type: application/json; charset=UTF-8');
            echo json_encode(['error' => 'temp_file_write_failed'], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
            exit;
        }

        header('Content-Type: application/json; charset=UTF-8');
        echo json_encode([
            'status' => 'saved',
            'id' => $fileId,
            'fileName' => $fileName,
            'bytes' => strlen($fileData),
        ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        exit;
    }

    if ($method === 'DELETE' && preg_match('#^/api/temp-files/([a-f0-9]{32})$#', $path, $matches) === 1) {
        $fileId = $matches[1];
        $tempDir = '/tmp/plantuml_exports';

        $deleted = false;
        foreach (['svg', 'png', 'txt'] as $ext) {
            $candidate = $tempDir . '/' . $fileId . '.' . $ext;
            if (is_file($candidate) && unlink($candidate)) {
                // Added: stop on first matching temp file deletion
                $deleted = true;
                break;
            }
        }

        if (!$deleted) {
            http_response_code(404);
            header('Content-Type: application/json; charset=UTF-8');
            echo json_encode(['error' => 'temp_file_not_found'], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
            exit;
        }

        header('Content-Type: application/json; charset=UTF-8');
        echo json_encode([
            'status' => 'deleted',
            'id' => $fileId,
        ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        exit;
    }

    if ($method === 'GET' && $path === '/api/health') {
        header('Content-Type: application/json; charset=UTF-8');
        echo json_encode([
            'status' => 'ok',
            'service' => 'plantuml-mvp-api',
        ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        exit;
    }

    http_response_code(404);
    header('Content-Type: application/json; charset=UTF-8');
    echo json_encode([
        'error' => 'api_not_found',
    ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
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
        #editor {
            width: 100%;
            height: 320px;
            margin-top: 12px;
            border: 1px solid var(--border);
            border-radius: 10px;
            overflow: hidden;
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
        .error-box {
            margin-top: 10px;
            border: 1px solid #f2b8bf;
            background: #fff1f3;
            color: #8a1c2f;
            border-radius: 10px;
            padding: 10px 12px;
            font-size: 0.9rem;
            display: none;
            white-space: pre-wrap;
            word-break: break-word;
        }
        .cheatsheet-list {
            margin-top: 12px;
            display: grid;
            gap: 10px;
            max-height: 340px;
            overflow: auto;
            padding-right: 4px;
        }
        .cheatsheet-item {
            border: 1px solid var(--border);
            border-radius: 10px;
            padding: 10px;
            background: #fbfcff;
        }
        .cheatsheet-title {
            font-size: 0.95rem;
            margin: 0 0 4px;
        }
        .cheatsheet-meta {
            margin: 0 0 8px;
            color: var(--muted);
            font-size: 0.85rem;
        }
        .cheatsheet-empty {
            color: var(--muted);
            font-size: 0.9rem;
            margin: 8px 0 0;
        }
        .problem-list {
            margin-top: 12px;
            display: grid;
            gap: 10px;
            max-height: 340px;
            overflow: auto;
            padding-right: 4px;
        }
        .problem-item {
            border: 1px solid var(--border);
            border-radius: 10px;
            padding: 10px;
            background: #fcfffb;
        }
        .problem-title {
            font-size: 0.95rem;
            margin: 0 0 4px;
        }
        .problem-meta {
            margin: 0 0 8px;
            color: var(--muted);
            font-size: 0.85rem;
        }
        .problem-empty {
            color: var(--muted);
            font-size: 0.9rem;
            margin: 8px 0 0;
        }
        .problem-detail {
            border: 1px solid var(--border);
            border-radius: 10px;
            padding: 12px;
            background: #ffffff;
        }
        .problem-detail h3 {
            margin: 0 0 8px;
            font-size: 1rem;
        }
        .problem-detail p {
            margin: 0 0 6px;
            color: var(--muted);
            font-size: 0.9rem;
        }
        .problem-detail pre {
            margin: 8px 0 0;
            max-height: 220px;
            overflow: auto;
            border: 1px dashed var(--border);
            border-radius: 8px;
            padding: 8px;
            background: #fbfcff;
            white-space: pre-wrap;
            font-family: ui-monospace, SFMono-Regular, Menlo, Consolas, monospace;
            font-size: 12px;
        }
        .answer-block {
            margin-top: 10px;
            border: 1px solid var(--border);
            border-radius: 8px;
            padding: 8px;
            background: #fffdf7;
            display: none;
        }
        .answer-block pre {
            margin: 0;
        }
        .compare-block {
            margin-top: 10px;
            border: 1px solid var(--border);
            border-radius: 8px;
            padding: 8px;
            background: #f8fbff;
        }
        .compare-block pre {
            margin: 0;
            max-height: 220px;
            overflow: auto;
            white-space: pre-wrap;
            font-family: ui-monospace, SFMono-Regular, Menlo, Consolas, monospace;
            font-size: 12px;
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
            <div id="editor"></div>
            <div class="controls">
                <label for="format">Format</label>
                <select id="format">
                    <option value="svg">svg</option>
                    <option value="png">png</option>
                    <option value="txt">txt</option>
                </select>
                <button id="renderBtn" type="button">Render</button>
                <button id="downloadSvgBtn" type="button">Download SVG</button>
                <button id="downloadPngBtn" type="button">Download PNG</button>
                <button id="downloadTxtBtn" type="button">Download TXT</button>
            </div>
            <p class="status" id="status">Ready</p>
            <div class="error-box" id="errorBox"></div>
        </section>

        <section class="card">
            <div class="result" id="result"></div>
        </section>

        <section class="card hero">
            <h2 class="cheatsheet-title">Cheat Sheets</h2>
            <p class="cheatsheet-meta">図種別のサンプルを読み込み、エディタへ反映できます。</p>
            <div class="cheatsheet-list" id="cheatsheetList"></div>
            <p class="cheatsheet-empty" id="cheatsheetEmpty">Loading cheatsheets...</p>
        </section>

        <section class="card hero">
            <h2 class="problem-title">Practice Problems</h2>
            <p class="problem-meta">問題一覧から選択してエディタへ反映できます。</p>
            <div class="problem-list" id="problemList"></div>
            <p class="problem-empty" id="problemEmpty">Loading problems...</p>
            <div class="problem-detail" id="problemDetail">
                <h3 id="problemDetailTitle">No problem selected</h3>
                <p id="problemDetailTheme">Theme: -</p>
                <p id="problemDetailId">ID: -</p>
                <pre id="problemDetailUml">Select a problem to view details.</pre>
                <button id="answerToggleBtn" type="button">Show Answer</button>
                <div class="answer-block" id="answerBlock">
                    <pre id="answerDetailUml">No answer available.</pre>
                </div>
                <button id="compareBtn" type="button">Compare Answer</button>
                <div class="compare-block" id="compareBlock">
                    <pre id="compareOutput">Comparison not run yet.</pre>
                </div>
            </div>
        </section>
    </div>

    <script src="https://cdnjs.cloudflare.com/ajax/libs/monaco-editor/0.52.2/min/vs/loader.min.js"></script>
    <script>
        const umlInput = document.getElementById('umlInput');
        const editorContainer = document.getElementById('editor');
        const format = document.getElementById('format');
        const renderBtn = document.getElementById('renderBtn');
        const downloadSvgBtn = document.getElementById('downloadSvgBtn');
        const downloadPngBtn = document.getElementById('downloadPngBtn');
        const downloadTxtBtn = document.getElementById('downloadTxtBtn');
        const result = document.getElementById('result');
        const status = document.getElementById('status');
        const errorBox = document.getElementById('errorBox');
        const cheatsheetList = document.getElementById('cheatsheetList');
        const cheatsheetEmpty = document.getElementById('cheatsheetEmpty');
        const problemList = document.getElementById('problemList');
        const problemEmpty = document.getElementById('problemEmpty');
        let editor = null;
        let debounceTimer = null;
        let lastRenderedSvg = '';
        let lastRenderedPngBlob = null;
        let lastRenderedTxt = '';
        const DEBOUNCE_MS = 500;

        const problemDetailTitle = document.getElementById('problemDetailTitle');
        const problemDetailTheme = document.getElementById('problemDetailTheme');
        const problemDetailId = document.getElementById('problemDetailId');
        const problemDetailUml = document.getElementById('problemDetailUml');
        const answerToggleBtn = document.getElementById('answerToggleBtn');
        const answerBlock = document.getElementById('answerBlock');
        const answerDetailUml = document.getElementById('answerDetailUml');
        const compareBtn = document.getElementById('compareBtn');
        const compareOutput = document.getElementById('compareOutput');
        let isAnswerVisible = false;

        const clearError = () => {
            if (!errorBox) {
                return;
            }
            errorBox.textContent = '';
            errorBox.style.display = 'none';
        };

        const showError = (message) => {
            if (!errorBox) {
                return;
            }
            errorBox.textContent = message;
            errorBox.style.display = 'block';
        };

        const initEditor = () => {
            if (!window.require || !editorContainer) {
                status.textContent = 'Monaco unavailable, using textarea';
                return;
            }

            window.require.config({
                paths: {
                    vs: 'https://cdnjs.cloudflare.com/ajax/libs/monaco-editor/0.52.2/min/vs'
                }
            });

            window.require(['vs/editor/editor.main'], () => {
                editor = window.monaco.editor.create(editorContainer, {
                    value: umlInput.value,
                    language: 'plaintext',
                    theme: 'vs',
                    minimap: { enabled: false },
                    fontSize: 14,
                    automaticLayout: true,
                    lineNumbers: 'on',
                    wordWrap: 'on'
                });

                // Added: bind realtime render updates to Monaco input changes
                editor.onDidChangeModelContent(scheduleRender);

                umlInput.style.display = 'none';
            }, () => {
                status.textContent = 'Monaco load failed, using textarea';
            });
        };

        const currentSource = () => {
            if (editor) {
                return editor.getValue();
            }
            return umlInput.value;
        };

        const setSource = (nextSource) => {
            if (editor) {
                editor.setValue(nextSource);
                return;
            }
            umlInput.value = nextSource;
        };

        const renderCheatsheetList = (items) => {
            if (!cheatsheetList || !cheatsheetEmpty) {
                return;
            }

            cheatsheetList.innerHTML = '';
            if (!Array.isArray(items) || items.length === 0) {
                cheatsheetEmpty.textContent = 'No cheatsheets available.';
                return;
            }

            cheatsheetEmpty.style.display = 'none';

            items.forEach((item) => {
                const wrap = document.createElement('div');
                wrap.className = 'cheatsheet-item';

                const title = document.createElement('p');
                title.className = 'cheatsheet-title';
                title.textContent = item.title || 'Untitled';

                const meta = document.createElement('p');
                meta.className = 'cheatsheet-meta';
                meta.textContent = (item.theme || 'unknown') + ' - ' + (item.description || '');

                const useBtn = document.createElement('button');
                useBtn.type = 'button';
                useBtn.textContent = 'Use in Editor';
                useBtn.addEventListener('click', () => {
                    setSource(String(item.uml || ''));
                    format.value = 'svg';
                    render();
                });

                wrap.appendChild(title);
                wrap.appendChild(meta);
                wrap.appendChild(useBtn);
                cheatsheetList.appendChild(wrap);
            });
        };

        const loadCheatsheets = async () => {
            try {
                const res = await fetch('/data/cheatsheets.json');
                if (!res.ok) {
                    throw new Error('Failed to load cheatsheets');
                }
                const items = await res.json();
                // Added: render cheatsheet entries from static JSON
                renderCheatsheetList(items);
            } catch (err) {
                if (cheatsheetEmpty) {
                    cheatsheetEmpty.textContent = 'Failed to load cheatsheets.';
                }
            }
        };

        const renderProblemList = (items) => {
            if (!problemList || !problemEmpty) {
                return;
            }

            problemList.innerHTML = '';
            if (!Array.isArray(items) || items.length === 0) {
                problemEmpty.textContent = 'No problems available.';
                return;
            }

            problemEmpty.style.display = 'none';

            items.forEach((item) => {
                const wrap = document.createElement('div');
                wrap.className = 'problem-item';

                const title = document.createElement('p');
                title.className = 'problem-title';
                title.textContent = (item.id ? '#' + item.id + ' ' : '') + (item.title || 'Untitled');

                const meta = document.createElement('p');
                meta.className = 'problem-meta';
                meta.textContent = item.theme || 'unknown';

                const openBtn = document.createElement('button');
                openBtn.type = 'button';
                openBtn.textContent = 'Open Problem';
                openBtn.addEventListener('click', () => {
                    showProblemDetail(item);
                    setSource(String(item.uml || ''));
                    format.value = 'svg';
                    render();
                });
                wrap.appendChild(title);
                wrap.appendChild(meta);
                wrap.appendChild(openBtn);
                problemList.appendChild(wrap);
            });
        };

        const showProblemDetail = (item) => {
            if (!problemDetailTitle || !problemDetailTheme || !problemDetailId || !problemDetailUml) {
                return;
            }

            problemDetailTitle.textContent = item.title || 'Untitled';
            problemDetailTheme.textContent = 'Theme: ' + (item.theme || '-');
            problemDetailId.textContent = 'ID: ' + (item.id ?? '-');
            // Added: show selected problem UML as detail preview
            problemDetailUml.textContent = String(item.uml || '');

            if (answerDetailUml) {
                // Added: use problem UML as answer seed for MVP answer toggle
                answerDetailUml.textContent = String(item.uml || 'No answer available.');
            }

            isAnswerVisible = false;
            if (answerBlock) {
                answerBlock.style.display = 'none';
            }
            if (answerToggleBtn) {
                answerToggleBtn.textContent = 'Show Answer';
            }

            if (compareOutput) {
                compareOutput.textContent = 'Comparison not run yet.';
            }
        };

        const buildComparisonText = (userSource, answerSource) => {
            const userLines = String(userSource || '').split(/\r?\n/);
            const answerLines = String(answerSource || '').split(/\r?\n/);
            const maxLen = Math.max(userLines.length, answerLines.length);
            const diffs = [];

            for (let i = 0; i < maxLen; i += 1) {
                const u = userLines[i] ?? '';
                const a = answerLines[i] ?? '';
                if (u !== a) {
                    diffs.push(`L${i + 1} | user: ${u}`);
                    diffs.push(`L${i + 1} | ans : ${a}`);
                }
            }

            if (diffs.length === 0) {
                return 'Matched: user answer is identical to reference answer.';
            }

            return [
                `Mismatch lines: ${diffs.length / 2}`,
                '',
                ...diffs
            ].join('\n');
        };

        const loadProblems = async () => {
            try {
                const res = await fetch('/data/problems.json');
                if (!res.ok) {
                    throw new Error('Failed to load problems');
                }
                const items = await res.json();
                // Added: render problem entries from static JSON
                renderProblemList(items);
            } catch (err) {
                if (problemEmpty) {
                    problemEmpty.textContent = 'Failed to load problems.';
                }
            }
        };

        const render = async () => {
            status.textContent = 'Rendering...';
            result.innerHTML = '';
            clearError();

            try {
                const res = await fetch('/api/render', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ uml: currentSource(), format: format.value })
                });

                if (!res.ok) {
                    const text = await res.text();
                    throw new Error(text || `HTTP ${res.status}`);
                }

                if (format.value === 'svg') {
                    const svg = await res.text();
                    // Added: cache latest SVG for download action
                    lastRenderedSvg = svg;
                    lastRenderedPngBlob = null;
                    lastRenderedTxt = '';
                    result.innerHTML = svg;
                } else if (format.value === 'png') {
                    const blob = await res.blob();
                    lastRenderedSvg = '';
                    // Added: cache latest PNG blob for download action
                    lastRenderedPngBlob = blob;
                    lastRenderedTxt = '';
                    const url = URL.createObjectURL(blob);
                    const img = document.createElement('img');
                    img.src = url;
                    img.alt = 'Rendered PNG';
                    result.appendChild(img);
                } else {
                    const text = await res.text();
                    lastRenderedSvg = '';
                    lastRenderedPngBlob = null;
                    // Added: cache latest ASCII text for download action
                    lastRenderedTxt = text;
                    const pre = document.createElement('pre');
                    pre.textContent = text;
                    result.appendChild(pre);
                }

                status.textContent = 'Rendered';
            } catch (err) {
                // Changed: move render error details to dedicated error area
                showError(String(err.message || err));
                status.textContent = 'Failed';
            }
        };

        // Added: debounce render requests for realtime preview updates
        const scheduleRender = () => {
            if (debounceTimer !== null) {
                clearTimeout(debounceTimer);
            }
            debounceTimer = setTimeout(() => {
                render();
            }, DEBOUNCE_MS);
        };

        renderBtn.addEventListener('click', render);
        downloadSvgBtn.addEventListener('click', () => {
            clearError();

            if (!lastRenderedSvg) {
                showError('No rendered SVG is available. Render with format=svg first.');
                return;
            }

            // Added: trigger client-side SVG download without server-side storage
            const blob = new Blob([lastRenderedSvg], { type: 'image/svg+xml;charset=utf-8' });
            const url = URL.createObjectURL(blob);
            const link = document.createElement('a');
            link.href = url;
            link.download = 'diagram.svg';
            document.body.appendChild(link);
            link.click();
            document.body.removeChild(link);
            URL.revokeObjectURL(url);
        });
        downloadPngBtn.addEventListener('click', () => {
            clearError();

            if (!lastRenderedPngBlob) {
                showError('No rendered PNG is available. Render with format=png first.');
                return;
            }

            // Added: trigger client-side PNG download without server-side storage
            const url = URL.createObjectURL(lastRenderedPngBlob);
            const link = document.createElement('a');
            link.href = url;
            link.download = 'diagram.png';
            document.body.appendChild(link);
            link.click();
            document.body.removeChild(link);
            URL.revokeObjectURL(url);
        });
        downloadTxtBtn.addEventListener('click', () => {
            clearError();

            if (!lastRenderedTxt) {
                showError('No rendered TXT is available. Render with format=txt first.');
                return;
            }

            // Added: trigger client-side TXT download without server-side storage
            const blob = new Blob([lastRenderedTxt], { type: 'text/plain;charset=utf-8' });
            const url = URL.createObjectURL(blob);
            const link = document.createElement('a');
            link.href = url;
            link.download = 'diagram.txt';
            document.body.appendChild(link);
            link.click();
            document.body.removeChild(link);
            URL.revokeObjectURL(url);
        });
        answerToggleBtn.addEventListener('click', () => {
            if (!answerBlock || !answerToggleBtn) {
                return;
            }

            isAnswerVisible = !isAnswerVisible;
            // Added: toggle answer visibility in problem detail panel
            answerBlock.style.display = isAnswerVisible ? 'block' : 'none';
            answerToggleBtn.textContent = isAnswerVisible ? 'Hide Answer' : 'Show Answer';
        });
        compareBtn.addEventListener('click', () => {
            if (!compareOutput || !answerDetailUml) {
                return;
            }

            // Added: compare current editor content with selected problem answer
            compareOutput.textContent = buildComparisonText(currentSource(), answerDetailUml.textContent);
        });
        format.addEventListener('change', scheduleRender);
        umlInput.addEventListener('input', scheduleRender);

        initEditor();
        loadCheatsheets();
        loadProblems();
    </script>
</body>
</html>
HTML;
        exit;
}

header('Content-Type: text/plain; charset=UTF-8');
echo "PlantUML MVP API placeholder\n";
