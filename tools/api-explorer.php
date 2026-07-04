<?php
/**
 * BAYROL PoolManager 5 API Explorer - staged low-load discovery
 *
 * Zweck:
 * - Schonender zweistufiger Scan der lokalen PM5 JSON-API
 * - Profilmodus fuer unterschiedliche Objektklassen
 * - Phase 1: Existenzscan ueber profilabhaengiges Suffix
 * - Phase 2: Detailscan nur fuer gefundene Objekt-IDs
 * - Watcher-Keys fuer bekannte Anzeige-/Soll-/Grenzwerte
 * - Platzhalterwerte werden gefiltert
 * - HTTP 500/503 werden erkannt und mit Backoff behandelt
 */

$pm5Host = '192.168.55.23';
$pm5Port = 80;
$sid = 'APIEXPLORERLOWLOAD0000000001';

// Profile:
// measurement = numerische Mess-/Soll-/Grenzwerte, typisch Prefix 34
// gauge       = Anzeige-/Dashboard-Elemente, typisch Prefix 13
// status      = Text-/Statusmeldungen, typisch Prefix 15
// actuator    = Ausgaenge/Relais/Aktoren, typisch Prefix 55
$profile = 'measurement';

// Scanbereiche passend zum Profil setzen.
$ranges = [
    34 => [4000, 4050],
];

// Wichtige bekannte/gesuchte Einzelwerte, die immer zusaetzlich abgefragt werden.
// Diese Liste bitte erweitern, sobald neue Kandidaten bekannt sind.
$watchKeys = [
    '34.4001.value', // pH Istwert
    '34.4022.value', // Redox Istwert
    '34.4033.value', // Pooltemperatur
    '13.16507.text2', // T3 Aussentemperatur als Dashboard-Text
    '13.16509.text1', // Leitfaehigkeit
];

$profiles = [
    'measurement' => [
        'existenceSuffix' => 'value',
        'detailSuffixes' => ['value'],
    ],
    'gauge' => [
        'existenceSuffix' => 'text1',
        'detailSuffixes' => ['pointer', 'text1', 'text2', 'color', 'state1', 'state2'],
    ],
    'status' => [
        'existenceSuffix' => 'value',
        'detailSuffixes' => ['value', 'status', 'text1', 'text2', 'state1', 'state2'],
    ],
    'actuator' => [
        'existenceSuffix' => 'value',
        'detailSuffixes' => ['value', 'status', 'state1', 'state2', 'text1', 'text2'],
    ],
];

if (!isset($profiles[$profile])) {
    throw new Exception('Unknown profile: ' . $profile);
}

$existenceSuffix = $profiles[$profile]['existenceSuffix'];
$detailSuffixes = $profiles[$profile]['detailSuffixes'];

$batchSizePhase1 = 20;
$batchSizePhase2 = 20;
$pauseMicroseconds = 500000; // 0,5 Sekunden
$errorBackoffSeconds = 5;
$maxConsecutiveErrors = 3;

