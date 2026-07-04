<?php
/**
 * Shared BAYROL PoolManager 5 API helper functions.
 *
 * This file is intended to be included by the tools in this repository.
 * It contains only generic HTTP/API, formatting and scan helper functions.
 */

function pm5_api_post(string $host, int $port, string $sid, array $payload, int $timeoutSeconds = 10): array
{
    $url = 'http://' . $host . ':' . $port . '/cgi-bin/webgui.fcgi?sid=' . urlencode($sid);

    $context = stream_context_create([
        'http' => [
            'method' => 'POST',
            'header' => "Content-Type: application/json;charset=UTF-8\r\nAccept: application/json\r\n",
            'content' => json_encode($payload),
            'timeout' => $timeoutSeconds,
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

function pm5_clean_value($value): string
{
    if (is_array($value) || is_object($value)) {
        return json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    return trim(html_entity_decode(strip_tags((string)$value), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
}

function pm5_classify_value($value): string
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

function pm5_parse_numbers(string $text): array
{
    $text = str_replace(',', '.', $text);
    preg_match_all('/-?[0-9]+(?:\.[0-9]+)?/', $text, $matches);
    return array_map('floatval', $matches[0] ?? []);
}

function pm5_chunk_array(array $items, int $size): array
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

function pm5_object_id_from_key(string $key): string
{
    $parts = explode('.', $key);
    return ($parts[0] ?? '') . '.' . ($parts[1] ?? '');
}

function pm5_build_keys(array $ranges, array $suffixes): array
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

    $keys = array_values(array_unique($keys));
    sort($keys, SORT_NATURAL);
    return $keys;
}

function pm5_scan_keys(
    string $host,
    int $port,
    string $sid,
    array $keys,
    int $batchSize,
    int $pauseMicroseconds,
    bool $printProgress = true
): array {
    $rows = [];
    $requestCount = 0;

    foreach (pm5_chunk_array($keys, $batchSize) as $batch) {
        $requestCount++;
        $response = pm5_api_post($host, $port, $sid, ['get' => $batch]);
        $json = $response['json'];

        if (!is_array($json)) {
            if ($printProgress) {
                echo "Request {$requestCount}: HTTP=" . $response['httpCode'] . " invalid JSON\n";
                echo substr((string)$response['raw'], 0, 160) . "\n";
            }
            sleep(5);
            continue;
        }

        $data = $json['data'] ?? [];
        if ($printProgress) {
            echo "Request {$requestCount}: HTTP=" . $response['httpCode'] . ", asked=" . count($batch) . ", returned=" . (is_array($data) ? count($data) : 0) . "\n";
        }

        if (is_array($data)) {
            foreach ($data as $key => $value) {
                $rows[$key] = [
                    'key' => $key,
                    'object' => pm5_object_id_from_key($key),
                    'value' => pm5_clean_value($value),
                    'raw' => is_scalar($value) ? (string)$value : json_encode($value),
                    'type' => pm5_classify_value($value)
                ];
            }
        }

        usleep($pauseMicroseconds);
    }

    ksort($rows, SORT_NATURAL);
    return $rows;
}

function pm5_is_placeholder(string $key, string $value): bool
{
    if ($value === '') return false;

    $parts = explode('.', $key);
    $suffix = end($parts);

    $placeholders = [
        'value', 'status', 'unit', 'name', 'min', 'max', 'enum', 'opmode',
        'color', 'text1', 'text2', 'state1', 'state2', 'pointer', 'mode', 'target', 'setpoint'
    ];

    if ($value === $suffix) return true;
    return in_array($value, $placeholders, true);
}

function pm5_print_table(array $rows): void
{
    echo "| Key | Type | Value |\n";
    echo "|---|---|---|\n";
    foreach ($rows as $row) {
        $value = str_replace('|', '\\|', $row['value']);
        echo '| `' . $row['key'] . '` | ' . $row['type'] . ' | `' . $value . '` |' . "\n";
    }
}

function pm5_print_csv(array $rows): void
{
    echo "key;object;type;value\n";
    foreach ($rows as $row) {
        $value = str_replace([';', "\r", "\n"], [',', ' ', ' '], $row['value']);
        echo $row['key'] . ';' . $row['object'] . ';' . $row['type'] . ';' . $value . "\n";
    }
}
