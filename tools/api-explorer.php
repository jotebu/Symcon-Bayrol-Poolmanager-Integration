<?php
/**
 * BAYROL PoolManager 5 API Explorer
 *
 * Zweck:
 * - Direktes Scannen der lokalen PM5 JSON-API
 * - Keine Anmeldung erforderlich fuer reine Lesezugriffe
 * - Fragt konfigurierbare Objektbereiche und Suffixe ab
 * - Gibt nur vorhandene Keys aus
 *
 * Nutzung:
 * - In IP-Symcon als normales PHP-Skript ausfuehren
 * - Zuerst mit kleinen Bereichen testen
 * - Danach Bereiche schrittweise erweitern
 */

$pm5Host = '192.168.55.23';
$pm5Port = 80;
$sid = 'APIEXPLORER0000000000000000001';

// Scanbereiche: prefix => [start, end]
// Bewusst klein gehalten fuer den ersten sicheren Lauf.
$ranges = [
    13 => [16500, 16520],
    14 => [16500, 16600],
    15 => [16700, 16720],
    34 => [4000, 4050],
    35 => [4000, 4050],
    55 => [17100, 17130],
];

$suffixes = [
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

$batchSize = 120;
$pauseMicroseconds = 150000; // 0,15 Sekunden Pause je Request

function pm5Post(string $host, int $port, string $sid, array $payload): array
{
    $url = 'http://' . $host . ':' . $port . '/cgi-bin/webgui.fcgi?sid=' . urlencode($sid);
    $body = json_encode($payload);

    $context = stream_context_create([
        'http' => [
            'method' => 'POST',
            'header' => "Content-Type: application/json;charset=UTF-8\r\nAccept: application/json\r\n",
            'content' => $body,
            'timeout' => 8,
            'ignore_errors' => true
        ]
    ]);

    $raw = @file_get_contents($url, false, $context);
    $json = json_decode((string)$raw, true);

    return [
        'url' => $url,
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

$allKeys = [];
foreach ($ranges as $prefix => $range) {
    [$start, $end] = $range;
    for ($id = $start; $id <= $end; $id++) {
        foreach ($suffixes as $suffix) {
            $allKeys[] = $prefix . '.' . $id . '.' . $suffix;
        }
    }
}

$totalCandidates = count($allKeys);
$found = [];
$requestCount = 0;

$started = microtime(true);

echo "BAYROL PM5 API Explorer\n";
echo "Host: {$pm5Host}:{$pm5Port}\n";
echo "Candidates: {$totalCandidates}\n";
echo "Batch size: {$batchSize}\n\n";

foreach (chunkArray($allKeys, $batchSize) as $batch) {
    $requestCount++;
    $response = pm5Post($pm5Host, $pm5Port, $sid, ['get' => $batch]);
    $json = $response['json'];

    if (!is_array($json)) {
        echo "Request {$requestCount}: invalid JSON\n";
        echo substr((string)$response['raw'], 0, 500) . "\n";
        continue;
    }

    $code = $json['status']['code'] ?? 'NO_CODE';
    $data = $json['data'] ?? [];

    echo "Request {$requestCount}: status={$code}, asked=" . count($batch) . ", found=" . count($data) . "\n";

    if (is_array($data)) {
        foreach ($data as $key => $value) {
            $found[$key] = [
                'key' => $key,
                'value' => cleanValue($value),
                'raw' => is_scalar($value) ? (string)$value : json_encode($value),
                'type' => classifyValue($value)
            ];
        }
    }

    usleep($pauseMicroseconds);
}

ksort($found, SORT_NATURAL);
$duration = round(microtime(true) - $started, 2);

echo "\n==============================\n";
echo "SUMMARY\n";
echo "Requests: {$requestCount}\n";
echo "Candidates: {$totalCandidates}\n";
echo "Found keys: " . count($found) . "\n";
echo "Duration: {$duration}s\n\n";

echo "FOUND KEYS\n";
echo "| Key | Type | Value |\n";
echo "|---|---|---|\n";
foreach ($found as $row) {
    $value = str_replace('|', '\\|', $row['value']);
    echo '| `' . $row['key'] . '` | ' . $row['type'] . ' | `' . $value . '` |' . "\n";
}

echo "\nCSV\n";
echo "key;type;value\n";
foreach ($found as $row) {
    $value = str_replace([';', "\r", "\n"], [',', ' ', ' '], $row['value']);
    echo $row['key'] . ';' . $row['type'] . ';' . $value . "\n";
}