$placeholderValues = [
    'value', 'status', 'unit', 'name', 'min', 'max', 'enum', 'opmode',
    'color', 'text1', 'text2', 'state1', 'state2', 'pointer'
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
    if ($clean === '') return false;

    $parts = explode('.', $key);
    $suffix = end($parts);

    if ($clean === $suffix) return true;
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
    if (count($chunk) > 0) $chunks[] = $chunk;
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

function addRow(array &$rows, string $key, $value): void
{
    $objectId = objectIdFromKey($key);
    $rows[$key] = [
        'key' => $key,
        'object' => $objectId,
        'value' => cleanValue($value),
        'type' => classifyValue($value)
    ];
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

function requestBatches(
    string $label,
    array $keys,
    int $batchSize,
    string $host,
    int $port,
    string $sid,
    array $placeholderValues,
    bool $filterPlaceholders,
    int $pauseMicroseconds,
    int $errorBackoffSeconds,
    int $maxConsecutiveErrors,
    int &$requestCount
): array {
    $rows = [];
    $consecutiveErrors = 0;

    foreach (chunkArray($keys, $batchSize) as $batch) {
        $requestCount++;
        $response = pm5Post($host, $port, $sid, ['get' => $batch]);
        $json = $response['json'];

        if (!is_array($json)) {
            $consecutiveErrors++;
            echo "{$label} request {$requestCount}: HTTP=" . $response['httpCode'] . " invalid JSON. Backoff {$errorBackoffSeconds}s\n";
            echo substr((string)$response['raw'], 0, 160) . "\n";
            sleep($errorBackoffSeconds);
            if ($consecutiveErrors >= $maxConsecutiveErrors) {
                echo "Too many consecutive errors. Stopping {$label}.\n";
                break;
            }
            continue;
        }

        $consecutiveErrors = 0;
        $code = $json['status']['code'] ?? 'NO_CODE';
        $data = $json['data'] ?? [];
        echo "{$label} request {$requestCount}: HTTP=" . $response['httpCode'] . ", status={$code}, asked=" . count($batch) . ", returned=" . (is_array($data) ? count($data) : 0) . "\n";

        if (is_array($data)) {
            foreach ($data as $key => $value) {
                if ($filterPlaceholders && isPlaceholder($key, $value, $placeholderValues)) {
                    continue;
                }
                addRow($rows, $key, $value);
            }
        }
        usleep($pauseMicroseconds);
    }

    ksort($rows, SORT_NATURAL);
    return $rows;
}

$started = microtime(true);
$requestCount = 0;
$existingObjects = [];

$phase1Keys = makeExistenceKeys($ranges, $existenceSuffix);

echo "BAYROL PM5 API Explorer - profile mode\n";
echo "Host: {$pm5Host}:{$pm5Port}\n";
echo "Profile: {$profile}\n";
echo "Phase 1 suffix: .{$existenceSuffix}\n";
echo "Phase 1 candidates: " . count($phase1Keys) . "\n";
echo "Phase 1 batch size: {$batchSizePhase1}\n";
echo "Phase 2 batch size: {$batchSizePhase2}\n";
echo "Pause: " . ($pauseMicroseconds / 1000000) . "s\n\n";

echo "==============================\n";
echo "PHASE 1: existence scan\n";
$phase1Rows = requestBatches('Phase1', $phase1Keys, $batchSizePhase1, $pm5Host, $pm5Port, $sid, $placeholderValues, true, $pauseMicroseconds, $errorBackoffSeconds, $maxConsecutiveErrors, $requestCount);

foreach ($phase1Rows as $row) {
    $existingObjects[$row['object']] = true;
}
ksort($existingObjects, SORT_NATURAL);
$objectIds = array_keys($existingObjects);

echo "\nPhase 1 found real objects: " . count($objectIds) . "\n";
foreach ($objectIds as $objectId) {
    echo "  - {$objectId}\n";
}

$phase2Rows = [];
if (count($objectIds) > 0) {
    echo "\n==============================\n";
    echo "PHASE 2: detail scan for found objects\n";

    $detailKeys = [];
    foreach ($objectIds as $objectId) {
        foreach ($detailSuffixes as $suffix) {
            $detailKeys[] = $objectId . '.' . $suffix;
        }
    }

    $phase2Rows = requestBatches('Phase2', $detailKeys, $batchSizePhase2, $pm5Host, $pm5Port, $sid, $placeholderValues, true, $pauseMicroseconds, $errorBackoffSeconds, $maxConsecutiveErrors, $requestCount);
} else {
    echo "\nNo real objects found in phase 1. Check profile/range/suffix.\n";
}

$watchRows = [];
if (count($watchKeys) > 0) {
    echo "\n==============================\n";
    echo "WATCH KEYS: known and searched values\n";
    $watchRows = requestBatches('Watch', $watchKeys, $batchSizePhase2, $pm5Host, $pm5Port, $sid, $placeholderValues, false, $pauseMicroseconds, $errorBackoffSeconds, $maxConsecutiveErrors, $requestCount);
}

$duration = round(microtime(true) - $started, 2);

echo "\n==============================\n";
echo "SUMMARY\n";
echo "Requests total: {$requestCount}\n";
echo "Phase 1 candidates: " . count($phase1Keys) . "\n";
echo "Objects found: " . count($objectIds) . "\n";
echo "Detail keys found: " . count($phase2Rows) . "\n";
echo "Watch keys returned: " . count($watchRows) . "\n";
echo "Duration: {$duration}s\n\n";

echo "PHASE 1 REAL VALUE KEYS\n";
printRows($phase1Rows);

echo "\nPHASE 2 DETAILS\n";
printRows($phase2Rows);

echo "\nWATCH KEYS\n";
printRows($watchRows);

echo "\nCSV DETAILS\n";
printCsv($phase2Rows);

echo "\nCSV WATCH\n";
printCsv($watchRows);
