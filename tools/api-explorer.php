<?php
/**
 * BAYROL PoolManager 5 API Explorer - modular framework version
 *
 * Modes:
 * - prefix: discover API prefixes/main groups
 * - scan: scan a known range using a profile
 * - target: search known min/max/setpoint values and collect texts
 */

require_once __DIR__ . '/lib/pm5-api.php';

$pm5Host = '192.168.55.23';
$pm5Port = 80;
$sid = 'APIEXPLORERMODULAR0000000001';

// prefix | scan | target
$mode = 'prefix';

$batchSize = 20;
$pauseMicroseconds = 500000;

$prefixDiscovery = [
    'prefixStart' => 1,
    'prefixEnd' => 99,
    'probeIds' => [0, 1, 10, 100, 1000, 2000, 3000, 4000, 4050, 8000, 12000, 16000, 16500, 17000, 17100, 17200, 18000],
    'probeSuffixes' => ['value', 'status', 'text1', 'text2', 'opmode', 'pointer'],
];

$profile = 'measurement';
$ranges = [34 => [4000, 4050]];

$profiles = [
    'measurement' => [
        'existenceSuffixes' => ['value'],
        'detailSuffixes' => ['value'],
    ],
    'gauge' => [
        'existenceSuffixes' => ['text1', 'text2'],
        'detailSuffixes' => ['pointer', 'text1', 'text2', 'color', 'state1', 'state2'],
    ],
    'status' => [
        'existenceSuffixes' => ['value'],
        'detailSuffixes' => ['value', 'status', 'text1', 'text2', 'state1', 'state2'],
    ],
    'actuator' => [
        'existenceSuffixes' => ['value'],
        'detailSuffixes' => ['value', 'status', 'opmode', 'mode', 'enum', 'pointer', 'state1', 'state2', 'text1', 'text2'],
    ],
    'broad' => [
        'existenceSuffixes' => ['value', 'text1', 'text2', 'status'],
        'detailSuffixes' => ['value', 'status', 'opmode', 'mode', 'enum', 'pointer', 'state1', 'state2', 'text1', 'text2', 'name', 'unit', 'min', 'max', 'setpoint', 'target'],
    ],
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

$targetRanges = [
    13 => [16500, 16650],
    34 => [3900, 4300],
    55 => [17000, 17300],
];

$targetSuffixes = ['value', 'min', 'max', 'setpoint', 'target', 'status', 'opmode', 'text1', 'text2', 'state1', 'state2', 'pointer', 'name', 'unit'];

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

$targetTolerance = 0.001;

function explorer_filter_real_rows(array $rows): array
{
    $out = [];
    foreach ($rows as $key => $row) {
        if (!pm5_is_placeholder($key, $row['value'])) {
            $out[$key] = $row;
        }
    }
    ksort($out, SORT_NATURAL);
    return $out;
}

function explorer_extract_objects(array $rows): array
{
    $objects = [];
    foreach ($rows as $row) {
        $objects[$row['object']] = true;
    }
    ksort($objects, SORT_NATURAL);
    return array_keys($objects);
}

function explorer_build_object_detail_keys(array $objectIds, array $suffixes): array
{
    $keys = [];
    foreach ($objectIds as $objectId) {
        foreach ($suffixes as $suffix) {
            $keys[] = $objectId . '.' . $suffix;
        }
    }
    $keys = array_values(array_unique($keys));
    sort($keys, SORT_NATURAL);
    return $keys;
}

function explorer_run_prefix_mode(string $host, int $port, string $sid, array $config, int $batchSize, int $pauseMicroseconds): void
{
    $keys = [];
    for ($prefix = $config['prefixStart']; $prefix <= $config['prefixEnd']; $prefix++) {
        foreach ($config['probeIds'] as $id) {
            foreach ($config['probeSuffixes'] as $suffix) {
                $keys[] = $prefix . '.' . $id . '.' . $suffix;
            }
        }
    }

    echo "BAYROL PM5 API Explorer - PREFIX DISCOVERY\n";
    echo "Keys: " . count($keys) . "\n\n";

    $rows = pm5_scan_keys($host, $port, $sid, $keys, $batchSize, $pauseMicroseconds, true);
    $realRows = explorer_filter_real_rows($rows);

    $prefixStats = [];
    foreach ($realRows as $key => $row) {
        $parts = explode('.', $key);
        $prefix = $parts[0] ?? 'unknown';
        if (!isset($prefixStats[$prefix])) {
            $prefixStats[$prefix] = ['count' => 0, 'examples' => []];
        }
        $prefixStats[$prefix]['count']++;
        if (count($prefixStats[$prefix]['examples']) < 8) {
            $prefixStats[$prefix]['examples'][] = $key . ' = ' . $row['value'];
        }
    }
    ksort($prefixStats, SORT_NATURAL);

    echo "\n==============================\nPREFIX SUMMARY\n";
    echo "| Prefix | Real hits | Examples |\n|---:|---:|---|\n";
    foreach ($prefixStats as $prefix => $info) {
        echo '| `' . $prefix . '` | ' . $info['count'] . ' | ' . str_replace('|', '\\|', implode('<br>', $info['examples'])) . ' |' . "\n";
    }

    echo "\nREAL ROWS\n";
    pm5_print_table($realRows);
}

function explorer_run_scan_mode(string $host, int $port, string $sid, array $ranges, string $profile, array $profiles, array $watchKeys, int $batchSize, int $pauseMicroseconds): void
{
    if (!isset($profiles[$profile])) {
        throw new Exception('Unknown profile: ' . $profile);
    }

    $existenceKeys = pm5_build_keys($ranges, $profiles[$profile]['existenceSuffixes']);
    echo "BAYROL PM5 API Explorer - SCAN\n";
    echo "Profile: {$profile}\n";
    echo "Existence keys: " . count($existenceKeys) . "\n\n";

    echo "==============================\nPHASE 1: existence scan\n";
    $phase1Rows = explorer_filter_real_rows(pm5_scan_keys($host, $port, $sid, $existenceKeys, $batchSize, $pauseMicroseconds, true));
    $objectIds = explorer_extract_objects($phase1Rows);

    echo "\nObjects found: " . count($objectIds) . "\n";
    foreach ($objectIds as $objectId) {
        echo "  - {$objectId}\n";
    }

    $phase2Rows = [];
    if (count($objectIds) > 0) {
        echo "\n==============================\nPHASE 2: detail scan\n";
        $detailKeys = explorer_build_object_detail_keys($objectIds, $profiles[$profile]['detailSuffixes']);
        $phase2Rows = explorer_filter_real_rows(pm5_scan_keys($host, $port, $sid, $detailKeys, $batchSize, $pauseMicroseconds, true));
    }

    echo "\n==============================\nWATCH KEYS\n";
    $watchRows = pm5_scan_keys($host, $port, $sid, $watchKeys, $batchSize, $pauseMicroseconds, true);

    echo "\n==============================\nSUMMARY\n";
    echo "Objects found: " . count($objectIds) . "\n";
    echo "Detail keys found: " . count($phase2Rows) . "\n";
    echo "Watch keys returned: " . count($watchRows) . "\n\n";

    echo "PHASE 1 REAL ROWS\n";
    pm5_print_table($phase1Rows);
    echo "\nPHASE 2 DETAILS\n";
    pm5_print_table($phase2Rows);
    echo "\nWATCH\n";
    pm5_print_table($watchRows);
    echo "\nCSV DETAILS\n";
    pm5_print_csv($phase2Rows);
}

function explorer_run_target_mode(string $host, int $port, string $sid, array $ranges, array $suffixes, array $targets, float $tolerance, int $batchSize, int $pauseMicroseconds): void
{
    $keys = pm5_build_keys($ranges, $suffixes);
    echo "BAYROL PM5 API Explorer - TARGET FINDER\n";
    echo "Keys: " . count($keys) . "\n\n";

    $rows = pm5_scan_keys($host, $port, $sid, $keys, $batchSize, $pauseMicroseconds, true);
    $matches = [];
    $texts = [];

    foreach ($rows as $key => $row) {
        if (pm5_is_placeholder($key, $row['value'])) {
            continue;
        }
        if (str_ends_with($key, '.value') && $row['value'] !== '' && !is_numeric(str_replace(',', '.', $row['value']))) {
            $texts[$key] = $row;
        }
        foreach (pm5_parse_numbers($row['value']) as $number) {
            foreach ($targets as $targetName => $targetValue) {
                if (abs($number - (float)$targetValue) <= $tolerance) {
                    $matches[] = ['target' => $targetName, 'targetValue' => $targetValue, 'key' => $key, 'value' => $row['value']];
                }
            }
        }
    }

    ksort($texts, SORT_NATURAL);

    echo "\n==============================\nTARGET MATCHES\n";
    echo "| Target | Target value | Key | API value |\n|---|---:|---|---|\n";
    foreach ($matches as $row) {
        echo '| `' . $row['target'] . '` | `' . $row['targetValue'] . '` | `' . $row['key'] . '` | `' . str_replace('|', '\\|', $row['value']) . '` |' . "\n";
    }

    echo "\nNON-NUMERIC VALUE TEXTS\n";
    pm5_print_table($texts);
}

$started = microtime(true);
try {
    if ($mode === 'prefix') {
        explorer_run_prefix_mode($pm5Host, $pm5Port, $sid, $prefixDiscovery, $batchSize, $pauseMicroseconds);
    } elseif ($mode === 'scan') {
        explorer_run_scan_mode($pm5Host, $pm5Port, $sid, $ranges, $profile, $profiles, $watchKeys, $batchSize, $pauseMicroseconds);
    } elseif ($mode === 'target') {
        explorer_run_target_mode($pm5Host, $pm5Port, $sid, $targetRanges, $targetSuffixes, $targets, $targetTolerance, $batchSize, $pauseMicroseconds);
    } else {
        throw new Exception('Unknown mode: ' . $mode);
    }
} catch (Throwable $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
}

echo "\nDuration: " . round(microtime(true) - $started, 2) . "s\n";
