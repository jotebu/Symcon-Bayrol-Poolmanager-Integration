<?php

declare(strict_types=1);

class BayrolDiscovery extends IPSModule
{
    private const STATUS_ACTIVE = 102;
    private const STATUS_STORAGE_ERROR = 202;
    private const STATUS_API_ERROR = 203;
    private const SCHEMA_VERSION = 1;

    private const CSV_FILES = [
        'meta' => ['key', 'value'],
        'scans' => ['scan_id', 'started_at', 'finished_at', 'host', 'port', 'generated_keys', 'found_keys', 'duration_ms', 'notes'],
        'api_keys' => ['api_key', 'current_value', 'value_type', 'confidence', 'suggested_name', 'is_favorite', 'first_seen', 'last_seen', 'last_scan_id'],
        'observations' => ['scan_id', 'api_key', 'value', 'value_type', 'observed_at'],
        'devices' => ['code', 'name', 'device_type', 'confidence', 'status_key', 'value_key', 'first_seen', 'last_seen'],
        'device_keys' => ['device_code', 'api_key', 'role', 'is_required', 'direction'],
        'tags' => ['name', 'color', 'description'],
        'key_tags' => ['api_key', 'tag']
    ];

    public function Create()
    {
        parent::Create();
        $this->RegisterPropertyString('Host', '192.168.55.23');
        $this->RegisterPropertyInteger('Port', 80);
        $this->RegisterPropertyInteger('Timeout', 10);
        $this->RegisterPropertyBoolean('DebugMode', false);
        $this->RegisterPropertyBoolean('KeepDatabaseOnDelete', true);
        $this->RegisterPropertyInteger('ScanGroupStart', 34);
        $this->RegisterPropertyInteger('ScanGroupEnd', 55);
        $this->RegisterPropertyInteger('ScanObjectStart', 4000);
        $this->RegisterPropertyInteger('ScanObjectEnd', 17200);
        $this->RegisterPropertyString('ScanSuffixes', 'value;status;opmode;text1;text2');
        $this->RegisterPropertyInteger('ScanMaxKeys', 500);
        $this->RegisterPropertyInteger('ScanBatchSize', 50);
        $this->RegisterPropertyString('BrowserSearch', '');
        $this->RegisterPropertyString('SelectedApiKey', '');
        $this->RegisterPropertyString('SelectedDeviceCode', '');
    }

    public function ApplyChanges()
    {
        parent::ApplyChanges();
        $this->RegisterVariables();
        try {
            $this->InitializeStorage();
            $this->SetValueSafe('StorageReady', true);
            $this->SetValueSafe('StorageStatus', 'CSV Storage bereit. Schema v' . self::SCHEMA_VERSION);
            $this->SetValueSafe('StoragePath', $this->GetStorageDirectory());
            $this->SetValueSafe('StorageSchemaVersion', self::SCHEMA_VERSION);
            $this->SetStatus(self::STATUS_ACTIVE);
        } catch (Throwable $e) {
            $this->SetValueSafe('StorageReady', false);
            $this->SetValueSafe('StorageStatus', $e->getMessage());
            $this->SetStatus(self::STATUS_STORAGE_ERROR);
        }
    }

