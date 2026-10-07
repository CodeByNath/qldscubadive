// Contract: Settings is a tab pattern inside a Station, registered through
// Station Manager, and its ownership boundaries hold.
//
// Three parts:
//   1. Executed registration — the real Service, Settings and Admin register
//      modules run through the real finalize. The public resolvers prove the
//      Services Station presents `Settings → General | Tools | Security`, with
//      each panel registered by its owner, and that the standalone Settings
//      Station (navigation, destination, deck) is retired.
//   2. Source boundaries — presentation never calls ./api; Settings imports no
//      Service peer and Service imports no Settings panel; register.ts is
//      entry-only; the secret projection type has no value slot.
//   3. Security boundaries — only the credential broker reads a decrypted
//      secret; secret mutation needs administrator authority; key rotation is
//      shell-only; Settings exposes no Service Element surface.

import { readFileSync, readdirSync, statSync } from 'node:fs';
import { resolve } from 'node:path';
import { registerServiceStation } from '../resources/ts/service-station/register';
import { registerSettingsStation } from '../resources/ts/settings-station/register';
import { registerAdminStation, registerPresentationPolicy } from '../resources/ts/admin-station/register';
import { finalizeStationRegistry } from '../resources/ts/station-manager/registry/boot';
import { headerNavItems, menuNavItems } from '../resources/ts/station-manager/registry/navigation';
import { resolveDestination } from '../resources/ts/station-manager/registry/destinations';
import { resolveSurfaceBindings, defaultHomeStation } from '../resources/ts/station-manager/registry/surfaceBindings';
import { resolveDrawerTemplate } from '../resources/ts/station-manager/registry/drawerTemplates';
import { resolveStationSettings } from '../resources/ts/station-manager/registry/stationSettings';
import { SecurityApiKeysPanel } from '../resources/ts/settings-station/presentation/SecurityApiKeysPanel';
import { ServiceMetaSchemaLane } from '../resources/ts/settings-station/presentation/ServiceMetaSchemaLane';
import { RezdyImporterTool } from '../resources/ts/settings-station/presentation/RezdyImporterTool';
import { ServiceCreateLaunchers } from '../resources/ts/service-station/presentation/ServiceCreateLaunchers';

const root = resolve(import.meta.dirname, '..');
let checks = 0;

function check(condition: unknown, message: string): asserts condition {
  checks += 1;
  if (!condition) throw new Error(`Settings Station contract: ${message}`);
}

function source(path: string): string {
  return readFileSync(resolve(root, path), 'utf8');
}

function sourceFiles(directory: string, acc: string[] = []): string[] {
  for (const entry of readdirSync(resolve(root, directory))) {
    const full = `${directory}/${entry}`;
    if (statSync(resolve(root, full)).isDirectory()) sourceFiles(full, acc);
    else if (/\.tsx?$/.test(entry)) acc.push(full);
  }
  return acc;
}

// ── 1. Executed registration, in the entry's exact order ─────────────────────

registerServiceStation();
registerSettingsStation();
registerAdminStation();
registerPresentationPolicy();
finalizeStationRegistry();

check(!headerNavItems().some((item) => item.id === 'settings') && !menuNavItems().some((item) => item.id === 'settings'), 'there is no standalone Settings Station in the header or menu');
check(resolveDestination('settings') === null, 'the retired settings activation key resolves to nothing');
check(resolveSurfaceBindings('settings', 'presentation').length === 0, 'no Settings deck binding remains');
check(defaultHomeStation() === 'services', 'Service remains the default Home');
check(resolveDrawerTemplate('settings') === null, 'Settings registers no drawer — it has no lifecycle-managed record');

const servicesSettings = resolveStationSettings('services');
check(servicesSettings.map((s) => s.section).join() === 'general,tools,security', 'Services → Settings presents General, Tools, Security in that order');
const panelsOf = (section: string) => servicesSettings.find((s) => s.section === section)?.contributions ?? [];
check(panelsOf('general').map((c) => c.id).join() === 'service.create,settings.service-meta'
  && panelsOf('general')[0].panel === ServiceCreateLaunchers && panelsOf('general')[1].panel === ServiceMetaSchemaLane, 'General holds Service\'s creation launchers, then the Settings-owned Service fields');
