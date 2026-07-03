<?php

class BayrolPoolManager extends IPSModule
{
    public function Create()
    {
        parent::Create();

        $this->RegisterPropertyString('Host', '192.168.55.23');
        $this->RegisterPropertyInteger('Port', 80);
        $this->RegisterPropertyString('Username', '');
        $this->RegisterPropertyString('Password', '');
        $this->RegisterPropertyInteger('PollInterval', 30);

        $this->RegisterTimer('UpdateTimer', 0, 'BPM_Update($_IPS["TARGET"]);');
    }

    public function ApplyChanges()
    {
        parent::ApplyChanges();

        $this->RegisterVariables();

        $interval = $this->ReadPropertyInteger('PollInterval');
        $this->SetTimerInterval('UpdateTimer', max(5, $interval) * 1000);
    }

    public function TestConnection()
    {
        $this->Login(true);
        $this->Update();
        return true;
    }

    public function Update()
    {
        $keys = [
            '34.4001.value',  // pH
            '34.4022.value',  // Redox mV
            '34.4033.value',  // Temperatur
            '15.16701.value', // Status 1
            '15.16704.value', // Status 2
            '15.16705.value', // Status 3
            '55.17106.status',
            '55.17106.value'
        ];

        $response = $this->Request(['get' => $keys]);

        if (($response['status']['code'] ?? null) !== 0) {
            $this->SendDebug('Update', 'Session ungueltig oder Fehler. Neuer Login wird versucht.', 0);
            $this->Login(true);
            $response = $this->Request(['get' => $keys]);
        }

        if (($response['status']['code'] ?? null) !== 0) {
            throw new Exception('PoolManager API Fehler: ' . json_encode($response));
        }

        $data = $response['data'] ?? [];

        $this->SetFloat('PH', $data['34.4001.value'] ?? null);
        $this->SetFloat('Redox', $data['34.4022.value'] ?? null);
        $this->SetFloat('Temperature', $data['34.4033.value'] ?? null);
        $this->SetString('Status1', $this->CleanText($data['15.16701.value'] ?? ''));
        $this->SetString('Status2', $this->CleanText($data['15.16704.value'] ?? ''));
        $this->SetString('Status3', $this->CleanText($data['15.16705.value'] ?? ''));
        $this->SetInteger('FilterPumpStatus', $data['55.17106.status'] ?? null);
        $this->SetString('FilterPumpText', $this->CleanText($data['55.17106.value'] ?? ''));

        $this->SetStatus(102);
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

    private function Login(bool $force = false)
    {
        $sid = $this->GetBuffer('SID');
        if (!$force && $sid !== '') {
            return $sid;
        }

        $sid = $this->GenerateSessionId();
        $this->SetBuffer('SID', $sid);

        $username = $this->ReadPropertyString('Username');
        $password = $this->ReadPropertyString('Password');

        $payload = [
            'set' => [
                '9.17401.user' => $username,
                '9.17401.pass' => $password
            ]
        ];

        $response = $this->PostJson($sid, $payload);
        $this->SendDebug('Login response', json_encode($response), 0);

        if (($response['status']['code'] ?? null) !== 0) {
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
        $host = trim($this->ReadPropertyString('Host'));
        $port = $this->ReadPropertyInteger('Port');
        $url = 'http://' . $host . ':' . $port . '/cgi-bin/webgui.fcgi?sid=' . rawurlencode($sid);
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
            throw new Exception('Keine Antwort vom PoolManager unter ' . $url);
        }

        $this->SendDebug('Raw response', $raw, 0);

        $decoded = json_decode($raw, true);
        if (!is_array($decoded)) {
            throw new Exception('Antwort ist kein gueltiges JSON: ' . $raw);
        }

        return $decoded;
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
        return trim(strip_tags((string)$value));
    }

    private function SetFloat(string $ident, $value)
    {
        if ($value === null || $value === '') {
            return;
        }
        SetValue($this->GetIDForIdent($ident), (float)str_replace(',', '.', (string)$value));
    }

    private function SetInteger(string $ident, $value)
    {
        if ($value === null || $value === '') {
            return;
        }
        SetValue($this->GetIDForIdent($ident), (int)$value);
    }

    private function SetString(string $ident, string $value)
    {
        SetValue($this->GetIDForIdent($ident), $value);
    }
}