    public function GetConfigurationForm()
    {
        return json_encode([
            'elements' => [
                ['type' => 'ValidationTextBox', 'name' => 'Host', 'caption' => 'PoolManager IP / Host'],
                ['type' => 'NumberSpinner', 'name' => 'Port', 'caption' => 'Port'],
                ['type' => 'NumberSpinner', 'name' => 'Timeout', 'caption' => 'HTTP Timeout in Sekunden'],
                ['type' => 'CheckBox', 'name' => 'DebugMode', 'caption' => 'Erweiterte Debug-Ausgaben'],
                ['type' => 'CheckBox', 'name' => 'KeepDatabaseOnDelete', 'caption' => 'CSV-Dateien bei Instanzloeschung behalten'],
                ['type' => 'Label', 'caption' => 'CSV Storage: keine SQLite-/PDO-Abhaengigkeit. Dateien liegen im Symcon user/BayrolDiscovery Bereich.'],
                ['type' => 'NumberSpinner', 'name' => 'ScanGroupStart', 'caption' => 'Scan Gruppe von'],
                ['type' => 'NumberSpinner', 'name' => 'ScanGroupEnd', 'caption' => 'Scan Gruppe bis'],
                ['type' => 'NumberSpinner', 'name' => 'ScanObjectStart', 'caption' => 'Scan Objekt-ID von'],
                ['type' => 'NumberSpinner', 'name' => 'ScanObjectEnd', 'caption' => 'Scan Objekt-ID bis'],
                ['type' => 'ValidationTextBox', 'name' => 'ScanSuffixes', 'caption' => 'Scan Suffixe, getrennt durch Semikolon oder Komma'],
                ['type' => 'NumberSpinner', 'name' => 'ScanMaxKeys', 'caption' => 'Maximale Keys pro Scan'],
                ['type' => 'NumberSpinner', 'name' => 'ScanBatchSize', 'caption' => 'Batchgroesse'],
                ['type' => 'ValidationTextBox', 'name' => 'BrowserSearch', 'caption' => 'Browser-Suche'],
                ['type' => 'ValidationTextBox', 'name' => 'SelectedApiKey', 'caption' => 'Ausgewaehlter API-Key fuer Details/Favorit'],
                ['type' => 'ValidationTextBox', 'name' => 'SelectedDeviceCode', 'caption' => 'Ausgewaehlter Device-Code fuer Details']
            ],
            'actions' => [
                ['type' => 'Label', 'caption' => 'CSV-Pfad: ' . $this->GetStorageDirectory()],
                ['type' => 'Button', 'caption' => 'CSV Storage pruefen', 'onClick' => 'echo BPD_CheckDatabase($id);'],
                ['type' => 'Button', 'caption' => 'Verbindung testen', 'onClick' => 'echo BPD_TestConnection($id);'],
                ['type' => 'Button', 'caption' => 'Scan starten', 'onClick' => 'echo BPD_RunScan($id);'],
                ['type' => 'Button', 'caption' => 'Scan-Zusammenfassung laden', 'onClick' => 'echo BPD_GetScanSummary($id);'],
                ['type' => 'Button', 'caption' => 'API-Key Browser laden', 'onClick' => 'echo BPD_LoadBrowser($id);'],
                ['type' => 'Button', 'caption' => 'API-Key Details laden', 'onClick' => 'echo BPD_LoadApiKeyDetails($id);'],
                ['type' => 'Button', 'caption' => 'Favorit umschalten', 'onClick' => 'echo BPD_ToggleFavorite($id);'],
                $this->GetApiKeyListDefinition(),
                ['type' => 'Button', 'caption' => 'Device Browser laden', 'onClick' => 'echo BPD_LoadDevices($id);'],
                ['type' => 'Button', 'caption' => 'Device Details laden', 'onClick' => 'echo BPD_LoadDeviceDetails($id);'],
                $this->GetDeviceListDefinition()
            ],
            'status' => [
                ['code' => self::STATUS_ACTIVE, 'icon' => 'active', 'caption' => 'CSV Storage bereit'],
                ['code' => self::STATUS_STORAGE_ERROR, 'icon' => 'error', 'caption' => 'CSV Storage Fehler'],
                ['code' => self::STATUS_API_ERROR, 'icon' => 'error', 'caption' => 'PM5 API Fehler']
            ]
        ]);
    }

    public function CheckDatabase(): string
    {
        try {
            $this->InitializeStorage();
            $message = 'CSV Storage OK. API-Keys: ' . count($this->ReadCsvAssoc('api_keys')) . ', Scans: ' . count($this->ReadCsvAssoc('scans')) . ', Observations: ' . count($this->ReadCsvAssoc('observations')) . ', Devices: ' . count($this->ReadCsvAssoc('devices'));
            $this->SetValueSafe('StorageStatus', $message);
            $this->SetStatus(self::STATUS_ACTIVE);
            return $message;
        } catch (Throwable $e) {
            $this->SetValueSafe('StorageStatus', $e->getMessage());
            $this->SetStatus(self::STATUS_STORAGE_ERROR);
            return 'CSV-Fehler: ' . $e->getMessage();
        }
    }

