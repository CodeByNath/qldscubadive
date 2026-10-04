// Service Home Bin mounted regression — Phase 6.1.
//
// Mounts the REAL ServiceBinLane (esbuild + happy-dom + Preact render, same
// technique as scripts/service-disable-enable-regression.mjs) against a fetch
// mock that reproduces the backend Bin contract already proven in PHP
// (tests/service-lifecycle-mask.php, tests/category-pending-lifecycle.php):
//   - restore from archived|trashed lands platform_status 'disabled' with the
//     mask cleared (unmasked Pending), never 'active';
//   - permanent delete is legal from archived or trashed only, returns the
//     deleted Platform ID, and the Category assigned-Services guard answers 409.
//
// Proves the FRONTEND wiring: archived/trashed lists, name + Platform ID
// visibility, the travel pill, the Owner-locked split-action order, armed
// in-place confirms for every destructive action, Restore re-entry as Pending,
// guarded delete, and that only the owning Stations' existing endpoints are
// called (no second lifecycle, status, or identity path).
//
// Usage: npm run regression:service-home-bin
//    or: node scripts/service-home-bin-regression.mjs

import { createRequire } from 'node:module';
import { dirname, resolve } from 'node:path';
import { fileURLToPath, pathToFileURL } from 'node:url';
import { mkdirSync, readFileSync } from 'node:fs';
import { Window } from 'happy-dom';

const require = createRequire(import.meta.url);
const { build } = require('esbuild');

const root = resolve(dirname(fileURLToPath(import.meta.url)), '..');
const outFile = resolve(root, 'node_modules/.cache/cz-service-home-bin-bundle.mjs');
mkdirSync(dirname(outFile), { recursive: true });

// ── DOM shim ─────────────────────────────────────────────────────────────
const window = new Window({ url: 'https://cz-test.local/' });
globalThis.window = window;
globalThis.document = window.document;
Object.defineProperty(globalThis, 'navigator', { value: window.navigator, configurable: true });
globalThis.MouseEvent = window.MouseEvent;
globalThis.HTMLElement = window.HTMLElement;
globalThis.Node = window.Node;
globalThis.requestAnimationFrame = (cb) => setTimeout(() => cb(Date.now()), 0);
globalThis.cancelAnimationFrame = (id) => clearTimeout(id);

window.QSDConfig = { apiRoot: 'https://cz-test.local/wp-json/', nonce: 'test-nonce' };

// ── Server-side truth ────────────────────────────────────────────────────
const services = new Map([
  [801, { id: 801, platformId: 'QSDS8A2RC', title: 'Archived Reef Dive', status: 'archived', previous: 'active' }],
  [802, { id: 802, platformId: 'QSDS8B2TW', title: 'Trashed Night Dive', status: 'trashed', previous: 'disabled' }],
]);
const categories = new Map([
  [31, { id: 31, platformId: 'QSDC3A4KZ', name: 'Archived Courses', status: 'archived', previous: 'active', assigned: 0 }],
  [32, { id: 32, platformId: 'QSDC3B2MW', name: 'Trashed Trips', status: 'trashed', previous: 'active', assigned: 2 }],
]);
const MODULE_STATUS = { overview: 'settled', inclusions: 'pending', faqs: 'not-configured' };
const tombstones = new Set();

const calls = [];
const bodies = [];
let binListLoads = 0;

function respond(body, status = 200) {
  return Promise.resolve({
    ok: status >= 200 && status < 300,
    status,
    statusText: String(status),
    json: () => Promise.resolve(body),
    text: () => Promise.resolve(JSON.stringify(body)),
  });
}

const BIN = ['archived', 'trashed'];

function serviceWire(s) {
  return {
    id: s.id, platform_id: s.platformId, title: s.title, slug: `s-${s.id}`,
    platform_status: s.status, previous_platform_status: s.previous,
    module_status: MODULE_STATUS, categories: [], has_drafts: false,
    inclusion_count: 0, faq_count: 0,
  };
}

