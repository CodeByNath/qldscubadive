// Contract: Account registers into Station Manager alongside Service without
// disturbing either peer, resolves through the real finalize, and exposes no
// Archive/Trash/Restore/Delete surface — Account is a singleton carve-out
// from the Station/Drawer lifecycle contract's travel table (see
// src/Modules/Account/CLAUDE.md), not an oversight to catch later.
//
// Three parts:
//   1. Executed registration, in the real entry's exact order — proves the
//      nav item, destination, surface binding, and drawer template all
//      resolve, and that Service's own registrations are unaffected.
//   2. Source boundaries — account-station imports no Service peer;
//      register.ts is entry-only; no travel/archive/trash/delete action.
//   3. The entry itself registers Account before the Service/Settings/Admin
//      trio another contract pins adjacent — this just proves Account's call
//      exists in the entry at all.

import { readFileSync, readdirSync, statSync } from 'node:fs';
import { resolve } from 'node:path';
import { registerAccountStation } from '../resources/ts/account-station/register';
import { registerServiceStation } from '../resources/ts/service-station/register';
import { registerSettingsStation } from '../resources/ts/settings-station/register';
import { registerAdminStation, registerPresentationPolicy } from '../resources/ts/admin-station/register';
import { finalizeStationRegistry } from '../resources/ts/station-manager/registry/boot';
import { headerNavItems, menuNavItems } from '../resources/ts/station-manager/registry/navigation';
import { resolveDestination } from '../resources/ts/station-manager/registry/destinations';
import { resolveSurfaceBindings, defaultHomeStation } from '../resources/ts/station-manager/registry/surfaceBindings';
import { resolveDrawerTemplate } from '../resources/ts/station-manager/registry/drawerTemplates';

const root = resolve(import.meta.dirname, '..');
let checks = 0;

function check(condition: unknown, message: string): asserts condition {
  checks += 1;
  if (!condition) throw new Error(`Account registration contract: ${message}`);
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

registerAccountStation();
registerServiceStation();
registerSettingsStation();
registerAdminStation();
registerPresentationPolicy();
finalizeStationRegistry();

const nav = headerNavItems().find((item) => item.id === 'account');
check(!!nav && nav.activationKey === 'account' && nav.label === 'Account', 'Account has a header nav item with a resolvable activation key');
check(menuNavItems().some((item) => item.id === 'account'), 'Account also appears in the menu nav');

const destination = resolveDestination('account');
check(destination?.stationId === 'account', "Account's nav activation resolves to the account station");

const accountBindings = resolveSurfaceBindings('account', 'presentation');
check(accountBindings.length === 1, 'Account has exactly one presentation-placement surface binding');
const [accountBinding] = accountBindings;
check(accountBinding.dataSourceKey === 'account' && accountBinding.templateKitKey === 'account-card', "the binding names Account's own data source and card kit");
check(accountBinding.drawerTemplateKey === 'account', 'the binding opens the Account drawer template');
check(
  accountBinding.actionIntents.length === 1 && accountBinding.actionIntents[0].id === 'view' && accountBinding.actionIntents[0].target === 'drawer',
  'Account exposes exactly one action intent — view — no archive, trash, restore, or delete intent exists',
);

const drawer = resolveDrawerTemplate('account');
check(!!drawer && drawer.title === 'Account', 'the Account drawer template is registered');
check(!!drawer && drawer.supportedModes.includes('view') && drawer.supportedModes.includes('edit'), 'the Account drawer supports both view and edit');

// Cross-station: Service's own registrations are unaffected by Account's addition.
check(headerNavItems().some((item) => item.id === 'services'), 'Service keeps its own header nav item alongside Account');
check(resolveDestination('services')?.stationId === 'services', "Service's own destination still resolves");
check(defaultHomeStation() === 'services', 'Service remains the default Home — Account does not displace it');
check(resolveSurfaceBindings('services', 'presentation').length === 1, "Service keeps exactly its own one presentation binding");

// ── 2. Source boundaries ─────────────────────────────────────────────────────

const accountFiles = sourceFiles('resources/ts/account-station');
for (const file of accountFiles) {
  const text = source(file);
  check(!/from '@\/service-station/.test(text) && !/service-station\//.test(text.replace(/\/\/[^\n]*/g, '')), `${file} imports no Service peer`);
  if (!file.endsWith('register.ts')) {
    check(!/registerPresentationPolicy|registerSurfaceBindings/.test(text), `${file} does not author placement policy — only Admin does`);
  }
}

const importers = sourceFiles('resources/ts').filter((file) => source(file).includes("account-station/register'"));
check(importers.length === 1 && importers[0] === 'resources/ts/modules/admin-station.ts', 'account-station/register.ts is imported only by the Admin Station entry');

const entry = source('resources/ts/modules/admin-station.ts');
check(/registerAccountStation\(\);/.test(entry), 'the entry registers Account');
check(/registerServiceStation\(\);\s*registerSettingsStation\(\);\s*registerAdminStation\(\);/.test(entry), "Account's registration does not disturb the pinned Service/Settings/Admin adjacency");

// No file under account-station mentions an archive/trash/restore/permanent-delete
// concept — the singleton carve-out stays a source-level absence, not just an
// unregistered intent.
for (const file of accountFiles) {
  const text = source(file).replace(/\/\/[^\n]*/g, '').replace(/\/\*[\s\S]*?\*\//g, '');
  check(!/\barchive\b|\btrash\b|\brestore\b|permanent.?delete/i.test(text), `${file} contains no archive/trash/restore/delete concept`);
}

console.log(`Account registration contract: PASS (${checks} checks)`);
