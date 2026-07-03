<?php

class BayrolPoolManager extends IPSModule
{
    private const STATUS_ACTIVE = 102;
    private const STATUS_INACTIVE = 104;
    private const STATUS_CONFIG_ERROR = 200;
    private const STATUS_CONNECTION_ERROR = 201;
    private const STATUS_LOGIN_ERROR = 202;
    private const STATUS_API_ERROR = 203;

    public function Create()
    {
        parent::Create();

        $this->RegisterPropertyString('Host', '192.168.55.23');
        $this->RegisterPropertyInteger('Port', 80);
        $this->RegisterPropertyString('Username', '');
        $this->RegisterPropertyString('Password', '');
        $this->RegisterPropertyInteger('PollInterval', 30);

        $this->SetBuffer('SID', '');
        $this->RegisterTimer('UpdateTimer', 0, 'BPM_Update($_IPS["TARGET"]);');
    }

    public function ApplyChanges()
    {
        parent::ApplyChanges();

        $this->RegisterVariables();

        if (!$this->HasBasicConfiguration()) {
            $this->SetTimerInterval('UpdateTimer', 0);
            $this->SetStatus(self::STATUS_CONFIG_ERROR);
            return;
        }

        $interval = $this->ReadPropertyInteger('PollInterval');
        $this->SetTimerInterval('UpdateTimer', max(5, $interval) * 1000);
        $this->SetStatus(self::STATUS_INACTIVE);
    }

    public function TestConnection()
    {
        if (!$this->HasBasicConfiguration()) {
            $this->SetStatus(self::STATUS_CONFIG_ERROR);
            throw new Exception('Bitte Host, Benutzername und Passwort/PIN konfigurieren.');
        }

        $this->Login(true);
        $this->Update();
        return true;
    }

    public function Update()
    {
        if (!$this->HasBasicConfiguration()) {
            $this->SetStatus(self::STATUS_CONFIG_ERROR);
            return;
        }

        $keys = $this->GetDefaultKeys();
        $response = $this->Request(['get' => $keys]);

        if (!$this->IsStatusOk($response)) {
            $this->SendDebug('Update', 'Session ungueltig oder API-Fehler. Neuer Login wird versucht.', 0);
            $this->Login(true);
            $response = $this->Request(['get' => $keys]);
        }

        if (!$this->IsStatusOk($response)) {
            $this->SetStatus(self::STATUS_API_ERROR);
            throw new Exception('PoolManager API Fehler: ' . json_encode($response));
        }

        $data = $response['data'] ?? [];

        $this->SetFloatValue('PH', $data['34.4001.value'] ?? null);
        $this->SetFloatValue('Redox', $data['34.4022.value'] ?? null);
        $this->SetFloatValue('Temperature', $data['34.4033.value'] ?? null);
        $this->SetStringValue('Status1', $this->CleanText($data['15.16701.value'] ?? ''));
        $this->SetStringValue('Status2', $this->CleanText($data['15.16704.value'] ?? ''));
        $this->SetStringValue('Status3', $this->CleanText($data['15.16705.value'] ?? ''));
        $this->SetIntegerValue('FilterPumpStatus', $data['55.17106.status'] ?? null);
        $this->SetStringValue('FilterPumpText', $this->CleanText($data['55.17106.value'] ?? ''));

        $this->SetStatus(self::STATUS_ACTIVE);
    }

    private function RegisterVariables()
    {
        $this->RegisterVariableFloat('PH', 'pH', '', 10);
        $this->RegisterVariableFloat('Redox', 'Redox', '', 20);
        $this->RegisterVariableFloat('Temperature', 'Temperatur', '~Temperature', 30);
        $this->RegisterVariableString('Status1', 'Status 1', '', 40);
        $this->RegisterVariableString('Status2', 'Status 2', '', 50);
        $this->RegisterVariableString('Status3', 'Status 3', '', 60);
        $this->RegisterVariableInteger('FilterPumpStatus', 'Filterpumpe Status', '', 70);
        $this->RegisterVariableString('FilterPumpText', 'Filterpumpe Text', '', 80);
    }