check(panelsOf('tools').length === 1 && panelsOf('tools')[0].label === 'Rezdy importer' && panelsOf('tools')[0].panel === RezdyImporterTool, 'Tools holds the Rezdy importer slot');
check(panelsOf('security').length === 1 && panelsOf('security')[0].label === 'API Keys' && panelsOf('security')[0].panel === SecurityApiKeysPanel, 'Security holds the API Keys panel');
check(resolveStationSettings('settings').length === 0 && resolveStationSettings('packages').length === 0, 'no other Station presents these panels yet');

// ── 2. Source boundaries ─────────────────────────────────────────────────────

const settingsFiles = sourceFiles('resources/ts/settings-station');
for (const file of settingsFiles) {
  const text = source(file);
  check(!/from '@\/service-station/.test(text) && !/service-station\//.test(text.replace(/\/\/[^\n]*/g, '')), `${file} imports no Service peer`);
  if (file.includes('/presentation/')) {
    check(!/from '\.\.\/api'/.test(text) && !/from '@\/api\//.test(text), `${file} (presentation) calls no endpoint module`);
  }
}

const entry = source('resources/ts/modules/admin-station.ts');
check(/registerServiceStation\(\);\s*registerSettingsStation\(\);\s*registerAdminStation\(\);/.test(entry), 'the entry registers Settings after Service and before Admin');
const importers = sourceFiles('resources/ts').filter((file) => source(file).includes("settings-station/register'"));
check(importers.length === 1 && importers[0] === 'resources/ts/modules/admin-station.ts', 'settings-station/register.ts is imported only by the Admin Station entry');
check(!/from '\.\/register'/.test(source('resources/ts/settings-station/index.ts')), 'the public barrel does not export register.ts');

const types = source('resources/ts/settings-station/types.ts');
const secretType = types.slice(types.indexOf('export interface ConnectionSecretField'), types.indexOf('}', types.indexOf('export interface ConnectionSecretField')));
check(secretType.includes('configured: boolean') && !/\bvalue\b/.test(secretType), 'the secret field projection has a configured flag and no value slot');
const api = source('resources/ts/settings-station/api.ts');
check(api.includes("field.type === 'secret'") && api.includes('configured: field.configured'), 'the adapter rebuilds secret fields from safe keys only');

const serviceSettingsLane = source('resources/ts/service-station/presentation/ServiceSettingsLane.tsx');
check(
  serviceSettingsLane.includes('<StationSettings stationId="services" onIntent={onIntent} />') && !serviceSettingsLane.includes('settings-station'),
  "Service Home's Settings lane hosts the shared Settings pattern and imports no Settings panel",
);
const launchers = source('resources/ts/service-station/presentation/ServiceCreateLaunchers.tsx');
check(launchers.includes("onIntent('new', 'create-service')") && launchers.includes("onIntent('new', 'create-category')"), 'the creation launchers still open the mature drawers at the new sentinel');
const sharedSettings = source('resources/ts/admin-station/presentation/StationSettings.tsx');
check(!/settings-station|service-station/.test(sharedSettings) && sharedSettings.includes('resolveStationSettings(stationId)'), 'the shared Station Settings presentation names no Station and resolves its panels from Station Manager');
const stationSettingsRegistry = source('resources/ts/station-manager/registry/stationSettings.ts');
check(!/from '@\/(admin-station|settings-station|service-station)/.test(stationSettingsRegistry), 'the station-settings registry imports no peer or Admin Station');

const apiKeys = source('resources/ts/settings-station/presentation/SecurityApiKeysPanel.tsx');
check(apiKeys.includes('type="password"') && apiKeys.includes('autocomplete="new-password"') && /setValue\(''\)/.test(apiKeys), 'API keys are typed into a write-only password input that is cleared after save');
check(!/wp-config|QSD_CREDENTIAL_KEY|SSH|WP-CLI|operator/i.test(apiKeys.replace(/\/\/[^\n]*/g, '')), 'the API Keys UI names no server file, master key, shell step or operator task');
check(/data-key-rotation/.test(apiKeys) && /confirm\.run\(rotateId, tools\.rotateKey\)/.test(apiKeys) && /\{tools\.canManageSecrets && <KeyRotation /.test(apiKeys), 'Rotate encryption key is administrator-only and armed in place before anything is sent');
check(/apiClient\.post<[^>]*>\('admin\/settings\/security\/rotation'\)/.test(source('resources/ts/settings-station/api.ts')), 'the rotation call sends no body');
check(/const viewOnly = !tools\.canManageSecrets/.test(apiKeys) && /if \(viewOnly\) return;/.test(apiKeys) && (apiKeys.match(/disabled=\{busy \|\| viewOnly\}/g) ?? []).length === 2, 'View only makes provider configuration read-only and sends nothing');
check(/useInlineConfirm/.test(apiKeys) && /:remove`/.test(apiKeys) && /:disconnect`/.test(apiKeys), 'Remove key and Disconnect are armed in place before anything is sent');

const phpModule = source('src/Modules/Settings/SettingsModule.php');
check(!phpModule.includes('PlatformIdentifier'), 'the Settings backend mints no Platform ID family');
const schema = source('src/Modules/Settings/ServiceMeta/ServiceMetaSchema.php');
check(!/get_post_meta|update_post_meta|qsd_service_/.test(schema), 'the Service Meta schema reads/writes no Service storage');

// ── 3. Credential broker and secret-authority boundaries ────────────────────

function phpFiles(directory: string, acc: string[] = []): string[] {
  for (const entry of readdirSync(resolve(root, directory))) {
    const full = `${directory}/${entry}`;
    if (statSync(resolve(root, full)).isDirectory()) phpFiles(full, acc);
    else if (entry.endsWith('.php')) acc.push(full);
  }
  return acc;
}
const backend = phpFiles('src');
const code = (path: string) => source(path).replace(/\/\*[\s\S]*?\*\/|\/\/[^\n]*/g, '');

const settingsBackend = backend.filter((file) => file.startsWith('src/Modules/Settings/'));
check(settingsBackend.every((file) => !/ServiceElement|qsd_service_elements|post_meta/.test(code(file))), 'Settings exposes no Service Element surface and touches no Service values (deferred to the Service Manager phase)');

check(backend.filter((file) => /ConnectorCredentials\b/.test(code(file))).every((file) => file.startsWith('src/Modules/Settings/')), 'ConnectorCredentials is never handed to code outside Settings');
const secretReaders = backend.filter((file) => /->secret\(/.test(code(file)));
check(secretReaders.length > 0 && secretReaders.every((file) => /Settings\/(Security\/CredentialBroker|Connections\/ConnectorCredentials)\.php$/.test(file)), `only the credential broker reads a decrypted secret (${secretReaders.join(', ')})`);
const brokerFiles = backend.filter((file) => file.startsWith('src/Modules/Settings/Security/'));
check(brokerFiles.every((file) => !code(file).includes('PlatformIdentifier')), 'request keys and request ids are never Platform IDs');
const rezdy = code('src/Modules/Settings/Connectors/RezdyConnector.php');
check(/\[self::SCOPE_VERIFY\],\s*\);/.test(rezdy) && /SCOPE_VERIFY = 'connection\.verify'/.test(rezdy) && !/BrokeredProviderOperation|wp_remote_/.test(rezdy), 'Rezdy declares only the read-only connection.verify scope; importer scopes wait for the Owner importer review');
const rezdyCheck = code('src/Modules/Settings/Connectors/RezdyConnectionCheck.php');
check(settingsBackend.filter((file) => /wp_remote_|curl_/.test(code(file))).join() === 'src/Modules/Settings/Connectors/RezdyConnectionCheck.php', 'the only Settings provider HTTP call is RezdyConnectionCheck');
check(/wp_remote_get\(/.test(rezdyCheck) && !/wp_remote_(post|request|head)|'method'/.test(rezdyCheck) && /ALLOWED_ENVIRONMENT = 'staging'/.test(rezdyCheck) && /BASE_URLS\[self::ALLOWED_ENVIRONMENT\]/.test(rezdyCheck), 'the Rezdy check is one read-only GET, bound to the staging API (Security Phase 2)');
check(/\[RezdyConnector::PROVIDER => new RezdyConnectionCheck\(\$credentials\)\]/.test(code('src/Modules/Settings/SettingsModule.php')) && (code('src/Modules/Settings/SettingsModule.php').match(/new \w+(Operation|Check)\(/g) ?? []).length === 1, 'Settings wires exactly one brokered provider operation: the Rezdy connection check');
check(/BrokerValidation::CALLER => \[RezdyConnector::PROVIDER \. ':' \. RezdyConnector::SCOPE_VERIFY\]/.test(code('src/Modules/Settings/SettingsModule.php')), 'the broker caller allow-list is server code with one entry: the validation run for Rezdy connection.verify');

const securityController = code('src/Modules/Settings/Http/SettingsSecurityController.php');
check(/'permission_callback' => \[\$this, 'requireAuthority'\]/.test(securityController) && /PlatformAccess::CAP\) && CredentialAuthority::allows\(\)/.test(securityController), 'the security validation route needs the platform capability and administrator authority');
check(/->run\(get_current_user_id\(\)\)/.test(securityController) && !/get_param|get_json_params|get_body/.test(securityController), 'the validation route derives the user from the session and reads nothing from the request');
check(backend.filter((file) => /->rotate\(\)/.test(code(file))).join() === 'src/Modules/Settings/Http/SettingsSecurityController.php', 'only the administrator rotation route rotates; the validation run only inspects');
check(/public function rotate\(\\WP_REST_Request \$request\)[\s\S]*?->rotate\(\)/.test(securityController) && !/get_param|get_json_params|get_body/.test(securityController), 'the rotation route takes no input: no key material crosses HTTP');

const connectionsController = code('src/Modules/Settings/Http/SettingsConnectionsController.php');
check(/'PUT',[\s\S]*?'permission_callback' => \[\$this, 'requireSaveAuthority'\]/.test(connectionsController)
  && /'DELETE',[\s\S]*?'permission_callback' => \[\$this, 'requireSecretAuthority'\]/.test(connectionsController), 'secret-changing routes are gated by secret authority, not the bare platform capability');
check(/const CAP = 'manage_options'/.test(code('src/Modules/Settings/Security/CredentialAuthority.php')), 'secret authority is the existing administrator capability, with no new role or capability family');
check(backend.every((file) => !/add_role|add_cap\(/.test(code(file)) || file === 'src/Core/PlatformAccess.php'), 'no role or capability is created outside PlatformAccess');
check(/'\/admin\/settings\/security\/rotation', \[\s*'methods'\s*=> 'POST',\s*'callback'\s*=> \[\$this, 'rotate'\],\s*'permission_callback' => \[\$this, 'requireAuthority'\]/.test(securityController), 'key rotation is one administrator-only qsd/v1 POST route');
check(backend.every((file) => !/WP_CLI::add_command\('qsd credentials'/.test(code(file))), 'no shell command is needed to manage credentials or keys');
const keyring = code('src/Modules/Settings/Security/CredentialKeyring.php');
check(/hash_hkdf\('sha256'/.test(keyring) && /WRAP_CONTEXT\s*= 'qsd-credential-wrap:v1'/.test(keyring) && /keyring:v1:/.test(keyring) && /'SECURE_AUTH_KEY', 'SECURE_AUTH_SALT'/.test(keyring), 'the keyring wraps its data key under an HKDF key from the WordPress secret keys, with QSD context separation');
check(!/delete_option|unset\(\$ring\['keys'\]/.test(keyring), 'the keyring never deletes a generation outside a completed rotation');
check(/->guard->hold\(fn\(\): \\WP_REST_Response => \$this->saveHeld\(/.test(connectionsController) && /->guard->hold\(function \(\) use \(\$definition\): void \{\s*\$this->guard->assertHeld\(\);\s*\$this->store->remove\(/.test(connectionsController) && !/function saveConnection[\s\S]*?\$this->store->(write|remove)\([\s\S]*?function saveHeld/.test(connectionsController), 'every connection write (save and disconnect) runs inside the credential mutation guard');
check(/->guard->hold\(fn\(\): array => \$this->rotateHeld\(\)\)/.test(code('src/Modules/Settings/Security/CredentialRotation.php')), 'rotation runs entirely inside the same credential mutation guard');
const guardSource = code('src/Modules/Settings/Security/WpdbCredentialMutationGuard.php');
check(/SELECT GET_LOCK\(%s, %d\)/.test(guardSource) && /SELECT RELEASE_LOCK\(%s\)/.test(guardSource) && !/expires_at|\bLEASE\b|\btime\(\)/.test(guardSource), 'the guard is a connection-owned database named lock with no lease or expiry');
check(/\$wpdb->reconnect_retries = 0;/.test(guardSource) && /IS_USED_LOCK\(%s\)/.test(guardSource), 'while held, reconnection is off and ownership is proven against this connection');
const rotationSource = code('src/Modules/Settings/Security/CredentialRotation.php');
check(/assertHeld\(\);\s*\$this->keyring->stage\(/.test(rotationSource) && /assertHeld\(\);\s*\$this->store->replaceSecrets\(/.test(rotationSource) && /assertHeld\(\);\s*\$this->keyring->retireUnreferenced\(/.test(rotationSource), 'rotation proves ownership before staging, replacing and retiring');
check(/assertHeld\(\);\s*\$this->store->write\(/.test(connectionsController), 'a connection save proves ownership before it writes');

console.log(`Settings Station contract passed: ${checks} checks.`);
