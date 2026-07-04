<?php
/**
 * BAYROL PoolManager 5 API Learning Tool
 *
 * Zweck:
 * - Einen Zustand A aufnehmen
 * - Genau eine Bedienaktion am PM5 ausfuehren
 * - Einen Zustand B aufnehmen
 * - Automatisch die geaenderten API-Keys anzeigen
 *
 * Nutzung:
 * 1. $action = 'baseline'; $name = 'auto'; ausfuehren
 * 2. Am PM5 genau eine Sache aendern, z. B. Licht EIN oder Pumpe ECO
 * 3. $action = 'learn'; $name = 'licht_ein'; $compareAgainst = 'auto'; ausfuehren
 *
 * Weitere Aktionen:
 * - list: vorhandene Snapshots anzeigen
 * - compare: zwei vorhandene Snapshots vergleichen, ohne neu zu scannen
 * - delete: Snapshot loeschen
 */

require_once __DIR__ . '/lib/pm5-api.php';

$pm5Host = '192.168.55.23';
$pm5Port = 80;
$sid = 'APILEARNING000000000000000001';

// baseline | learn | compare | list | delete
$action = 'baseline';

// Name des neuen Snapshots bei baseline/learn oder zu loeschender Snapshot bei delete
$name = 'baseline';

// Vergleichsbasis bei learn/compare
$compareAgainst = 'baseline';

// Neuer Snapshot bei compare, falls compare ohne neuen Scan gegen gespeicherte Snapshots laufen soll
// Bei action=compare: $name = neuer Snapshot, $compareAgainst = alter Snapshot

$batchSize = 20;
$pauseMicroseconds = 500000;

// Scanbereiche fuer Learning bewusst praxisnah, nicht komplett blind.
// Bei Bedarf erweitern.
$ranges = [
    13 => [16500, 16650], // Dashboard/Gauges
    34 => [3900, 4300],   // Mess-/Grenzwerte
    55 => [17000, 17300], // Aktoren/Status
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
    'text2',
    'name',
    'unit',
    'min',
    'max',
    'setpoint',
    'target'
];

// Keys, die immer mit ueberwacht werden sollen, auch wenn sie ausserhalb der Bereiche liegen.
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

// Live-Werte, die bei Vergleichen optional als Rauschen markiert werden.
$volatileKeys = [
    '34.4001.value',
    '34.4022.value',
    '34.4033.value',
    '13.16507.text2',
];

function learning_storage_path(): string
{
    if (isset($_IPS['SELF']) && function_exists('IPS_GetScriptFile')) {
        try {
            $scriptFile = IPS_GetScriptFile((int)$_IPS['SELF']);
            return dirname($scriptFile) . DIRECTORY_SEPARATOR . 'pm5-learning-snapshots.json';
        } catch (Throwable $e) {
            // fallback below
        }
    }

    return __DIR__ . DIRECTORY_SEPARATOR . 'pm5-learning-snapshots.json';
}

function learning_load_snapshots(string $path): array
{
    if (!file_exists($path)) return [];
    $json = json_decode((string)file_get_contents($path), true);
    return is_array($json) ? $json : [];
}

function learning_save_snapshots(string $path, array $snapshots): void
{
    file_put_contents($path, json_encode($snapshots, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
}

function learning_build_keys(array $ranges, array $suffixes, array $watchKeys): array
{
    $keys = pm5_build_keys($ranges, $suffixes);
    foreach ($watchKeys as $key) {
        $keys[] = $key;
    }
    $keys = array_values(array_unique($keys));
    sort($keys, SORT_NATURAL);
    return $keys;
}

function learning_scan(string $host, int $port, string $sid, array $ranges, array $suffixes, array $watchKeys, int $batchSize, int $pauseMicroseconds): array
{
    $keys = learning_build_keys($ranges, $suffixes, $watchKeys);
    echo "Scan keys: " . count($keys) . "\n";
    $rows = pm5_scan_keys($host, $port, $sid, $keys, $batchSize, $pauseMicroseconds, true);

    $filtered = [];
    foreach ($rows as $key => $row) {
        if (pm5_is_placeholder($key, $row['value'])) {
            continue;
        }
        $filtered[$key] = $row;
    }

    ksort($filtered, SORT_NATURAL);
    return $filtered;
}

function learning_diff(array $oldRows, array $newRows): array
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
                'key' => $key,
                'object' => pm5_object_id_from_key($key),
                'old' => $oldValue,
                'new' => $newValue,
                'oldType' => $oldExists ? $oldRows[$key]['type'] : '-',
                'newType' => $newExists ? $newRows[$key]['type'] : '-',
            ];
        }
    }

    return $diff;
}

function learning_classify_diff(array $diff, array $volatileKeys): array
{
    $signal = [];
    $noise = [];

    foreach ($diff as $key => $row) {
        if (in_array($key, $volatileKeys, true)) {
            $noise[$key] = $row;
            continue;
        }

        if (preg_match('/^34\.(4001|4022|4033)\.value$/', $key)) {
            $noise[$key] = $row;
            continue;
        }

        $signal[$key] = $row;
    }

    return [$signal, $noise];
}