    private function GetDefaultKeys()
    {
        return [
            '34.4001.value',  // pH
            '34.4022.value',  // Redox mV
            '34.4033.value',  // Temperatur
            '15.16701.value', // Status 1
            '15.16704.value', // Status 2
            '15.16705.value', // Status 3
            '55.17106.status',
            '55.17106.value'
        ];
    }

    private function Login(bool $force = false)
    {
        $sid = $this->GetBuffer('SID');
        if (!$force && $sid !== '') {
            return $sid;
        }

        $sid = $this->GenerateSessionId();
        $this->SetBuffer('SID', $sid);

        $payload = [
            'set' => [
                '9.17401.user' => $this->ReadPropertyString('Username'),
                '9.17401.pass' => $this->ReadPropertyString('Password')
            ]
        ];

        $response = $this->PostJson($sid, $payload);
        $this->SendDebug('Login response', json_encode($response), 0);

        if (!$this->IsStatusOk($response)) {
            $this->SetStatus(self::STATUS_LOGIN_ERROR);
            $this->SetBuffer('SID', '');
            throw new Exception('Login am PoolManager fehlgeschlagen: ' . json_encode($response));
        }

        return $sid;
    }

    private function Request(array $payload)
    {
        $sid = $this->Login(false);
        return $this->PostJson($sid, $payload);
    }

    private function PostJson(string $sid, array $payload)
    {
        $url = $this->BuildUrl($sid);
        $body = json_encode($payload);

        $options = [
            'http' => [
                'method' => 'POST',
                'header' => "Content-Type: application/json;charset=UTF-8\r\nAccept: application/json\r\n",
                'content' => $body,
                'timeout' => 5,
                'ignore_errors' => true
            ]
        ];

        $this->SendDebug('POST ' . $url, $body, 0);

        $context = stream_context_create($options);
        $raw = @file_get_contents($url, false, $context);

        if ($raw === false) {
            $this->SetStatus(self::STATUS_CONNECTION_ERROR);
            throw new Exception('Keine Antwort vom PoolManager unter ' . $url);
        }

        $this->SendDebug('Raw response', $raw, 0);

        $decoded = json_decode($raw, true);
        if (!is_array($decoded)) {
            $this->SetStatus(self::STATUS_API_ERROR);
            throw new Exception('Antwort ist kein gueltiges JSON: ' . $raw);
        }

        return $decoded;
    }

    private function BuildUrl(string $sid)
    {
        $host = trim($this->ReadPropertyString('Host'));
        $port = $this->ReadPropertyInteger('Port');
        return 'http://' . $host . ':' . $port . '/cgi-bin/webgui.fcgi?sid=' . rawurlencode($sid);
    }

    private function HasBasicConfiguration()
    {
        return trim($this->ReadPropertyString('Host')) !== ''
            && trim($this->ReadPropertyString('Username')) !== ''
            && $this->ReadPropertyString('Password') !== '';
    }

    private function IsStatusOk($response)
    {
        return is_array($response) && (($response['status']['code'] ?? null) === 0);
    }

    private function GenerateSessionId()
    {
        $chars = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
        $sid = '';
        for ($i = 0; $i < 32; $i++) {
            $sid .= $chars[random_int(0, strlen($chars) - 1)];
        }
        return $sid;
    }

    private function CleanText($value)
    {
        return trim(html_entity_decode(strip_tags((string)$value), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
    }

    private function SetFloatValue(string $ident, $value)
    {
        if ($value === null || $value === '') {
            return;
        }
        SetValue($this->GetIDForIdent($ident), (float)str_replace(',', '.', (string)$value));
    }

    private function SetIntegerValue(string $ident, $value)
    {
        if ($value === null || $value === '') {
            return;
        }
        SetValue($this->GetIDForIdent($ident), (int)$value);
    }

    private function SetStringValue(string $ident, string $value)
    {
        SetValue($this->GetIDForIdent($ident), $value);
    }
}
