// Service Home Connections mounted regression — Phase 6.2 (reachable Categories).
//
// Mounts the REAL ServiceConnectionsLane (esbuild + happy-dom + Preact render,
// same technique as scripts/service-home-bin-regression.mjs) against a fetch
// mock of the one authoritative live Category list (`GET admin/categories`).
//
// Proves: every live Category is reachable (All includes connected and
// unassigned), Connected is exactly `assigned_count > 0`, Unassigned is exactly
// `assigned_count === 0`, View dispatches the existing `view-category` intent
// with the native numeric Category id (bound to the existing `category`
// drawer in admin-station/register.ts), and filter changes perform no request
// — no mutation and no second fetch path.
//
// Usage: npm run regression:service-home-connections
//    or: node scripts/service-home-connections-regression.mjs

import { createRequire } from 'node:module';
import { dirname, resolve } from 'node:path';
import { fileURLToPath, pathToFileURL } from 'node:url';
import { mkdirSync, readFileSync } from 'node:fs';
import { Window } from 'happy-dom';

const require = createRequire(import.meta.url);
const { build } = require('esbuild');

const root = resolve(dirname(fileURLToPath(import.meta.url)), '..');
const outFile = resolve(root, 'node_modules/.cache/cz-service-home-connections-bundle.mjs');
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

// ── Server-side truth: the live Category list (bin states already excluded) ──
const CATEGORIES = [
  { id: 41, platform_id: 'QSDC4A2KZ', name: 'Open Water Courses', assigned_count: 3, platform_status: 'active' },
  { id: 42, platform_id: 'QSDC4B3MW', name: 'Night Dives', assigned_count: 1, platform_status: 'disabled' },
  { id: 43, platform_id: 'QSDC4C4PX', name: 'Freediving', assigned_count: 0, platform_status: 'disabled' },
  { id: 44, platform_id: 'QSDC4D5RT', name: 'Snorkel Trips', assigned_count: 0, platform_status: 'active' },
].map((c) => ({
  ...c, slug: `c-${c.id}`, description: '', previous_platform_status: '',
  module_status: { overview: 'settled' }, has_draft: false, group_id: null,
}));

const calls = [];
globalThis.fetch = (url, init = {}) => {
  const path = String(url).replace('https://cz-test.local/wp-json/', '');
  const method = (init?.method ?? 'GET').toUpperCase();
  calls.push(`${method} ${path}`);
  if (method === 'GET' && path === 'admin/categories') {
    return Promise.resolve({
      ok: true, status: 200,
      json: () => Promise.resolve({ categories: CATEGORIES }),
      text: () => Promise.resolve(''),
    });
  }
  return Promise.reject(new Error(`Unexpected fetch in regression harness: ${method} ${path}`));
};

// ── Bundle the REAL lane ─────────────────────────────────────────────────
await build({
  entryPoints: [resolve(root, 'resources/ts/service-station/presentation/ServiceConnectionsLane.tsx')],
  bundle: true,
  format: 'esm',
  outfile: outFile,
  jsx: 'automatic',
  jsxImportSource: 'preact',
  alias: { '@': resolve(root, 'resources/ts') },
  external: ['preact', 'preact/hooks', 'preact/jsx-runtime'],
  logLevel: 'silent',
});

const { ServiceConnectionsLane } = await import(pathToFileURL(outFile).href);
const { h, render } = await import('preact');

const intents = [];
const onIntent = (recordId, intentId) => intents.push({ recordId, intentId });

const container = document.createElement('div');
document.body.appendChild(container);

const sleep = (ms) => new Promise((r) => setTimeout(r, ms));
const failures = [];
function check(label, cond, detail) {
  if (cond) console.log(`  ok — ${label}`);
  else {
    console.error(`  FAIL — ${label}${detail ? `: ${detail}` : ''}`);
    failures.push(label);
  }
}

