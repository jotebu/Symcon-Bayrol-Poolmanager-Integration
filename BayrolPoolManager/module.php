<?php

declare(strict_types=1);

class BayrolPoolManager5 extends IPSModule
{
    private const TIMER_UPDATE = 'UpdateTimer';

    private const STATUS_ACTIVE = 102;
    private const STATUS_INACTIVE = 104;
    private const STATUS_HOST_MISSING = 201;
    private const STATUS_API_ERROR = 202;

    private const API_KEYS = [
        'pH' => '34.4001.value',
        'Redox' => '34.4022.value',
        'PoolTemperature' => '34.4033.value',
        'OutdoorTemperatureText' => '13.16507.text2',
        'ConductivityText' => '13.16509.text1',
        'PoolLightStatus' => '55.17102.status',
        'PoolLightText' => '55.17102.value',
        'FilterPumpStatus' => '55.17106.status',
        'FilterPumpOpmode' => '55.17106.opmode',
        'FilterPumpText' => '55.17106.value'
    ];

    private const KNOWN_KEYS = [
        '34.4001.value' => 'pH',
        '34.4022.value' => 'Redox',
        '34.4033.value' => 'Pooltemperatur',
        '13.16507.text2' => 'Aussentemperatur T3',
        '13.16509.text1' => 'Leitfaehigkeit',
        '55.17102.status' => 'Poollicht Status',
        '55.17102.value' => 'Poollicht Text',
        '55.17106.status' => 'Filterpumpe Status',
        '55.17106.opmode' => 'Filterpumpe Betriebsart',
        '55.17106.value' => 'Filterpumpe Text'
    ];

    public function Create()
    {
        parent::Create();

        $this->RegisterPropertyString('Host', '192.168.55.23');
        $this->RegisterPropertyInteger('Port', 80);
        $this->RegisterPropertyInteger('UpdateInterval', 60);
        $this->RegisterPropertyInteger('Timeout', 10);
        $this->RegisterPropertyBoolean('DebugMode', false);
        $this->RegisterPropertyString('ExplorerKeys', "34.4001.value\n34.4022.value\n34.4033.value\n13.16507.text2\n13.16509.text1\n55.17106.status\n55.17106.opmode\n55.17106.value");
        $this->RegisterPropertyInteger('DiscoveryGroupStart', 34);
        $this->RegisterPropertyInteger('DiscoveryGroupEnd', 55);
        $this->RegisterPropertyInteger('DiscoveryObjectStart', 4000);
        $this->RegisterPropertyInteger('DiscoveryObjectEnd', 17200);
        $this->RegisterPropertyString('DiscoverySuffixes', "value\nstatus\nopmode\ntext1\ntext2");
        $this->RegisterPropertyInteger('DiscoveryMaxKeys', 500);
        $this->RegisterPropertyInteger('DiscoveryBatchSize', 50);
        $this->RegisterPropertyString('DiscoveryFilterKey', '');
        $this->RegisterPropertyString('DiscoveryFilterValue', '');
        $this->RegisterPropertyString('DiscoveryFilterType', '');
        $this->RegisterPropertyBoolean('DiscoveryOnlyFound', true);
        $this->RegisterPropertyString('FavoriteKeys', '');
        $this->RegisterPropertyString('SelectedImportKeys', '');

        $this->RegisterTimer(self::TIMER_UPDATE, 0, 'BPM_UpdateValues($_IPS["TARGET"]);');
    }

    public function ApplyChanges()
    {
        parent::ApplyChanges();

        $this->CreateProfiles();
        $this->RegisterVariables();

        if (trim($this->ReadPropertyString('Host')) === '') {
            $this->SetTimerInterval(self::TIMER_UPDATE, 0);
            $this->SetStatus(self::STATUS_HOST_MISSING);
            return;
        }

        $this->UpdateTimer();
        $this->SetStatus(self::STATUS_INACTIVE);
    }

