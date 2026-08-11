<?php

declare(strict_types=1);

class BayrolDiscovery extends IPSModule
{
    private const STATUS_ACTIVE = 102;
    private const STATUS_STORAGE_ERROR = 202;
    private const STATUS_API_ERROR = 203;
    private const SCHEMA_VERSION = 5;

    private const CSV_FILES = [
        'meta' => ['key', 'value'],
        'scans' => ['scan_id', 'started_at', 'finished_at', 'host', 'port', 'generated_keys', 'found_keys', 'duration_ms', 'notes'],
        'api_keys' => ['api_key', 'current_value', 'value_type', 'confidence', 'suggested_name', 'is_favorite', 'first_seen', 'last_seen', 'last_scan_id', 'comment', 'gateway_variable_name', 'gateway_import_enabled'],
        'observations' => ['scan_id', 'api_key', 'value', 'value_type', 'observed_at'],
        'devices' => ['code', 'name', 'device_type', 'confidence', 'status_key', 'value_key', 'first_seen', 'last_seen'],
        'device_keys' => ['device_code', 'api_key', 'role', 'is_required', 'direction', 'assignment_source'],
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
        $this->RegisterPropertyString('SelectedApiKey', '');
        $this->RegisterPropertyString('SelectedApiKeyComment', '');
        $this->RegisterPropertyString('SelectedGatewayVariableName', '');
        $this->RegisterPropertyString('SelectedDeviceCode', '');
        $this->RegisterPropertyString('ManualDeviceCode', '');
        $this->RegisterPropertyString('ManualDeviceName', '');
        $this->RegisterPropertyString('ManualDeviceType', 'sensor_group');
        $this->RegisterPropertyString('ManualDeviceRole', 'measurement');
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
            $this->SetValueSafe('ScanSummary', $this->BuildScanSummary());
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
                ['type' => 'Label', 'caption' => 'CSV Storage ohne SQLite/PDO.'],
                ['type' => 'NumberSpinner', 'name' => 'ScanGroupStart', 'caption' => 'Scan Gruppe von'],
                ['type' => 'NumberSpinner', 'name' => 'ScanGroupEnd', 'caption' => 'Scan Gruppe bis'],
                ['type' => 'NumberSpinner', 'name' => 'ScanObjectStart', 'caption' => 'Scan Objekt-ID von'],
                ['type' => 'NumberSpinner', 'name' => 'ScanObjectEnd', 'caption' => 'Scan Objekt-ID bis'],
                ['type' => 'ValidationTextBox', 'name' => 'ScanSuffixes', 'caption' => 'Scan Suffixe'],
                ['type' => 'NumberSpinner', 'name' => 'ScanMaxKeys', 'caption' => 'Maximale Keys pro Scan'],
                ['type' => 'NumberSpinner', 'name' => 'ScanBatchSize', 'caption' => 'Batchgroesse'],
                ['type' => 'ValidationTextBox', 'name' => 'SelectedApiKey', 'caption' => 'API-Key (Fallback/manuell)'],
                ['type' => 'ValidationTextBox', 'name' => 'SelectedDeviceCode', 'caption' => 'Device-Code (Fallback/manuell)']
            ],
            'actions' => [
                ['type' => 'Label', 'caption' => 'CSV-Pfad: ' . $this->GetStorageDirectory()],
                ['type' => 'Button', 'caption' => 'CSV Storage pruefen', 'onClick' => 'echo BPD_CheckDatabase($id);'],
                ['type' => 'Button', 'caption' => 'Verbindung testen', 'onClick' => 'echo BPD_TestConnection($id);'],
                ['type' => 'Button', 'caption' => 'Scan starten', 'onClick' => 'echo BPD_RunScan($id);'],
                ['type' => 'Button', 'caption' => 'Scan-Zusammenfassung laden', 'onClick' => 'echo BPD_GetScanSummary($id);'],
                ['type' => 'Label', 'caption' => 'API-Key Browser 2.0: Schnellfilter durchsucht API-Key, Name, Gateway-Name, Importstatus, Wert, Typ, Vertrauen, Device und Kommentar.'],
                ['type' => 'Button', 'caption' => 'API-Key Browser laden', 'onClick' => 'echo BPD_LoadBrowser($id);'],
                ['type' => 'Label', 'caption' => 'Zeile markieren; Details und Aktionen verwenden direkt diese Zeile.'],
                ['type' => 'Button', 'caption' => 'API-Key jetzt testen', 'onClick' => 'echo BPD_TestApiKeyFor($id, (string) ($BrowserList["api_key"] ?? ""));'],
                ['type' => 'Button', 'caption' => 'API-Key Details laden', 'onClick' => 'echo BPD_LoadApiKeyDetailsFor($id, (string) ($BrowserList["api_key"] ?? ""));'],
                ['type' => 'ValidationTextBox', 'name' => 'SelectedApiKeyComment', 'caption' => 'Kommentar fuer markierte Zeile'],
                ['type' => 'Button', 'caption' => 'Kommentar speichern', 'onClick' => 'echo BPD_SaveApiKeyCommentFor($id, (string) ($BrowserList["api_key"] ?? ""), (string) $SelectedApiKeyComment);'],
                ['type' => 'ValidationTextBox', 'name' => 'SelectedGatewayVariableName', 'caption' => 'Gateway-Variablenname fuer markierte Zeile'],
                ['type' => 'Button', 'caption' => 'Gateway-Variablenname speichern', 'onClick' => 'echo BPD_SaveGatewayVariableNameFor($id, (string) ($BrowserList["api_key"] ?? ""), (string) $SelectedGatewayVariableName);'],
                ['type' => 'Button', 'caption' => 'Gateway-Import umschalten', 'onClick' => 'echo BPD_ToggleGatewayImportFor($id, (string) ($BrowserList["api_key"] ?? ""));'],
                ['type' => 'Button', 'caption' => 'Favorit umschalten', 'onClick' => 'echo BPD_ToggleFavoriteFor($id, (string) ($BrowserList["api_key"] ?? ""));'],
                ['type' => 'Label', 'caption' => 'Manuelle Device-Zuordnung: Werte eingeben und direkt zuordnen; Speichern der Instanzkonfiguration ist nicht erforderlich.'],
                ['type' => 'ValidationTextBox', 'name' => 'ManualDeviceCode', 'caption' => 'Neuer/bestehender Device-Code'],
                ['type' => 'ValidationTextBox', 'name' => 'ManualDeviceName', 'caption' => 'Device-Name'],
                ['type' => 'ValidationTextBox', 'name' => 'ManualDeviceType', 'caption' => 'Device-Typ, z.B. sensor_group'],
                ['type' => 'ValidationTextBox', 'name' => 'ManualDeviceRole', 'caption' => 'Rolle, z.B. measurement/status/value/info'],
                ['type' => 'Button', 'caption' => 'API-Key Device manuell zuordnen', 'onClick' => 'echo BPD_AssignApiKeyToDeviceFor($id, (string) ($BrowserList["api_key"] ?? ""), (string) $ManualDeviceCode, (string) $ManualDeviceName, (string) $ManualDeviceType, (string) $ManualDeviceRole);'],
                $this->GetApiKeyListDefinition(),
                ['type' => 'Button', 'caption' => 'Device Browser laden', 'onClick' => 'echo BPD_LoadDevices($id);'],
                ['type' => 'Label', 'caption' => 'Manuelle Device-Zuordnungen haben Vorrang vor der automatischen Klassifizierung.'],
                ['type' => 'Label', 'caption' => 'Device-Familie: Device markieren, Status-Key eingeben und gleiche Objekt-ID nach verwandten Keys durchsuchen.'],
                ['type' => 'ValidationTextBox', 'name' => 'DeviceFamilyStatusKey', 'caption' => 'Status-Key, z.B. 55.17120.status'],
                ['type' => 'Button', 'caption' => 'Status-Key setzen und Device-Familie suchen', 'onClick' => 'echo BPD_DiscoverDeviceFamilyFor($id, (string) ($DeviceList["code"] ?? ""), (string) $DeviceFamilyStatusKey);'],
                ['type' => 'NumberSpinner', 'name' => 'DeviceNeighborRadius', 'caption' => 'Nachbarschafts-Radius Objekt-IDs', 'value' => 2],
                ['type' => 'Button', 'caption' => 'Device-Nachbarschaft scannen', 'onClick' => 'echo BPD_DiscoverDeviceNeighborsFor($id, (string) ($DeviceList["code"] ?? ""), (string) $DeviceFamilyStatusKey, (int) $DeviceNeighborRadius);'],
                ['type' => 'Button', 'caption' => 'Device Details laden', 'onClick' => 'echo BPD_LoadDeviceDetailsFor($id, (string) ($DeviceList["code"] ?? ""));'],
                ['type' => 'Button', 'caption' => 'Device Export-Vorschau', 'onClick' => 'echo BPD_PreviewDeviceExportFor($id, (string) ($DeviceList["code"] ?? ""));'],
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
            $message = 'CSV Storage OK. API-Keys: ' . count($this->ReadCsvAssoc('api_keys')) . ', Scans: ' . count($this->ReadCsvAssoc('scans')) . ', Observations: ' . count($this->ReadCsvAssoc('observations')) . ', Devices: ' . count($this->ReadCsvAssoc('devices')) . ', Schema: v' . self::SCHEMA_VERSION;
            $this->SetValueSafe('StorageStatus', $message);
            $this->SetValueSafe('ScanSummary', $this->BuildScanSummary());
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
            $this->SetValueSafe('LastApiStatus', (int) ($response['status']['code'] ?? -1));
            $this->SetValueSafe('LastResponseTimeMs', (int) ($response['_meta']['duration_ms'] ?? 0));
            $this->SetValueSafe('LastError', $ok ? '' : $message);
            $this->SetStatus($ok ? self::STATUS_ACTIVE : self::STATUS_API_ERROR);
            return $message;
        } catch (Throwable $e) {
            $this->SetValueSafe('LastError', $e->getMessage());
            $this->SetStatus(self::STATUS_API_ERROR);
            return 'Verbindungsfehler: ' . $e->getMessage();
        }
    }

    public function TestApiKeyFor(string $key): string
    {
        $key = trim($key);
        if ($key === '') { return 'Keine Browser-Zeile ausgewaehlt.'; }
        try {
            $response = $this->ApiGet([$key]);
            if (!array_key_exists($key, $response['data'] ?? [])) { return 'API-Key nicht in Antwort enthalten: ' . $key; }
            $clean = $this->CleanString((string) $response['data'][$key]);
            $type = $this->DetectValueType($clean);
            $apiKeys = $this->IndexBy($this->ReadCsvAssoc('api_keys'), 'api_key');
            $existing = $apiKeys[$key] ?? [];
            $now = date('Y-m-d H:i:s');
            $apiKeys[$key] = [
                'api_key' => $key,
                'current_value' => $clean,
                'value_type' => $type,
                'confidence' => $existing['confidence'] ?? (string) $this->GetConfidence($key),
                'suggested_name' => $existing['suggested_name'] ?? $this->GetKnownName($key),
                'is_favorite' => $existing['is_favorite'] ?? '0',
                'first_seen' => $existing['first_seen'] ?? $now,
                'last_seen' => $now,
                'last_scan_id' => $existing['last_scan_id'] ?? '',
                'comment' => $existing['comment'] ?? '',
                'gateway_variable_name' => $existing['gateway_variable_name'] ?? '',
                'gateway_import_enabled' => $existing['gateway_import_enabled'] ?? '0'
            ];
            $this->WriteCsvAssoc('api_keys', array_values($apiKeys));
            $this->UpdateBrowserFormList($this->BuildBrowserRows());
            return 'API-Key getestet: ' . $key . ' | Wert: ' . $clean . ' | Typ: ' . $type;
        } catch (Throwable $e) {
            return 'API-Key Testfehler: ' . $e->getMessage();
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
            $observations = [];
            foreach (array_chunk($keys, max(1, min(100, $this->ReadPropertyInteger('ScanBatchSize')))) as $chunk) {
                $response = $this->ApiGet($chunk);
                $duration += (int) ($response['_meta']['duration_ms'] ?? 0);
                foreach (($response['data'] ?? []) as $key => $value) {
                    $clean = $this->CleanString((string) $value);
                    if ($clean === '') { continue; }
                    $type = $this->DetectValueType($clean);
                    $existing = $apiKeys[(string) $key] ?? [];
                    $apiKeys[(string) $key] = [
                        'api_key' => (string) $key,
                        'current_value' => $clean,
                        'value_type' => $type,
                        'confidence' => (string) $this->GetConfidence((string) $key),
                        'suggested_name' => ($existing['suggested_name'] ?? '') !== '' ? $existing['suggested_name'] : $this->GetKnownName((string) $key),
                        'is_favorite' => $existing['is_favorite'] ?? '0',
                        'first_seen' => $existing['first_seen'] ?? $started,
                        'last_seen' => $started,
                        'last_scan_id' => (string) $scanId,
                        'comment' => $existing['comment'] ?? '',
                        'gateway_variable_name' => $existing['gateway_variable_name'] ?? '',
                        'gateway_import_enabled' => $existing['gateway_import_enabled'] ?? '0'
                    ];
                    $observations[] = ['scan_id' => (string) $scanId, 'api_key' => (string) $key, 'value' => $clean, 'value_type' => $type, 'observed_at' => $started];
                    $this->ClassifyDevice((string) $key, $started, $devices, $deviceKeys);
                    $found++;
                }
            }
            $this->WriteCsvAssoc('api_keys', array_values($apiKeys));
            $this->AppendCsvAssoc('observations', $observations);
            $this->WriteCsvAssoc('devices', array_values($devices));
            $this->WriteCsvAssoc('device_keys', $this->UniqueRows($deviceKeys, ['device_code', 'api_key']));
            $finished = date('Y-m-d H:i:s');
            $this->AppendCsvAssoc('scans', [[
                'scan_id' => (string) $scanId, 'started_at' => $started, 'finished_at' => $finished,
                'host' => $this->ReadPropertyString('Host'), 'port' => (string) $this->ReadPropertyInteger('Port'),
                'generated_keys' => (string) count($keys), 'found_keys' => (string) $found,
                'duration_ms' => (string) $duration, 'notes' => 'CSV scan'
            ]]);
            $this->SetValueSafe('LastScanId', $scanId);
            $this->SetValueSafe('LastScanStarted', $started);
            $this->SetValueSafe('LastScanFinished', $finished);
            $this->SetValueSafe('LastScanGeneratedKeys', count($keys));
            $this->SetValueSafe('LastScanFoundKeys', $found);
            $this->SetValueSafe('LastResponseTimeMs', $duration);
            $this->SetValueSafe('LastError', '');
            $this->SetValueSafe('ScanSummary', $this->BuildScanSummary());
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
            $message = $this->BuildScanSummary();
            $this->SetValueSafe('ScanSummary', $message);
            return $message;
        } catch (Throwable $e) { return 'Zusammenfassungsfehler: ' . $e->getMessage(); }
    }

    public function LoadBrowser(): string
    {
        $rows = $this->BuildBrowserRows();
        $this->UpdateBrowserFormList($rows);
        $message = 'API-Key Browser 2.0 geladen. Zeilen: ' . count($rows);
        $this->SetValueSafe('BrowserSummary', $message);
        return $message;
    }

    public function LoadDevices(): string
    {
        $rows = $this->BuildDeviceRows();
        $this->UpdateDeviceFormList($rows);
        return 'Device Browser geladen. Devices: ' . count($rows);
    }

    public function DiscoverDeviceFamilyFor(string $code, string $statusKey): string
    {
        return $this->DiscoverDeviceRangeFor($code, $statusKey, 0);
    }

    public function DiscoverDeviceNeighborsFor(string $code, string $statusKey, int $radius): string
    {
        return $this->DiscoverDeviceRangeFor($code, $statusKey, max(1, min(5, $radius)));
    }

    public function LoadApiKeyDetails(): string
    {
        return $this->LoadApiKeyDetailsFor($this->ReadPropertyString('SelectedApiKey'));
    }

    public function LoadApiKeyDetailsFor(string $key): string
    {
        $key = trim($key);
        if ($key === '') { return 'Keine Browser-Zeile ausgewaehlt.'; }
        $apiKeys = $this->IndexBy($this->ReadCsvAssoc('api_keys'), 'api_key');
        if (!isset($apiKeys[$key])) { return 'API-Key nicht gefunden: ' . $key; }
        $row = $apiKeys[$key];
        $device = $this->GetDeviceForApiKey($key);
        $source = $this->GetAssignmentSourceForApiKey($key);
        $historyAll = array_values(array_filter($this->ReadCsvAssoc('observations'), static function ($r) use ($key) { return ($r['api_key'] ?? '') === $key; }));
        $history = array_slice(array_reverse($historyAll), 0, 12);
        $lines = [];
        foreach ($history as $h) { $lines[] = ($h['observed_at'] ?? '') . ' | Scan ' . ($h['scan_id'] ?? '') . ' | ' . ($h['value'] ?? '') . ' | ' . ($h['value_type'] ?? ''); }
        $detail = 'API-Key: ' . $key . "\nDevice: " . $device . "\nZuordnung: " . $source . "\nName: " . ($row['suggested_name'] ?? '') . "\nGateway-Variablenname: " . ($row['gateway_variable_name'] ?? '') . "\nGateway-Import: " . (($row['gateway_import_enabled'] ?? '0') === '1' ? 'ja' : 'nein') . "\nWert: " . ($row['current_value'] ?? '') . "\nTyp: " . ($row['value_type'] ?? '') . "\nVertrauen: " . ($row['confidence'] ?? '') . "\nFavorit: " . (($row['is_favorite'] ?? '0') === '1' ? 'ja' : 'nein') . "\nErst gesehen: " . ($row['first_seen'] ?? '') . "\nZuletzt gesehen: " . ($row['last_seen'] ?? '') . "\nBeobachtungen: " . count($historyAll) . "\nKommentar: " . ($row['comment'] ?? '') . "\n\nLetzte Werte:\n" . implode("\n", $lines);
        $this->SetValueSafe('SelectedApiKeyDetails', $detail);
        return $detail;
    }

    public function SaveApiKeyComment(): string
    {
        return $this->SaveApiKeyCommentFor($this->ReadPropertyString('SelectedApiKey'), $this->ReadPropertyString('SelectedApiKeyComment'));
    }

    public function SaveApiKeyCommentFor(string $key, string $comment): string
    {
        $key = trim($key);
        if ($key === '') { return 'Keine Browser-Zeile ausgewaehlt.'; }
        $apiKeys = $this->IndexBy($this->ReadCsvAssoc('api_keys'), 'api_key');
        if (!isset($apiKeys[$key])) { return 'API-Key nicht gefunden: ' . $key; }
        $apiKeys[$key]['comment'] = trim($comment);
        $this->WriteCsvAssoc('api_keys', array_values($apiKeys));
        $this->UpdateBrowserFormList($this->BuildBrowserRows());
        return 'Kommentar gespeichert: ' . $key;
    }

    public function SaveGatewayVariableName(): string
    {
        return $this->SaveGatewayVariableNameFor($this->ReadPropertyString('SelectedApiKey'), $this->ReadPropertyString('SelectedGatewayVariableName'));
    }

    public function SaveGatewayVariableNameFor(string $key, string $name): string
    {
        $key = trim($key);
        if ($key === '') { return 'Keine Browser-Zeile ausgewaehlt.'; }
        $apiKeys = $this->IndexBy($this->ReadCsvAssoc('api_keys'), 'api_key');
        if (!isset($apiKeys[$key])) { return 'API-Key nicht gefunden: ' . $key; }
        $apiKeys[$key]['gateway_variable_name'] = trim($name);
        $this->WriteCsvAssoc('api_keys', array_values($apiKeys));
        $this->UpdateBrowserFormList($this->BuildBrowserRows());
        return 'Gateway-Variablenname gespeichert: ' . $key . ' -> ' . (trim($name) !== '' ? trim($name) : '[Vorschlagsname]');
    }

    public function ToggleGatewayImport(): string
    {
        return $this->ToggleGatewayImportFor($this->ReadPropertyString('SelectedApiKey'));
    }

    public function ToggleGatewayImportFor(string $key): string
    {
        $key = trim($key);
        if ($key === '') { return 'Keine Browser-Zeile ausgewaehlt.'; }
        $apiKeys = $this->IndexBy($this->ReadCsvAssoc('api_keys'), 'api_key');
        if (!isset($apiKeys[$key])) { return 'API-Key nicht gefunden: ' . $key; }
        $enabled = (($apiKeys[$key]['gateway_import_enabled'] ?? '0') === '1') ? '0' : '1';
        $apiKeys[$key]['gateway_import_enabled'] = $enabled;
        $this->WriteCsvAssoc('api_keys', array_values($apiKeys));
        $this->UpdateBrowserFormList($this->BuildBrowserRows());
        $this->UpdateDeviceFormList($this->BuildDeviceRows());
        return 'Gateway-Import ' . ($enabled === '1' ? 'aktiviert' : 'deaktiviert') . ': ' . $key;
    }

    public function ToggleFavorite(): string
    {
        return $this->ToggleFavoriteFor($this->ReadPropertyString('SelectedApiKey'));
    }

    public function ToggleFavoriteFor(string $key): string
    {
        $key = trim($key);
        if ($key === '') { return 'Keine Browser-Zeile ausgewaehlt.'; }
        $apiKeys = $this->IndexBy($this->ReadCsvAssoc('api_keys'), 'api_key');
        if (!isset($apiKeys[$key])) { return 'API-Key nicht gefunden: ' . $key; }
        $apiKeys[$key]['is_favorite'] = (($apiKeys[$key]['is_favorite'] ?? '0') === '1') ? '0' : '1';
        $this->WriteCsvAssoc('api_keys', array_values($apiKeys));
        $this->UpdateBrowserFormList($this->BuildBrowserRows());
        return 'Favorit umgeschaltet: ' . $key;
    }

    public function AssignApiKeyToDeviceFor(string $key, string $code, string $name, string $type, string $role): string
    {
        $key = trim($key);
        $code = trim($code);
        $name = trim($name);
        $type = trim($type);
        $role = trim($role);
        if ($key === '') { return 'Keine Browser-Zeile ausgewaehlt.'; }
        if ($code === '' || !preg_match('/^[A-Za-z0-9_\-]+$/', $code)) { return 'Ungueltiger Device-Code. Erlaubt: Buchstaben, Zahlen, _ und -.'; }
        if ($name === '') { $name = $code; }
        if ($type === '') { $type = 'sensor_group'; }
        if ($role === '') { $role = 'measurement'; }

        $apiKeys = $this->IndexBy($this->ReadCsvAssoc('api_keys'), 'api_key');
        if (!isset($apiKeys[$key])) { return 'API-Key nicht gefunden: ' . $key; }
        $now = date('Y-m-d H:i:s');
        $devices = $this->IndexBy($this->ReadCsvAssoc('devices'), 'code');
        $existing = $devices[$code] ?? [];
        $devices[$code] = [
            'code' => $code,
            'name' => $name,
            'device_type' => $type,
            'confidence' => '100',
            'status_key' => $role === 'status' ? $key : ($existing['status_key'] ?? ''),
            'value_key' => in_array($role, ['value', 'measurement'], true) ? $key : ($existing['value_key'] ?? ''),
            'first_seen' => $existing['first_seen'] ?? $now,
            'last_seen' => $now
        ];

        $deviceKeys = array_values(array_filter($this->ReadCsvAssoc('device_keys'), static function ($row) use ($key) {
            return ($row['api_key'] ?? '') !== $key;
        }));
        $deviceKeys[] = [
            'device_code' => $code,
            'api_key' => $key,
            'role' => $role,
            'is_required' => in_array($role, ['status', 'value', 'measurement'], true) ? '1' : '0',
            'direction' => 'read',
            'assignment_source' => 'manual'
        ];

        $this->WriteCsvAssoc('devices', array_values($devices));
        $this->WriteCsvAssoc('device_keys', $this->UniqueRows($deviceKeys, ['device_code', 'api_key']));
        $this->UpdateBrowserFormList($this->BuildBrowserRows());
        $this->UpdateDeviceFormList($this->BuildDeviceRows());
        return 'Manuelle Device-Zuordnung gespeichert: ' . $key . ' -> ' . $code . ' (' . $role . ').';
    }

    public function LoadDeviceDetails(): string
    {
        return $this->LoadDeviceDetailsFor($this->ReadPropertyString('SelectedDeviceCode'));
    }

    public function LoadDeviceDetailsFor(string $code): string
    {
        $code = trim($code);
        if ($code === '') { return 'Keine Device-Zeile ausgewaehlt.'; }
        $devices = $this->IndexBy($this->ReadCsvAssoc('devices'), 'code');
        if (!isset($devices[$code])) { return 'Device nicht gefunden: ' . $code; }
        $keys = array_values(array_filter($this->ReadCsvAssoc('device_keys'), static function ($r) use ($code) { return ($r['device_code'] ?? '') === $code; }));
        $apiKeys = $this->IndexBy($this->ReadCsvAssoc('api_keys'), 'api_key');
        $lines = [];
        foreach ($keys as $k) {
            $api = $apiKeys[$k['api_key']] ?? [];
            $lines[] = (($k['is_required'] ?? '0') === '1' ? 'Pflicht' : 'Optional') . ' | ' . ($k['role'] ?? '') . ' | ' . ($k['api_key'] ?? '') . ' | Zuordnung: ' . ($k['assignment_source'] ?? 'auto') . ' | Import: ' . (($api['gateway_import_enabled'] ?? '0') === '1' ? 'ja' : 'nein') . ' | ' . ($api['current_value'] ?? '') . ' | ' . ($api['value_type'] ?? '');
        }
        $d = $devices[$code];
        $detail = 'Device: ' . ($d['name'] ?? '') . "\nCode: " . $code . "\nTyp: " . ($d['device_type'] ?? '') . "\nVertrauen: " . ($d['confidence'] ?? '') . "\nStatus-Key: " . ($d['status_key'] ?? '') . "\nValue-Key: " . ($d['value_key'] ?? '') . "\n\nKeys:\n" . implode("\n", $lines);
        $this->SetValueSafe('SelectedDeviceDetails', $detail);
        return $detail;
    }

    public function PreviewDeviceExportFor(string $code): string
    {
        $code = trim($code);
        if ($code === '') { return 'Keine Device-Zeile ausgewaehlt.'; }
        $devices = $this->IndexBy($this->ReadCsvAssoc('devices'), 'code');
        if (!isset($devices[$code])) { return 'Device nicht gefunden: ' . $code; }
        $keys = array_values(array_filter($this->ReadCsvAssoc('device_keys'), static function ($r) use ($code) { return ($r['device_code'] ?? '') === $code; }));
        $apiKeys = $this->IndexBy($this->ReadCsvAssoc('api_keys'), 'api_key');
        $lines = [];
        foreach ($keys as $k) {
            $apiKey = $k['api_key'] ?? '';
            $api = $apiKeys[$apiKey] ?? [];
            if (($api['gateway_import_enabled'] ?? '0') !== '1') { continue; }
            $explicitName = trim((string) ($api['gateway_variable_name'] ?? ''));
            $suggestedName = trim((string) ($api['suggested_name'] ?? ''));
            $variableName = $explicitName !== '' ? $explicitName : ($suggestedName !== '' ? $suggestedName : $apiKey);
            $source = $explicitName !== '' ? 'benutzerdefiniert' : ($suggestedName !== '' ? 'Vorschlag' : 'API-Key');
            $lines[] = (($k['is_required'] ?? '0') === '1' ? 'Pflicht' : 'Optional') . ' | ' . ($k['role'] ?? '') . ' | ' . $apiKey . ' | Variable: ' . $variableName . ' [' . $source . '] | Typ: ' . ($api['value_type'] ?? '') . ' | Wert: ' . ($api['current_value'] ?? '');
        }
        $d = $devices[$code];
        $preview = 'Gateway Export-Vorschau' . "\nDevice: " . ($d['name'] ?? '') . "\nCode: " . $code . "\nTyp: " . ($d['device_type'] ?? '') . "\nVertrauen: " . ($d['confidence'] ?? '') . "\nAktivierte Variablen: " . count($lines) . "\n\nVariablen:\n" . (count($lines) > 0 ? implode("\n", $lines) : '[keine fuer Gateway-Import aktiviert]');
        $this->SetValueSafe('DeviceExportPreview', $preview);
        return $preview;
    }

    private function DiscoverDeviceRangeFor(string $code, string $statusKey, int $radius): string
    {
        $code = trim($code);
        $statusKey = trim($statusKey);
        if ($code === '') { return 'Keine Device-Zeile ausgewaehlt.'; }
        $devices = $this->IndexBy($this->ReadCsvAssoc('devices'), 'code');
        if (!isset($devices[$code])) { return 'Device nicht gefunden: ' . $code; }
        if (!preg_match('/^(\d+)\.(\d+)\.status$/', $statusKey, $match)) {
            return 'Ungueltiger Status-Key. Erwartet wird z.B. 55.17120.status.';
        }

        $group = (int) $match[1];
        $object = (int) $match[2];
        $suffixes = array_values(array_unique(array_merge(['status', 'value', 'opmode', 'text1', 'text2'], $this->ParseSuffixes($this->ReadPropertyString('ScanSuffixes')))));
        $keys = [];
        for ($candidateObject = max(1, $object - $radius); $candidateObject <= $object + $radius; $candidateObject++) {
            foreach ($suffixes as $suffix) {
                $keys[] = $group . '.' . $candidateObject . '.' . $suffix;
            }
        }

        try {
            $response = $this->ApiGet($keys);
            $data = is_array($response['data'] ?? null) ? $response['data'] : [];
            if (!array_key_exists($statusKey, $data) || $this->CleanString((string) $data[$statusKey]) === '') {
                return 'Status-Key wurde vom PM5 nicht geliefert: ' . $statusKey;
            }

            $now = date('Y-m-d H:i:s');
            $apiKeys = $this->IndexBy($this->ReadCsvAssoc('api_keys'), 'api_key');
            $deviceKeys = $this->ReadCsvAssoc('device_keys');
            $found = 0;
            $assigned = 0;
            $protected = 0;

            foreach ($data as $apiKey => $value) {
                $apiKey = (string) $apiKey;
                $clean = $this->CleanString((string) $value);
                if ($clean === '') { continue; }
                $found++;

                $manualDevice = $this->GetManualAssignmentDevice($apiKey, $deviceKeys);
                if ($manualDevice !== '' && $manualDevice !== $code) {
                    $protected++;
                    continue;
                }

                $existing = $apiKeys[$apiKey] ?? [];
                $apiKeys[$apiKey] = [
                    'api_key' => $apiKey,
                    'current_value' => $clean,
                    'value_type' => $this->DetectValueType($clean),
                    'confidence' => $existing['confidence'] ?? (string) $this->GetConfidence($apiKey),
                    'suggested_name' => ($existing['suggested_name'] ?? '') !== '' ? $existing['suggested_name'] : $this->GetKnownName($apiKey),
                    'is_favorite' => $existing['is_favorite'] ?? '0',
                    'first_seen' => $existing['first_seen'] ?? $now,
                    'last_seen' => $now,
                    'last_scan_id' => $existing['last_scan_id'] ?? '',
                    'comment' => $existing['comment'] ?? '',
                    'gateway_variable_name' => $existing['gateway_variable_name'] ?? '',
                    'gateway_import_enabled' => $existing['gateway_import_enabled'] ?? '0'
                ];

                $deviceKeys = array_values(array_filter($deviceKeys, static function ($row) use ($apiKey) {
                    return ($row['api_key'] ?? '') !== $apiKey;
                }));
                $role = $this->GetRoleForSuffix($this->GetKeySuffix($apiKey));
                $deviceKeys[] = [
                    'device_code' => $code,
                    'api_key' => $apiKey,
                    'role' => $role,
                    'is_required' => in_array($role, ['status', 'value', 'measurement'], true) ? '1' : '0',
                    'direction' => 'read',
                    'assignment_source' => 'manual'
                ];
                $assigned++;
            }

            $device = $devices[$code];
            $device['status_key'] = $statusKey;
            $device['confidence'] = '100';
            $device['last_seen'] = $now;
            $sameObjectValueKey = $group . '.' . $object . '.value';
            if (isset($apiKeys[$sameObjectValueKey])) { $device['value_key'] = $sameObjectValueKey; }
            $devices[$code] = $device;

            $this->WriteCsvAssoc('api_keys', array_values($apiKeys));
            $this->WriteCsvAssoc('devices', array_values($devices));
            $this->WriteCsvAssoc('device_keys', $this->UniqueRows($deviceKeys, ['device_code', 'api_key']));
            $this->UpdateBrowserFormList($this->BuildBrowserRows());
            $this->UpdateDeviceFormList($this->BuildDeviceRows());
            $this->SetValueSafe('ScanSummary', $this->BuildScanSummary());

            $scope = $radius === 0 ? 'Device-Familie' : 'Device-Nachbarschaft Radius ' . $radius;
            return $scope . ' abgeschlossen: ' . $code . ' | Status-Key: ' . $statusKey . ' | getestet: ' . count($keys) . ' | gefunden: ' . $found . ' | zugeordnet: ' . $assigned . ' | geschuetzt: ' . $protected . '.';
        } catch (Throwable $e) {
            return 'Device-Familienfehler: ' . $e->getMessage();
        }
    }

    private function GetManualAssignmentDevice(string $apiKey, array $deviceKeys): string
    {
        foreach ($deviceKeys as $row) {
            if (($row['api_key'] ?? '') === $apiKey && ($row['assignment_source'] ?? 'auto') === 'manual') {
                return (string) ($row['device_code'] ?? '');
            }
        }
        return '';
    }

    private function GetRoleForSuffix(string $suffix): string
    {
        if ($suffix === 'status') { return 'status'; }
        if ($suffix === 'value') { return 'value'; }
        if ($suffix === 'opmode') { return 'opmode'; }
        if (strpos($suffix, 'text') === 0) { return 'info'; }
        return 'info';
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
        $this->RegisterVariableString('BrowserSummary', 'Browser Zusammenfassung', '', 300);
        $this->RegisterVariableString('SelectedApiKeyDetails', 'API-Key Details', '', 310);
        $this->RegisterVariableString('SelectedDeviceDetails', 'Device Details', '', 410);
        $this->RegisterVariableString('DeviceExportPreview', 'Device Export Vorschau', '', 420);
    }

    private function GetApiKeyListDefinition(): array
    {
        return ['type' => 'List', 'name' => 'BrowserList', 'caption' => 'API-Key Browser 2.0', 'rowCount' => 16, 'add' => false, 'delete' => false, 'loadValuesFromConfiguration' => false,
            'columns' => [
                ['name' => 'favorite', 'caption' => 'Fav', 'width' => '45px', 'add' => '', 'edit' => false],
                ['name' => 'gateway_import', 'caption' => 'Import', 'width' => '60px', 'add' => '', 'edit' => false, 'quickFilter' => true],
                ['name' => 'confidence', 'caption' => 'Vertrauen', 'width' => '80px', 'add' => '', 'edit' => false, 'quickFilter' => true],
                ['name' => 'api_key', 'caption' => 'API-Key', 'width' => '170px', 'add' => '', 'edit' => false, 'quickFilter' => true],
                ['name' => 'suggested_name', 'caption' => 'Name', 'width' => '140px', 'add' => '', 'edit' => false, 'quickFilter' => true],
                ['name' => 'gateway_variable_name', 'caption' => 'Gateway-Name', 'width' => '160px', 'add' => '', 'edit' => false, 'quickFilter' => true],
                ['name' => 'current_value', 'caption' => 'Wert', 'width' => '140px', 'add' => '', 'edit' => false, 'quickFilter' => true],
                ['name' => 'value_type', 'caption' => 'Typ', 'width' => '100px', 'add' => '', 'edit' => false, 'quickFilter' => true],
                ['name' => 'device', 'caption' => 'Device', 'width' => '120px', 'add' => '', 'edit' => false, 'quickFilter' => true],
                ['name' => 'assignment_source', 'caption' => 'Zuordnung', 'width' => '80px', 'add' => '', 'edit' => false, 'quickFilter' => true],
                ['name' => 'observations', 'caption' => 'Obs', 'width' => '60px', 'add' => '', 'edit' => false],
                ['name' => 'first_seen', 'caption' => 'Erst gesehen', 'width' => '140px', 'add' => '', 'edit' => false],
                ['name' => 'last_seen', 'caption' => 'Zuletzt', 'width' => '140px', 'add' => '', 'edit' => false],
                ['name' => 'comment', 'caption' => 'Kommentar', 'width' => '190px', 'add' => '', 'edit' => false, 'quickFilter' => true]
            ], 'values' => $this->BuildBrowserRowsSafe()];
    }

    private function GetDeviceListDefinition(): array
    {
        return ['type' => 'List', 'name' => 'DeviceList', 'caption' => 'Device Browser', 'rowCount' => 10, 'add' => false, 'delete' => false, 'loadValuesFromConfiguration' => false,
            'columns' => [
                ['name' => 'code', 'caption' => 'Code', 'width' => '130px', 'add' => '', 'edit' => false],
                ['name' => 'name', 'caption' => 'Name', 'width' => '160px', 'add' => '', 'edit' => false],
                ['name' => 'device_type', 'caption' => 'Typ', 'width' => '130px', 'add' => '', 'edit' => false],
                ['name' => 'confidence', 'caption' => 'Vertrauen', 'width' => '85px', 'add' => '', 'edit' => false],
                ['name' => 'key_count', 'caption' => 'Keys', 'width' => '60px', 'add' => '', 'edit' => false],
                ['name' => 'import_count', 'caption' => 'Import', 'width' => '60px', 'add' => '', 'edit' => false],
                ['name' => 'status_key', 'caption' => 'Status-Key', 'width' => '170px', 'add' => '', 'edit' => false]
            ], 'values' => $this->BuildDeviceRowsSafe()];
    }

    private function BuildBrowserRowsSafe(): array { try { return $this->BuildBrowserRows(); } catch (Throwable $e) { return []; } }
    private function BuildDeviceRowsSafe(): array { try { return $this->BuildDeviceRows(); } catch (Throwable $e) { return []; } }

    private function BuildBrowserRows(): array
    {
        $counts = $this->GetObservationCounts();
        $deviceByKey = $this->GetDeviceByKeyMap();
        $sourceByKey = $this->GetAssignmentSourceByKeyMap();
        $rows = [];
        foreach ($this->ReadCsvAssoc('api_keys') as $r) {
            $key = $r['api_key'] ?? '';
            $rows[] = [
                'favorite' => (($r['is_favorite'] ?? '0') === '1') ? 'ja' : '',
                'gateway_import' => (($r['gateway_import_enabled'] ?? '0') === '1') ? 'ja' : '',
                'confidence' => $r['confidence'] ?? '',
                'api_key' => $key,
                'suggested_name' => $r['suggested_name'] ?? '',
                'gateway_variable_name' => $r['gateway_variable_name'] ?? '',
                'current_value' => $r['current_value'] ?? '',
                'value_type' => $r['value_type'] ?? '',
                'device' => $deviceByKey[$key] ?? '',
                'assignment_source' => $sourceByKey[$key] ?? '',
                'observations' => (string) ($counts[$key] ?? 0),
                'first_seen' => $r['first_seen'] ?? '',
                'last_seen' => $r['last_seen'] ?? '',
                'comment' => $r['comment'] ?? ''
            ];
        }
        usort($rows, static function ($a, $b) { return strcmp($b['last_seen'], $a['last_seen']); });
        return array_slice($rows, 0, 250);
    }

    private function BuildDeviceRows(): array
    {
        $counts = [];
        $importCounts = [];
        $apiKeys = $this->IndexBy($this->ReadCsvAssoc('api_keys'), 'api_key');
        foreach ($this->ReadCsvAssoc('device_keys') as $dk) {
            $code = $dk['device_code'] ?? '';
            $apiKey = $dk['api_key'] ?? '';
            if ($code === '') { continue; }
            $counts[$code] = ($counts[$code] ?? 0) + 1;
            if (($apiKeys[$apiKey]['gateway_import_enabled'] ?? '0') === '1') { $importCounts[$code] = ($importCounts[$code] ?? 0) + 1; }
        }
        $rows = [];
        foreach ($this->ReadCsvAssoc('devices') as $d) {
            $code = $d['code'] ?? '';
            $rows[] = ['code' => $code, 'name' => $d['name'] ?? '', 'device_type' => $d['device_type'] ?? '', 'confidence' => $d['confidence'] ?? '', 'key_count' => (string) ($counts[$code] ?? 0), 'import_count' => (string) ($importCounts[$code] ?? 0), 'status_key' => $d['status_key'] ?? ''];
        }
        return $rows;
    }

    private function BuildScanSummary(): string
    {
        return 'Scans: ' . count($this->ReadCsvAssoc('scans')) . ', API-Keys: ' . count($this->ReadCsvAssoc('api_keys')) . ', Beobachtungen: ' . count($this->ReadCsvAssoc('observations')) . ', Devices: ' . count($this->ReadCsvAssoc('devices'));
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
            if (!is_file($path)) { $this->WriteRawCsv($path, [$header]); } else { $this->EnsureCsvHeader($name); }
        }
        $this->WriteCsvAssoc('meta', [['key' => 'schema_version', 'value' => (string) self::SCHEMA_VERSION], ['key' => 'version', 'value' => '0.2.0-alpha']]);
    }

    private function EnsureCsvHeader(string $name): void
    {
        $path = $this->GetCsvPath($name);
        $handle = fopen($path, 'rb');
        if ($handle === false) { throw new Exception('CSV konnte nicht gelesen werden: ' . $path); }
        $oldHeader = fgetcsv($handle, 0, ';');
        fclose($handle);
        if (!is_array($oldHeader)) { $oldHeader = []; }
        if (count(array_diff(self::CSV_FILES[$name], $oldHeader)) === 0) { return; }
        $rows = $this->ReadCsvAssoc($name);
        $this->WriteCsvAssoc($name, $rows);
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
        foreach ($rows as $row) { $data[] = array_map(static function ($key) use ($row) { return (string) ($row[$key] ?? ''); }, $header); }
        $this->WriteRawCsv($this->GetCsvPath($name), $data);
    }

    private function AppendCsvAssoc(string $name, array $rows): void
    {
        if (count($rows) === 0) { return; }
        $path = $this->GetCsvPath($name);
        $handle = fopen($path, 'ab');
        if ($handle === false) { throw new Exception('CSV konnte nicht geschrieben werden: ' . $path); }
        flock($handle, LOCK_EX);
        foreach ($rows as $row) { $line = []; foreach (self::CSV_FILES[$name] as $key) { $line[] = (string) ($row[$key] ?? ''); } fputcsv($handle, $line, ';'); }
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
        foreach ($this->ReadCsvAssoc('scans') as $scan) { $max = max($max, (int) ($scan['scan_id'] ?? 0)); }
        return $max + 1;
    }

    private function ClassifyDevice(string $key, string $now, array &$devices, array &$deviceKeys): void
    {
        if ($this->HasManualAssignment($key, $deviceKeys)) { return; }
        if (strpos($key, '55.17106.') === 0) { $this->EnsureDevice('filter_pump', 'Filterpumpe', 'actuator', $key, $this->GetKeySuffix($key), $now, $devices, $deviceKeys); }
        if (strpos($key, '55.17102.') === 0) { $this->EnsureDevice('pool_light', 'Poollicht', 'actuator', $key, $this->GetKeySuffix($key), $now, $devices, $deviceKeys); }
        if (strpos($key, '34.') === 0) { $this->EnsureDevice('water_values', 'Wasserwerte', 'sensor_group', $key, 'measurement', $now, $devices, $deviceKeys); }
        if (strpos($key, '13.') === 0) { $this->EnsureDevice('system_values', 'Systemwerte', 'system_group', $key, 'info', $now, $devices, $deviceKeys); }
    }

    private function HasManualAssignment(string $key, array $deviceKeys): bool
    {
        foreach ($deviceKeys as $row) {
            if (($row['api_key'] ?? '') === $key && ($row['assignment_source'] ?? 'auto') === 'manual') { return true; }
        }
        return false;
    }

    private function EnsureDevice(string $code, string $name, string $type, string $apiKey, string $role, string $now, array &$devices, array &$deviceKeys): void
    {
        $existing = $devices[$code] ?? [];
        $devices[$code] = ['code' => $code, 'name' => $name, 'device_type' => $type, 'confidence' => '80', 'status_key' => $role === 'status' ? $apiKey : ($existing['status_key'] ?? ''), 'value_key' => ($role === 'value' || $role === 'measurement') ? $apiKey : ($existing['value_key'] ?? ''), 'first_seen' => $existing['first_seen'] ?? $now, 'last_seen' => $now];
        $deviceKeys[] = ['device_code' => $code, 'api_key' => $apiKey, 'role' => $role, 'is_required' => in_array($role, ['status', 'value', 'measurement'], true) ? '1' : '0', 'direction' => 'read', 'assignment_source' => 'auto'];
    }

    private function ApiGet(array $keys): array
    {
        $host = trim($this->ReadPropertyString('Host'));
        if ($host === '') { throw new Exception('Host ist leer.'); }
        $url = 'http://' . $host . ':' . max(1, min(65535, $this->ReadPropertyInteger('Port'))) . '/cgi-bin/webgui.fcgi?sid=' . rawurlencode($this->CreateSid());
        $payload = json_encode(['get' => array_values($keys)]);
        if ($payload === false) { throw new Exception('JSON-Encoding fehlgeschlagen.'); }
        $context = stream_context_create(['http' => ['method' => 'POST', 'header' => "Content-Type: application/json;charset=UTF-8\r\nAccept: application/json\r\n", 'content' => $payload, 'timeout' => max(1, $this->ReadPropertyInteger('Timeout')), 'ignore_errors' => true]]);
        $started = microtime(true);
        $raw = @file_get_contents($url, false, $context);
        $durationMs = (int) round((microtime(true) - $started) * 1000);
        if ($raw === false) { throw new Exception('HTTP request failed.'); }
        $json = json_decode((string) $raw, true);
        if (!is_array($json)) { throw new Exception('Ungueltige JSON-Antwort.'); }
        if ((int) ($json['status']['code'] ?? -1) !== 0) { throw new Exception('API Status ' . (int) ($json['status']['code'] ?? -1)); }
        $json['_meta'] = ['duration_ms' => $durationMs];
        return $json;
    }

    private function BuildScanKeys(): array
    {
        $keys = [];
        $suffixes = $this->ParseSuffixes($this->ReadPropertyString('ScanSuffixes'));
        $max = max(1, min(5000, $this->ReadPropertyInteger('ScanMaxKeys')));
        $gs = max(1, $this->ReadPropertyInteger('ScanGroupStart'));
        $ge = max($gs, $this->ReadPropertyInteger('ScanGroupEnd'));
        $os = max(1, $this->ReadPropertyInteger('ScanObjectStart'));
        $oe = max($os, $this->ReadPropertyInteger('ScanObjectEnd'));
        for ($g = $gs; $g <= $ge; $g++) { for ($o = $os; $o <= $oe; $o++) { foreach ($suffixes as $s) { $keys[] = $g . '.' . $o . '.' . $s; if (count($keys) >= $max) { return $keys; } } } }
        return $keys;
    }

    private function GetObservationCounts(): array { $counts = []; foreach ($this->ReadCsvAssoc('observations') as $o) { $key = $o['api_key'] ?? ''; if ($key !== '') { $counts[$key] = ($counts[$key] ?? 0) + 1; } } return $counts; }

    private function GetDeviceByKeyMap(): array
    {
        $map = [];
        $source = [];
        foreach ($this->ReadCsvAssoc('device_keys') as $dk) {
            $key = $dk['api_key'] ?? '';
            if ($key === '') { continue; }
            $rowSource = $dk['assignment_source'] ?? 'auto';
            if (!isset($map[$key]) || $rowSource === 'manual' || ($source[$key] ?? '') !== 'manual') {
                $map[$key] = $dk['device_code'] ?? '';
                $source[$key] = $rowSource;
            }
        }
        return $map;
    }

    private function GetAssignmentSourceByKeyMap(): array
    {
        $map = [];
        foreach ($this->ReadCsvAssoc('device_keys') as $dk) {
            $key = $dk['api_key'] ?? '';
            if ($key === '') { continue; }
            $source = $dk['assignment_source'] ?? 'auto';
            if (!isset($map[$key]) || $source === 'manual') { $map[$key] = $source; }
        }
        return $map;
    }

    private function GetAssignmentSourceForApiKey(string $key): string { $map = $this->GetAssignmentSourceByKeyMap(); return $map[$key] ?? ''; }
    private function GetDeviceForApiKey(string $key): string { $map = $this->GetDeviceByKeyMap(); return $map[$key] ?? ''; }
    private function ParseSuffixes(string $raw): array { $raw = str_replace(["\r\n", "\r", ',', ';'], "\n", $raw); $result = []; foreach (explode("\n", $raw) as $line) { $s = trim($line); if ($s !== '' && preg_match('/^[A-Za-z0-9_]+$/', $s)) { $result[$s] = $s; } } return array_values($result ?: ['value']); }
    private function DetectValueType(string $value): string { $n = str_replace(',', '.', $value); if ($value === '0' || $value === '1') { return 'boolean-candidate'; } return is_numeric($n) ? (strpos($n, '.') === false ? 'integer' : 'float') : 'string'; }
    private function GetConfidence(string $key): int { return in_array($key, ['34.4001.value', '34.4022.value', '34.4033.value', '13.16507.text2', '13.16509.text1', '55.17102.status', '55.17102.value', '55.17106.status', '55.17106.opmode', '55.17106.value'], true) ? 100 : 60; }
    private function GetKnownName(string $key): string { $n = ['34.4001.value' => 'pH', '34.4022.value' => 'Redox', '34.4033.value' => 'Pooltemperatur', '13.16507.text2' => 'Aussentemperatur T3', '13.16509.text1' => 'Leitfaehigkeit', '55.17106.status' => 'Filterpumpe Status', '55.17106.opmode' => 'Filterpumpe Betriebsart', '55.17106.value' => 'Filterpumpe Text', '55.17102.status' => 'Poollicht Status', '55.17102.value' => 'Poollicht Text']; return $n[$key] ?? ''; }
    private function GetKeySuffix(string $key): string { $p = explode('.', $key); return (string) (end($p) ?: ''); }
    private function CleanString(string $value): string { return trim(html_entity_decode(strip_tags($value), ENT_QUOTES | ENT_HTML5, 'UTF-8')); }
    private function CreateSid(): string { return 'SYMBAYROL' . substr(strtoupper(md5((string) microtime(true) . mt_rand())), 0, 23); }
    private function IndexBy(array $rows, string $key): array { $out = []; foreach ($rows as $row) { if (($row[$key] ?? '') !== '') { $out[$row[$key]] = $row; } } return $out; }
    private function UniqueRows(array $rows, array $keys): array { $seen = []; $out = []; foreach ($rows as $row) { $parts = []; foreach ($keys as $key) { $parts[] = $row[$key] ?? ''; } $hash = implode('|', $parts); if (!isset($seen[$hash])) { $seen[$hash] = true; $out[] = $row; } } return $out; }
    private function GetStorageDirectory(): string { return rtrim(IPS_GetKernelDir(), DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . 'user' . DIRECTORY_SEPARATOR . 'BayrolDiscovery'; }
    private function GetCsvPath(string $name): string { return $this->GetStorageDirectory() . DIRECTORY_SEPARATOR . $name . '.csv'; }
    private function SetValueSafe(string $ident, $value): void { $id = @$this->GetIDForIdent($ident); if ($id !== false && $id > 0) { SetValue($id, $value); } }
}
