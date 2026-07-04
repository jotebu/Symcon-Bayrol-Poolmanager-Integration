<?php
/**
 * BAYROL PoolManager 5 API Explorer - staged low-load discovery
 *
 * Zweck:
 * - Schonender zweistufiger Scan der lokalen PM5 JSON-API
 * - Phase 1: Existenzscan nur ueber .value
 * - Phase 2: Detailscan nur fuer gefundene Objekt-IDs
 * - Platzhalterwerte werden gefiltert
 * - HTTP 500/503 werden erkannt und mit Backoff behandelt
 *
 * Nutzung:
 * - In IP-Symcon als normales PHP-Skript ausfuehren
 * - Zuerst mit den kleinen Default-Bereichen testen
 * - Bereiche danach schrittweise erweitern
 */

$pm5Host = '192.168.55.23';
$pm5Port = 80;
$sid = 'APIEXPLORERLOWLOAD0000000001';

// Scanbereiche: prefix => [start, end]
// Diese Bereiche sind bewusst konservativ gehalten.
$ranges = [
    13 => [16500, 16520],
    14 => [16500, 16600],
    15 => [16700, 16720],
    34 => [4000, 4050],
    35 => [4000, 4050],
    55 => [17100, 17130],
];

// Phase 1 prueft nur dieses Suffix.
$existenceSuffix = 'value';

// Phase 2 prueft Detailfelder nur fuer gefundene Objekt-IDs.
$detailSuffixes = [
    'value',
    'status',
    'unit',
    'name',
    'min',
    'max',
    'pointer',
    'color',
    'text1',
    'text2',
    'state1',
    'state2',
    'enum',
    'opmode'
];

// PM5 schonen: kleine Batches und deutliche Pausen.
$batchSizePhase1 = 20;
$batchSizePhase2 = 20;
$pauseMicroseconds = 500000; // 0,5 Sekunden
$errorBackoffSeconds = 5;
$maxConsecutiveErrors = 3;

$placeholderValues = [
    'value',
    'status',
    'unit',
    'name',
    'min',
    'max',
    'enum',
    'opmode',
    'color',
    'text1',
    'text2',
    'state1',
    'state2',
    'pointer'
];

function pm5Post(string $host, int $port, string $sid, array $payload): array
{
    $url = 'http://' . $host . ':' . $port . '/cgi-bin/webgui.fcgi?sid=' . urlencode($sid);
    $body = json_encode($payload);

    $context = stream_context_create([
        'http' => [
            'method' => 'POST',
            'header' => "Content-Type: application/json;charset=UTF-8\r\nAccept: application/json\r\n",
            'content' => $body,
            'timeout' => 10,
            'ignore_errors' => true
        ]
    ]);

    $raw = @file_get_contents($url, false, $context);
    $headers = $http_response_header ?? [];
    $httpCode = 0;

    if (isset($headers[0]) && preg_match('/HTTP\/\S+\s+(\d+)/', $headers[0], $match)) {
        $httpCode = (int)$match[1];
    }

    $json = json_decode((string)$raw, true);

    return [
        'url' => $url,
        'httpCode' => $httpCode,
        'raw' => $raw,
        'json' => is_array($json) ? $json : null
    ];
}

function classifyValue($value): string
{
    if (is_bool($value)) {
        return 'bool';
    }
    if (is_int($value)) {
        return 'int';
    }
    if (is_float($value)) {
        return 'float';
    }
    if (!is_string($value)) {
        return gettype($value);
    }

    $trimmed = trim(strip_tags($value));
    $normalized = str_replace(',', '.', $trimmed);

    if ($trimmed === '') {
        return 'empty-string';
    }
    if (preg_match('/^-?[0-9]+$/', $trimmed)) {
        return 'int-string';
    }
    if (preg_match('/^-?[0-9]+\.[0-9]+$/', $normalized)) {
        return 'float-string';
    }
    if ($trimmed !== $value) {
        return 'html-string';
    }
    return 'string';
}