    public function GetConfigurationForm()
    {
        return json_encode([
            'elements' => [
                ['type' => 'ValidationTextBox', 'name' => 'Host', 'caption' => 'PoolManager IP / Host'],
                ['type' => 'NumberSpinner', 'name' => 'Port', 'caption' => 'Port'],
                ['type' => 'NumberSpinner', 'name' => 'UpdateInterval', 'caption' => 'Aktualisierungsintervall in Sekunden'],
                ['type' => 'NumberSpinner', 'name' => 'Timeout', 'caption' => 'HTTP Timeout in Sekunden'],
                ['type' => 'CheckBox', 'name' => 'DebugMode', 'caption' => 'Erweiterte Debug-Ausgaben'],
                ['type' => 'Label', 'caption' => 'Explorer: Nur lesende JSON-POST get-Abfragen. Keine Schreib-/Aktorbefehle.'],
                ['type' => 'TextBox', 'name' => 'ExplorerKeys', 'caption' => 'Explorer API-Keys, ein Key pro Zeile'],
                ['type' => 'Label', 'caption' => 'Discovery-Assistent: findet Datenpunkte, legt aber keine gefundenen Datenpunkte automatisch im Objektbaum an.'],
                ['type' => 'NumberSpinner', 'name' => 'DiscoveryGroupStart', 'caption' => 'Discovery Gruppe von'],
                ['type' => 'NumberSpinner', 'name' => 'DiscoveryGroupEnd', 'caption' => 'Discovery Gruppe bis'],
                ['type' => 'NumberSpinner', 'name' => 'DiscoveryObjectStart', 'caption' => 'Discovery Objekt-ID von'],
                ['type' => 'NumberSpinner', 'name' => 'DiscoveryObjectEnd', 'caption' => 'Discovery Objekt-ID bis'],
                ['type' => 'TextBox', 'name' => 'DiscoverySuffixes', 'caption' => 'Discovery Suffixe, ein Suffix pro Zeile'],
                ['type' => 'NumberSpinner', 'name' => 'DiscoveryMaxKeys', 'caption' => 'Maximale Discovery-Keys pro Lauf'],
                ['type' => 'NumberSpinner', 'name' => 'DiscoveryBatchSize', 'caption' => 'Discovery Batchgroesse'],
                ['type' => 'Label', 'caption' => 'Discovery Filter und Auswahl'],
                ['type' => 'ValidationTextBox', 'name' => 'DiscoveryFilterKey', 'caption' => 'Filter API-Key enthaelt'],
                ['type' => 'ValidationTextBox', 'name' => 'DiscoveryFilterValue', 'caption' => 'Filter Wert enthaelt'],
                ['type' => 'ValidationTextBox', 'name' => 'DiscoveryFilterType', 'caption' => 'Filter Typ enthaelt'],
                ['type' => 'CheckBox', 'name' => 'DiscoveryOnlyFound', 'caption' => 'Nur gefundene Datenpunkte anzeigen'],
                ['type' => 'TextBox', 'name' => 'FavoriteKeys', 'caption' => 'Favoriten-Keys, ein Key pro Zeile'],
                ['type' => 'TextBox', 'name' => 'SelectedImportKeys', 'caption' => 'Ausgewaehlte Import-Keys, ein Key pro Zeile']
            ],
            'actions' => [
                ['type' => 'Button', 'caption' => 'Verbindung testen', 'onClick' => 'echo BPM_TestConnection($id) ? "Verbindung erfolgreich." : "Verbindung fehlgeschlagen. Siehe Status und Debug-Ausgabe.";'],
                ['type' => 'Button', 'caption' => 'Werte jetzt aktualisieren', 'onClick' => 'BPM_UpdateValues($id); echo "Aktualisierung ausgefuehrt. Siehe Variablen, Status und Debug-Ausgabe.";'],
                ['type' => 'Button', 'caption' => 'Explorer jetzt ausfuehren', 'onClick' => 'BPM_RunExplorer($id); echo "Explorer ausgefuehrt. Ergebnis siehe Variable Explorer Rohdaten.";'],
                ['type' => 'Button', 'caption' => 'Discovery-Assistent ausfuehren', 'onClick' => 'BPM_RunDiscovery($id); echo "Discovery ausgefuehrt. Ergebnisliste wurde direkt aktualisiert. Es wurden keine Datenpunkte automatisch angelegt.";'],
                ['type' => 'Button', 'caption' => 'Ausgewaehlte Datenpunkte uebernehmen', 'onClick' => 'BPM_ImportSelectedKeys($id); echo "Import ist in dieser Alpha noch gesperrt. Ausgewaehlte Keys wurden nur validiert und dokumentiert.";'],
                ['type' => 'Label', 'caption' => 'Discovery Ergebnisliste: Anzeige nur zur Auswahl/Pruefung. Kein Datenpunkt wird automatisch importiert.'],
                [
                    'type' => 'List',
                    'name' => 'DiscoveryResultList',
                    'caption' => 'Gefundene Datenpunkte',
                    'rowCount' => 15,
                    'add' => false,
                    'delete' => false,
                    'sort' => ['column' => 'confidence', 'direction' => 'ascending'],
                    'columns' => [
                        ['name' => 'favorite', 'caption' => 'Fav', 'width' => '50px', 'add' => '', 'edit' => false],
                        ['name' => 'selected', 'caption' => 'Import', 'width' => '70px', 'add' => '', 'edit' => false],
                        ['name' => 'confidence', 'caption' => 'Vertrauen', 'width' => '160px', 'add' => '', 'edit' => false],
                        ['name' => 'key', 'caption' => 'API-Key', 'width' => '220px', 'add' => '', 'edit' => false],
                        ['name' => 'name', 'caption' => 'Name/Vorschlag', 'width' => '180px', 'add' => '', 'edit' => false],
                        ['name' => 'value', 'caption' => 'Wert', 'width' => '220px', 'add' => '', 'edit' => false],
                        ['name' => 'type', 'caption' => 'Typ', 'width' => '130px', 'add' => '', 'edit' => false]
                    ],
                    'values' => $this->BuildDiscoveryListValues()
                ],
                ['type' => 'Label', 'caption' => 'Vertrauen: gruen = bekannt/getestet, gelb = plausibel/noch unbekannt, rot = neu/auffaellig. Import bleibt bis nach dem Test deaktiviert.'],
                ['type' => 'Label', 'caption' => 'Version 0.1.0-alpha: Discovery-Liste wird nach dem Lauf direkt im Formular aktualisiert.']
            ],
            'status' => [
                ['code' => self::STATUS_ACTIVE, 'icon' => 'active', 'caption' => 'Aktiv'],
                ['code' => self::STATUS_INACTIVE, 'icon' => 'inactive', 'caption' => 'Inaktiv / noch nicht aktualisiert'],
                ['code' => self::STATUS_HOST_MISSING, 'icon' => 'error', 'caption' => 'Keine Host-Adresse konfiguriert'],
                ['code' => self::STATUS_API_ERROR, 'icon' => 'error', 'caption' => 'PoolManager nicht erreichbar oder API-Fehler']
            ]
        ]);
    }

