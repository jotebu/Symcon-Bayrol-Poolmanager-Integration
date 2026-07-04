<?php
/**
 * BAYROL PoolManager 5 API Delta Scanner
 *
 * Zweck:
 * - Erstellt Snapshots definierter PM5 API-Keys
 * - Vergleicht aktuellen Scan mit vorherigem Snapshot
 * - Zeigt nur geaenderte Werte
 *
 * Nutzung in IP-Symcon:
 * 1. $mode = 'snapshot'; $snapshotName = 'eco'; ausfuehren
 * 2. Zustand am PM5 aendern, z. B. Filterpumpe Normal
 * 3. $mode = 'compare'; $compareAgainst = 'eco'; $snapshotName = 'normal'; ausfuehren
 *
 * Hinweis:
 * - Das Skript speichert Snapshots in einer JSON-Datei im Symcon-Script-Verzeichnis.
 * - Falls IPS_GetScriptFile nicht verfuegbar ist, wird sys_get_temp_dir() verwendet.
 */

$pm5Host = '192.168.55.23';
$pm5Port = 80;
$sid = 'APIDELTASCAN000000000000000001';

// snapshot = nur aktuellen Zustand speichern
// compare  = aktuellen Zustand speichern und gegen einen alten Snapshot vergleichen
$mode = 'snapshot';
$snapshotName = 'snapshot_a';
$compareAgainst = 'snapshot_a';

// Fuer Filterpumpen-Modi bewusst breiter als nur 55.17106,
// damit Nachbarobjekte mit erkannt werden.
$ranges = [
    55 => [17100, 17130],
];

$suffixes = [
    'value',
    'status',
    'opmode',
    'mode',
    'enum',
    'pointer',
    'state1',
    'state2',
    'text1',
    'text2'
];

$watchKeys = [
    '34.4001.value',
    '34.4022.value',
    '34.4033.value',
    '13.16507.text2',
    '13.16509.text1',
    '55.17102.status',
    '55.17102.value',
    '55.17106.status',
    '55.17106.opmode',
    '55.17106.value',
];

$batchSize = 20;
$pauseMicroseconds = 500000;

function getStoragePath(): string
{
    if (function_exists('IPS_GetScriptIDByName') && function_exists('IPS_GetParent') && function_exists('IPS_GetScriptFile')) {
        try {
            $scriptId = $_IPS['SELF'] ?? 0;
            if ($scriptId > 0) {
                $scriptFile = IPS_GetScriptFile($scriptId);
                $dir = dirname($scriptFile);
                return $dir . DIRECTORY_SEPARATOR . 'pm5-api-snapshots.json';
            }
        } catch (Throwable $e) {
            // fallback below
        }
    }
    return sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'pm5-api-snapshots.json';
}

function pm5Post(string $host, int $port, string $sid, array $payload): array
{
    $url = 'http://' . $host . ':' . $port . '/cgi-bin/webgui.fcgi?sid=' . urlencode($sid);
    $context = stream_context_create([
        'http' => [
            'method' => 'POST',
            'header' => "Content-Type: application/json;charset=UTF-8\r\nAccept: application/json\r\n",
            'content' => json_encode($payload),
            'timeout' => 10,
            'ignore_errors' => true
        ]
    ]);

    $raw = @file_get_contents($url, false, $context);
    $headers = $http_response_header ?? [];
    $httpCode = 0;
    if (isset($headers[0]) && preg_match('/HTTP\/\S+\s+(\d+)/', $headers[0], $m)) {
        $httpCode = (int)$m[1];
    }

    return [
        'httpCode' => $httpCode,
        'raw' => $raw,
        'json' => json_decode((string)$raw, true)
    ];
}