    public function TestConnection(): string
    {
        try {
            $response = $this->ApiGet(['34.4001.value']);
            $ok = isset($response['data']['34.4001.value']);
            $message = $ok ? 'Verbindung OK. pH-Key empfangen.' : 'Verbindung OK, aber pH-Key nicht in Antwort enthalten.';
            $this->SetValueSafe('LastApiStatus', (int)($response['status']['code'] ?? -1));
            $this->SetValueSafe('LastResponseTimeMs', (int)($response['_meta']['duration_ms'] ?? 0));
            $this->SetValueSafe('LastError', $ok ? '' : $message);
            $this->SetStatus($ok ? self::STATUS_ACTIVE : self::STATUS_API_ERROR);
            return $message;
        } catch (Throwable $e) {
            $this->SetValueSafe('LastError', $e->getMessage());
            $this->SetStatus(self::STATUS_API_ERROR);
            return 'Verbindungsfehler: ' . $e->getMessage();
        }
    }

    public function RunScan(): string
    {
        try {
            $this->InitializeStorage();
            $keys = $this->BuildScanKeys();
            $scanId = $this->GetNextScanId();
            $started = date('Y-m-d H:i:s');
            $found = 0;
            $duration = 0;
            $apiKeys = $this->IndexBy($this->ReadCsvAssoc('api_keys'), 'api_key');
            $devices = $this->IndexBy($this->ReadCsvAssoc('devices'), 'code');
            $deviceKeys = $this->ReadCsvAssoc('device_keys');
            $observationsToAppend = [];

            foreach (array_chunk($keys, max(1, min(100, $this->ReadPropertyInteger('ScanBatchSize')))) as $chunk) {
                $response = $this->ApiGet($chunk);
                $duration += (int)($response['_meta']['duration_ms'] ?? 0);
                foreach (($response['data'] ?? []) as $key => $value) {
                    $clean = $this->CleanString((string)$value);
                    if ($clean === '') {
                        continue;
                    }
                    $type = $this->DetectValueType($clean);
                    $confidence = (string)$this->GetConfidence((string)$key, $clean);
                    $existing = $apiKeys[(string)$key] ?? [];
                    $apiKeys[(string)$key] = [
                        'api_key' => (string)$key,
                        'current_value' => $clean,
                        'value_type' => $type,
                        'confidence' => $confidence,
                        'suggested_name' => $existing['suggested_name'] ?? $this->GetKnownName((string)$key),
                        'is_favorite' => $existing['is_favorite'] ?? '0',
                        'first_seen' => $existing['first_seen'] ?? $started,
                        'last_seen' => $started,
                        'last_scan_id' => (string)$scanId
                    ];
                    $observationsToAppend[] = ['scan_id' => (string)$scanId, 'api_key' => (string)$key, 'value' => $clean, 'value_type' => $type, 'observed_at' => $started];
                    $this->ClassifyDevice((string)$key, $started, $devices, $deviceKeys);
                    $found++;
                }
            }

            $this->WriteCsvAssoc('api_keys', array_values($apiKeys));
            $this->AppendCsvAssoc('observations', $observationsToAppend);
            $this->WriteCsvAssoc('devices', array_values($devices));
            $this->WriteCsvAssoc('device_keys', $this->UniqueRows($deviceKeys, ['device_code', 'api_key']));
            $this->AppendCsvAssoc('scans', [[
                'scan_id' => (string)$scanId,
                'started_at' => $started,
                'finished_at' => date('Y-m-d H:i:s'),
                'host' => $this->ReadPropertyString('Host'),
                'port' => (string)$this->ReadPropertyInteger('Port'),
                'generated_keys' => (string)count($keys),
                'found_keys' => (string)$found,
                'duration_ms' => (string)$duration,
                'notes' => 'CSV scan'
            ]]);

            $this->SetValueSafe('LastScanId', $scanId);
            $this->SetValueSafe('LastScanStarted', $started);
            $this->SetValueSafe('LastScanFinished', date('Y-m-d H:i:s'));
            $this->SetValueSafe('LastScanGeneratedKeys', count($keys));
            $this->SetValueSafe('LastScanFoundKeys', $found);
            $this->SetValueSafe('LastResponseTimeMs', $duration);
            $this->SetValueSafe('LastError', '');
            $this->UpdateBrowserFormList($this->BuildBrowserRows());
            $this->UpdateDeviceFormList($this->BuildDeviceRows());
            $this->SetStatus(self::STATUS_ACTIVE);
            return 'Scan abgeschlossen. Scan-ID: ' . $scanId . ', erzeugte Keys: ' . count($keys) . ', gefunden: ' . $found . ', Dauer API: ' . $duration . ' ms';
        } catch (Throwable $e) {
            $this->SetValueSafe('LastError', $e->getMessage());
            $this->SetStatus(self::STATUS_API_ERROR);
            return 'Scan-Fehler: ' . $e->getMessage();
        }
    }