function categoryWire(c) {
  return {
    id: c.id, platform_id: c.platformId, name: c.name, slug: `c-${c.id}`, description: '',
    platform_status: c.status, previous_platform_status: c.previous,
    module_status: { overview: 'settled' }, has_draft: false, assigned_count: c.assigned, group_id: null,
  };
}

globalThis.fetch = (url, init = {}) => {
  const path = String(url).replace('https://cz-test.local/wp-json/', '');
  const method = (init?.method ?? 'GET').toUpperCase();
  calls.push(`${method} ${path}`);
  if (init.body) bodies.push(String(init.body));
  let m;

  if (method === 'GET' && (m = path.match(/^admin\/services\?platform_status=(archived|trashed)$/))) {
    binListLoads += 1;
    return respond({ categories: [], stations: [...services.values()].filter((s) => s.status === m[1]).map(serviceWire) });
  }
  if (method === 'GET' && (m = path.match(/^admin\/categories\?platform_status=(archived|trashed)$/))) {
    return respond({ categories: [...categories.values()].filter((c) => c.status === m[1]).map(categoryWire) });
  }
  if (method === 'POST' && (m = path.match(/^admin\/services\/(\d+)\/restore$/))) {
    const s = services.get(Number(m[1]));
    if (!s || !BIN.includes(s.status)) return respond({ success: false }, 422);
    s.status = 'disabled'; s.previous = '';
    return respond({ success: true, service: { ...serviceWire(s), is_active: false, post_status: 'publish' } });
  }
  if (method === 'POST' && (m = path.match(/^admin\/services\/(\d+)\/status$/))) {
    const s = services.get(Number(m[1]));
    const payload = JSON.parse(init.body ?? '{}');
    if (!s || payload.platform_status !== 'trashed') return Promise.reject(new Error(`Unexpected Service status write: ${init.body}`));
    s.status = 'trashed';
    return respond({ success: true, service: { ...serviceWire(s), is_active: false, post_status: 'publish' } });
  }
  if (method === 'DELETE' && (m = path.match(/^admin\/services\/(\d+)$/))) {
    const s = services.get(Number(m[1]));
    if (!s || !BIN.includes(s.status)) return respond({ success: false, message: 'Only archived or trashed services can be permanently deleted.' }, 422);
    services.delete(s.id); tombstones.add(s.platformId);
    return respond({ success: true, deleted: s.id, platform_id: s.platformId });
  }
  if (method === 'POST' && (m = path.match(/^admin\/categories\/(\d+)\/restore$/))) {
    const c = categories.get(Number(m[1]));
    if (!c || !BIN.includes(c.status)) return respond({ success: false }, 422);
    c.status = 'disabled'; c.previous = '';
    return respond({ success: true, category: categoryWire(c) });
  }
  if (method === 'PATCH' && (m = path.match(/^admin\/categories\/(\d+)\/status$/))) {
    const c = categories.get(Number(m[1]));
    const payload = JSON.parse(init.body ?? '{}');
    if (!c || payload.platform_status !== 'trashed') return Promise.reject(new Error(`Unexpected Category status write: ${init.body}`));
    c.status = 'trashed';
    return respond({ success: true, category: categoryWire(c) });
  }
  if (method === 'DELETE' && (m = path.match(/^admin\/categories\/(\d+)$/))) {
    const c = categories.get(Number(m[1]));
    if (!c || !BIN.includes(c.status)) return respond({ success: false, message: 'Only archived or trashed categories can be permanently deleted.' }, 422);
    if (c.assigned > 0) {
      return respond({ success: false, message: 'This category still has services assigned to it. Unassign them before deleting.', assigned_count: c.assigned }, 409);
    }
    categories.delete(c.id); tombstones.add(c.platformId);
    return respond({ success: true, deleted: c.id, platform_id: c.platformId });
  }
  return Promise.reject(new Error(`Unexpected fetch in regression harness: ${method} ${path}`));
};

