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

    public function Create()
    {
        parent::Create();
        $this->RegisterPropertyString('Host', '192.168.55.23');
        $this->RegisterPropertyInteger('Port', 80);
        $this->RegisterPropertyInteger('UpdateInterval', 60);
        $this->RegisterPropertyInteger('Timeout', 10);
        $this->RegisterPropertyBoolean('DebugMode', false);
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
                ['type' => 'Label', 'caption' => 'Discovery und Reverse Engineering erfolgen ausschliesslich im separaten BayrolDiscovery-Modul.'],
                ['type' => 'Label', 'caption' => 'Discovery-Import liest lokal aus: ' . $this->GetDiscoveryStorageDirectory()]
            ],
            'actions' => [
                ['type' => 'Button', 'caption' => 'Verbindung testen', 'onClick' => 'echo BPM_TestConnection($id) ? "Verbindung erfolgreich." : "Verbindung fehlgeschlagen. Siehe Status und Debug-Ausgabe.";'],
                ['type' => 'Button', 'caption' => 'Werte jetzt aktualisieren', 'onClick' => 'BPM_UpdateValues($id); echo "Aktualisierung ausgefuehrt. Siehe Variablen, Status und Debug-Ausgabe.";'],
                ['type' => 'Button', 'caption' => 'Discovery-Import anwenden', 'onClick' => 'echo BPM_ImportDiscovery($id);']
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
            $this->SetValueSafe('LastApiStatus', (int) ($response['status']['code'] ?? -1));
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

    public function ImportDiscovery(): string
    {
        try {
            $definitions = $this->LoadDiscoveryDefinitions();
            if (count($definitions) === 0) {
                $message = 'Discovery-Import: Keine API-Keys fuer Gateway-Import aktiviert.';
                $this->SetValueSafe('DiscoveryImportStatus', $message);
                return $message;
            }

            $rootId = $this->EnsureCategory($this->InstanceID, 'BPMDiscoveryImport', 'Discovery Import', 500);
            $created = 0;
            $reused = 0;
            $renamed = 0;
            $typeConflicts = 0;
            $positionByDevice = [];

            foreach ($definitions as $definition) {
                $deviceCode = $definition['device_code'];
                $deviceCategoryId = $this->EnsureCategory(
                    $rootId,
                    'BPMDevice_' . $this->MakeSafeIdent($deviceCode),
                    $definition['device_name'],
                    10
                );
                $positionByDevice[$deviceCode] = ($positionByDevice[$deviceCode] ?? 0) + 10;
                $result = $this->EnsureImportedVariable($deviceCategoryId, $definition, $positionByDevice[$deviceCode]);
                if ($result === 'created') {
                    $created++;
                } elseif ($result === 'renamed') {
                    $renamed++;
                } elseif ($result === 'type_conflict') {
                    $typeConflicts++;
                } else {
                    $reused++;
                }
            }

            $message = 'Discovery-Import angewendet. Aktiviert: ' . count($definitions) . ', neu: ' . $created . ', vorhanden: ' . $reused . ', umbenannt: ' . $renamed . ', Typkonflikte: ' . $typeConflicts . '.';
            $this->SetValueSafe('DiscoveryImportStatus', $message);
            $this->SetValueSafe('DiscoveryImportedKeys', count($definitions));
            return $message;
        } catch (Throwable $e) {
            $message = 'Discovery-Import Fehler: ' . $e->getMessage();
            $this->SetValueSafe('DiscoveryImportStatus', $message);
            return $message;
        }
    }

    public function UpdateValues(): void
    {
        try {
            $discoveryDefinitions = $this->LoadDiscoveryDefinitionsSafe();
            $keys = array_values(self::API_KEYS);
            foreach ($discoveryDefinitions as $definition) {
                $keys[] = $definition['api_key'];
            }
            $keys = array_values(array_unique($keys));

            $this->SendDebugMessage('UpdateValues', 'Reading ' . count($keys) . ' keys');
            $response = $this->ApiGet($keys);
            $data = $response['data'] ?? [];
            if (!is_array($data)) {
                throw new Exception('Invalid API data block');
            }

            $this->SetValueSafe('ConnectionState', true);
            $this->SetValueSafe('LastApiStatus', (int) ($response['status']['code'] ?? 0));
            $this->SetValueSafe('LastError', '');
            $this->SetValueSafe('LastUpdate', date('Y-m-d H:i:s'));
            $this->SetValueSafe('LastSuccessfulUpdate', date('Y-m-d H:i:s'));
            $this->SetValueSafe('ResponseTimeMs', (int) ($response['_meta']['duration_ms'] ?? 0));
            $this->SetValueSafe('ReceivedDataPoints', count($data));

            $this->UpdateKnownVariables($data);
            $this->UpdateImportedVariables($data, $discoveryDefinitions);
            $this->SetStatus(self::STATUS_ACTIVE);
        } catch (Throwable $e) {
            $this->HandleError('UpdateValues', $e);
        }
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
        $this->RegisterVariableString('DiscoveryImportStatus', 'Discovery Import Status', '', 220);
        $this->RegisterVariableInteger('DiscoveryImportedKeys', 'Discovery Importierte Keys', '', 221);
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

        $outdoor = $this->ExtractFirstNumber($this->CleanString((string) ($data[self::API_KEYS['OutdoorTemperatureText']] ?? '')));
        if ($outdoor !== null) {
            $this->SetValueSafe('OutdoorTemperature', $outdoor);
        }
        $conductivity = $this->ExtractFirstNumber($this->CleanString((string) ($data[self::API_KEYS['ConductivityText']] ?? '')));
        if ($conductivity !== null) {
            $this->SetValueSafe('Conductivity', $conductivity);
        }

        $lightStatus = $this->GetIntValue($data, self::API_KEYS['PoolLightStatus']);
        if ($lightStatus !== null) {
            $this->SetValueSafe('PoolLightActive', $lightStatus === 0);
        }
        $this->SetValueSafe('PoolLightText', $this->CleanString((string) ($data[self::API_KEYS['PoolLightText']] ?? '')));

        $filterStatus = $this->GetIntValue($data, self::API_KEYS['FilterPumpStatus']);
        if ($filterStatus !== null) {
            $this->SetValueSafe('FilterPumpActive', $filterStatus === 0);
        }
        $filterOpmode = $this->GetIntValue($data, self::API_KEYS['FilterPumpOpmode']);
        if ($filterOpmode !== null) {
            $this->SetValueSafe('FilterPumpOpmode', $filterOpmode);
        }
        $filterText = $this->CleanString((string) ($data[self::API_KEYS['FilterPumpText']] ?? ''));
        $this->SetValueSafe('FilterPumpText', $filterText);
        $this->SetValueSafe('FilterPumpDetailedMode', $this->ParseFilterDetailedMode($filterText));
    }

    private function LoadDiscoveryDefinitionsSafe(): array
    {
        try {
            return $this->LoadDiscoveryDefinitions();
        } catch (Throwable $e) {
            $this->SendDebugMessage('Discovery Import', $e->getMessage());
            return [];
        }
    }

    private function LoadDiscoveryDefinitions(): array
    {
        $directory = $this->GetDiscoveryStorageDirectory();
        $apiPath = $directory . DIRECTORY_SEPARATOR . 'api_keys.csv';
        $deviceKeysPath = $directory . DIRECTORY_SEPARATOR . 'device_keys.csv';
        $devicesPath = $directory . DIRECTORY_SEPARATOR . 'devices.csv';
        if (!is_file($apiPath)) {
            throw new Exception('api_keys.csv nicht gefunden: ' . $apiPath);
        }
        if (!is_file($deviceKeysPath)) {
            throw new Exception('device_keys.csv nicht gefunden: ' . $deviceKeysPath);
        }

        $apiRows = $this->ReadCsvAssocFile($apiPath);
        $deviceKeyRows = $this->ReadCsvAssocFile($deviceKeysPath);
        $deviceRows = is_file($devicesPath) ? $this->ReadCsvAssocFile($devicesPath) : [];

        $devices = [];
        foreach ($deviceRows as $row) {
            $code = trim((string) ($row['code'] ?? ''));
            if ($code !== '') {
                $devices[$code] = $row;
            }
        }

        $deviceByKey = [];
        $roleByKey = [];
        foreach ($deviceKeyRows as $row) {
            $apiKey = trim((string) ($row['api_key'] ?? ''));
            if ($apiKey === '') {
                continue;
            }
            $deviceByKey[$apiKey] = trim((string) ($row['device_code'] ?? ''));
            $roleByKey[$apiKey] = trim((string) ($row['role'] ?? ''));
        }

        $definitions = [];
        foreach ($apiRows as $row) {
            if (($row['gateway_import_enabled'] ?? '0') !== '1') {
                continue;
            }
            $apiKey = trim((string) ($row['api_key'] ?? ''));
            if ($apiKey === '') {
                continue;
            }

            $deviceCode = $deviceByKey[$apiKey] ?? 'unassigned';
            if ($deviceCode === '') {
                $deviceCode = 'unassigned';
            }
            $deviceName = trim((string) ($devices[$deviceCode]['name'] ?? ''));
            if ($deviceName === '') {
                $deviceName = $deviceCode === 'unassigned' ? 'Nicht zugeordnet' : $deviceCode;
            }
            $customName = trim((string) ($row['gateway_variable_name'] ?? ''));
            $suggestedName = trim((string) ($row['suggested_name'] ?? ''));
            $variableName = $customName !== '' ? $customName : ($suggestedName !== '' ? $suggestedName : $apiKey);

            $definition = [
                'api_key' => $apiKey,
                'variable_name' => $variableName,
                'value_type' => trim((string) ($row['value_type'] ?? 'string')),
                'device_code' => $deviceCode,
                'device_name' => $deviceName,
                'role' => $roleByKey[$apiKey] ?? '',
                'confidence' => trim((string) ($row['confidence'] ?? '')),
                'profile' => '',
                'transform' => 'generic'
            ];
            $definitions[] = $this->ApplyKnownImportSemantics($definition);
        }

        usort($definitions, static function (array $a, array $b): int {
            $deviceCompare = strcmp($a['device_code'], $b['device_code']);
            return $deviceCompare !== 0 ? $deviceCompare : strcmp($a['api_key'], $b['api_key']);
        });
        return $definitions;
    }

    private function ApplyKnownImportSemantics(array $definition): array
    {
        switch ($definition['api_key']) {
            case '34.4001.value':
                $definition['value_type'] = 'float';
                $definition['profile'] = 'BPM.pH';
                break;
            case '34.4022.value':
                $definition['value_type'] = 'integer';
                $definition['profile'] = 'BPM.Redox';
                break;
            case '34.4033.value':
                $definition['value_type'] = 'float';
                $definition['profile'] = '~Temperature';
                break;
            case '55.17102.status':
            case '55.17106.status':
                $definition['value_type'] = 'boolean-candidate';
                $definition['profile'] = '~Switch';
                $definition['transform'] = 'active_when_zero';
                break;
            case '55.17106.opmode':
                $definition['value_type'] = 'integer';
                $definition['profile'] = 'BPM.FilterOpmode';
                break;
            case '55.17102.value':
            case '55.17106.value':
                $definition['value_type'] = 'string';
                break;
        }
        return $definition;
    }

    private function EnsureImportedVariable(int $parentId, array $definition, int $position): string
    {
        $ident = $this->GetImportedVariableIdent($definition['api_key']);
        $expectedType = $this->MapDiscoveryTypeToSymconType($definition['value_type']);
        $variableId = @IPS_GetObjectIDByIdent($ident, $parentId);
        $created = false;

        if ($variableId === false || $variableId <= 0) {
            $variableId = IPS_CreateVariable($expectedType);
            IPS_SetParent($variableId, $parentId);
            IPS_SetIdent($variableId, $ident);
            $created = true;
        } else {
            $variable = IPS_GetVariable($variableId);
            if ((int) ($variable['VariableType'] ?? -1) !== $expectedType) {
                return 'type_conflict';
            }
        }

        IPS_SetPosition($variableId, $position);
        IPS_SetInfo($variableId, 'Bayrol Discovery API-Key: ' . $definition['api_key'] . '; Rolle: ' . $definition['role'] . '; Transform: ' . $definition['transform']);
        $profile = (string) ($definition['profile'] ?? '');
        if ($profile !== '' && IPS_VariableProfileExists($profile)) {
            IPS_SetVariableCustomProfile($variableId, $profile);
        }

        $object = IPS_GetObject($variableId);
        $renamed = (($object['ObjectName'] ?? '') !== $definition['variable_name']);
        if ($renamed) {
            IPS_SetName($variableId, $definition['variable_name']);
        }
        if ($created) {
            return 'created';
        }
        return $renamed ? 'renamed' : 'reused';
    }

    private function UpdateImportedVariables(array $data, array $definitions): void
    {
        $rootId = @IPS_GetObjectIDByIdent('BPMDiscoveryImport', $this->InstanceID);
        if ($rootId === false || $rootId <= 0) {
            return;
        }

        foreach ($definitions as $definition) {
            if (!array_key_exists($definition['api_key'], $data)) {
                continue;
            }
            $deviceCategoryId = @IPS_GetObjectIDByIdent('BPMDevice_' . $this->MakeSafeIdent($definition['device_code']), $rootId);
            if ($deviceCategoryId === false || $deviceCategoryId <= 0) {
                continue;
            }
            $variableId = @IPS_GetObjectIDByIdent($this->GetImportedVariableIdent($definition['api_key']), $deviceCategoryId);
            if ($variableId === false || $variableId <= 0) {
                continue;
            }

            $raw = $this->CleanString((string) $data[$definition['api_key']]);
            if (($definition['transform'] ?? 'generic') === 'active_when_zero') {
                if (is_numeric($raw)) {
                    SetValue($variableId, ((int) $raw) === 0);
                }
                continue;
            }

            $variable = IPS_GetVariable($variableId);
            $type = (int) ($variable['VariableType'] ?? VARIABLETYPE_STRING);
            if ($type === VARIABLETYPE_BOOLEAN) {
                if (is_numeric($raw)) {
                    SetValue($variableId, ((int) $raw) !== 0);
                }
            } elseif ($type === VARIABLETYPE_INTEGER) {
                $normalized = str_replace(',', '.', $raw);
                if (is_numeric($normalized)) {
                    SetValue($variableId, (int) ((float) $normalized));
                }
            } elseif ($type === VARIABLETYPE_FLOAT) {
                $normalized = str_replace(',', '.', $raw);
                if (is_numeric($normalized)) {
                    SetValue($variableId, (float) $normalized);
                }
            } else {
                SetValue($variableId, $raw);
            }
        }
    }

    private function EnsureCategory(int $parentId, string $ident, string $name, int $position): int
    {
        $categoryId = @IPS_GetObjectIDByIdent($ident, $parentId);
        if ($categoryId === false || $categoryId <= 0) {
            $categoryId = IPS_CreateCategory();
            IPS_SetParent($categoryId, $parentId);
            IPS_SetIdent($categoryId, $ident);
        }
        IPS_SetName($categoryId, $name);
        IPS_SetPosition($categoryId, $position);
        return $categoryId;
    }

    private function ReadCsvAssocFile(string $path): array
    {
        $handle = fopen($path, 'rb');
        if ($handle === false) {
            throw new Exception('CSV konnte nicht gelesen werden: ' . $path);
        }
        flock($handle, LOCK_SH);
        $header = fgetcsv($handle, 0, ';');
        if (!is_array($header)) {
            flock($handle, LOCK_UN);
            fclose($handle);
            return [];
        }
        $rows = [];
        while (($data = fgetcsv($handle, 0, ';')) !== false) {
            $row = [];
            foreach ($header as $index => $key) {
                $row[$key] = $data[$index] ?? '';
            }
            if (implode('', $row) !== '') {
                $rows[] = $row;
            }
        }
        flock($handle, LOCK_UN);
        fclose($handle);
        return $rows;
    }

    private function MapDiscoveryTypeToSymconType(string $valueType): int
    {
        if ($valueType === 'float') {
            return VARIABLETYPE_FLOAT;
        }
        if ($valueType === 'integer') {
            return VARIABLETYPE_INTEGER;
        }
        if ($valueType === 'boolean-candidate') {
            return VARIABLETYPE_BOOLEAN;
        }
        return VARIABLETYPE_STRING;
    }

    private function GetImportedVariableIdent(string $apiKey): string
    {
        return 'BPMImport_' . substr(sha1($apiKey), 0, 20);
    }

    private function MakeSafeIdent(string $value): string
    {
        $safe = preg_replace('/[^A-Za-z0-9_]/', '_', $value);
        return $safe !== null && $safe !== '' ? $safe : 'unknown';
    }

    private function GetDiscoveryStorageDirectory(): string
    {
        return rtrim(IPS_GetKernelDir(), DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . 'user' . DIRECTORY_SEPARATOR . 'BayrolDiscovery';
    }

    private function ApiGet(array $keys): array
    {
        $host = trim($this->ReadPropertyString('Host'));
        $port = max(1, min(65535, $this->ReadPropertyInteger('Port')));
        $timeout = max(1, $this->ReadPropertyInteger('Timeout'));
        if ($host === '') {
            throw new Exception('Host is empty');
        }
        $url = 'http://' . $host . ':' . $port . '/cgi-bin/webgui.fcgi?sid=' . rawurlencode($this->CreateSid());
        $payload = json_encode(['get' => array_values($keys)]);
        if ($payload === false) {
            throw new Exception('JSON payload encoding failed');
        }
        $this->SendDebugMessage('API URL', $url);
        $this->SendDebugMessage('API Payload', $payload);
        $context = stream_context_create(['http' => ['method' => 'POST', 'header' => "Content-Type: application/json;charset=UTF-8\r\nAccept: application/json\r\n", 'content' => $payload, 'timeout' => $timeout, 'ignore_errors' => true]]);
        $started = microtime(true);
        $raw = @file_get_contents($url, false, $context);
        $durationMs = (int) round((microtime(true) - $started) * 1000);
        $headers = $http_response_header ?? [];
        $httpCode = $this->ExtractHttpCode($headers);
        if ($raw === false) {
            throw new Exception('HTTP request failed');
        }
        $this->SendDebugMessage('HTTP Code', (string) $httpCode);
        $this->SendDebugMessage('API Duration', $durationMs . ' ms');
        $this->SendDebugMessage('API Raw', (string) $raw);
        if ($httpCode !== 0 && ($httpCode < 200 || $httpCode >= 300)) {
            throw new Exception('HTTP error ' . $httpCode);
        }
        $json = json_decode((string) $raw, true);
        if (!is_array($json)) {
            throw new Exception('Invalid JSON response');
        }
        $apiStatus = (int) ($json['status']['code'] ?? -1);
        if ($apiStatus !== 0) {
            throw new Exception('API status code ' . $apiStatus);
        }
        $json['_meta'] = ['duration_ms' => $durationMs, 'http_code' => $httpCode, 'requested_keys' => count($keys)];
        return $json;
    }

    private function ExtractHttpCode(array $headers): int
    {
        if (isset($headers[0]) && preg_match('/HTTP\/\S+\s+(\d+)/', $headers[0], $match)) {
            return (int) $match[1];
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
        $value = str_replace(',', '.', $this->CleanString((string) $data[$key]));
        if (is_numeric($value)) {
            $this->SetValueSafe($ident, (float) $value);
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
        $value = $this->CleanString((string) $data[$key]);
        if ($value === '' || !is_numeric($value)) {
            return null;
        }
        return (int) $value;
    }

    private function ExtractFirstNumber(string $text): ?float
    {
        $normalized = str_replace(',', '.', $text);
        if (preg_match('/-?[0-9]+(?:\.[0-9]+)?/', $normalized, $match)) {
            return (float) $match[0];
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
