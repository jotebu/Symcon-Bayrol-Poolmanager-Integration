<?php

declare(strict_types=1);

class BayrolDiscovery extends IPSModule
{
    private const STATUS_ACTIVE = 102;
    private const STATUS_SQLITE_MISSING = 201;
    private const STATUS_DATABASE_ERROR = 202;
    private const SCHEMA_VERSION = 1;

    public function Create()
    {
        parent::Create();

        $this->RegisterPropertyString('Host', '192.168.55.23');
        $this->RegisterPropertyInteger('Port', 80);
        $this->RegisterPropertyInteger('Timeout', 10);
        $this->RegisterPropertyBoolean('DebugMode', false);
        $this->RegisterPropertyBoolean('KeepDatabaseOnDelete', true);
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
                ['type' => 'CheckBox', 'name' => 'KeepDatabaseOnDelete', 'caption' => 'Datenbank bei Instanzloeschung behalten']
            ],
            'actions' => [
                ['type' => 'Label', 'caption' => 'SQLite ist Pflicht. Datenbankpfad: ' . $this->GetDatabasePath()],
                ['type' => 'Button', 'caption' => 'Datenbank pruefen', 'onClick' => 'echo BPD_CheckDatabase($id);']
            ],
            'status' => [
                ['code' => self::STATUS_ACTIVE, 'icon' => 'active', 'caption' => 'SQLite Datenbank bereit'],
                ['code' => self::STATUS_SQLITE_MISSING, 'icon' => 'error', 'caption' => 'PDO SQLite fehlt'],
                ['code' => self::STATUS_DATABASE_ERROR, 'icon' => 'error', 'caption' => 'Datenbankfehler']
            ]
        ]);
    }

    public function CheckDatabase(): string
    {
        try {
            $this->InitializeDatabase();
            $pdo = $this->OpenDatabase();
            $tables = (int)$pdo->query("SELECT COUNT(*) FROM sqlite_master WHERE type='table'")->fetchColumn();
            $message = 'Datenbank OK. Tabellen: ' . $tables;
            $this->SetValueSafe('DatabaseStatus', $message);
            $this->SetStatus(self::STATUS_ACTIVE);
            return $message;
        } catch (Throwable $e) {
            $this->SetValueSafe('DatabaseStatus', $e->getMessage());
            $this->SetStatus(self::STATUS_DATABASE_ERROR);
            return 'Datenbankfehler: ' . $e->getMessage();
        }
    }

    private function RegisterVariables(): void
    {
        $this->RegisterVariableBoolean('DatabaseReady', 'Datenbank bereit', '~Switch', 10);
        $this->RegisterVariableString('DatabaseStatus', 'Datenbank Status', '', 20);
        $this->RegisterVariableString('DatabasePath', 'Datenbank Pfad', '', 30);
        $this->RegisterVariableInteger('DatabaseSchemaVersion', 'Datenbank Schema Version', '', 40);
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

        $statement = $pdo->prepare('INSERT OR REPLACE INTO meta(key, value) VALUES(:key, :value)');
        $statement->execute([':key' => 'schema_version', ':value' => (string)self::SCHEMA_VERSION]);
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
}