// ── Bundle the REAL lane ─────────────────────────────────────────────────
await build({
  entryPoints: [resolve(root, 'resources/ts/service-station/presentation/ServiceBinLane.tsx')],
  bundle: true,
  format: 'esm',
  outfile: outFile,
  jsx: 'automatic',
  jsxImportSource: 'preact',
  alias: { '@': resolve(root, 'resources/ts') },
  external: ['preact', 'preact/hooks', 'preact/jsx-runtime'],
  logLevel: 'silent',
});

const { ServiceBinLane } = await import(pathToFileURL(outFile).href);
const { h, render } = await import('preact');

// ── Harness ──────────────────────────────────────────────────────────────
let changedCalls = 0;
const onChanged = () => { changedCalls += 1; };

const container = document.createElement('div');
document.body.appendChild(container);
const mount = (active) => render(h(ServiceBinLane, { active, onChanged }), container);

const sleep = (ms) => new Promise((r) => setTimeout(r, ms));
async function settle() {
  let previous = -1;
  for (let i = 0; i < 200; i += 1) {
    await sleep(5);
    if (calls.length === previous && i > 10) return;
    previous = calls.length;
  }
}

const failures = [];
function check(label, cond, detail) {
  if (cond) console.log(`  ok — ${label}`);
  else {
    console.error(`  FAIL — ${label}${detail ? `: ${detail}` : ''}`);
    failures.push(label);
  }
}

const click = (el) => el?.dispatchEvent(new window.MouseEvent('click', { bubbles: true }));
const row = (key) => container.querySelector(`[data-bin-key="${key}"]`);
const rowKeys = () => [...container.querySelectorAll('[data-bin-key]')].map((el) => el.getAttribute('data-bin-key'));
const primaryLabel = (key) => row(key)?.querySelector('.cz-station-split__primary')?.textContent.trim();
async function menuLabels(key) {
  click(row(key)?.querySelector('.cz-station-split__trigger'));
  await sleep(10);
  const labels = [...(row(key)?.querySelectorAll('.cz-station-split__item') ?? [])].map((b) => b.textContent.trim());
  click(row(key)?.querySelector('.cz-station-split__trigger'));
  await sleep(10);
  return labels;
}
async function chooseMenu(key, label) {
  click(row(key)?.querySelector('.cz-station-split__trigger'));
  await sleep(10);
  click([...(row(key)?.querySelectorAll('.cz-station-split__item') ?? [])].find((b) => b.textContent.trim() === label));
  await sleep(10);
}
const buttonIn = (key, text) => [...(row(key)?.querySelectorAll('button') ?? [])].find((b) => b.textContent.trim() === text);
const mutationCalls = () => calls.filter((c) => !c.startsWith('GET '));

console.log('Service Home Bin regression (Phase 6.1)\n');

console.log('1) Archived and trashed Services and Categories list in one Bin');
mount(false);
await settle();
check('all four Bin records render as rows', rowKeys().length === 4, rowKeys().join(', '));
check('rows are keyed by kind + native id, never label or position',
  ['service:801', 'service:802', 'category:31', 'category:32'].every((k) => rowKeys().includes(k)), rowKeys().join(', '));

console.log('\n2) Every row shows its name, permanent Platform ID, and travel pill');
const expectations = [
  ['service:801', 'Archived Reef Dive', 'QSDS8A2RC', 'Archived', 'Service'],
  ['service:802', 'Trashed Night Dive', 'QSDS8B2TW', 'Trashed', 'Service'],
  ['category:31', 'Archived Courses', 'QSDC3A4KZ', 'Archived', 'Category'],
  ['category:32', 'Trashed Trips', 'QSDC3B2MW', 'Trashed', 'Category'],
];
for (const [key, name, platformId, pill, kind] of expectations) {
  const el = row(key);
  check(`${key} shows its name`, el?.querySelector('.cz-service-deck__identity-name')?.textContent.trim() === name);
  check(`${key} shows its kind`, el?.querySelector('.cz-service-deck__identity-ref')?.textContent.trim() === kind);
  check(`${key} shows Platform ID ${platformId}`, el?.textContent.includes(platformId));
  check(`${key} reads the ${pill} travel pill`, el?.querySelector('.cz-module-status-pill')?.textContent.includes(pill), el?.querySelector('.cz-module-status-pill')?.textContent);
}

