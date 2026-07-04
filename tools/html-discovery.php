<?php
/**
 * BAYROL PoolManager 5 HTML Discovery Tool
 *
 * Purpose:
 * - Fetch selected PM5 WebGUI pages
 * - Extract API keys used by the WebGUI, especially wui.reg.get("...")
 * - Print a sorted key list that can be copied into api-discovery.php
 *
 * Run inside IP-Symcon as a normal PHP script first.
 */

$pm5Host = '192.168.55.23';
$pm5Port = 80;
$sid = 'HTMLDISCOVERY000000000000000001';

// Start with known/likely pages from observed API events.
// Add more cmd IDs later when discovered.
$pages = [
    'root' => '/',
    'home_3_16901_0' => '/cgi-bin/webgui.fcgi?sid=' . $sid . '&cmd=3.16901.0',
    'values_48_30000_0' => '/cgi-bin/webgui.fcgi?sid=' . $sid . '&cmd=48.30000.0',
];

function fetchUrl(string $host, int $port, string $path): array
{
    $url = 'http://' . $host . ':' . $port . $path;

    $context = stream_context_create([
        'http' => [
            'method' => 'GET',
            'timeout' => 8,
            'ignore_errors' => true,
            'header' => "Accept: text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8\r\n"
        ]
    ]);

    $body = @file_get_contents($url, false, $context);

    return [
        'url' => $url,
        'body' => $body === false ? '' : $body,
        'headers' => $http_response_header ?? []
    ];
}

function extractApiKeys(string $body): array
{
    $keys = [];

    $patterns = [
        '/wui\.reg\.get\(\s*["\']([^"\']+)["\']\s*\)/',
        '/wui\.reg\.set\(\s*["\']([^"\']+)["\']\s*,/',
        '/id=["\']([0-9]+\.[0-9]+\.[a-zA-Z0-9_]+)["\']/',
        '/name=["\']([0-9]+\.[0-9]+\.[a-zA-Z0-9_]+)["\']/',
        '/["\']([0-9]+\.[0-9]+\.(?:value|status|pointer|color|text1|text2|state1|state2|unit|name|min|max|enum|opmode))["\']/'
    ];

    foreach ($patterns as $pattern) {
        if (preg_match_all($pattern, $body, $matches)) {
            foreach ($matches[1] as $key) {
                $keys[$key] = true;
            }
        }
    }

    $keys = array_keys($keys);
    sort($keys, SORT_NATURAL);
    return $keys;
}

function groupKeys(array $keys): array
{
    $groups = [];
    foreach ($keys as $key) {
        $parts = explode('.', $key);
        $prefix = $parts[0] ?? 'unknown';
        if (!isset($groups[$prefix])) {
            $groups[$prefix] = [];
        }
        $groups[$prefix][] = $key;
    }
    ksort($groups, SORT_NATURAL);
    return $groups;
}

$allKeys = [];
$pageResults = [];

echo "BAYROL PM5 HTML Discovery\n";
echo "Host: {$pm5Host}:{$pm5Port}\n\n";

foreach ($pages as $name => $path) {
    $result = fetchUrl($pm5Host, $pm5Port, $path);
    $keys = extractApiKeys($result['body']);

    foreach ($keys as $key) {
        $allKeys[$key] = true;
    }

    $pageResults[$name] = [
        'url' => $result['url'],
        'bytes' => strlen($result['body']),
        'keys' => $keys,
        'headers' => $result['headers']
    ];
}

foreach ($pageResults as $name => $info) {
    echo "==============================\n";
    echo "PAGE: {$name}\n";
    echo "URL: " . $info['url'] . "\n";
    echo "Bytes: " . $info['bytes'] . "\n";
    echo "Keys found: " . count($info['keys']) . "\n";
    foreach ($info['keys'] as $key) {
        echo "  - {$key}\n";
    }
    echo "\n";
}

$allKeys = array_keys($allKeys);
sort($allKeys, SORT_NATURAL);
$groups = groupKeys($allKeys);

echo "==============================\n";
echo "SUMMARY\n";
echo "Total unique keys: " . count($allKeys) . "\n\n";

foreach ($groups as $prefix => $keys) {
    echo "Prefix {$prefix}: " . count($keys) . " keys\n";
}

echo "\n==============================\n";
echo "COPY THIS KEY ARRAY INTO api-discovery.php\n";
echo "\$keys = [\n";
foreach ($allKeys as $key) {
    echo "    '" . addslashes($key) . "',\n";
}
echo "];\n";
