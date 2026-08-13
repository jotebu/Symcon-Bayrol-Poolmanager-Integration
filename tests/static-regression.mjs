import fs from 'node:fs';
import assert from 'node:assert/strict';

const source = fs.readFileSync(new URL('../BayrolDiscovery/module.php', import.meta.url), 'utf8');
const library = JSON.parse(fs.readFileSync(new URL('../library.json', import.meta.url), 'utf8'));

assert.equal(library.build, 16, 'library build must be 16');
assert.doesNotMatch(source, /private function BuildScanKeys\(\):array\{[^}]*\brange\(/s, 'BuildScanKeys must not allocate ranges');

const documentedMatch = source.match(/private const DOCUMENTED_OBJECT_IDS=\[\s*34=>\[([^\]]+)\],\s*44=>\[([^\]]+)\]/s);
assert.ok(documentedMatch, 'documented object ID lists missing');

const ids = documentedMatch.slice(1).map(group => group.split(',').map(value => Number(value.trim())));
assert.equal(ids[0].length, 27, 'group 34 object count');
assert.equal(ids[1].length, 31, 'group 44 object count');
assert.ok(ids[0].includes(4001), 'group 34 pH object missing');
assert.ok(ids[1].includes(2001), 'group 44 collective alarm object missing');

const actionMethod = source.match(/private function GetConfigurationActions\(\):array\{return\[(.*?)\n    \];\}/s);
assert.ok(actionMethod, 'configuration actions method missing');
assert.doesNotMatch(actionMethod[1], /'type'=>'ExpansionPanel'/, 'actions must remain top-level');

for (const field of ['SelectedApiKeyComment', 'SelectedGatewayVariableName', 'ApiKeyTypeOverride', 'ApiKeySemanticType', 'ApiKeyProfileOverride', 'ApiKeyUnitOverride', 'ManualDeviceCode', 'ManualDeviceName', 'ManualDeviceType', 'ManualDeviceRole', 'DeviceFamilyStatusKey', 'DeviceNeighborRadius']) {
    assert.match(actionMethod[1], new RegExp("'name'=>'" + field + "'"), `action field ${field} missing`);
}
assert.match(source, /'name'=>'BrowserList'/, 'action field BrowserList missing');
assert.match(source, /'name'=>'DeviceList'/, 'action field DeviceList missing');

console.log('Static discovery regression tests passed.');
