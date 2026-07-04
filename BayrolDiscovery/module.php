<?php

declare(strict_types=1);

class BayrolDiscovery extends IPSModule
{
    private const STATUS_ACTIVE = 102;
    private const STATUS_SQLITE_MISSING = 201;
    private const STATUS_DATABASE_ERROR = 202;
    private const STATUS_API_ERROR = 203;
    private const SCHEMA_VERSION = 3;

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
        $this->RegisterPropertyString('BrowserTypeFilter', '');
        $this->RegisterPropertyInteger('BrowserMinConfidence', 0);
        $this->RegisterPropertyBoolean('BrowserFavoritesOnly', false);
        $this->RegisterPropertyString('SelectedApiKey', '');
        $this->RegisterPropertyString('DeviceSearch', '');
        $this->RegisterPropertyString('DeviceTypeFilter', '');
        $this->RegisterPropertyString('SelectedDeviceCode', '');
    }

    public function ApplyChanges()
    {
        parent::ApplyChanges();
        $this->RegisterVariables();
        if (!$this->IsSqliteAvailable()) {
            $this->SetValueSafe('DatabaseReady', false);
            $this->SetValueSafe('DatabaseStatus', 'PDO SQLite ist nicht verfuegbar. Bitte pdo_sqlite fuer die Symcon-PHP-Umgebung aktivieren.');
            $this->SetStatus(self::STATUS_SQLITE_MISSING);
            return;
        }
        try {
            $this->InitializeDatabase();
            $this->SetValueSafe('DatabaseReady', true);
            $this->SetValueSafe('DatabaseStatus', 'SQLite Datenbank bereit. Schema v' . self::SCHEMA_VERSION);
            $this->SetValueSafe('DatabasePath', $this->GetDatabasePath());
            $this->SetValueSafe('DatabaseSchemaVersion', self::SCHEMA_VERSION);
            $this->SetStatus(self::STATUS_ACTIVE);
        } catch (Throwable $e) {
            $this->SetValueSafe('DatabaseReady', false);
            $this->SetValueSafe('DatabaseStatus', $e->getMessage());
            $this->SetStatus(self::STATUS_DATABASE_ERROR);
        }
    }

    public function GetConfigurationForm()
    {
        $sqliteHint = $this->IsSqliteAvailable()
            ? 'SQLite ist verfuegbar. Datenbankpfad: ' . $this->GetDatabasePath()
            : 'PDO SQLite ist nicht verfuegbar. Die Konfigurationsform wird geladen, Scanner und Browser bleiben bis zur Aktivierung von pdo_sqlite deaktiviert.';

        return json_encode([
            'elements' => [
                ['type' => 'ValidationTextBox', 'name' => 'Host', 'caption' => 'PoolManager IP / Host'],
                ['type' => 'NumberSpinner', 'name' => 'Port', 'caption' => 'Port'],
                ['type' => 'NumberSpinner', 'name' => 'Timeout', 'caption' => 'HTTP Timeout in Sekunden'],
                ['type' => 'CheckBox', 'name' => 'DebugMode', 'caption' => 'Erweiterte Debug-Ausgaben'],
                ['type' => 'CheckBox', 'name' => 'KeepDatabaseOnDelete', 'caption' => 'Datenbank bei Instanzloeschung behalten'],
                ['type' => 'Label', 'caption' => 'Scanner: rein lesende JSON-POST get-Abfragen. Keine Variablenanlage, keine Schreibbefehle.'],
                ['type' => 'NumberSpinner', 'name' => 'ScanGroupStart', 'caption' => 'Scan Gruppe von'],
                ['type' => 'NumberSpinner', 'name' => 'ScanGroupEnd', 'caption' => 'Scan Gruppe bis'],
                ['type' => 'NumberSpinner', 'name' => 'ScanObjectStart', 'caption' => 'Scan Objekt-ID von'],
                ['type' => 'NumberSpinner', 'name' => 'ScanObjectEnd', 'caption' => 'Scan Objekt-ID bis'],
                ['type' => 'ValidationTextBox', 'name' => 'ScanSuffixes', 'caption' => 'Scan Suffixe, getrennt durch Semikolon oder Komma'],
                ['type' => 'NumberSpinner', 'name' => 'ScanMaxKeys', 'caption' => 'Maximale Keys pro Scan'],
                ['type' => 'NumberSpinner', 'name' => 'ScanBatchSize', 'caption' => 'Batchgroesse'],
                ['type' => 'Label', 'caption' => 'API-Key Browser'],
                ['type' => 'ValidationTextBox', 'name' => 'BrowserSearch', 'caption' => 'Suche API-Key, Name, Wert'],
                ['type' => 'ValidationTextBox', 'name' => 'BrowserTypeFilter', 'caption' => 'Typfilter'],
                ['type' => 'NumberSpinner', 'name' => 'BrowserMinConfidence', 'caption' => 'Minimales Vertrauen'],
                ['type' => 'CheckBox', 'name' => 'BrowserFavoritesOnly', 'caption' => 'Nur Favoriten anzeigen'],
                ['type' => 'ValidationTextBox', 'name' => 'SelectedApiKey', 'caption' => 'Ausgewaehlter API-Key fuer Details/Favorit'],
                ['type' => 'Label', 'caption' => 'Device Browser'],
                ['type' => 'ValidationTextBox', 'name' => 'DeviceSearch', 'caption' => 'Device-Suche'],
                ['type' => 'ValidationTextBox', 'name' => 'DeviceTypeFilter', 'caption' => 'Device-Typfilter'],
                ['type' => 'ValidationTextBox', 'name' => 'SelectedDeviceCode', 'caption' => 'Ausgewaehlter Device-Code fuer Details']
            ],
            'actions' => [
                ['type' => 'Label', 'caption' => $sqliteHint],
                ['type' => 'Button', 'caption' => 'Datenbank pruefen', 'onClick' => 'echo BPD_CheckDatabase($id);'],
                ['type' => 'Button', 'caption' => 'Verbindung testen', 'onClick' => 'echo BPD_TestConnection($id);'],
                ['type' => 'Button', 'caption' => 'Scan starten', 'onClick' => 'echo BPD_RunScan($id);'],
                ['type' => 'Button', 'caption' => 'Scan-Zusammenfassung laden', 'onClick' => 'echo BPD_GetScanSummary($id);'],
                ['type' => 'Button', 'caption' => 'Browser laden', 'onClick' => 'echo BPD_LoadBrowser($id);'],
                ['type' => 'Button', 'caption' => 'API-Key Details laden', 'onClick' => 'echo BPD_LoadApiKeyDetails($id);'],
                ['type' => 'Button', 'caption' => 'Favorit umschalten', 'onClick' => 'echo BPD_ToggleFavorite($id);'],
                $this->GetApiKeyListDefinition(),
                ['type' => 'Button', 'caption' => 'Devices neu berechnen', 'onClick' => 'echo BPD_RecalculateDevices($id);'],
                ['type' => 'Button', 'caption' => 'Device Browser laden', 'onClick' => 'echo BPD_LoadDevices($id);'],
                ['type' => 'Button', 'caption' => 'Device Details laden', 'onClick' => 'echo BPD_LoadDeviceDetails($id);'],
                $this->GetDeviceListDefinition()
            ],
            'status' => [
                ['code' => self::STATUS_ACTIVE, 'icon' => 'active', 'caption' => 'SQLite Datenbank bereit'],
                ['code' => self::STATUS_SQLITE_MISSING, 'icon' => 'error', 'caption' => 'PDO SQLite fehlt'],
                ['code' => self::STATUS_DATABASE_ERROR, 'icon' => 'error', 'caption' => 'Datenbankfehler'],
                ['code' => self::STATUS_API_ERROR, 'icon' => 'error', 'caption' => 'PM5 API Fehler']
            ]
        ]);
    }

    public function CheckDatabase(): string
    {
        if (!$this->IsSqliteAvailable()) {
            $this->SetStatus(self::STATUS_SQLITE_MISSING);
            return 'Fehler: PDO SQLite ist nicht verfuegbar.';
        }
        try {
            $this->InitializeDatabase();
            $pdo = $this->OpenDatabase();
            $message = 'Datenbank OK. Tabellen: ' . (int)$pdo->query("SELECT COUNT(*) FROM sqlite_master WHERE type='table'")->fetchColumn()
                . ', API-Keys: ' . (int)$pdo->query('SELECT COUNT(*) FROM api_keys')->fetchColumn()
                . ', Scans: ' . (int)$pdo->query('SELECT COUNT(*) FROM scans')->fetchColumn()
                . ', Tags: ' . (int)$pdo->query('SELECT COUNT(*) FROM tags')->fetchColumn()
                . ', Devices: ' . (int)$pdo->query('SELECT COUNT(*) FROM devices')->fetchColumn()
                . ', Schema: v' . self::SCHEMA_VERSION;
            $this->SetValueSafe('DatabaseStatus', $message);
            $this->SetStatus(self::STATUS_ACTIVE);
            return $message;
        } catch (Throwable $e) {
            $this->SetValueSafe('DatabaseStatus', $e->getMessage());
            $this->SetStatus(self::STATUS_DATABASE_ERROR);
            return 'Datenbankfehler: ' . $e->getMessage();
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
        if (!$this->IsSqliteAvailable()) {
            $this->SetStatus(self::STATUS_SQLITE_MISSING);
            return 'Scan nicht moeglich: PDO SQLite ist nicht verfuegbar.';
        }
        try {
            $this->InitializeDatabase();
            $keys = $this->BuildScanKeys();
            $pdo = $this->OpenDatabase();
            $started = date('Y-m-d H:i:s');
            $scanId = $this->CreateScanRow($pdo, $started, count($keys));
            $chunks = array_chunk($keys, max(1, min(100, $this->ReadPropertyInteger('ScanBatchSize'))));
            $found = 0;
            $duration = 0;
            foreach ($chunks as $chunk) {
                $response = $this->ApiGet($chunk);
                $duration += (int)($response['_meta']['duration_ms'] ?? 0);
                foreach (($response['data'] ?? []) as $key => $value) {
                    $clean = $this->CleanString((string)$value);
                    if ($clean === '') { continue; }
                    $this->StoreObservation($pdo, $scanId, (string)$key, $clean, $started);
                    $found++;
                }
            }
            $finished = date('Y-m-d H:i:s');
            $pdo->prepare('UPDATE scans SET finished_at=:finished, found_keys=:found, duration_ms=:duration WHERE id=:id')->execute([':finished' => $finished, ':found' => $found, ':duration' => $duration, ':id' => $scanId]);
            $this->RecalculateAllDevices($pdo);
            $this->SetValueSafe('LastScanId', $scanId);
            $this->SetValueSafe('LastScanStarted', $started);
            $this->SetValueSafe('LastScanFinished', $finished);
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
        if (!$this->IsSqliteAvailable()) { return 'Keine Zusammenfassung: PDO SQLite ist nicht verfuegbar.'; }
        try {
            $pdo = $this->OpenDatabase();
            $message = 'Scans: ' . (int)$pdo->query('SELECT COUNT(*) FROM scans')->fetchColumn()
                . ', API-Keys: ' . (int)$pdo->query('SELECT COUNT(*) FROM api_keys')->fetchColumn()
                . ', Beobachtungen: ' . (int)$pdo->query('SELECT COUNT(*) FROM observations')->fetchColumn()
                . ', Devices: ' . (int)$pdo->query('SELECT COUNT(*) FROM devices')->fetchColumn();
            $this->SetValueSafe('ScanSummary', $message);
            return $message;
        } catch (Throwable $e) { return 'Zusammenfassungsfehler: ' . $e->getMessage(); }
    }

    public function LoadBrowser(): string
    {
        if (!$this->IsSqliteAvailable()) { return 'Browser nicht moeglich: PDO SQLite ist nicht verfuegbar.'; }
        $rows = $this->BuildBrowserRows();
        $this->UpdateBrowserFormList($rows);
        return 'Browser geladen. Angezeigte Zeilen: ' . count($rows);
    }

    public function LoadDevices(): string
    {
        if (!$this->IsSqliteAvailable()) { return 'Device Browser nicht moeglich: PDO SQLite ist nicht verfuegbar.'; }
        $rows = $this->BuildDeviceRows();
        $this->UpdateDeviceFormList($rows);
        return 'Device Browser geladen. Angezeigte Devices: ' . count($rows);
    }

    public function RecalculateDevices(): string
    {
        if (!$this->IsSqliteAvailable()) { return 'Neuberechnung nicht moeglich: PDO SQLite ist nicht verfuegbar.'; }
        try { $pdo = $this->OpenDatabase(); $this->RecalculateAllDevices($pdo); $this->UpdateDeviceFormList($this->BuildDeviceRows()); return 'Devices neu berechnet.'; } catch (Throwable $e) { return 'Device-Rechenfehler: ' . $e->getMessage(); }
    }

    public function LoadDeviceDetails(): string
    {
        if (!$this->IsSqliteAvailable()) { return 'Device Details nicht moeglich: PDO SQLite ist nicht verfuegbar.'; }
        $code = trim($this->ReadPropertyString('SelectedDeviceCode'));
        if ($code === '') { return 'Kein Device-Code ausgewaehlt.'; }
        try {
            $pdo = $this->OpenDatabase();
            $stmt = $pdo->prepare('SELECT * FROM devices WHERE code=:code');
            $stmt->execute([':code' => $code]);
            $device = $stmt->fetch();
            if (!is_array($device)) { return 'Device nicht gefunden: ' . $code; }
            $keys = $pdo->prepare('SELECT dk.role, dk.is_required, dk.direction, k.api_key, k.current_value, k.value_type, k.confidence FROM device_keys dk INNER JOIN api_keys k ON k.api_key=dk.api_key WHERE dk.device_id=:id ORDER BY dk.is_required DESC, dk.role, k.api_key');
            $keys->execute([':id' => (int)$device['id']]);
            $lines = [];
            foreach ($keys->fetchAll() as $row) { $lines[] = ($row['is_required'] ? 'Pflicht' : 'Optional') . ' | ' . $row['role'] . ' | ' . $row['api_key'] . ' | ' . $row['current_value'] . ' | ' . $row['value_type']; }
            $detail = 'Device: ' . $device['name'] . "\nCode: " . $device['code'] . "\nTyp: " . ($device['device_type'] ?? '') . "\nKategorie: " . ($device['category'] ?? '') . "\nVertrauen: " . ($device['confidence'] ?? '') . "\nStatus-Key: " . ($device['status_key'] ?? '') . "\nValue-Key: " . ($device['value_key'] ?? '') . "\n\nKeys:\n" . implode("\n", $lines);
            $this->SetValueSafe('SelectedDeviceDetails', $detail);
            return $detail;
        } catch (Throwable $e) { return 'Device-Detail-Fehler: ' . $e->getMessage(); }
    }

    public function LoadApiKeyDetails(): string
    {
        if (!$this->IsSqliteAvailable()) { return 'API-Key Details nicht moeglich: PDO SQLite ist nicht verfuegbar.'; }
        $key = trim($this->ReadPropertyString('SelectedApiKey'));
        if ($key === '') { return 'Kein API-Key ausgewaehlt.'; }
        try {
            $pdo = $this->OpenDatabase();
            $stmt = $pdo->prepare('SELECT * FROM api_keys WHERE api_key=:key');
            $stmt->execute([':key' => $key]);
            $row = $stmt->fetch();
            if (!is_array($row)) { return 'API-Key nicht gefunden: ' . $key; }
            $detail = 'API-Key: ' . $row['api_key'] . "\nName: " . ($row['suggested_name'] ?? '') . "\nWert: " . ($row['current_value'] ?? '') . "\nTyp: " . ($row['value_type'] ?? '') . "\nVertrauen: " . ($row['confidence'] ?? '') . "\nFavorit: " . ((int)$row['is_favorite'] === 1 ? 'ja' : 'nein') . "\nErst gesehen: " . ($row['first_seen'] ?? '') . "\nZuletzt gesehen: " . ($row['last_seen'] ?? '');
            $this->SetValueSafe('SelectedApiKeyDetails', $detail);
            return $detail;
        } catch (Throwable $e) { return 'Detail-Fehler: ' . $e->getMessage(); }
    }

    public function ToggleFavorite(): string
    {
        if (!$this->IsSqliteAvailable()) { return 'Favorit nicht moeglich: PDO SQLite ist nicht verfuegbar.'; }
        $key = trim($this->ReadPropertyString('SelectedApiKey'));
        if ($key === '') { return 'Kein API-Key ausgewaehlt.'; }
        $pdo = $this->OpenDatabase();
        $stmt = $pdo->prepare('UPDATE api_keys SET is_favorite = CASE WHEN is_favorite=1 THEN 0 ELSE 1 END WHERE api_key=:key');
        $stmt->execute([':key' => $key]);
        $this->UpdateBrowserFormList($this->BuildBrowserRows());
        return $stmt->rowCount() === 0 ? 'API-Key nicht gefunden: ' . $key : 'Favorit umgeschaltet: ' . $key;
    }

    private function RegisterVariables(): void
    {
        $this->RegisterVariableBoolean('DatabaseReady', 'Datenbank bereit', '~Switch', 10);
        $this->RegisterVariableString('DatabaseStatus', 'Datenbank Status', '', 20);
        $this->RegisterVariableString('DatabasePath', 'Datenbank Pfad', '', 30);
        $this->RegisterVariableInteger('DatabaseSchemaVersion', 'Datenbank Schema Version', '', 40);
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
            ['name' => 'value_type', 'caption' => 'Typ', 'width' => '120px', 'add' => '', 'edit' => false],
            ['name' => 'device', 'caption' => 'Device', 'width' => '130px', 'add' => '', 'edit' => false]
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

    private function BuildBrowserRowsSafe(): array { try { return $this->IsSqliteAvailable() ? $this->BuildBrowserRows() : []; } catch (Throwable $e) { return []; } }
    private function BuildDeviceRowsSafe(): array { try { return $this->IsSqliteAvailable() ? $this->BuildDeviceRows() : []; } catch (Throwable $e) { return []; } }

    private function BuildBrowserRows(): array
    {
        $pdo = $this->OpenDatabase();
        $rows = [];
        $sql = 'SELECT k.*, d.name AS device_name FROM api_keys k LEFT JOIN device_keys dk ON dk.api_key=k.api_key LEFT JOIN devices d ON d.id=dk.device_id ORDER BY k.last_seen DESC LIMIT 200';
        foreach ($pdo->query($sql)->fetchAll() as $row) {
            $rows[] = ['favorite' => ((int)$row['is_favorite'] === 1) ? 'ja' : '', 'confidence' => (string)$row['confidence'], 'api_key' => (string)$row['api_key'], 'suggested_name' => (string)($row['suggested_name'] ?? ''), 'current_value' => (string)($row['current_value'] ?? ''), 'value_type' => (string)($row['value_type'] ?? ''), 'device' => (string)($row['device_name'] ?? '')];
        }
        return $rows;
    }

    private function BuildDeviceRows(): array
    {
        $pdo = $this->OpenDatabase();
        $rows = [];
        $sql = 'SELECT d.*, COUNT(dk.api_key) AS key_count FROM devices d LEFT JOIN device_keys dk ON dk.device_id=d.id GROUP BY d.id ORDER BY d.confidence DESC, d.name ASC LIMIT 100';
        foreach ($pdo->query($sql)->fetchAll() as $row) {
            $rows[] = ['code' => (string)$row['code'], 'name' => (string)$row['name'], 'device_type' => (string)($row['device_type'] ?? ''), 'confidence' => (string)($row['confidence'] ?? 0), 'key_count' => (string)($row['key_count'] ?? 0), 'status_key' => (string)($row['status_key'] ?? '')];
        }
        return $rows;
    }

    private function UpdateBrowserFormList(array $rows): void { $this->UpdateFormField('BrowserList', 'values', json_encode($rows) ?: '[]'); }
    private function UpdateDeviceFormList(array $rows): void { $this->UpdateFormField('DeviceList', 'values', json_encode($rows) ?: '[]'); }

    private function CreateScanRow(PDO $pdo, string $started, int $generatedKeys): int
    {
        $pdo->prepare('INSERT INTO scans(started_at, host, port, generated_keys, notes) VALUES(:started, :host, :port, :generated, :notes)')->execute([':started' => $started, ':host' => $this->ReadPropertyString('Host'), ':port' => $this->ReadPropertyInteger('Port'), ':generated' => $generatedKeys, ':notes' => 'Scan']);
        return (int)$pdo->lastInsertId();
    }

    private function StoreObservation(PDO $pdo, int $scanId, string $key, string $value, string $observedAt): void
    {
        $type = $this->DetectValueType($value); $confidence = $this->GetConfidence($key, $value); $name = $this->GetKnownName($key);
        $pdo->prepare('INSERT INTO api_keys(api_key,current_value,value_type,confidence,suggested_name,first_seen,last_seen,last_scan_id) VALUES(:key,:value,:type,:confidence,:name,:first,:last,:scan) ON CONFLICT(api_key) DO UPDATE SET current_value=excluded.current_value,value_type=excluded.value_type,confidence=excluded.confidence,last_seen=excluded.last_seen,last_scan_id=excluded.last_scan_id')->execute([':key'=>$key, ':value'=>$value, ':type'=>$type, ':confidence'=>$confidence, ':name'=>$name, ':first'=>$observedAt, ':last'=>$observedAt, ':scan'=>$scanId]);
        $pdo->prepare('INSERT INTO observations(scan_id, api_key, value, value_type, observed_at) VALUES(:scan, :key, :value, :type, :time)')->execute([':scan'=>$scanId, ':key'=>$key, ':value'=>$value, ':type'=>$type, ':time'=>$observedAt]);
        $this->AutoClassifyKey($pdo, $key);
    }

    private function AutoClassifyKey(PDO $pdo, string $key): void
    {
        if (strpos($key, '55.17106.') === 0) { $this->EnsureDeviceForKey($pdo, 'filter_pump', 'Filterpumpe', 'actuator', $key, $this->GetKeySuffix($key)); }
        if (strpos($key, '55.17102.') === 0) { $this->EnsureDeviceForKey($pdo, 'pool_light', 'Poollicht', 'actuator', $key, $this->GetKeySuffix($key)); }
        if (strpos($key, '34.') === 0) { $this->EnsureDeviceForKey($pdo, 'water_values', 'Wasserwerte', 'sensor_group', $key, 'measurement'); }
        if (strpos($key, '13.') === 0) { $this->EnsureDeviceForKey($pdo, 'system_values', 'Systemwerte', 'system_group', $key, 'info'); }
    }

    private function EnsureDeviceForKey(PDO $pdo, string $code, string $name, string $type, string $apiKey, string $role): void
    {
        $now = date('Y-m-d H:i:s');
        $pdo->prepare('INSERT OR IGNORE INTO devices(code,name,device_type,first_seen,last_seen) VALUES(:code,:name,:type,:first,:last)')->execute([':code'=>$code, ':name'=>$name, ':type'=>$type, ':first'=>$now, ':last'=>$now]);
        $deviceId = (int)$pdo->query('SELECT id FROM devices WHERE code=' . $pdo->quote($code))->fetchColumn();
        $pdo->prepare('INSERT OR IGNORE INTO device_keys(device_id, api_key, role, is_required, direction) VALUES(:device,:key,:role,:required,:direction)')->execute([':device'=>$deviceId, ':key'=>$apiKey, ':role'=>$role, ':required'=>in_array($role, ['status','value','measurement'], true) ? 1 : 0, ':direction'=>'read']);
        if ($role === 'status') { $pdo->prepare('UPDATE devices SET status_key=:key WHERE id=:id AND (status_key IS NULL OR status_key=\'\')')->execute([':key'=>$apiKey, ':id'=>$deviceId]); }
        if ($role === 'value' || $role === 'measurement') { $pdo->prepare('UPDATE devices SET value_key=:key WHERE id=:id AND (value_key IS NULL OR value_key=\'\')')->execute([':key'=>$apiKey, ':id'=>$deviceId]); }
    }

    private function RecalculateAllDevices(PDO $pdo): void
    {
        foreach ($pdo->query('SELECT id FROM devices')->fetchAll() as $device) {
            $id = (int)$device['id'];
            $avg = (int)$pdo->query('SELECT COALESCE(AVG(k.confidence),50) FROM device_keys dk INNER JOIN api_keys k ON k.api_key=dk.api_key WHERE dk.device_id=' . $id)->fetchColumn();
            $pdo->prepare('UPDATE devices SET confidence=:confidence WHERE id=:id')->execute([':confidence'=>$avg, ':id'=>$id]);
        }
    }

    private function InitializeDatabase(): void
    {
        $dir = $this->GetDatabaseDirectory();
        if (!is_dir($dir) && !mkdir($dir, 0775, true) && !is_dir($dir)) { throw new Exception('Datenbankverzeichnis konnte nicht erstellt werden: ' . $dir); }
        $pdo = $this->OpenDatabase();
        $this->CreateSchema($pdo);
    }

    private function OpenDatabase(): PDO
    {
        if (!$this->IsSqliteAvailable()) { throw new Exception('PDO SQLite ist nicht verfuegbar.'); }
        $pdo = new PDO('sqlite:' . $this->GetDatabasePath());
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        return $pdo;
    }

    private function CreateSchema(PDO $pdo): void
    {
        $pdo->exec('CREATE TABLE IF NOT EXISTS meta (key TEXT PRIMARY KEY, value TEXT NOT NULL)');
        $pdo->exec('CREATE TABLE IF NOT EXISTS scans (id INTEGER PRIMARY KEY AUTOINCREMENT, started_at TEXT NOT NULL, finished_at TEXT, host TEXT NOT NULL, port INTEGER NOT NULL, generated_keys INTEGER DEFAULT 0, found_keys INTEGER DEFAULT 0, duration_ms INTEGER DEFAULT 0, notes TEXT)');
        $pdo->exec('CREATE TABLE IF NOT EXISTS api_keys (api_key TEXT PRIMARY KEY, current_value TEXT, value_type TEXT, confidence INTEGER DEFAULT 0, suggested_name TEXT, is_favorite INTEGER DEFAULT 0, first_seen TEXT NOT NULL, last_seen TEXT NOT NULL, last_scan_id INTEGER)');
        $pdo->exec('CREATE TABLE IF NOT EXISTS observations (id INTEGER PRIMARY KEY AUTOINCREMENT, scan_id INTEGER NOT NULL, api_key TEXT NOT NULL, value TEXT, value_type TEXT, observed_at TEXT NOT NULL)');
        $pdo->exec('CREATE TABLE IF NOT EXISTS devices (id INTEGER PRIMARY KEY AUTOINCREMENT, code TEXT NOT NULL UNIQUE, name TEXT NOT NULL, device_type TEXT, confidence INTEGER DEFAULT 50, status_key TEXT, value_key TEXT, first_seen TEXT NOT NULL, last_seen TEXT NOT NULL)');
        $pdo->exec('CREATE TABLE IF NOT EXISTS device_keys (device_id INTEGER NOT NULL, api_key TEXT NOT NULL, role TEXT, is_required INTEGER DEFAULT 0, direction TEXT DEFAULT \'read\', PRIMARY KEY(device_id, api_key))');
        $pdo->prepare('INSERT OR REPLACE INTO meta(key, value) VALUES(:key, :value)')->execute([':key'=>'schema_version', ':value'=>(string)self::SCHEMA_VERSION]);
    }

    private function ApiGet(array $keys): array
    {
        $host = trim($this->ReadPropertyString('Host')); if ($host === '') { throw new Exception('Host ist leer.'); }
        $url = 'http://' . $host . ':' . max(1, min(65535, $this->ReadPropertyInteger('Port'))) . '/cgi-bin/webgui.fcgi?sid=' . rawurlencode($this->CreateSid());
        $payload = json_encode(['get' => array_values($keys)]);
        $context = stream_context_create(['http' => ['method'=>'POST', 'header'=>"Content-Type: application/json;charset=UTF-8\r\nAccept: application/json\r\n", 'content'=>$payload, 'timeout'=>max(1, $this->ReadPropertyInteger('Timeout')), 'ignore_errors'=>true]]);
        $started = microtime(true); $raw = @file_get_contents($url, false, $context); $durationMs = (int)round((microtime(true) - $started) * 1000);
        if ($raw === false) { throw new Exception('HTTP request failed.'); }
        $json = json_decode((string)$raw, true); if (!is_array($json)) { throw new Exception('Ungueltige JSON-Antwort.'); }
        if ((int)($json['status']['code'] ?? -1) !== 0) { throw new Exception('API Status ' . (int)($json['status']['code'] ?? -1)); }
        $json['_meta'] = ['duration_ms'=>$durationMs]; return $json;
    }

    private function BuildScanKeys(): array { $keys=[]; $suffixes=$this->ParseSuffixes($this->ReadPropertyString('ScanSuffixes')); $max=max(1,min(5000,$this->ReadPropertyInteger('ScanMaxKeys'))); for($g=max(1,$this->ReadPropertyInteger('ScanGroupStart'));$g<=max($g,$this->ReadPropertyInteger('ScanGroupEnd'));$g++){ for($o=max(1,$this->ReadPropertyInteger('ScanObjectStart'));$o<=max($o,$this->ReadPropertyInteger('ScanObjectEnd'));$o++){ foreach($suffixes as $s){ $keys[]=$g.'.'.$o.'.'.$s; if(count($keys)>=$max){return $keys;} } } } return $keys; }
    private function ParseSuffixes(string $raw): array { $raw=str_replace(["\r\n","\r",',',';'],"\n",$raw); $r=[]; foreach(explode("\n",$raw) as $line){$s=trim($line); if($s!==''&&preg_match('/^[A-Za-z0-9_]+$/',$s)){$r[$s]=$s;}} return array_values($r ?: ['value']); }
    private function DetectValueType(string $value): string { $n=str_replace(',','.',$value); if($value==='0'||$value==='1'){return 'boolean-candidate';} return is_numeric($n) ? (strpos($n,'.')===false?'integer':'float') : 'string'; }
    private function GetConfidence(string $key, string $value): int { return in_array($key, ['34.4001.value','34.4022.value','34.4033.value','13.16507.text2','13.16509.text1','55.17102.status','55.17102.value','55.17106.status','55.17106.opmode','55.17106.value'], true) ? 100 : 60; }
    private function GetKnownName(string $key): string { $n=['34.4001.value'=>'pH','34.4022.value'=>'Redox','34.4033.value'=>'Pooltemperatur','13.16507.text2'=>'Aussentemperatur T3','13.16509.text1'=>'Leitfaehigkeit','55.17106.status'=>'Filterpumpe Status','55.17106.opmode'=>'Filterpumpe Betriebsart','55.17106.value'=>'Filterpumpe Text','55.17102.status'=>'Poollicht Status','55.17102.value'=>'Poollicht Text']; return $n[$key] ?? ''; }
    private function GetKeySuffix(string $key): string { $p=explode('.', $key); return end($p) ?: ''; }
    private function CleanString(string $value): string { return trim(html_entity_decode(strip_tags($value), ENT_QUOTES | ENT_HTML5, 'UTF-8')); }
    private function CreateSid(): string { return 'SYMBAYROL' . substr(strtoupper(md5((string)microtime(true) . mt_rand())), 0, 23); }
    private function GetDatabaseDirectory(): string { return rtrim(IPS_GetKernelDir(), DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . 'user' . DIRECTORY_SEPARATOR . 'BayrolDiscovery'; }
    private function GetDatabasePath(): string { return $this->GetDatabaseDirectory() . DIRECTORY_SEPARATOR . 'bayrol_discovery.sqlite'; }
    private function IsSqliteAvailable(): bool { return class_exists('PDO') && in_array('sqlite', PDO::getAvailableDrivers(), true); }
    private function SetValueSafe(string $ident, $value): void { $id=@$this->GetIDForIdent($ident); if($id!==false&&$id>0){SetValue($id,$value);} }
}