console.log('\n3) One split control per row, Restore first, Owner-locked order');
for (const [key] of expectations) {
  check(`${key} has exactly one split control`, row(key)?.querySelectorAll('.cz-station-split').length === 1);
  check(`${key} primary action is Restore`, primaryLabel(key) === 'Restore', primaryLabel(key));
}
const archivedMenu = await menuLabels('service:801');
check('archived menu: Move to Trash, then Permanently delete', JSON.stringify(archivedMenu) === JSON.stringify(['Move to Trash', 'Permanently delete']), JSON.stringify(archivedMenu));
const trashedMenu = await menuLabels('service:802');
check('trashed menu: Permanently delete only', JSON.stringify(trashedMenu) === JSON.stringify(['Permanently delete']), JSON.stringify(trashedMenu));
check('opening menus called no endpoint', mutationCalls().length === 0, mutationCalls().join(', '));

console.log('\n4) Selecting the lane reloads the Bin');
const loadsBefore = binListLoads;
mount(true);
await settle();
check('becoming the selected lane re-reads the Bin lists', binListLoads > loadsBefore, `${loadsBefore} → ${binListLoads}`);

console.log('\n5) Restore an archived Service — returns to unmasked Pending, never Active');
click(row('service:801')?.querySelector('.cz-station-split__primary'));
await settle();
check('Restore called the Service restore endpoint once', mutationCalls().filter((c) => c === 'POST admin/services/801/restore').length === 1, mutationCalls().join(', '));
check('Restore wrote no status of its own (no /status call)', !mutationCalls().some((c) => c.includes('801/status')));
check('server state is disabled storage — not active', services.get(801).status === 'disabled', services.get(801).status);
check('the Disable mask is cleared — Pending, not Disabled', services.get(801).previous === '', `"${services.get(801).previous}"`);
check('Platform ID survives the restore', services.get(801).platformId === 'QSDS8A2RC');
check('the restored Service left the Bin', !rowKeys().includes('service:801'), rowKeys().join(', '));
check('the surface refresh ran once so Details reflects it', changedCalls === 1, `changedCalls=${changedCalls}`);

console.log('\n6) Move to Trash is armed, cancellable, then confirmed in place');
await chooseMenu('category:31', 'Move to Trash');
check('arming Move to Trash called no endpoint', !mutationCalls().some((c) => c.includes('categories/31')));
check('the row shows the in-place confirm prompt', row('category:31')?.querySelector('.cz-service-bin__prompt')?.textContent.trim() === 'Move to Trash?');
check('the split control is replaced while armed (no scattered buttons)', row('category:31')?.querySelector('.cz-station-split') == null);
click(buttonIn('category:31', 'Cancel'));
await sleep(20);
check('Cancel disarms without any request', row('category:31')?.querySelector('.cz-station-split') != null && !mutationCalls().some((c) => c.includes('categories/31')));
await chooseMenu('category:31', 'Move to Trash');
click(buttonIn('category:31', 'Confirm'));
await settle();
check('Confirm sent the existing Category status write (trashed)', mutationCalls().includes('PATCH admin/categories/31/status'), mutationCalls().join(', '));
check('the Category now reads Trashed in the Bin', row('category:31')?.querySelector('.cz-module-status-pill')?.textContent.includes('Trashed'));
check('after moving to Trash its menu offers Permanently delete only', JSON.stringify(await menuLabels('category:31')) === JSON.stringify(['Permanently delete']));

console.log('\n7) Guarded permanent delete — the owner\'s dependency guard is surfaced, the row stays');
await chooseMenu('category:32', 'Permanently delete');
check('arming delete called no endpoint', !mutationCalls().some((c) => c === 'DELETE admin/categories/32'));
check('the delete prompt warns it cannot be undone', row('category:32')?.querySelector('.cz-service-bin__prompt')?.textContent.includes('cannot be undone'));
click(buttonIn('category:32', 'Confirm'));
await settle();
check('Confirm sent the existing Category delete', mutationCalls().includes('DELETE admin/categories/32'));
const alertText = container.querySelector('.cz-service-bin__error')?.textContent ?? '';
check('the 409 assigned-Services message is shown', alertText.includes('still has services assigned'), alertText);
check('the guarded Category is still in the Bin', rowKeys().includes('category:32'));
check('no tombstone for the guarded Category', !tombstones.has('QSDC3B2MW'));