    public function TestConnection(): bool
    {
        $this->SendDebugMessage('TestConnection', 'Start');

        try {
            $response = $this->ApiGet(['34.4001.value']);
            $ok = isset($response['data']['34.4001.value']);

            $this->SetValueSafe('ConnectionState', $ok);
            $this->SetValueSafe('LastApiStatus', (int)($response['status']['code'] ?? -1));
            $this->SetValueSafe('LastError', $ok ? '' : 'API response does not contain pH key');
            $this->SetValueSafe('LastUpdate', date('Y-m-d H:i:s'));
            $this->SetValueSafe('LastSuccessfulUpdate', $ok ? date('Y-m-d H:i:s') : '');
            $this->SetStatus($ok ? self::STATUS_ACTIVE : self::STATUS_API_ERROR);

            return $ok;
        } catch (Throwable $e) {
            $this->HandleError('TestConnection', $e);
            return false;
        }
    }

    public function UpdateValues(): void
    {
        $keys = array_values(self::API_KEYS);
        $this->SendDebugMessage('UpdateValues', 'Reading ' . count($keys) . ' keys');

        try {
            $response = $this->ApiGet($keys);
            $data = $response['data'] ?? [];

            if (!is_array($data)) {
                throw new Exception('Invalid API data block');
            }

            $this->SetValueSafe('ConnectionState', true);
            $this->SetValueSafe('LastApiStatus', (int)($response['status']['code'] ?? 0));
            $this->SetValueSafe('LastError', '');
            $this->SetValueSafe('LastUpdate', date('Y-m-d H:i:s'));
            $this->SetValueSafe('LastSuccessfulUpdate', date('Y-m-d H:i:s'));
            $this->SetValueSafe('ResponseTimeMs', (int)($response['_meta']['duration_ms'] ?? 0));
            $this->SetValueSafe('ReceivedDataPoints', count($data));

            $this->UpdateKnownVariables($data);
            $this->SetStatus(self::STATUS_ACTIVE);
        } catch (Throwable $e) {
            $this->HandleError('UpdateValues', $e);
        }
    }