    public function GetScanSummary(): string
    {
        try {
            $message = 'Scans: ' . count($this->ReadCsvAssoc('scans')) . ', API-Keys: ' . count($this->ReadCsvAssoc('api_keys')) . ', Beobachtungen: ' . count($this->ReadCsvAssoc('observations')) . ', Devices: ' . count($this->ReadCsvAssoc('devices'));
            $this->SetValueSafe('ScanSummary', $message);
            return $message;
        } catch (Throwable $e) {
            return 'Zusammenfassungsfehler: ' . $e->getMessage();
        }
    }

    public function LoadBrowser(): string
    {
        $rows = $this->BuildBrowserRows();
        $this->UpdateBrowserFormList($rows);
        return 'API-Key Browser geladen. Zeilen: ' . count($rows);
    }

    public function LoadDevices(): string
    {
        $rows = $this->BuildDeviceRows();
        $this->UpdateDeviceFormList($rows);
        return 'Device Browser geladen. Devices: ' . count($rows);
    }

    public function LoadApiKeyDetails(): string
    {
        $key = trim($this->ReadPropertyString('SelectedApiKey'));
        if ($key === '') {
            return 'Kein API-Key ausgewaehlt.';
        }
        $apiKeys = $this->IndexBy($this->ReadCsvAssoc('api_keys'), 'api_key');
        if (!isset($apiKeys[$key])) {
            return 'API-Key nicht gefunden: ' . $key;
        }
        $row = $apiKeys[$key];
        $history = array_slice(array_reverse(array_values(array_filter($this->ReadCsvAssoc('observations'), fn($r) => ($r['api_key'] ?? '') === $key))), 0, 8);
        $lines = [];
        foreach ($history as $h) {
            $lines[] = ($h['observed_at'] ?? '') . ' | ' . ($h['value'] ?? '') . ' | ' . ($h['value_type'] ?? '');
        }
        $detail = 'API-Key: ' . $key . "\nName: " . ($row['suggested_name'] ?? '') . "\nWert: " . ($row['current_value'] ?? '') . "\nTyp: " . ($row['value_type'] ?? '') . "\nVertrauen: " . ($row['confidence'] ?? '') . "\nFavorit: " . (($row['is_favorite'] ?? '0') === '1' ? 'ja' : 'nein') . "\nErst gesehen: " . ($row['first_seen'] ?? '') . "\nZuletzt gesehen: " . ($row['last_seen'] ?? '') . "\n\nLetzte Werte:\n" . implode("\n", $lines);
        $this->SetValueSafe('SelectedApiKeyDetails', $detail);
        return $detail;
    }

    public function LoadDeviceDetails(): string
    {
        $code = trim($this->ReadPropertyString('SelectedDeviceCode'));
        if ($code === '') {
            return 'Kein Device-Code ausgewaehlt.';
        }
        $devices = $this->IndexBy($this->ReadCsvAssoc('devices'), 'code');
        if (!isset($devices[$code])) {
            return 'Device nicht gefunden: ' . $code;
        }
        $keys = array_values(array_filter($this->ReadCsvAssoc('device_keys'), fn($r) => ($r['device_code'] ?? '') === $code));
        $apiKeys = $this->IndexBy($this->ReadCsvAssoc('api_keys'), 'api_key');
        $lines = [];
        foreach ($keys as $k) {
            $api = $apiKeys[$k['api_key']] ?? [];
            $lines[] = (($k['is_required'] ?? '0') === '1' ? 'Pflicht' : 'Optional') . ' | ' . ($k['role'] ?? '') . ' | ' . ($k['api_key'] ?? '') . ' | ' . ($api['current_value'] ?? '') . ' | ' . ($api['value_type'] ?? '');
        }
        $d = $devices[$code];
        $detail = 'Device: ' . ($d['name'] ?? '') . "\nCode: " . $code . "\nTyp: " . ($d['device_type'] ?? '') . "\nVertrauen: " . ($d['confidence'] ?? '') . "\nStatus-Key: " . ($d['status_key'] ?? '') . "\nValue-Key: " . ($d['value_key'] ?? '') . "\n\nKeys:\n" . implode("\n", $lines);
        $this->SetValueSafe('SelectedDeviceDetails', $detail);
        return $detail;
    }

