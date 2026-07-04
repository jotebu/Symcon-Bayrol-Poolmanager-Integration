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
                [
                    'type' => 'ValidationTextBox',
                    'name' => 'Host',
                    'caption' => 'PoolManager IP / Host'
                ],
                [
                    'type' => 'NumberSpinner',
                    'name' => 'Port',
                    'caption' => 'Port'
                ],
                [
                    'type' => 'NumberSpinner',
                    'name' => 'UpdateInterval',
                    'caption' => 'Aktualisierungsintervall in Sekunden'
                ],
                [
                    'type' => 'NumberSpinner',
                    'name' => 'Timeout',
                    'caption' => 'HTTP Timeout in Sekunden'
                ],
                [
                    'type' => 'CheckBox',
                    'name' => 'DebugMode',
                    'caption' => 'Erweiterte Debug-Ausgaben'
                ]
            ],
            'actions' => [
                [
                    'type' => 'Button',
                    'caption' => 'Verbindung testen',
                    'onClick' => 'echo BPM_TestConnection($id) ? "Verbindung erfolgreich." : "Verbindung fehlgeschlagen. Siehe Status und Debug-Ausgabe.";'
                ],
                [
                    'type' => 'Button',
                    'caption' => 'Werte jetzt aktualisieren',
                    'onClick' => 'BPM_UpdateValues($id); echo "Aktualisierung ausgefuehrt. Siehe Variablen, Status und Debug-Ausgabe.";'
                ],
                [
                    'type' => 'Label',
                    'caption' => 'Version 0.1.0-alpha: Lesender Zugriff auf bekannte PM5 API-Datenpunkte.'
                ]
            ],
            'status' => [
                [
                    'code' => self::STATUS_ACTIVE,
                    'icon' => 'active',
                    'caption' => 'Aktiv'
                ],
                [
                    'code' => self::STATUS_INACTIVE,
                    'icon' => 'inactive',
                    'caption' => 'Inaktiv / noch nicht aktualisiert'
                ],
                [
                    'code' => self::STATUS_HOST_MISSING,
                    'icon' => 'error',
                    'caption' => 'Keine Host-Adresse konfiguriert'
                ],
                [
                    'code' => self::STATUS_API_ERROR,
                    'icon' => 'error',
                    'caption' => 'PoolManager nicht erreichbar oder API-Fehler'
                ]
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

            $this->UpdateKnownVariables($data);
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
        $this->RegisterVariableInteger('LastApiStatus', 'Letzter API Status', '', 202);
        $this->RegisterVariableString('LastError', 'Letzter Fehler', '', 203);
    }

    private function CreateProfiles(): void
    {
        $this->CreateFloatProfile('BPM.pH', 'Gauge', '', '', 0, 14, 0.01, 2);
        $this->CreateIntegerProfile('BPM.Redox', 'Electricity', '', ' mV', 0, 1000, 1);
        $this->CreateFloatProfile('BPM.Conductivity', 'Electricity', '', ' mS/cm', 0, 20, 0.1, 1);

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

        $raw = @file_get_contents($url, false, $context);
        $headers = $http_response_header ?? [];
        $httpCode = $this->ExtractHttpCode($headers);

        if ($raw === false) {
            throw new Exception('HTTP request failed');
        }

        $this->SendDebugMessage('HTTP Code', (string)$httpCode);
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

        return $json;
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