    public function RunExplorer(): void
    {
        $keys = $this->GetExplorerKeys();
        $this->SendDebugMessage('Explorer', 'Reading ' . count($keys) . ' keys');

        if (count($keys) === 0) {
            $this->SetValueSafe('ExplorerRawData', 'Keine Explorer-Keys konfiguriert.');
            $this->SetValueSafe('ExplorerDataPoints', 0);
            $this->SetValueSafe('ExplorerLastRun', date('Y-m-d H:i:s'));
            return;
        }

        try {
            $response = $this->ApiGet($keys);
            $data = $response['data'] ?? [];

            if (!is_array($data)) {
                throw new Exception('Invalid explorer API data block');
            }

            ksort($data);
            $raw = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
            if ($raw === false) {
                $raw = 'Explorer JSON encoding failed';
            }

            $this->SetValueSafe('ExplorerRawData', $raw);
            $this->SetValueSafe('ExplorerDataPoints', count($data));
            $this->SetValueSafe('ExplorerLastRun', date('Y-m-d H:i:s'));
            $this->SetValueSafe('ExplorerResponseTimeMs', (int)($response['_meta']['duration_ms'] ?? 0));
            $this->SetValueSafe('ConnectionState', true);
            $this->SetValueSafe('LastError', '');
            $this->SetStatus(self::STATUS_ACTIVE);
        } catch (Throwable $e) {
            $this->SetValueSafe('ExplorerRawData', 'Explorer error: ' . $e->getMessage());
            $this->SetValueSafe('ExplorerLastRun', date('Y-m-d H:i:s'));
            $this->HandleError('Explorer', $e);
        }
    }

    public function RunDiscovery(): void
    {
        $keys = $this->BuildDiscoveryKeys();
        $generated = count($keys);
        $this->SetValueSafe('DiscoveryGeneratedKeys', $generated);
        $this->SetValueSafe('DiscoveryLastRun', date('Y-m-d H:i:s'));

        if ($generated === 0) {
            $this->SetValueSafe('DiscoveryResult', 'Keine Discovery-Keys generiert.');
            $this->SetValueSafe('DiscoveryFoundDataPoints', 0);
            $this->UpdateDiscoveryFormList([]);
            return;
        }

        $batchSize = max(1, min(100, $this->ReadPropertyInteger('DiscoveryBatchSize')));
        $chunks = array_chunk($keys, $batchSize);
        $found = [];
        $tested = [];
        $totalDuration = 0;

        try {
            foreach ($chunks as $index => $chunk) {
                $this->SendDebugMessage('Discovery batch', ($index + 1) . '/' . count($chunks) . ' with ' . count($chunk) . ' keys');
                foreach ($chunk as $key) {
                    $tested[$key] = ['value' => '', 'type' => 'not-found', 'confidence' => 'rot - nicht gefunden', 'name' => ''];
                }

                $response = $this->ApiGet($chunk);
                $data = $response['data'] ?? [];
                $totalDuration += (int)($response['_meta']['duration_ms'] ?? 0);

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
                    $entry = [
                        'value' => $clean,
                        'type' => $this->DetectValueType($clean),
                        'confidence' => $this->GetConfidenceLevel($key, $clean),
                        'name' => $this->GetKnownKeyName($key),
                        'selected' => false,
                        'favorite' => false
                    ];
                    $found[$key] = $entry;
                    $tested[$key] = $entry;
                }
            }

            ksort($found);
            ksort($tested);
            $output = [
                'note' => 'Discovery ist rein lesend. Gefundene Datenpunkte werden nicht automatisch im Objektbaum angelegt.',
                'next_step' => 'Gewuenschte Keys in SelectedImportKeys uebernehmen. Importfunktion bleibt bis nach dem Test gesperrt.',
                'generated_keys' => $generated,
                'found_count' => count($found),
                'found' => $found,
                'tested' => $tested
            ];
            $raw = json_encode($output, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
            if ($raw === false) {
                $raw = 'Discovery JSON encoding failed';
            }

            $this->SetValueSafe('DiscoveryResult', $raw);
            $this->SetValueSafe('DiscoveryFoundDataPoints', count($found));
            $this->SetValueSafe('DiscoveryResponseTimeMs', $totalDuration);
            $this->SetValueSafe('ConnectionState', true);
            $this->SetValueSafe('LastError', '');
            $this->UpdateDiscoveryFormList($this->BuildDiscoveryListRowsFromData($output));
            $this->SetStatus(self::STATUS_ACTIVE);
        } catch (Throwable $e) {
            $this->SetValueSafe('DiscoveryResult', 'Discovery error: ' . $e->getMessage());
            $this->UpdateDiscoveryFormList([]);
            $this->HandleError('Discovery', $e);
        }
    }

    public function ImportSelectedKeys(): void
    {
        $selected = $this->ParseKeyList($this->ReadPropertyString('SelectedImportKeys'));
        $message = count($selected) . " Key(s) ausgewaehlt. Auto-Import ist in dieser Alpha absichtlich gesperrt.\n" . implode("\n", $selected);
        $this->SetValueSafe('DiscoveryImportPreview', $message);
        $this->SendDebugMessage('Import preview only', $message);
    }