    public function ToggleFavorite(): string
    {
        $key = trim($this->ReadPropertyString('SelectedApiKey'));
        if ($key === '') {
            return 'Kein API-Key ausgewaehlt.';
        }
        $apiKeys = $this->IndexBy($this->ReadCsvAssoc('api_keys'), 'api_key');
        if (!isset($apiKeys[$key])) {
            return 'API-Key nicht gefunden: ' . $key;
        }
        $apiKeys[$key]['is_favorite'] = (($apiKeys[$key]['is_favorite'] ?? '0') === '1') ? '0' : '1';
        $this->WriteCsvAssoc('api_keys', array_values($apiKeys));
        $this->UpdateBrowserFormList($this->BuildBrowserRows());
        return 'Favorit umgeschaltet: ' . $key;
    }

    public function Destroy()
    {
        if (!$this->ReadPropertyBoolean('KeepDatabaseOnDelete')) {
            $dir = $this->GetStorageDirectory();
            if (is_dir($dir)) {
                foreach (glob($dir . DIRECTORY_SEPARATOR . '*.csv') ?: [] as $file) {
                    @unlink($file);
                }
            }
        }
        parent::Destroy();
    }

    private function RegisterVariables(): void
    {
        $this->RegisterVariableBoolean('StorageReady', 'CSV Storage bereit', '~Switch', 10);
        $this->RegisterVariableString('StorageStatus', 'CSV Storage Status', '', 20);
        $this->RegisterVariableString('StoragePath', 'CSV Storage Pfad', '', 30);
        $this->RegisterVariableInteger('StorageSchemaVersion', 'CSV Schema Version', '', 40);
        $this->RegisterVariableInteger('LastScanId', 'Letzte Scan-ID', '', 100);
        $this->RegisterVariableString('LastScanStarted', 'Letzter Scan Start', '', 110);
        $this->RegisterVariableString('LastScanFinished', 'Letzter Scan Ende', '', 120);
        $this->RegisterVariableInteger('LastScanGeneratedKeys', 'Letzter Scan erzeugte Keys', '', 130);
        $this->RegisterVariableInteger('LastScanFoundKeys', 'Letzter Scan Treffer', '', 140);
        $this->RegisterVariableInteger('LastResponseTimeMs', 'Letzte API Antwortzeit gesamt', '', 150);
        $this->RegisterVariableInteger('LastApiStatus', 'Letzter API Status', '', 160);
        $this->RegisterVariableString('LastError', 'Letzter Fehler', '', 170);
        $this->RegisterVariableString('ScanSummary', 'Scan Zusammenfassung', '', 180);
        $this->RegisterVariableString('SelectedApiKeyDetails', 'API-Key Details', '', 310);
        $this->RegisterVariableString('SelectedDeviceDetails', 'Device Details', '', 410);
    }

    private function GetApiKeyListDefinition(): array
    {
        return ['type' => 'List', 'name' => 'BrowserList', 'caption' => 'API-Key Browser', 'rowCount' => 12, 'add' => false, 'delete' => false, 'columns' => [
            ['name' => 'favorite', 'caption' => 'Fav', 'width' => '45px', 'add' => '', 'edit' => false],
            ['name' => 'confidence', 'caption' => 'Vertrauen', 'width' => '80px', 'add' => '', 'edit' => false],
            ['name' => 'api_key', 'caption' => 'API-Key', 'width' => '200px', 'add' => '', 'edit' => false],
            ['name' => 'suggested_name', 'caption' => 'Name', 'width' => '160px', 'add' => '', 'edit' => false],
            ['name' => 'current_value', 'caption' => 'Wert', 'width' => '170px', 'add' => '', 'edit' => false],
            ['name' => 'value_type', 'caption' => 'Typ', 'width' => '120px', 'add' => '', 'edit' => false]
        ], 'values' => $this->BuildBrowserRowsSafe()];
    }