function cleanValue($value): string
{
    if (is_array($value) || is_object($value)) {
        return json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }
    return trim(html_entity_decode(strip_tags((string)$value), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
}

function isPlaceholder(string $key, $value, array $placeholderValues): bool
{
    $clean = cleanValue($value);
    if ($clean === '') {
        return false;
    }

    $parts = explode('.', $key);
    $suffix = end($parts);

    if ($clean === $suffix) {
        return true;
    }

    return in_array($clean, $placeholderValues, true);
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
    if (count($chunk) > 0) {
        $chunks[] = $chunk;
    }
    return $chunks;
}

function objectIdFromKey(string $key): string
{
    $parts = explode('.', $key);
    return ($parts[0] ?? '') . '.' . ($parts[1] ?? '');
}

function makeExistenceKeys(array $ranges, string $suffix): array
{
    $keys = [];
    foreach ($ranges as $prefix => $range) {
        [$start, $end] = $range;
        for ($id = $start; $id <= $end; $id++) {
            $keys[] = $prefix . '.' . $id . '.' . $suffix;
        }
    }
    return $keys;
}

function printRows(array $rows): void
{
    echo "| Key | Type | Value |\n";
    echo "|---|---|---|\n";
    foreach ($rows as $row) {
        $value = str_replace('|', '\\|', $row['value']);
        echo '| `' . $row['key'] . '` | ' . $row['type'] . ' | `' . $value . '` |' . "\n";
    }
}

function printCsv(array $rows): void
{
    echo "key;object;type;value\n";
    foreach ($rows as $row) {
        $value = str_replace([';', "\r", "\n"], [',', ' ', ' '], $row['value']);
        echo $row['key'] . ';' . $row['object'] . ';' . $row['type'] . ';' . $value . "\n";
    }
}

$started = microtime(true);
$requestCount = 0;
$consecutiveErrors = 0;
$existingObjects = [];
$phase1Rows = [];
$phase2Rows = [];

$phase1Keys = makeExistenceKeys($ranges, $existenceSuffix);

echo "BAYROL PM5 API Explorer - staged low-load discovery\n";
echo "Host: {$pm5Host}:{$pm5Port}\n";
echo "Phase 1 candidates: " . count($phase1Keys) . "\n";
echo "Phase 1 batch size: {$batchSizePhase1}\n";
echo "Phase 2 batch size: {$batchSizePhase2}\n";
echo "Pause: " . ($pauseMicroseconds / 1000000) . "s\n\n";

echo "==============================\n";
echo "PHASE 1: existence scan using .{$existenceSuffix}\n";

foreach (chunkArray($phase1Keys, $batchSizePhase1) as $batch) {
    $requestCount++;
    $response = pm5Post($pm5Host, $pm5Port, $sid, ['get' => $batch]);
    $json = $response['json'];

    if (!is_array($json)) {
        $consecutiveErrors++;
        echo "Phase1 request {$requestCount}: HTTP=" . $response['httpCode'] . " invalid JSON. Backoff {$errorBackoffSeconds}s\n";
        echo substr((string)$response['raw'], 0, 160) . "\n";
        sleep($errorBackoffSeconds);
        if ($consecutiveErrors >= $maxConsecutiveErrors) {
            echo "Too many consecutive errors. Stopping phase 1.\n";
            break;
        }
        continue;
    }

    $consecutiveErrors = 0;
    $code = $json['status']['code'] ?? 'NO_CODE';
    $data = $json['data'] ?? [];

    echo "Phase1 request {$requestCount}: HTTP=" . $response['httpCode'] . ", status={$code}, asked=" . count($batch) . ", returned=" . (is_array($data) ? count($data) : 0) . "\n";

    if (is_array($data)) {
        foreach ($data as $key => $value) {
            if (isPlaceholder($key, $value, $placeholderValues)) {
                continue;
            }

            $objectId = objectIdFromKey($key);
            $existingObjects[$objectId] = true;
            $phase1Rows[$key] = [
                'key' => $key,
                'object' => $objectId,
                'value' => cleanValue($value),
                'type' => classifyValue($value)
            ];
        }
    }

    usleep($pauseMicroseconds);
}

ksort($existingObjects, SORT_NATURAL);
ksort($phase1Rows, SORT_NATURAL);

$objectIds = array_keys($existingObjects);

echo "\nPhase 1 found real objects: " . count($objectIds) . "\n";
foreach ($objectIds as $objectId) {
    echo "  - {$objectId}\n";
}

if (count($objectIds) === 0) {
    echo "\nNo real objects found in phase 1. Try different ranges or inspect placeholder filtering.\n";
} else {
    echo "\n==============================\n";
    echo "PHASE 2: detail scan for found objects\n";

    $detailKeys = [];
    foreach ($objectIds as $objectId) {
        foreach ($detailSuffixes as $suffix) {
            $detailKeys[] = $objectId . '.' . $suffix;
        }
    }

    foreach (chunkArray($detailKeys, $batchSizePhase2) as $batch) {
        $requestCount++;
        $response = pm5Post($pm5Host, $pm5Port, $sid, ['get' => $batch]);
        $json = $response['json'];

        if (!is_array($json)) {
            $consecutiveErrors++;
            echo "Phase2 request {$requestCount}: HTTP=" . $response['httpCode'] . " invalid JSON. Backoff {$errorBackoffSeconds}s\n";
            echo substr((string)$response['raw'], 0, 160) . "\n";
            sleep($errorBackoffSeconds);
            if ($consecutiveErrors >= $maxConsecutiveErrors) {
                echo "Too many consecutive errors. Stopping phase 2.\n";
                break;
            }
            continue;
        }

        $consecutiveErrors = 0;
        $code = $json['status']['code'] ?? 'NO_CODE';
        $data = $json['data'] ?? [];

        echo "Phase2 request {$requestCount}: HTTP=" . $response['httpCode'] . ", status={$code}, asked=" . count($batch) . ", returned=" . (is_array($data) ? count($data) : 0) . "\n";

        if (is_array($data)) {
            foreach ($data as $key => $value) {
                if (isPlaceholder($key, $value, $placeholderValues)) {
                    continue;
                }

                $objectId = objectIdFromKey($key);
                $phase2Rows[$key] = [
                    'key' => $key,
                    'object' => $objectId,
                    'value' => cleanValue($value),
                    'type' => classifyValue($value)
                ];
            }
        }

        usleep($pauseMicroseconds);
    }
}

ksort($phase2Rows, SORT_NATURAL);
$duration = round(microtime(true) - $started, 2);

echo "\n==============================\n";
echo "SUMMARY\n";
echo "Requests total: {$requestCount}\n";
echo "Phase 1 candidates: " . count($phase1Keys) . "\n";
echo "Objects found: " . count($objectIds) . "\n";
echo "Detail keys found: " . count($phase2Rows) . "\n";
echo "Duration: {$duration}s\n\n";

echo "PHASE 1 REAL VALUE KEYS\n";
printRows($phase1Rows);

echo "\nPHASE 2 DETAILS\n";
printRows($phase2Rows);

echo "\nCSV\n";
printCsv($phase2Rows);