    private function RegisterVariables(): void
    {
        $this->RegisterVariableFloat('pH', 'pH', 'BPM.pH', 10);
        $this->RegisterVariableInteger('Redox', 'Redox', 'BPM.Redox', 20);
        $this->RegisterVariableFloat('PoolTemperature', 'Pooltemperatur', '~Temperature', 30);
        $this->RegisterVariableFloat('OutdoorTemperature', 'Aussentemperatur T3', '~Temperature', 40);
        $this->RegisterVariableFloat('Conductivity', 'Leitfaehigkeit', 'BPM.Conductivity', 50);

        $this->RegisterVariableBoolean('PoolLightActive', 'Lampen Becken aktiv', '~Switch', 100);
        $this->RegisterVariableString('PoolLightText', 'Lampen Becken Text', '', 101);

        $this->RegisterVariableBoolean('FilterPumpActive', 'Filterpumpe aktiv', '~Switch', 110);
        $this->RegisterVariableInteger('FilterPumpOpmode', 'Filterpumpe Betriebsart', 'BPM.FilterOpmode', 111);
        $this->RegisterVariableString('FilterPumpText', 'Filterpumpe Text', '', 112);
        $this->RegisterVariableString('FilterPumpDetailedMode', 'Filterpumpe Detailmodus', '', 113);

        $this->RegisterVariableBoolean('ConnectionState', 'Verbindung aktiv', '~Switch', 200);
        $this->RegisterVariableString('LastUpdate', 'Letzte Aktualisierung', '', 201);
        $this->RegisterVariableString('LastSuccessfulUpdate', 'Letzte erfolgreiche Aktualisierung', '', 202);
        $this->RegisterVariableInteger('LastApiStatus', 'Letzter API Status', '', 203);
        $this->RegisterVariableInteger('ResponseTimeMs', 'API Antwortzeit', 'BPM.Milliseconds', 204);
        $this->RegisterVariableInteger('ReceivedDataPoints', 'Empfangene Datenpunkte', '', 205);
        $this->RegisterVariableString('LastError', 'Letzter Fehler', '', 206);

        $this->RegisterVariableString('ExplorerRawData', 'Explorer Rohdaten', '', 300);
        $this->RegisterVariableInteger('ExplorerDataPoints', 'Explorer Datenpunkte', '', 301);
        $this->RegisterVariableInteger('ExplorerResponseTimeMs', 'Explorer Antwortzeit', 'BPM.Milliseconds', 302);
        $this->RegisterVariableString('ExplorerLastRun', 'Explorer letzter Lauf', '', 303);

        $this->RegisterVariableString('DiscoveryResult', 'Discovery Ergebnis', '', 400);
        $this->RegisterVariableInteger('DiscoveryGeneratedKeys', 'Discovery generierte Keys', '', 401);
        $this->RegisterVariableInteger('DiscoveryFoundDataPoints', 'Discovery gefundene Datenpunkte', '', 402);
        $this->RegisterVariableInteger('DiscoveryResponseTimeMs', 'Discovery Antwortzeit gesamt', 'BPM.Milliseconds', 403);
        $this->RegisterVariableString('DiscoveryLastRun', 'Discovery letzter Lauf', '', 404);
        $this->RegisterVariableString('DiscoveryImportPreview', 'Discovery Import Vorschau', '', 405);
    }

    private function CreateProfiles(): void
    {
        $this->CreateFloatProfile('BPM.pH', 'Gauge', '', '', 0, 14, 0.01, 2);
        $this->CreateIntegerProfile('BPM.Redox', 'Electricity', '', ' mV', 0, 1000, 1);
        $this->CreateFloatProfile('BPM.Conductivity', 'Electricity', '', ' mS/cm', 0, 20, 0.1, 1);
        $this->CreateIntegerProfile('BPM.Milliseconds', 'Clock', '', ' ms', 0, 60000, 1);

        if (!IPS_VariableProfileExists('BPM.FilterOpmode')) {
            IPS_CreateVariableProfile('BPM.FilterOpmode', VARIABLETYPE_INTEGER);
        }
        IPS_SetVariableProfileAssociation('BPM.FilterOpmode', 0, 'Auto', '', -1);
        IPS_SetVariableProfileAssociation('BPM.FilterOpmode', 1, 'Manuell', '', -1);
        IPS_SetVariableProfileAssociation('BPM.FilterOpmode', 2, 'Aus', '', -1);
    }