    private function GetDeviceListDefinition(): array
    {
        return ['type' => 'List', 'name' => 'DeviceList', 'caption' => 'Device Browser', 'rowCount' => 10, 'add' => false, 'delete' => false, 'columns' => [
            ['name' => 'code', 'caption' => 'Code', 'width' => '130px', 'add' => '', 'edit' => false],
            ['name' => 'name', 'caption' => 'Name', 'width' => '160px', 'add' => '', 'edit' => false],
            ['name' => 'device_type', 'caption' => 'Typ', 'width' => '130px', 'add' => '', 'edit' => false],
            ['name' => 'confidence', 'caption' => 'Vertrauen', 'width' => '85px', 'add' => '', 'edit' => false],
            ['name' => 'key_count', 'caption' => 'Keys', 'width' => '60px', 'add' => '', 'edit' => false],
            ['name' => 'status_key', 'caption' => 'Status-Key', 'width' => '170px', 'add' => '', 'edit' => false]
        ], 'values' => $this->BuildDeviceRowsSafe()];
    }

    private function BuildBrowserRowsSafe(): array { try { return $this->BuildBrowserRows(); } catch (Throwable $e) { return []; } }
    private function BuildDeviceRowsSafe(): array { try { return $this->BuildDeviceRows(); } catch (Throwable $e) { return []; } }

    private function BuildBrowserRows(): array
    {
        $search = mb_strtolower(trim($this->ReadPropertyString('BrowserSearch')));
        $rows = [];
        foreach ($this->ReadCsvAssoc('api_keys') as $r) {
            $haystack = mb_strtolower(($r['api_key'] ?? '') . ' ' . ($r['suggested_name'] ?? '') . ' ' . ($r['current_value'] ?? ''));
            if ($search !== '' && strpos($haystack, $search) === false) { continue; }
            $rows[] = ['favorite' => (($r['is_favorite'] ?? '0') === '1') ? 'ja' : '', 'confidence' => $r['confidence'] ?? '', 'api_key' => $r['api_key'] ?? '', 'suggested_name' => $r['suggested_name'] ?? '', 'current_value' => $r['current_value'] ?? '', 'value_type' => $r['value_type'] ?? ''];
        }
        return array_slice(array_reverse($rows), 0, 200);
    }

    private function BuildDeviceRows(): array
    {
        $deviceKeys = $this->ReadCsvAssoc('device_keys');
        $counts = [];
        foreach ($deviceKeys as $dk) { $counts[$dk['device_code']] = ($counts[$dk['device_code']] ?? 0) + 1; }
        $rows = [];
        foreach ($this->ReadCsvAssoc('devices') as $d) {
            $rows[] = ['code' => $d['code'] ?? '', 'name' => $d['name'] ?? '', 'device_type' => $d['device_type'] ?? '', 'confidence' => $d['confidence'] ?? '', 'key_count' => (string)($counts[$d['code'] ?? ''] ?? 0), 'status_key' => $d['status_key'] ?? ''];
        }
        return $rows;
    }