const names = () => [...container.querySelectorAll('.cz-service-deck__identity-name')].map((el) => el.textContent.trim());
const rowFor = (name) => [...container.querySelectorAll('.cz-station-list__row')]
  .find((el) => el.querySelector('.cz-service-deck__identity-name')?.textContent.trim() === name);
const filterSelect = () => container.querySelector('select[aria-label="Filter Categories by connection"]');
async function setFilter(value) {
  const select = filterSelect();
  select.value = value;
  select.dispatchEvent(new window.Event('change', { bubbles: true }));
  await sleep(20);
}

console.log('Service Home Connections regression (Phase 6.2)\n');

render(h(ServiceConnectionsLane, { onIntent }), container);
await sleep(50);

console.log('1) One compact filter, default All');
const options = [...(filterSelect()?.querySelectorAll('option') ?? [])].map((o) => `${o.value}:${o.textContent.trim()}`);
check('filter offers exactly All, Connected, Unassigned', JSON.stringify(options) === JSON.stringify(['all:All', 'connected:Connected', 'unassigned:Unassigned']), JSON.stringify(options));
check('filter defaults to All', filterSelect()?.value === 'all', filterSelect()?.value);
check('the lane read the one live Category list exactly once', JSON.stringify(calls) === JSON.stringify(['GET admin/categories']), calls.join(', '));

console.log('\n2) All includes connected and unassigned live Categories');
check('All shows all four live Categories', JSON.stringify(names()) === JSON.stringify(['Open Water Courses', 'Night Dives', 'Freediving', 'Snorkel Trips']), names().join(', '));
for (const c of CATEGORIES) {
  const row = rowFor(c.name);
  check(`${c.name} shows Platform ID ${c.platform_id}`, row?.textContent.includes(c.platform_id));
  check(`${c.name} shows its Services count ${c.assigned_count}`, row?.querySelector('.cz-service-deck__count')?.textContent.trim() === String(c.assigned_count));
  check(`${c.name} keeps the status pill and one View action`, row?.querySelector('.cz-module-status-pill') != null && row?.querySelectorAll('.cz-station-split').length === 1);
}

const callsBeforeFilter = calls.length;

console.log('\n3) Connected = assigned_count > 0');
await setFilter('connected');
check('Connected shows only connected Categories', JSON.stringify(names()) === JSON.stringify(['Open Water Courses', 'Night Dives']), names().join(', '));

console.log('\n4) Unassigned = assigned_count === 0');
await setFilter('unassigned');
check('Unassigned shows only unassigned Categories', JSON.stringify(names()) === JSON.stringify(['Freediving', 'Snorkel Trips']), names().join(', '));

console.log('\n5) View opens the existing Category drawer by native id');
rowFor('Freediving')?.querySelector('.cz-station-split__primary')?.dispatchEvent(new window.MouseEvent('click', { bubbles: true }));
await sleep(10);
check('View on an unassigned Category dispatches view-category with the native numeric id',
  intents.length === 1 && intents[0].recordId === 43 && typeof intents[0].recordId === 'number' && intents[0].intentId === 'view-category',
  JSON.stringify(intents));
const adminRegister = readFileSync(resolve(root, 'resources/ts/admin-station/register.ts'), 'utf8');
check('view-category stays bound to the existing category drawer',
  /\{ id: 'view-category', target: 'drawer', mode: 'view', drawerTemplateKey: 'category' \}/.test(adminRegister));

await setFilter('all');
check('back to All shows every live Category again', names().length === 4);

console.log('\n6) Filter changes perform no mutation and no duplicate fetch');
check('switching filters and View made no request', calls.length === callsBeforeFilter, calls.slice(callsBeforeFilter).join(', '));
check('no request other than the one live Category list was ever made', calls.every((c) => c === 'GET admin/categories'), calls.join(', '));

console.log('');
if (failures.length > 0) {
  console.error(`REGRESSION FAILED — ${failures.length} check(s) did not hold:`);
  for (const f of failures) console.error(`  - ${f}`);
  process.exit(1);
}
console.log('All Service Home Connections checks passed.');
process.exit(0);