    private function CreateFloatProfile(string $name, string $icon, string $prefix, string $suffix, float $min, float $max, float $step, int $digits): void
    {
        if (!IPS_VariableProfileExists($name)) {
            IPS_CreateVariableProfile($name, VARIABLETYPE_FLOAT);
        }
        IPS_SetVariableProfileIcon($name, $icon);
        IPS_SetVariableProfileText($name, $prefix, $suffix);
        IPS_SetVariableProfileDigits($name, $digits);
        IPS_SetVariableProfileValues($name, $min, $max, $step);
    }

    private function CreateIntegerProfile(string $name, string $icon, string $prefix, string $suffix, int $min, int $max, int $step): void
    {
        if (!IPS_VariableProfileExists($name)) {
            IPS_CreateVariableProfile($name, VARIABLETYPE_INTEGER);
        }
        IPS_SetVariableProfileIcon($name, $icon);
        IPS_SetVariableProfileText($name, $prefix, $suffix);
        IPS_SetVariableProfileValues($name, $min, $max, $step);
    }

    private function UpdateKnownVariables(array $data): void
    {
        $this->SetFloatFromKey('pH', $data, self::API_KEYS['pH']);
        $this->SetIntegerFromKey('Redox', $data, self::API_KEYS['Redox']);
        $this->SetFloatFromKey('PoolTemperature', $data, self::API_KEYS['PoolTemperature']);

        $outdoorText = $this->CleanString((string)($data[self::API_KEYS['OutdoorTemperatureText']] ?? ''));
        $outdoor = $this->ExtractFirstNumber($outdoorText);
        if ($outdoor !== null) {
            $this->SetValueSafe('OutdoorTemperature', $outdoor);
        }

        $conductivityText = $this->CleanString((string)($data[self::API_KEYS['ConductivityText']] ?? ''));
        $conductivity = $this->ExtractFirstNumber($conductivityText);
        if ($conductivity !== null) {
            $this->SetValueSafe('Conductivity', $conductivity);
        }

        $lightStatus = $this->GetIntValue($data, self::API_KEYS['PoolLightStatus']);
        if ($lightStatus !== null) {
            $this->SetValueSafe('PoolLightActive', $lightStatus === 0);
        }
        $this->SetValueSafe('PoolLightText', $this->CleanString((string)($data[self::API_KEYS['PoolLightText']] ?? '')));

        $filterStatus = $this->GetIntValue($data, self::API_KEYS['FilterPumpStatus']);
        if ($filterStatus !== null) {
            $this->SetValueSafe('FilterPumpActive', $filterStatus === 0);
        }

        $filterOpmode = $this->GetIntValue($data, self::API_KEYS['FilterPumpOpmode']);
        if ($filterOpmode !== null) {
            $this->SetValueSafe('FilterPumpOpmode', $filterOpmode);
        }

        $filterText = $this->CleanString((string)($data[self::API_KEYS['FilterPumpText']] ?? ''));
        $this->SetValueSafe('FilterPumpText', $filterText);
        $this->SetValueSafe('FilterPumpDetailedMode', $this->ParseFilterDetailedMode($filterText));
    }

    private function ApiGet(array $keys): array
    {
        $host = trim($this->ReadPropertyString('Host'));
        $port = max(1, min(65535, $this->ReadPropertyInteger('Port')));
        $timeout = max(1, $this->ReadPropertyInteger('Timeout'));

        if ($host === '') {
            throw new Exception('Host is empty');
        }

        $sid = $this->CreateSid();
        $url = 'http://' . $host . ':' . $port . '/cgi-bin/webgui.fcgi?sid=' . rawurlencode($sid);
        $payload = json_encode(['get' => array_values($keys)]);

        if ($payload === false) {
            throw new Exception('JSON payload encoding failed');
        }

        $this->SendDebugMessage('API URL', $url);
        $this->SendDebugMessage('API Payload', $payload);

        $context = stream_context_create([
            'http' => [
                'method' => 'POST',
                'header' => "Content-Type: application/json;charset=UTF-8\r\nAccept: application/json\r\n",
                'content' => $payload,
                'timeout' => $timeout,
                'ignore_errors' => true
            ]
        ]);

        $started = microtime(true);
        $raw = @file_get_contents($url, false, $context);
        $durationMs = (int)round((microtime(true) - $started) * 1000);
        $headers = $http_response_header ?? [];
        $httpCode = $this->ExtractHttpCode($headers);

        if ($raw === false) {
            throw new Exception('HTTP request failed');
        }

        $this->SendDebugMessage('HTTP Code', (string)$httpCode);
        $this->SendDebugMessage('API Duration', $durationMs . ' ms');
        $this->SendDebugMessage('API Raw', (string)$raw);

        if ($httpCode !== 0 && ($httpCode < 200 || $httpCode >= 300)) {
            throw new Exception('HTTP error ' . $httpCode);
        }

        $json = json_decode((string)$raw, true);
        if (!is_array($json)) {
            throw new Exception('Invalid JSON response');
        }

        $apiStatus = (int)($json['status']['code'] ?? -1);
        if ($apiStatus !== 0) {
            throw new Exception('API status code ' . $apiStatus);
        }

        $json['_meta'] = [
            'duration_ms' => $durationMs,
            'http_code' => $httpCode,
            'requested_keys' => count($keys)
        ];

        return $json;
    }