    private function UpdateBrowserFormList(array $rows): void { $this->UpdateFormField('BrowserList', 'values', json_encode($rows, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '[]'); }
    private function UpdateDeviceFormList(array $rows): void { $this->UpdateFormField('DeviceList', 'values', json_encode($rows, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '[]'); }

    private function InitializeStorage(): void
    {
        $dir = $this->GetStorageDirectory();
        if (!is_dir($dir) && !mkdir($dir, 0775, true) && !is_dir($dir)) { throw new Exception('CSV-Verzeichnis konnte nicht erstellt werden: ' . $dir); }
        if (!is_writable($dir)) { throw new Exception('CSV-Verzeichnis ist nicht beschreibbar: ' . $dir); }
        foreach (self::CSV_FILES as $name => $header) {
            $path = $this->GetCsvPath($name);
            if (!is_file($path)) { $this->WriteRawCsv($path, [$header]); }
        }
        $this->WriteCsvAssoc('meta', [['key' => 'schema_version', 'value' => (string)self::SCHEMA_VERSION]]);
    }

    private function ReadCsvAssoc(string $name): array
    {
        $path = $this->GetCsvPath($name);
        if (!is_file($path)) { return []; }
        $handle = fopen($path, 'rb');
        if ($handle === false) { throw new Exception('CSV konnte nicht gelesen werden: ' . $path); }
        flock($handle, LOCK_SH);
        $header = fgetcsv($handle, 0, ';');
        $rows = [];
        if (is_array($header)) {
            while (($data = fgetcsv($handle, 0, ';')) !== false) {
                $row = [];
                foreach ($header as $index => $key) { $row[$key] = $data[$index] ?? ''; }
                if (implode('', $row) !== '') { $rows[] = $row; }
            }
        }
        flock($handle, LOCK_UN);
        fclose($handle);
        return $rows;
    }

    private function WriteCsvAssoc(string $name, array $rows): void
    {
        $header = self::CSV_FILES[$name];
        $data = [$header];
        foreach ($rows as $row) { $data[] = array_map(fn($key) => (string)($row[$key] ?? ''), $header); }
        $this->WriteRawCsv($this->GetCsvPath($name), $data);
    }

    private function AppendCsvAssoc(string $name, array $rows): void
    {
        if (count($rows) === 0) { return; }
        $path = $this->GetCsvPath($name);
        $handle = fopen($path, 'ab');
        if ($handle === false) { throw new Exception('CSV konnte nicht geschrieben werden: ' . $path); }
        flock($handle, LOCK_EX);
        foreach ($rows as $row) { fputcsv($handle, array_map(fn($key) => (string)($row[$key] ?? ''), self::CSV_FILES[$name]), ';'); }
        flock($handle, LOCK_UN);
        fclose($handle);
    }

    private function WriteRawCsv(string $path, array $rows): void
    {
        $tmp = $path . '.tmp';
        $handle = fopen($tmp, 'wb');
        if ($handle === false) { throw new Exception('CSV Temp-Datei konnte nicht erstellt werden: ' . $tmp); }
        flock($handle, LOCK_EX);
        foreach ($rows as $row) { fputcsv($handle, $row, ';'); }
        fflush($handle);
        flock($handle, LOCK_UN);
        fclose($handle);
        if (!rename($tmp, $path)) { throw new Exception('CSV konnte nicht ersetzt werden: ' . $path); }
    }

    private function GetNextScanId(): int
    {
        $max = 0;
        foreach ($this->ReadCsvAssoc('scans') as $scan) { $max = max($max, (int)($scan['scan_id'] ?? 0)); }
        return $max + 1;
    }

    private function ClassifyDevice(string $key, string $now, array &$devices, array &$deviceKeys): void
    {
        if (strpos($key, '55.17106.') === 0) { $this->EnsureDevice('filter_pump', 'Filterpumpe', 'actuator', $key, $this->GetKeySuffix($key), $now, $devices, $deviceKeys); }
        if (strpos($key, '55.17102.') === 0) { $this->EnsureDevice('pool_light', 'Poollicht', 'actuator', $key, $this->GetKeySuffix($key), $now, $devices, $deviceKeys); }
        if (strpos($key, '34.') === 0) { $this->EnsureDevice('water_values', 'Wasserwerte', 'sensor_group', $key, 'measurement', $now, $devices, $deviceKeys); }
        if (strpos($key, '13.') === 0) { $this->EnsureDevice('system_values', 'Systemwerte', 'system_group', $key, 'info', $now, $devices, $deviceKeys); }
    }

    private function EnsureDevice(string $code, string $name, string $type, string $apiKey, string $role, string $now, array &$devices, array &$deviceKeys): void
    {
        $existing = $devices[$code] ?? [];
        $devices[$code] = ['code' => $code, 'name' => $name, 'device_type' => $type, 'confidence' => '80', 'status_key' => $role === 'status' ? $apiKey : ($existing['status_key'] ?? ''), 'value_key' => ($role === 'value' || $role === 'measurement') ? $apiKey : ($existing['value_key'] ?? ''), 'first_seen' => $existing['first_seen'] ?? $now, 'last_seen' => $now];
        $deviceKeys[] = ['device_code' => $code, 'api_key' => $apiKey, 'role' => $role, 'is_required' => in_array($role, ['status', 'value', 'measurement'], true) ? '1' : '0', 'direction' => 'read'];
    }

    private function ApiGet(array $keys): array
    {
        $host = trim($this->ReadPropertyString('Host')); if ($host === '') { throw new Exception('Host ist leer.'); }
        $url = 'http://' . $host . ':' . max(1, min(65535, $this->ReadPropertyInteger('Port'))) . '/cgi-bin/webgui.fcgi?sid=' . rawurlencode($this->CreateSid());
        $payload = json_encode(['get' => array_values($keys)]);
        if ($payload === false) { throw new Exception('JSON-Encoding fehlgeschlagen.'); }
        $context = stream_context_create(['http' => ['method' => 'POST', 'header' => "Content-Type: application/json;charset=UTF-8\r\nAccept: application/json\r\n", 'content' => $payload, 'timeout' => max(1, $this->ReadPropertyInteger('Timeout')), 'ignore_errors' => true]]);
        $started = microtime(true); $raw = @file_get_contents($url, false, $context); $durationMs = (int)round((microtime(true) - $started) * 1000);
        if ($raw === false) { throw new Exception('HTTP request failed.'); }
        $json = json_decode((string)$raw, true);
        if (!is_array($json)) { throw new Exception('Ungueltige JSON-Antwort.'); }
        if ((int)($json['status']['code'] ?? -1) !== 0) { throw new Exception('API Status ' . (int)($json['status']['code'] ?? -1)); }
        $json['_meta'] = ['duration_ms' => $durationMs];
        return $json;
    }

    private function BuildScanKeys(): array
    {
        $keys = []; $suffixes = $this->ParseSuffixes($this->ReadPropertyString('ScanSuffixes')); $max = max(1, min(5000, $this->ReadPropertyInteger('ScanMaxKeys')));
        for ($g = max(1, $this->ReadPropertyInteger('ScanGroupStart')); $g <= max($g, $this->ReadPropertyInteger('ScanGroupEnd')); $g++) {
            for ($o = max(1, $this->ReadPropertyInteger('ScanObjectStart')); $o <= max($o, $this->ReadPropertyInteger('ScanObjectEnd')); $o++) {
                foreach ($suffixes as $s) { $keys[] = $g . '.' . $o . '.' . $s; if (count($keys) >= $max) { return $keys; } }
            }
        }
        return $keys;
    }

    private function ParseSuffixes(string $raw): array { $raw = str_replace(["\r\n", "\r", ',', ';'], "\n", $raw); $r = []; foreach (explode("\n", $raw) as $line) { $s = trim($line); if ($s !== '' && preg_match('/^[A-Za-z0-9_]+$/', $s)) { $r[$s] = $s; } } return array_values($r ?: ['value']); }
    private function DetectValueType(string $value): string { $n = str_replace(',', '.', $value); if ($value === '0' || $value === '1') { return 'boolean-candidate'; } return is_numeric($n) ? (strpos($n, '.') === false ? 'integer' : 'float') : 'string'; }
    private function GetConfidence(string $key, string $value): int { return in_array($key, ['34.4001.value', '34.4022.value', '34.4033.value', '13.16507.text2', '13.16509.text1', '55.17102.status', '55.17102.value', '55.17106.status', '55.17106.opmode', '55.17106.value'], true) ? 100 : 60; }
    private function GetKnownName(string $key): string { $n = ['34.4001.value' => 'pH', '34.4022.value' => 'Redox', '34.4033.value' => 'Pooltemperatur', '13.16507.text2' => 'Aussentemperatur T3', '13.16509.text1' => 'Leitfaehigkeit', '55.17106.status' => 'Filterpumpe Status', '55.17106.opmode' => 'Filterpumpe Betriebsart', '55.17106.value' => 'Filterpumpe Text', '55.17102.status' => 'Poollicht Status', '55.17102.value' => 'Poollicht Text']; return $n[$key] ?? ''; }
    private function GetKeySuffix(string $key): string { $p = explode('.', $key); return end($p) ?: ''; }
    private function CleanString(string $value): string { return trim(html_entity_decode(strip_tags($value), ENT_QUOTES | ENT_HTML5, 'UTF-8')); }
    private function CreateSid(): string { return 'SYMBAYROL' . substr(strtoupper(md5((string)microtime(true) . mt_rand())), 0, 23); }
    private function IndexBy(array $rows, string $key): array { $out = []; foreach ($rows as $row) { if (($row[$key] ?? '') !== '') { $out[$row[$key]] = $row; } } return $out; }
    private function UniqueRows(array $rows, array $keys): array { $seen = []; $out = []; foreach ($rows as $row) { $hash = implode('|', array_map(fn($k) => $row[$k] ?? '', $keys)); if (!isset($seen[$hash])) { $seen[$hash] = true; $out[] = $row; } } return $out; }
    private function GetStorageDirectory(): string { return rtrim(IPS_GetKernelDir(), DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . 'user' . DIRECTORY_SEPARATOR . 'BayrolDiscovery'; }
    private function GetCsvPath(string $name): string { return $this->GetStorageDirectory() . DIRECTORY_SEPARATOR . $name . '.csv'; }
    private function SetValueSafe(string $ident, $value): void { $id = @$this->GetIDForIdent($ident); if ($id !== false && $id > 0) { SetValue($id, $value); } }
}
