<?php

declare(strict_types=1);

class BayrolDiscovery extends IPSModule
{
    private const STATUS_ACTIVE = 102;
    private const STATUS_SQLITE_MISSING = 201;
    private const STATUS_DATABASE_ERROR = 202;
    private const STATUS_API_ERROR = 203;
    private const SCHEMA_VERSION = 1;

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
    }

    public function ApplyChanges()
    {
        parent::ApplyChanges();
        $this->RegisterVariables();

        if (!$this->IsSqliteAvailable()) {
            $this->SetValueSafe('DatabaseReady', false);
            $this->SetValueSafe('DatabaseStatus', 'PDO SQLite ist nicht verfuegbar.');
            $this->SetStatus(self::STATUS_SQLITE_MISSING);
            return;
        }

        try {
            $this->InitializeDatabase();
            $this->SetValueSafe('DatabaseReady', true);
            $this->SetValueSafe('DatabaseStatus', 'SQLite Datenbank bereit.');
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
        return json_encode([
            'elements' => [
                ['type' => 'ValidationTextBox', 'name' => 'Host', 'caption' => 'PoolManager IP / Host'],
                ['type' => 'NumberSpinner', 'name' => 'Port', 'caption' => 'Port'],
                ['type' => 'NumberSpinner', 'name' => 'Timeout', 'caption' => 'HTTP Timeout in Sekunden'],
                ['type' => 'CheckBox', 'name' => 'DebugMode', 'caption' => 'Erweiterte Debug-Ausgaben'],
                ['type' => 'CheckBox', 'name' => 'KeepDatabaseOnDelete', 'caption' => 'Datenbank bei Instanzloeschung behalten'],
                ['type' => 'Label', 'caption' => 'Phase 1 Scanner: rein lesende JSON-POST get-Abfragen. Keine Variablenanlage, keine Schreibbefehle.'],
                ['type' => 'NumberSpinner', 'name' => 'ScanGroupStart', 'caption' => 'Scan Gruppe von'],
                ['type' => 'NumberSpinner', 'name' => 'ScanGroupEnd', 'caption' => 'Scan Gruppe bis'],
                ['type' => 'NumberSpinner', 'name' => 'ScanObjectStart', 'caption' => 'Scan Objekt-ID von'],
                ['type' => 'NumberSpinner', 'name' => 'ScanObjectEnd', 'caption' => 'Scan Objekt-ID bis'],
                ['type' => 'ValidationTextBox', 'name' => 'ScanSuffixes', 'caption' => 'Scan Suffixe, getrennt durch Semikolon oder Komma'],
                ['type' => 'NumberSpinner', 'name' => 'ScanMaxKeys', 'caption' => 'Maximale Keys pro Scan'],
                ['type' => 'NumberSpinner', 'name' => 'ScanBatchSize', 'caption' => 'Batchgroesse']
            ],
            'actions' => [
                ['type' => 'Label', 'caption' => 'SQLite ist Pflicht. Datenbankpfad: ' . $this->GetDatabasePath()],
                ['type' => 'Button', 'caption' => 'Datenbank pruefen', 'onClick' => 'echo BPD_CheckDatabase($id);'],
                ['type' => 'Button', 'caption' => 'Verbindung testen', 'onClick' => 'echo BPD_TestConnection($id);'],
                ['type' => 'Button', 'caption' => 'Scan starten', 'onClick' => 'echo BPD_RunScan($id);'],
                ['type' => 'Button', 'caption' => 'Scan-Zusammenfassung laden', 'onClick' => 'echo BPD_GetScanSummary($id);']
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
        try {
            $this->InitializeDatabase();
            $pdo = $this->OpenDatabase();
            $tables = (int)$pdo->query("SELECT COUNT(*) FROM sqlite_master WHERE type='table'")->fetchColumn();
            $keys = (int)$pdo->query('SELECT COUNT(*) FROM api_keys')->fetchColumn();
            $scans = (int)$pdo->query('SELECT COUNT(*) FROM scans')->fetchColumn();
            $message = 'Datenbank OK. Tabellen: ' . $tables . ', API-Keys: ' . $keys . ', Scans: ' . $scans;
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
        try {
            $this->InitializeDatabase();
            $keys = $this->BuildScanKeys();
            if (count($keys) === 0) {
                return 'Keine Scan-Keys erzeugt.';
            }

            $pdo = $this->OpenDatabase();
            $started = date('Y-m-d H:i:s');
            $scanId = $this->CreateScanRow($pdo, $started, count($keys));
            $batchSize = max(1, min(100, $this->ReadPropertyInteger('ScanBatchSize')));
            $chunks = array_chunk($keys, $batchSize);
            $found = 0;
            $duration = 0;

            foreach ($chunks as $index => $chunk) {
                $this->SendDebugMessage('Scan batch', ($index + 1) . '/' . count($chunks));
                $response = $this->ApiGet($chunk);
                $duration += (int)($response['_meta']['duration_ms'] ?? 0);
                $data = $response['data'] ?? [];
                if (!is_array($data)) {
                    continue;
                }
                foreach ($data as $key => $value) {
                    if ($value === null || $value === '') {
                        continue;
                    }
                    $clean = $this->CleanString((string)$value);
                    if ($clean === '') {
                        continue;
                    }
                    $this->StoreObservation($pdo, $scanId, (string)$key, $clean, $started);
                    $found++;
                }
            }

            $finished = date('Y-m-d H:i:s');
            $stmt = $pdo->prepare('UPDATE scans SET finished_at=:finished, found_keys=:found, duration_ms=:duration WHERE id=:id');
            $stmt->execute([':finished' => $finished, ':found' => $found, ':duration' => $duration, ':id' => $scanId]);

            $this->SetValueSafe('LastScanId', $scanId);
            $this->SetValueSafe('LastScanStarted', $started);
            $this->SetValueSafe('LastScanFinished', $finished);
            $this->SetValueSafe('LastScanGeneratedKeys', count($keys));
            $this->SetValueSafe('LastScanFoundKeys', $found);
            $this->SetValueSafe('LastResponseTimeMs', $duration);
            $this->SetValueSafe('LastError', '');
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
            $pdo = $this->OpenDatabase();
            $scans = (int)$pdo->query('SELECT COUNT(*) FROM scans')->fetchColumn();
            $keys = (int)$pdo->query('SELECT COUNT(*) FROM api_keys')->fetchColumn();
            $obs = (int)$pdo->query('SELECT COUNT(*) FROM observations')->fetchColumn();
            $last = $pdo->query('SELECT id, started_at, found_keys FROM scans ORDER BY id DESC LIMIT 1')->fetch();
            $message = 'Scans: ' . $scans . ', API-Keys: ' . $keys . ', Beobachtungen: ' . $obs;
            if (is_array($last)) {
                $message .= ', letzter Scan #' . $last['id'] . ' vom ' . $last['started_at'] . ', Treffer: ' . $last['found_keys'];
            }
            $this->SetValueSafe('ScanSummary', $message);
            return $message;
        } catch (Throwable $e) {
            return 'Zusammenfassungsfehler: ' . $e->getMessage();
        }
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
    }

    private function CreateScanRow(PDO $pdo, string $started, int $generatedKeys): int
    {
        $stmt = $pdo->prepare('INSERT INTO scans(started_at, host, port, generated_keys, notes) VALUES(:started, :host, :port, :generated, :notes)');
        $stmt->execute([
            ':started' => $started,
            ':host' => $this->ReadPropertyString('Host'),
            ':port' => $this->ReadPropertyInteger('Port'),
            ':generated' => $generatedKeys,
            ':notes' => 'Phase 1 scan'
        ]);
        return (int)$pdo->lastInsertId();
    }

    private function StoreObservation(PDO $pdo, int $scanId, string $key, string $value, string $observedAt): void
    {
        $type = $this->DetectValueType($value);
        $confidence = $this->GetConfidence($key, $value);
        $name = $this->GetKnownName($key);

        $stmt = $pdo->prepare('INSERT INTO api_keys(api_key,current_value,value_type,confidence,suggested_name,first_seen,last_seen,last_scan_id) VALUES(:key,:value,:type,:confidence,:name,:first,:last,:scan) ON CONFLICT(api_key) DO UPDATE SET current_value=excluded.current_value,value_type=excluded.value_type,confidence=excluded.confidence,suggested_name=excluded.suggested_name,last_seen=excluded.last_seen,last_scan_id=excluded.last_scan_id');
        $stmt->execute([':key' => $key, ':value' => $value, ':type' => $type, ':confidence' => $confidence, ':name' => $name, ':first' => $observedAt, ':last' => $observedAt, ':scan' => $scanId]);

        $obs = $pdo->prepare('INSERT INTO observations(scan_id, api_key, value, value_type, observed_at) VALUES(:scan, :key, :value, :type, :time)');
        $obs->execute([':scan' => $scanId, ':key' => $key, ':value' => $value, ':type' => $type, ':time' => $observedAt]);
    }

    private function InitializeDatabase(): void
    {
        $directory = $this->GetDatabaseDirectory();
        if (!is_dir($directory) && !mkdir($directory, 0775, true) && !is_dir($directory)) {
            throw new Exception('Datenbankverzeichnis konnte nicht erstellt werden: ' . $directory);
        }
        $pdo = $this->OpenDatabase();
        $pdo->exec('PRAGMA foreign_keys=ON');
        $this->CreateSchema($pdo);
    }

    private function OpenDatabase(): PDO
    {
        if (!$this->IsSqliteAvailable()) {
            throw new Exception('PDO SQLite ist nicht verfuegbar.');
        }
        $pdo = new PDO('sqlite:' . $this->GetDatabasePath());
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        return $pdo;
    }

    private function CreateSchema(PDO $pdo): void
    {
        $pdo->exec('CREATE TABLE IF NOT EXISTS meta (key TEXT PRIMARY KEY, value TEXT NOT NULL)');
        $pdo->exec('CREATE TABLE IF NOT EXISTS scans (id INTEGER PRIMARY KEY AUTOINCREMENT, started_at TEXT NOT NULL, finished_at TEXT, host TEXT NOT NULL, port INTEGER NOT NULL, generated_keys INTEGER DEFAULT 0, found_keys INTEGER DEFAULT 0, duration_ms INTEGER DEFAULT 0, notes TEXT)');
        $pdo->exec('CREATE TABLE IF NOT EXISTS api_keys (api_key TEXT PRIMARY KEY, current_value TEXT, value_type TEXT, confidence INTEGER DEFAULT 0, category TEXT, suggested_name TEXT, is_favorite INTEGER DEFAULT 0, is_writable INTEGER, first_seen TEXT NOT NULL, last_seen TEXT NOT NULL, last_scan_id INTEGER, comment TEXT)');
        $pdo->exec('CREATE TABLE IF NOT EXISTS observations (id INTEGER PRIMARY KEY AUTOINCREMENT, scan_id INTEGER NOT NULL, api_key TEXT NOT NULL, value TEXT, value_type TEXT, observed_at TEXT NOT NULL)');
        $pdo->exec('CREATE INDEX IF NOT EXISTS idx_observations_api_key ON observations(api_key)');
        $pdo->exec('CREATE INDEX IF NOT EXISTS idx_observations_scan_id ON observations(scan_id)');
        $stmt = $pdo->prepare('INSERT OR REPLACE INTO meta(key, value) VALUES(:key, :value)');
        $stmt->execute([':key' => 'schema_version', ':value' => (string)self::SCHEMA_VERSION]);
    }

    private function ApiGet(array $keys): array
    {
        $host = trim($this->ReadPropertyString('Host'));
        $port = max(1, min(65535, $this->ReadPropertyInteger('Port')));
        $timeout = max(1, $this->ReadPropertyInteger('Timeout'));
        if ($host === '') {
            throw new Exception('Host ist leer.');
        }
        $url = 'http://' . $host . ':' . $port . '/cgi-bin/webgui.fcgi?sid=' . rawurlencode($this->CreateSid());
        $payload = json_encode(['get' => array_values($keys)]);
        if ($payload === false) {
            throw new Exception('JSON-Encoding fehlgeschlagen.');
        }
        $context = stream_context_create(['http' => ['method' => 'POST', 'header' => "Content-Type: application/json;charset=UTF-8\r\nAccept: application/json\r\n", 'content' => $payload, 'timeout' => $timeout, 'ignore_errors' => true]]);
        $started = microtime(true);
        $raw = @file_get_contents($url, false, $context);
        $durationMs = (int)round((microtime(true) - $started) * 1000);
        if ($raw === false) {
            throw new Exception('HTTP request failed.');
        }
        $json = json_decode((string)$raw, true);
        if (!is_array($json)) {
            throw new Exception('Ungueltige JSON-Antwort.');
        }
        $apiStatus = (int)($json['status']['code'] ?? -1);
        if ($apiStatus !== 0) {
            throw new Exception('API Status ' . $apiStatus);
        }
        $json['_meta'] = ['duration_ms' => $durationMs, 'requested_keys' => count($keys)];
        return $json;
    }

    private function BuildScanKeys(): array
    {
        $groupStart = max(1, $this->ReadPropertyInteger('ScanGroupStart'));
        $groupEnd = max($groupStart, $this->ReadPropertyInteger('ScanGroupEnd'));
        $objectStart = max(1, $this->ReadPropertyInteger('ScanObjectStart'));
        $objectEnd = max($objectStart, $this->ReadPropertyInteger('ScanObjectEnd'));
        $maxKeys = max(1, min(5000, $this->ReadPropertyInteger('ScanMaxKeys')));
        $suffixes = $this->ParseSuffixes($this->ReadPropertyString('ScanSuffixes'));
        $keys = [];
        for ($group = $groupStart; $group <= $groupEnd; $group++) {
            for ($object = $objectStart; $object <= $objectEnd; $object++) {
                foreach ($suffixes as $suffix) {
                    $keys[] = $group . '.' . $object . '.' . $suffix;
                    if (count($keys) >= $maxKeys) {
                        return $keys;
                    }
                }
            }
        }
        return $keys;
    }

    private function ParseSuffixes(string $raw): array
    {
        $raw = str_replace(["\r\n", "\r", ',', ';'], "\n", $raw);
        $suffixes = [];
        foreach (explode("\n", $raw) as $line) {
            $suffix = trim($line);
            if ($suffix !== '' && preg_match('/^[A-Za-z0-9_]+$/', $suffix)) {
                $suffixes[$suffix] = $suffix;
            }
        }
        return array_values($suffixes ?: ['value']);
    }

    private function DetectValueType(string $value): string
    {
        $normalized = str_replace(',', '.', $value);
        if ($value === '0' || $value === '1') {
            return 'boolean-candidate';
        }
        if (is_numeric($normalized)) {
            return strpos($normalized, '.') === false ? 'integer' : 'float';
        }
        return 'string';
    }

    private function GetConfidence(string $key, string $value): int
    {
        $known = ['34.4001.value', '34.4022.value', '34.4033.value', '13.16507.text2', '13.16509.text1', '55.17102.status', '55.17102.value', '55.17106.status', '55.17106.opmode', '55.17106.value'];
        if (in_array($key, $known, true)) {
            return 100;
        }
        if ($value !== '' && preg_match('/^(13|34|55)\.[0-9]+\.(value|status|opmode|text1|text2)$/', $key)) {
            return 60;
        }
        return 20;
    }

    private function GetKnownName(string $key): string
    {
        $names = ['34.4001.value' => 'pH', '34.4022.value' => 'Redox', '34.4033.value' => 'Pooltemperatur', '13.16507.text2' => 'Aussentemperatur T3', '13.16509.text1' => 'Leitfaehigkeit', '55.17106.status' => 'Filterpumpe Status'];
        return $names[$key] ?? '';
    }

    private function CleanString(string $value): string
    {
        return trim(html_entity_decode(strip_tags($value), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
    }

    private function CreateSid(): string
    {
        return 'SYMBAYROL' . substr(strtoupper(md5((string)microtime(true) . mt_rand())), 0, 23);
    }

    private function GetDatabaseDirectory(): string
    {
        return rtrim(IPS_GetKernelDir(), DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . 'user' . DIRECTORY_SEPARATOR . 'BayrolDiscovery';
    }

    private function GetDatabasePath(): string
    {
        return $this->GetDatabaseDirectory() . DIRECTORY_SEPARATOR . 'bayrol_discovery.sqlite';
    }

    private function IsSqliteAvailable(): bool
    {
        return class_exists('PDO') && in_array('sqlite', PDO::getAvailableDrivers(), true);
    }

    private function SetValueSafe(string $ident, $value): void
    {
        $id = @$this->GetIDForIdent($ident);
        if ($id !== false && $id > 0) {
            SetValue($id, $value);
        }
    }

    private function SendDebugMessage(string $message, string $data): void
    {
        if ($this->ReadPropertyBoolean('DebugMode')) {
            $this->SendDebug($message, $data, 0);
        }
    }
}