    private function BuildDiscoveryListValues(): array
    {
        $raw = $this->GetVariableValueByIdent('DiscoveryResult');
        if (!is_string($raw) || trim($raw) === '' || strpos(trim($raw), '{') !== 0) {
            return [];
        }

        $decoded = json_decode($raw, true);
        if (!is_array($decoded)) {
            return [];
        }

        return $this->BuildDiscoveryListRowsFromData($decoded);
    }

    private function BuildDiscoveryListRowsFromData(array $decoded): array
    {
        $sourceKey = $this->ReadPropertyBoolean('DiscoveryOnlyFound') ? 'found' : 'tested';
        if (!isset($decoded[$sourceKey]) || !is_array($decoded[$sourceKey])) {
            return [];
        }

        $selectedKeys = array_flip($this->ParseKeyList($this->ReadPropertyString('SelectedImportKeys')));
        $favoriteKeys = array_flip($this->ParseKeyList($this->ReadPropertyString('FavoriteKeys')));
        $filterKey = mb_strtolower(trim($this->ReadPropertyString('DiscoveryFilterKey')));
        $filterValue = mb_strtolower(trim($this->ReadPropertyString('DiscoveryFilterValue')));
        $filterType = mb_strtolower(trim($this->ReadPropertyString('DiscoveryFilterType')));
        $rows = [];

        foreach ($decoded[$sourceKey] as $key => $info) {
            $value = (string)($info['value'] ?? '');
            $type = (string)($info['type'] ?? 'unknown');
            $name = (string)($info['name'] ?? $this->GetKnownKeyName((string)$key));
            $confidence = (string)($info['confidence'] ?? $this->GetConfidenceLevel((string)$key, $value));

            if ($filterKey !== '' && strpos(mb_strtolower((string)$key), $filterKey) === false) {
                continue;
            }
            if ($filterValue !== '' && strpos(mb_strtolower($value), $filterValue) === false) {
                continue;
            }
            if ($filterType !== '' && strpos(mb_strtolower($type), $filterType) === false) {
                continue;
            }

            $rows[] = [
                'favorite' => isset($favoriteKeys[$key]) ? 'ja' : '',
                'selected' => isset($selectedKeys[$key]) ? 'ja' : 'nein',
                'confidence' => $confidence,
                'key' => (string)$key,
                'name' => $name,
                'value' => $value,
                'type' => $type
            ];
        }

        return $rows;
    }