function cleanValue($value): string
{
    if (is_array($value) || is_object($value)) {
        return json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }
    return trim(html_entity_decode(strip_tags((string)$value), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
}

function classifyValue($value): string
{
    if (is_bool($value)) return 'bool';
    if (is_int($value)) return 'int';
    if (is_float($value)) return 'float';
    if (!is_string($value)) return gettype($value);
    $trimmed = trim(strip_tags($value));
    $normalized = str_replace(',', '.', $trimmed);
    if ($trimmed === '') return 'empty-string';
    if (preg_match('/^-?[0-9]+$/', $trimmed)) return 'int-string';
    if (preg_match('/^-?[0-9]+\.[0-9]+$/', $normalized)) return 'float-string';
    if ($trimmed !== $value) return 'html-string';
    return 'string';
}

function chunkArray(array $items, int $size): array
{
    $chunks = [];
    $chunk = [];
    foreach ($items as $item) {
        $chunk[] = $item;
        if (count($chunk) >= $size) {
            $chunks[] = $chunk;
            $chunk = [];
        }
    }
    if (count($chunk) > 0) $chunks[] = $chunk;
    return $chunks;
}

function buildKeys(array $ranges, array $suffixes, array $watchKeys): array
{
    $keys = [];
    foreach ($ranges as $prefix => $range) {
        [$start, $end] = $range;
        for ($id = $start; $id <= $end; $id++) {
            foreach ($suffixes as $suffix) {
                $keys[] = $prefix . '.' . $id . '.' . $suffix;
            }
        }
    }
    foreach ($watchKeys as $key) {
        $keys[] = $key;
    }
    $keys = array_values(array_unique($keys));
    sort($keys, SORT_NATURAL);
    return $keys;
}

function scanKeys(string $host, int $port, string $sid, array $keys, int $batchSize, int $pauseMicroseconds): array
{
    $rows = [];
    $requestCount = 0;
    foreach (chunkArray($keys, $batchSize) as $batch) {
        $requestCount++;
        $response = pm5Post($host, $port, $sid, ['get' => $batch]);
        $json = $response['json'];
        if (!is_array($json)) {
            echo "Request {$requestCount}: HTTP=" . $response['httpCode'] . " invalid JSON\n";
            continue;
        }
        $data = $json['data'] ?? [];
        echo "Request {$requestCount}: HTTP=" . $response['httpCode'] . ", asked=" . count($batch) . ", returned=" . (is_array($data) ? count($data) : 0) . "\n";
        if (is_array($data)) {
            foreach ($data as $key => $value) {
                $rows[$key] = [
                    'value' => cleanValue($value),
                    'raw' => is_scalar($value) ? (string)$value : json_encode($value),
                    'type' => classifyValue($value)
                ];
            }
        }
        usleep($pauseMicroseconds);
    }
    ksort($rows, SORT_NATURAL);
    return $rows;
}

function loadSnapshots(string $path): array
{
    if (!file_exists($path)) return [];
    $json = json_decode((string)file_get_contents($path), true);
    return is_array($json) ? $json : [];
}

function saveSnapshots(string $path, array $snapshots): void
{
    file_put_contents($path, json_encode($snapshots, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
}

function diffSnapshots(array $oldRows, array $newRows): array
{
    $allKeys = array_unique(array_merge(array_keys($oldRows), array_keys($newRows)));
    sort($allKeys, SORT_NATURAL);
    $diff = [];
    foreach ($allKeys as $key) {
        $oldExists = array_key_exists($key, $oldRows);
        $newExists = array_key_exists($key, $newRows);
        $oldValue = $oldExists ? $oldRows[$key]['value'] : '<missing>';
        $newValue = $newExists ? $newRows[$key]['value'] : '<missing>';
        if ($oldValue !== $newValue) {
            $diff[$key] = [
                'old' => $oldValue,
                'new' => $newValue,
                'oldType' => $oldExists ? $oldRows[$key]['type'] : '-',
                'newType' => $newExists ? $newRows[$key]['type'] : '-',
            ];
        }
    }
    return $diff;
}

$keys = buildKeys($ranges, $suffixes, $watchKeys);
$storagePath = getStoragePath();

$started = microtime(true);
echo "BAYROL PM5 API Delta Scanner\n";
echo "Mode: {$mode}\n";
echo "Snapshot: {$snapshotName}\n";
echo "Compare against: {$compareAgainst}\n";
echo "Storage: {$storagePath}\n";
echo "Keys: " . count($keys) . "\n\n";

$currentRows = scanKeys($pm5Host, $pm5Port, $sid, $keys, $batchSize, $pauseMicroseconds);

$snapshots = loadSnapshots($storagePath);
$snapshots[$snapshotName] = [
    'createdAt' => date('c'),
    'rows' => $currentRows
];
saveSnapshots($storagePath, $snapshots);

echo "\nSaved snapshot '{$snapshotName}' with " . count($currentRows) . " returned keys.\n";

if ($mode === 'compare') {
    if (!isset($snapshots[$compareAgainst])) {
        echo "\nCompare snapshot '{$compareAgainst}' not found. Available snapshots:\n";
        foreach (array_keys($snapshots) as $name) {
            echo "  - {$name}\n";
        }
        exit;
    }

    $oldRows = $snapshots[$compareAgainst]['rows'] ?? [];
    $diff = diffSnapshots($oldRows, $currentRows);

    echo "\n==============================\n";
    echo "DIFF {$compareAgainst} -> {$snapshotName}\n";
    echo "Changed keys: " . count($diff) . "\n\n";
    echo "| Key | Old | New |\n";
    echo "|---|---|---|\n";
    foreach ($diff as $key => $row) {
        $old = str_replace('|', '\\|', $row['old']);
        $new = str_replace('|', '\\|', $row['new']);
        echo '| `' . $key . '` | `' . $old . '` | `' . $new . '` |' . "\n";
    }

    echo "\nCSV DIFF\n";
    echo "key;old;new\n";
    foreach ($diff as $key => $row) {
        $old = str_replace([';', "\r", "\n"], [',', ' ', ' '], $row['old']);
        $new = str_replace([';', "\r", "\n"], [',', ' ', ' '], $row['new']);
        echo $key . ';' . $old . ';' . $new . "\n";
    }
}

$duration = round(microtime(true) - $started, 2);
echo "\nDuration: {$duration}s\n";
