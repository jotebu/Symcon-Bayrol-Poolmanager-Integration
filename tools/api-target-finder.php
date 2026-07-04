<?php
/**
 * BAYROL PoolManager 5 API Target Finder
 *
 * Zweck:
 * - Gesuchte Min-/Max-/Sollwerte in der WebGUI-API finden
 * - Alle nicht-leeren .value-Texte sammeln
 * - Schonende Abfrage in kleinen Batches
 *
 * Nutzung:
 * - In IP-Symcon als normales PHP-Skript ausfuehren
 * - Bereiche bei Bedarf schrittweise erweitern
 */

$pm5Host = '192.168.55.23';
$pm5Port = 80;
$sid = 'APITARGETFINDER00000000000001';

// Gesuchte bekannte Werte. Es wird numerisch tolerant verglichen.
$targets = [
    'pH_min' => 6.00,
    'pH_max' => 7.80,
    'Redox_min' => 400,
    'Redox_soll' => 790,
    'Redox_max' => 900,
    'Temp_min' => 5.0,
    'Temp_soll' => 25.0,
    'Temp_max' => 50.0,
    'Nachspeisung_minuten' => 5.0,
];

// Grobe Suchbereiche. Bei Bedarf anpassen/erweitern.
$ranges = [
    13 => [16500, 16650],
    14 => [16500, 16650],
    15 => [16000, 17200],
    34 => [3900, 4300],
    35 => [3900, 4300],
    55 => [17000, 17300],
];

$suffixes = [
    'value', 'min', 'max', 'setpoint', 'target', 'status', 'opmode',
    'text1', 'text2', 'state1', 'state2', 'pointer', 'name', 'unit'
];

$batchSize = 20;
$pauseMicroseconds = 500000;
$tolerance = 0.001;

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
    return ['httpCode' => $httpCode, 'raw' => $raw, 'json' => json_decode((string)$raw, true)];
}

function cleanValue($value): string
{
    if (is_array($value) || is_object($value)) {
        return json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }
    return trim(html_entity_decode(strip_tags((string)$value), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
}

function parseNumbers(string $text): array
{
    $text = str_replace(',', '.', $text);
    preg_match_all('/-?[0-9]+(?:\.[0-9]+)?/', $text, $m);
    return array_map('floatval', $m[0] ?? []);
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

function buildKeys(array $ranges, array $suffixes): array
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
    return $keys;
}

$keys = buildKeys($ranges, $suffixes);
$matches = [];
$valueTexts = [];
$requestCount = 0;

$started = microtime(true);
echo "BAYROL PM5 API Target Finder\n";
echo "Keys: " . count($keys) . "\n";
echo "Batch size: {$batchSize}\n\n";

foreach (chunkArray($keys, $batchSize) as $batch) {
    $requestCount++;
    $response = pm5Post($pm5Host, $pm5Port, $sid, ['get' => $batch]);
    $json = $response['json'];
    if (!is_array($json)) {
        echo "Request {$requestCount}: HTTP=" . $response['httpCode'] . " invalid JSON\n";
        sleep(5);
        continue;
    }

    $data = $json['data'] ?? [];
    echo "Request {$requestCount}: HTTP=" . $response['httpCode'] . ", asked=" . count($batch) . ", returned=" . (is_array($data) ? count($data) : 0) . "\n";

    if (is_array($data)) {
        foreach ($data as $key => $rawValue) {
            $clean = cleanValue($rawValue);
            if ($clean === '') continue;

            if (substr($key, -6) === '.value' && !is_numeric(str_replace(',', '.', $clean))) {
                $valueTexts[$key] = $clean;
            }

            $numbers = parseNumbers($clean);
            foreach ($numbers as $number) {
                foreach ($targets as $targetName => $targetValue) {
                    if (abs($number - (float)$targetValue) <= $GLOBALS['tolerance']) {
                        $matches[] = [
                            'target' => $targetName,
                            'targetValue' => $targetValue,
                            'key' => $key,
                            'value' => $clean
                        ];
                    }
                }
            }
        }
    }
    usleep($pauseMicroseconds);
}

ksort($valueTexts, SORT_NATURAL);
$duration = round(microtime(true) - $started, 2);

echo "\n==============================\n";
echo "TARGET MATCHES\n";
echo "| Target | Target value | Key | API value |\n";
echo "|---|---:|---|---|\n";
foreach ($matches as $row) {
    echo '| `' . $row['target'] . '` | `' . $row['targetValue'] . '` | `' . $row['key'] . '` | `' . str_replace('|', '\\|', $row['value']) . '` |' . "\n";
}

echo "\n==============================\n";
echo "NON-NUMERIC VALUE TEXTS\n";
echo "| Key | Value |\n";
echo "|---|---|\n";
foreach ($valueTexts as $key => $value) {
    echo '| `' . $key . '` | `' . str_replace('|', '\\|', $value) . '` |' . "\n";
}

echo "\nCSV TARGET MATCHES\n";
echo "target;target_value;key;api_value\n";
foreach ($matches as $row) {
    echo $row['target'] . ';' . $row['targetValue'] . ';' . $row['key'] . ';' . str_replace([';', "\r", "\n"], [',', ' ', ' '], $row['value']) . "\n";
}

echo "\nCSV VALUE TEXTS\n";
echo "key;value\n";
foreach ($valueTexts as $key => $value) {
    echo $key . ';' . str_replace([';', "\r", "\n"], [',', ' ', ' '], $value) . "\n";
}

echo "\nRequests: {$requestCount}\n";
echo "Duration: {$duration}s\n";
