// Contract: the Settings Station is a real peer registered through Station
// Manager, and its ownership boundaries hold.
//
// Two halves:
//   1. Executed registration — the real Service, Settings and Admin register
//      modules run through the real finalize, then the public resolvers prove
//      Settings' navigation, destination, binding, source and kit resolve, with
//      no drawer and no record collection.
//   2. Source boundaries — presentation never calls ./api; Settings imports no
//      Service peer; register.ts is entry-only; the secret projection type has
//      no value slot; Service Home's own Settings lane is untouched.
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
import { resolveTemplateKit } from '../resources/ts/station-manager/registry/templateKits';
import { resolveDataSource } from '../resources/ts/station-manager/registry/dataSources';
import { resolveDrawerTemplate } from '../resources/ts/station-manager/registry/drawerTemplates';
import { SettingsDeck } from '../resources/ts/settings-station/presentation/SettingsDeck';

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

const header = headerNavItems().map((item) => item.id);
check(header.includes('settings') && header.indexOf('settings') > header.indexOf('services'), 'Settings appears in the header after Services');
check(menuNavItems().some((item) => item.id === 'settings' && item.label === 'Settings'), 'Settings appears in the slide menu');

const destination = resolveDestination('settings');
check(destination?.stationId === 'settings' && destination.surfaceId === 'settings-home', 'the settings activation key resolves to the Settings station');
check(defaultHomeStation() === 'services', 'Service remains the default Home');

const bindings = resolveSurfaceBindings('settings', 'presentation');
check(bindings.length === 1 && bindings[0].templateKitKey === 'settings-deck' && bindings[0].dataSourceKey === 'settings-home', 'Settings Home binds one deck to its own source');
check(bindings[0].actionIntents.length === 0 && bindings[0].drawerTemplateKey === undefined, 'Settings Home dispatches no drawer intent');
check(resolveTemplateKit('settings-deck') === SettingsDeck, 'the settings-deck kit resolves to SettingsDeck');
const home = resolveDataSource('settings-home')();
check(Array.isArray(home.items) && home.items.length === 0 && home.loading === false && home.error === null, 'the Settings Home source is an honest empty collection — no fabricated record');
check(resolveDrawerTemplate('settings') === null, 'Settings registers no drawer — it has no lifecycle-managed record');

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
  serviceSettingsLane.includes("onIntent('new', 'create-service')") && serviceSettingsLane.includes("onIntent('new', 'create-category')") && !serviceSettingsLane.includes('settings-station'),
  "Service Home's Settings lane is still its two creation launchers and independent of the Settings Station",
);

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
check(backend.filter((file) => /->reseal\(/.test(code(file))).every((file) => file === 'src/Modules/Settings/Security/CredentialRotationCommand.php'), 'only the WP-CLI command performs a re-seal; the validation run only inspects');

const connectionsController = code('src/Modules/Settings/Http/SettingsConnectionsController.php');
check(/'PUT',[\s\S]*?'permission_callback' => \[\$this, 'requireSaveAuthority'\]/.test(connectionsController)
  && /'DELETE',[\s\S]*?'permission_callback' => \[\$this, 'requireSecretAuthority'\]/.test(connectionsController), 'secret-changing routes are gated by secret authority, not the bare platform capability');
check(/const CAP = 'manage_options'/.test(code('src/Modules/Settings/Security/CredentialAuthority.php')), 'secret authority is the existing administrator capability, with no new role or capability family');
check(backend.every((file) => !/add_role|add_cap\(/.test(code(file)) || file === 'src/Core/PlatformAccess.php'), 'no role or capability is created outside PlatformAccess');
check(backend.filter((file) => /CredentialRotation\b/.test(code(file))).every((file) => !/register_rest_route/.test(code(file))), 'key rotation has no REST route');
check(/WP_CLI::add_command\('qsd credentials', CredentialRotationCommand::class\)/.test(code('src/Modules/Settings/SettingsModule.php')), 'key rotation is registered only as a WP-CLI command');

console.log(`Settings Station contract passed: ${checks} checks.`);