    private function UpdateDiscoveryFormList(array $rows): void
    {
        $encoded = json_encode($rows, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        if ($encoded === false) {
            $encoded = '[]';
        }
        $this->UpdateFormField('DiscoveryResultList', 'values', $encoded);
    }

    private function BuildDiscoveryKeys(): array
    {
        $groupStart = max(1, $this->ReadPropertyInteger('DiscoveryGroupStart'));
        $groupEnd = max($groupStart, $this->ReadPropertyInteger('DiscoveryGroupEnd'));
        $objectStart = max(1, $this->ReadPropertyInteger('DiscoveryObjectStart'));
        $objectEnd = max($objectStart, $this->ReadPropertyInteger('DiscoveryObjectEnd'));
        $maxKeys = max(1, min(5000, $this->ReadPropertyInteger('DiscoveryMaxKeys')));
        $suffixes = $this->GetDiscoverySuffixes();
        $keys = [];

        foreach (range($groupStart, $groupEnd) as $group) {
            foreach (range($objectStart, $objectEnd) as $object) {
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

    private function GetExplorerKeys(): array
    {
        return $this->ParseKeyList($this->ReadPropertyString('ExplorerKeys'));
    }

    private function GetDiscoverySuffixes(): array
    {
        $raw = str_replace(["\r\n", "\r", ',', ';'], "\n", $this->ReadPropertyString('DiscoverySuffixes'));
        $lines = explode("\n", $raw);
        $suffixes = [];

        foreach ($lines as $line) {
            $suffix = trim($line);
            if ($suffix === '' || strpos($suffix, '#') === 0 || strpos($suffix, '//') === 0) {
                continue;
            }
            if (!preg_match('/^[A-Za-z0-9_]+$/', $suffix)) {
                $this->SendDebugMessage('Discovery skipped invalid suffix', $suffix);
                continue;
            }
            $suffixes[$suffix] = $suffix;
        }

        return array_values($suffixes ?: ['value']);
    }

    private function ParseKeyList(string $rawKeys): array
    {
        $raw = str_replace(["\r\n", "\r", ',', ';'], "\n", $rawKeys);
        $lines = explode("\n", $raw);
        $keys = [];

        foreach ($lines as $line) {
            $key = trim($line);
            if ($key === '' || strpos($key, '#') === 0 || strpos($key, '//') === 0) {
                continue;
            }
            if (!preg_match('/^[0-9]+\.[0-9]+\.[A-Za-z0-9_]+$/', $key)) {
                $this->SendDebugMessage('Skipped invalid key', $key);
                continue;
            }
            $keys[$key] = $key;
        }

        return array_values($keys);
    }

    private function GetKnownKeyName(string $key): string
    {
        return self::KNOWN_KEYS[$key] ?? '';
    }

    private function GetConfidenceLevel(string $key, string $value): string
    {
        if (isset(self::KNOWN_KEYS[$key])) {
            return 'gruen - bekannt/getestet';
        }

        if ($value === '') {
            return 'rot - nicht gefunden';
        }

        if (preg_match('/^(13|34|55)\.[0-9]+\.(value|status|opmode|text1|text2)$/', $key)) {
            return 'gelb - plausibel/unbekannt';
        }

        return 'rot - neu/auffaellig';
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

    private function GetVariableValueByIdent(string $ident)
    {
        $id = @$this->GetIDForIdent($ident);
        if ($id === false || $id <= 0) {
            return null;
        }
        return GetValue($id);
    }

    private function ExtractHttpCode(array $headers): int
    {
        if (isset($headers[0]) && preg_match('/HTTP\/\S+\s+(\d+)/', $headers[0], $match)) {
            return (int)$match[1];
        }
        return 0;
    }

    private function CreateSid(): string
    {
        return 'SYMBAYROL' . substr(strtoupper(md5((string) microtime(true) . mt_rand())), 0, 23);
    }

    private function UpdateTimer(): void
    {
        $interval = $this->ReadPropertyInteger('UpdateInterval');
        if ($interval < 5) {
            $interval = 0;
        }
        $this->SetTimerInterval(self::TIMER_UPDATE, $interval * 1000);
    }

    private function SetFloatFromKey(string $ident, array $data, string $key): void
    {
        if (!array_key_exists($key, $data)) {
            return;
        }
        $value = str_replace(',', '.', $this->CleanString((string)$data[$key]));
        if (is_numeric($value)) {
            $this->SetValueSafe($ident, (float)$value);
        }
    }

    private function SetIntegerFromKey(string $ident, array $data, string $key): void
    {
        $value = $this->GetIntValue($data, $key);
        if ($value !== null) {
            $this->SetValueSafe($ident, $value);
        }
    }

    private function GetIntValue(array $data, string $key): ?int
    {
        if (!array_key_exists($key, $data)) {
            return null;
        }
        $value = $this->CleanString((string)$data[$key]);
        if ($value === '' || !is_numeric($value)) {
            return null;
        }
        return (int)$value;
    }

    private function ExtractFirstNumber(string $text): ?float
    {
        $normalized = str_replace(',', '.', $text);
        if (preg_match('/-?[0-9]+(?:\.[0-9]+)?/', $normalized, $match)) {
            return (float)$match[0];
        }
        return null;
    }

    private function CleanString(string $value): string
    {
        return trim(html_entity_decode(strip_tags($value), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
    }

    private function ParseFilterDetailedMode(string $text): string
    {
        if (stripos($text, 'Eco') !== false) {
            return 'Eco';
        }
        if (stripos($text, 'Normal') !== false) {
            return 'Normal';
        }
        if (stripos($text, 'erhoeht') !== false || stripos($text, 'erhöht') !== false || stripos($text, 'High') !== false) {
            return 'High';
        }
        if ($text === 'Filterpumpe') {
            return 'Auto/Aus';
        }
        return $text;
    }

    private function HandleError(string $context, Throwable $e): void
    {
        $this->SetValueSafe('ConnectionState', false);
        $this->SetValueSafe('LastError', $e->getMessage());
        $this->SetValueSafe('LastUpdate', date('Y-m-d H:i:s'));
        $this->SetStatus(self::STATUS_API_ERROR);
        $this->SendDebugMessage($context . ' error', $e->getMessage());
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
