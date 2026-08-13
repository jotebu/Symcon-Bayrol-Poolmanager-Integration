<?php

declare(strict_types=1);

class IPSModule
{
    public array $properties = [];

    public function ReadPropertyInteger(string $name): int
    {
        return (int) $this->properties[$name];
    }

    public function ReadPropertyString(string $name): string
    {
        return (string) $this->properties[$name];
    }
}

function IPS_GetKernelDir(): string
{
    return sys_get_temp_dir();
}

require_once dirname(__DIR__) . '/BayrolDiscovery/module.php';

function assertTrue(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

function scanKeys(BayrolDiscovery $module, array $properties): array
{
    $module->properties = array_merge([
        'ScanGroupStart' => 34,
        'ScanGroupEnd' => 55,
        'ScanObjectStart' => 4000,
        'ScanObjectEnd' => 17200,
        'ScanSuffixes' => 'value;status;opmode;text1;text2',
        'ScanMaxKeys' => 500
    ], $properties);

    $method = new ReflectionMethod($module, 'BuildScanKeys');
    $method->setAccessible(true);

    return $method->invoke($module);
}

$module = new BayrolDiscovery();

$group34 = scanKeys($module, ['ScanGroupStart' => 34, 'ScanGroupEnd' => 34, 'ScanSuffixes' => 'value', 'ScanMaxKeys' => 5000]);
assertTrue(count($group34) === 27, 'Gruppe 34 muss exakt 27 dokumentierte Objekt-IDs enthalten.');
assertTrue(in_array('34.4001.value', $group34, true), 'Dokumentierter pH-Key fehlt.');
assertTrue(!in_array('34.4002.value', $group34, true), 'Nicht dokumentierte Objekt-ID in Gruppe 34 enthalten.');

$group44 = scanKeys($module, ['ScanGroupStart' => 44, 'ScanGroupEnd' => 44, 'ScanSuffixes' => 'value', 'ScanMaxKeys' => 5000]);
assertTrue(count($group44) === 31, 'Gruppe 44 muss exakt 31 dokumentierte Objekt-IDs enthalten.');
assertTrue(in_array('44.2001.value', $group44, true), 'Dokumentierter Sammelalarm-Key fehlt.');
assertTrue(!in_array('44.2007.value', $group44, true), 'Nicht dokumentierte Objekt-ID in Gruppe 44 enthalten.');

$limited = scanKeys($module, [
    'ScanGroupStart' => 1,
    'ScanGroupEnd' => 999,
    'ScanObjectStart' => 1,
    'ScanObjectEnd' => 99999,
    'ScanSuffixes' => 'value',
    'ScanMaxKeys' => 500
]);
assertTrue(count($limited) === 500, 'ScanMaxKeys muss auch bei maximalen Bereichen strikt eingehalten werden.');

$form = json_decode($module->GetConfigurationForm(), true, 512, JSON_THROW_ON_ERROR);
foreach ($form['actions'] as $action) {
    assertTrue(($action['type'] ?? '') !== 'ExpansionPanel', 'Action-Felder duerfen nicht in ExpansionPanels verschachtelt sein.');
}

echo "Discovery regression tests passed.\n";