console.log('\n8) Permanently delete from archived (unified Bin) and from trashed');
services.set(803, { id: 803, platformId: 'QSDS8C3XP', title: 'Archived Wreck Dive', status: 'archived', previous: 'active' });
mount(false); mount(true);
await settle();
check('a newly archived Service appears after the lane is reselected', rowKeys().includes('service:803'), rowKeys().join(', '));
await chooseMenu('service:803', 'Permanently delete');
click(buttonIn('service:803', 'Confirm'));
await settle();
check('archived Service delete called the existing Service delete', mutationCalls().includes('DELETE admin/services/803'));
check('the archived Service is gone and tombstoned', !rowKeys().includes('service:803') && tombstones.has('QSDS8C3XP'));
await chooseMenu('service:802', 'Permanently delete');
click(buttonIn('service:802', 'Confirm'));
await settle();
check('the trashed Service is gone and tombstoned', !rowKeys().includes('service:802') && tombstones.has('QSDS8B2TW'));
await chooseMenu('category:31', 'Permanently delete');
click(buttonIn('category:31', 'Confirm'));
await settle();
check('the (formerly archived, now trashed) Category is gone and tombstoned', !rowKeys().includes('category:31') && tombstones.has('QSDC3A4KZ'));

console.log('\n9) Restore a trashed Category — Pending re-entry, then the Bin is empty');
click(row('category:32')?.querySelector('.cz-station-split__primary'));
await settle();
check('trashed Category restored to unmasked disabled/Pending', categories.get(32).status === 'disabled' && categories.get(32).previous === '');
check('the Bin shows its empty state', container.textContent.includes('The Bin is empty.'));

console.log('\n10) Only the owning Stations\' existing endpoints were called');
const allowed = [
  /^POST admin\/services\/\d+\/restore$/, /^POST admin\/services\/\d+\/status$/, /^DELETE admin\/services\/\d+$/,
  /^POST admin\/categories\/\d+\/restore$/, /^PATCH admin\/categories\/\d+\/status$/, /^DELETE admin\/categories\/\d+$/,
];
check('every mutation hit an existing Station route', mutationCalls().every((c) => allowed.some((re) => re.test(c))), mutationCalls().join(', '));
check('no request body ever asked for active', !bodies.some((b) => b.includes('"active"')), bodies.join(' | '));

// Read code, not the prose explaining it.
const code = (path) => readFileSync(resolve(root, path), 'utf8').replace(/\/\*[\s\S]*?\*\//g, ' ').replace(/\/\/[^\n]*/g, ' ');
const laneSource = code('resources/ts/service-station/presentation/ServiceBinLane.tsx');
const binSource = code('resources/ts/service-station/surface/serviceHomeBin.ts');
const laneImports = (laneSource.match(/from '([^']+)'/g) ?? []).map((from) => from.slice(6, -1));
check('the lane imports no endpoint module', laneImports.every((from) => !from.includes('/api') && !from.endsWith('api')), laneImports.join(', '));
check('the lane makes no fetch or apiClient call', !/\bfetch\(/.test(laneSource) && !laneSource.includes('apiClient'));
check('the Bin surface uses only the owning endpoint functions (no apiClient, no lifecycle util)',
  !binSource.includes('apiClient') && !binSource.includes('moduleStatus') && !binSource.includes('StationLifecycle('));

console.log('');
if (failures.length > 0) {
  console.error(`REGRESSION FAILED — ${failures.length} check(s) did not hold:`);
  for (const f of failures) console.error(`  - ${f}`);
  process.exit(1);
}
console.log('All Service Home Bin checks passed.');
process.exit(0);