function learning_print_diff_table(array $diff): void
{
    echo "| Key | Old | New |\n";
    echo "|---|---|---|\n";
    foreach ($diff as $key => $row) {
        $old = str_replace('|', '\\|', $row['old']);
        $new = str_replace('|', '\\|', $row['new']);
        echo '| `' . $key . '` | `' . $old . '` | `' . $new . '` |' . "\n";
    }
}

function learning_print_diff_csv(array $diff): void
{
    echo "key;object;old;new\n";
    foreach ($diff as $key => $row) {
        $old = str_replace([';', "\r", "\n"], [',', ' ', ' '], $row['old']);
        $new = str_replace([';', "\r", "\n"], [',', ' ', ' '], $row['new']);
        echo $key . ';' . $row['object'] . ';' . $old . ';' . $new . "\n";
    }
}

$storagePath = learning_storage_path();
$snapshots = learning_load_snapshots($storagePath);
$started = microtime(true);

echo "BAYROL PM5 API Learning Tool\n";
echo "Action: {$action}\n";
echo "Name: {$name}\n";
echo "Compare against: {$compareAgainst}\n";
echo "Storage: {$storagePath}\n\n";

try {
    if ($action === 'list') {
        echo "Snapshots:\n";
        if (count($snapshots) === 0) {
            echo "  Keine Snapshots vorhanden.\n";
        } else {
            foreach ($snapshots as $snapshotName => $snapshot) {
                $createdAt = $snapshot['createdAt'] ?? '-';
                $count = isset($snapshot['rows']) && is_array($snapshot['rows']) ? count($snapshot['rows']) : 0;
                echo "  - {$snapshotName} ({$createdAt}, {$count} keys)\n";
            }
        }
    } elseif ($action === 'delete') {
        if (isset($snapshots[$name])) {
            unset($snapshots[$name]);
            learning_save_snapshots($storagePath, $snapshots);
            echo "Deleted snapshot '{$name}'.\n";
        } else {
            echo "Snapshot '{$name}' not found.\n";
        }
    } elseif ($action === 'baseline' || $action === 'learn') {
        $rows = learning_scan($pm5Host, $pm5Port, $sid, $ranges, $suffixes, $watchKeys, $batchSize, $pauseMicroseconds);
        $snapshots[$name] = [
            'createdAt' => date('c'),
            'rows' => $rows
        ];
        learning_save_snapshots($storagePath, $snapshots);
        echo "\nSaved snapshot '{$name}' with " . count($rows) . " keys.\n";

        if ($action === 'learn') {
            if (!isset($snapshots[$compareAgainst])) {
                throw new Exception("Compare snapshot '{$compareAgainst}' not found.");
            }

            $oldRows = $snapshots[$compareAgainst]['rows'] ?? [];
            $diff = learning_diff($oldRows, $rows);
            [$signal, $noise] = learning_classify_diff($diff, $volatileKeys);

            echo "\n==============================\n";
            echo "LEARN DIFF {$compareAgainst} -> {$name}\n";
            echo "Changed keys total: " . count($diff) . "\n";
            echo "Likely signal: " . count($signal) . "\n";
            echo "Likely volatile/noise: " . count($noise) . "\n\n";

            echo "LIKELY SIGNAL\n";
            learning_print_diff_table($signal);

            echo "\nVOLATILE / NOISE\n";
            learning_print_diff_table($noise);

            echo "\nCSV SIGNAL\n";
            learning_print_diff_csv($signal);

            echo "\nCSV NOISE\n";
            learning_print_diff_csv($noise);
        }
    } elseif ($action === 'compare') {
        if (!isset($snapshots[$compareAgainst])) {
            throw new Exception("Compare snapshot '{$compareAgainst}' not found.");
        }
        if (!isset($snapshots[$name])) {
            throw new Exception("Snapshot '{$name}' not found.");
        }

        $oldRows = $snapshots[$compareAgainst]['rows'] ?? [];
        $newRows = $snapshots[$name]['rows'] ?? [];
        $diff = learning_diff($oldRows, $newRows);
        [$signal, $noise] = learning_classify_diff($diff, $volatileKeys);

        echo "\n==============================\n";
        echo "COMPARE {$compareAgainst} -> {$name}\n";
        echo "Changed keys total: " . count($diff) . "\n";
        echo "Likely signal: " . count($signal) . "\n";
        echo "Likely volatile/noise: " . count($noise) . "\n\n";

        echo "LIKELY SIGNAL\n";
        learning_print_diff_table($signal);

        echo "\nVOLATILE / NOISE\n";
        learning_print_diff_table($noise);
    } else {
        throw new Exception('Unknown action: ' . $action);
    }
} catch (Throwable $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
}

$duration = round(microtime(true) - $started, 2);
echo "\nDuration: {$duration}s\n";
