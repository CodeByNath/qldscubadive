// Services → Settings mounted regression — General only.
//
// Mounts the REAL Service Settings lane (esbuild + happy-dom + Preact render,
// same technique as the other mounted regressions) through the REAL Station
// Manager station-settings registry, with the real Settings registration and
// Service's creation-launcher contribution, against a fetch mock of the
// Settings admin routes that records every request.
//
// Phase D correction: global Connections (Tools) and Security relocated to
// Account (see scripts/account-settings-regression.mjs, which now carries
// that detailed behavioural proof against the same panels/data/backend).
// Services' own Settings tab presents General only from here on.
//
// Proves:
//   Structure — Settings presents General only; General holds the creation
//   launchers (which dispatch the existing drawer intents) and Service fields.
//   Service fields — Add POSTs a definition and the row shows the
//   server-minted id; edit keeps type fixed and offers no removal of an
//   existing option; move sends ids only; Retire is armed then POSTs retire;
//   Restore POSTs restore; there is no delete request.
//
// Usage: npm run regression:services-settings

import { createRequire } from 'node:module';
import { dirname, resolve } from 'node:path';
import { fileURLToPath, pathToFileURL } from 'node:url';
import { mkdirSync } from 'node:fs';
import { Window } from 'happy-dom';

const require = createRequire(import.meta.url);
const { build } = require('esbuild');

const root = resolve(dirname(fileURLToPath(import.meta.url)), '..');
const outFile = resolve(root, 'node_modules/.cache/cz-services-settings-bundle.mjs');
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
const server = { fields: [], nextId: 0 };
let calls = [];

const mintId = (prefix) => `${prefix}${String(++server.nextId).padStart(10, '2').replace(/[01]/g, '2')}`;

function jsonResponse(body, status = 200) {
  return Promise.resolve({
    ok: status >= 200 && status < 300, status, statusText: String(status),
    json: () => Promise.resolve(body),
    text: () => Promise.resolve(JSON.stringify(body)),
  });
}

globalThis.fetch = (url, init = {}) => {
  const path = String(url).replace('https://cz-test.local/wp-json/', '');
  const method = (init?.method ?? 'GET').toUpperCase();
  const body = init.body ? JSON.parse(init.body) : null;
  calls.push({ method, path, body });
  let m;

  if (method === 'GET' && path === 'admin/settings/service-meta/fields') return jsonResponse({ success: true, fields: server.fields });
  if (method === 'POST' && path === 'admin/settings/service-meta/fields') {
    const field = {
      id: mintId('fld_'), label: body.label, type: body.type, help: body.help ?? '', required: !!body.required, status: 'active',
      ...(body.options ? { options: body.options.map((o) => ({ id: mintId('opt_'), label: o.label })) } : {}),
    };
    server.fields.push(field);
    return jsonResponse({ success: true, field }, 201);
  }
  if (method === 'POST' && path === 'admin/settings/service-meta/fields/order') {
    server.fields = body.ids.map((id) => server.fields.find((f) => f.id === id));
    return jsonResponse({ success: true, fields: server.fields });
  }
  if (method === 'POST' && (m = path.match(/^admin\/settings\/service-meta\/fields\/(fld_\w+)\/(retire|restore)$/))) {
    const field = server.fields.find((f) => f.id === m[1]);
    field.status = m[2] === 'retire' ? 'retired' : 'active';
    return jsonResponse({ success: true, field });
  }
  if (method === 'PUT' && (m = path.match(/^admin\/settings\/service-meta\/fields\/(fld_\w+)$/))) {
    const field = server.fields.find((f) => f.id === m[1]);
    Object.assign(field, { label: body.label, help: body.help, required: body.required, ...(body.options ? { options: body.options.map((o) => ({ id: o.id ?? mintId('opt_'), label: o.label })) } : {}) });
    return jsonResponse({ success: true, field });
  }
  return Promise.reject(new Error(`Unexpected fetch in regression harness: ${method} ${path}`));
};

// ── Bundle the REAL lane, registry and registrations ─────────────────────
await build({
  stdin: {
    contents: [
      "export { registerSettingsStation } from '@/settings-station/register';",
      "export { registerStationSettings } from '@/station-manager/registry/stationSettings';",
      "export { finalizeStationRegistry } from '@/station-manager/registry/boot';",
      "export { ServiceSettingsLane } from '@/service-station/presentation/ServiceSettingsLane';",
      "export { ServiceCreateLaunchers } from '@/service-station/presentation/ServiceCreateLaunchers';",
    ].join('\n'),
    resolveDir: root, loader: 'ts',
  },
  bundle: true, format: 'esm', outfile: outFile,
  jsx: 'automatic', jsxImportSource: 'preact',
  alias: { '@': resolve(root, 'resources/ts') },
  external: ['preact', 'preact/hooks', 'preact/jsx-runtime'],
  logLevel: 'silent',
});

const bundle = await import(pathToFileURL(outFile).href);
const { h, render } = await import('preact');

// Service's own contribution, exactly as service-station/register.ts declares it.
bundle.registerStationSettings([
  { id: 'service.create', section: 'general', stationIds: ['services'], label: 'Create', order: 10, panel: bundle.ServiceCreateLaunchers },
]);
bundle.registerSettingsStation();
bundle.finalizeStationRegistry();

const sleep = (ms) => new Promise((r) => setTimeout(r, ms));
const failures = [];
function check(label, cond, detail) {
  if (cond) console.log(`  ok — ${label}`);
  else {
    console.error(`  FAIL — ${label}${detail ? `: ${detail}` : ''}`);
    failures.push(label);
  }
}
const describe = (list) => list.map((c) => `${c.method} ${c.path}${c.body ? ' ' + JSON.stringify(c.body) : ''}`).join(' | ') || '(none)';
const mutations = () => calls.filter((c) => c.method !== 'GET');

function click(rootEl, text) {
  const btn = [...rootEl.querySelectorAll('button')].find((b) => b.textContent.trim() === text);
  btn?.dispatchEvent(new window.MouseEvent('click', { bubbles: true }));
  return btn;
}
async function type(input, value) {
  input.value = value;
  input.dispatchEvent(new window.Event('input', { bubbles: true }));
  await sleep(10);
}
async function choose(select, value) {
  select.value = value;
  select.dispatchEvent(new window.Event('change', { bubbles: true }));
  await sleep(10);
}

const intents = [];
const container = document.createElement('div');
document.body.appendChild(container);
const mount = async () => {
  render(null, container);
  render(h(bundle.ServiceSettingsLane, { onIntent: (recordId, intentId) => intents.push([recordId, intentId]) }), container);
  await sleep(60);
};
await mount();

console.log('Services → Settings regression (General only, post Phase D relocation)');

console.log('\n1) Settings presents General only — Tools/Security relocated to Account');
const sectionTabs = () => [...container.querySelectorAll('[role="tab"]')];
check('section tabs are General only', JSON.stringify(sectionTabs().map((t) => t.textContent.trim())) === '["General"]', JSON.stringify(sectionTabs().map((t) => t.textContent.trim())));
check('General is selected', sectionTabs()[0].getAttribute('aria-selected') === 'true');
const contribution = (id) => container.querySelector(`[data-settings-contribution="${id}"]`);
const general = container.querySelector('[data-settings-section="general"]');
check('General holds Create, then Service fields', JSON.stringify([...general.querySelectorAll('[data-settings-contribution]')].map((el) => el.getAttribute('data-settings-contribution'))) === '["service.create","settings.service-meta"]');
click(contribution('service.create'), 'Create Service');
click(contribution('service.create'), 'Create Category');
check('the launchers dispatch the existing creation intents at the new sentinel', JSON.stringify(intents) === '[["new","create-service"],["new","create-category"]]', JSON.stringify(intents));
check('no Rezdy importer or API Keys contribution exists here any more', contribution('settings.rezdy-importer') === null && contribution('settings.api-keys') === null);
check('loading read the Service fields once', calls.filter((c) => c.path === 'admin/settings/service-meta/fields').length === 1, describe(calls));
check('no Settings connections request was ever made — that moved to Account', !calls.some((c) => c.path === 'admin/settings/connections'), describe(calls));

console.log('\n2) Service fields — add a select field');
const metaPanel = () => contribution('settings.service-meta');
calls = [];
click(metaPanel(), 'Add field');
await sleep(10);
const form = () => metaPanel().querySelector('form.cz-settings-meta-editor');
await type(form().querySelector('input[type="text"]'), 'Certification level');
await choose(form().querySelector('select'), 'select');
await sleep(10);
await type(form().querySelector('input[aria-label="Option 1"]'), 'Open Water');
click(form(), 'Add option');
await sleep(10);
await type(form().querySelector('input[aria-label="Option 2"]'), 'Advanced');
form().dispatchEvent(new window.Event('submit', { bubbles: true, cancelable: true }));
await sleep(40);
const post = mutations()[0];
check('Add field POSTs one definition with label, type and option labels', mutations().length === 1 && post.method === 'POST' && post.body.label === 'Certification level' && post.body.type === 'select' && JSON.stringify(post.body.options) === '[{"label":"Open Water"},{"label":"Advanced"}]', describe(mutations()));
check('the client sends no id', post.body.id === undefined && post.body.options.every((o) => o.id === undefined));
const certId = server.fields[0].id;
check('the row shows the server-minted id', metaPanel().querySelector(`[data-field-id="${certId}"] .cz-settings-meta-row__id`)?.textContent.trim() === certId);

console.log('\n3) Service fields — edit keeps identity rules');
click(metaPanel().querySelector(`[data-field-id="${certId}"]`), 'Edit');
await sleep(10);
const editForm = () => metaPanel().querySelector(`[data-field-id="${certId}"] form`);
check('the type select is disabled on edit', editForm()?.querySelector('select')?.disabled === true);
check('existing options offer no Remove', ![...editForm().querySelectorAll('button')].map((b) => b.textContent.trim()).includes('Remove'));
calls = [];
await type(editForm().querySelector('input[type="text"]'), 'Certification');
editForm().dispatchEvent(new window.Event('submit', { bubbles: true, cancelable: true }));
await sleep(40);
check('save PUTs the field by id with existing option ids', mutations().length === 1 && mutations()[0].path.endsWith(certId) && mutations()[0].body.options.every((o) => o.id?.startsWith('opt_')), describe(mutations()));

console.log('\n4) Service fields — reorder sends ids only; retire is armed; restore; no delete');
click(metaPanel(), 'Add field');
await sleep(10);
await type(form().querySelector('input[type="text"]'), 'Max depth');
await choose(form().querySelector('select'), 'number');
form().dispatchEvent(new window.Event('submit', { bubbles: true, cancelable: true }));
await sleep(40);
const depthId = server.fields[1].id;
calls = [];
click(metaPanel().querySelector(`[data-field-id="${depthId}"]`), '↑');
await sleep(40);
check('move up POSTs the new id order', mutations().length === 1 && JSON.stringify(mutations()[0].body) === JSON.stringify({ ids: [depthId, certId] }), describe(mutations()));
calls = [];
click(metaPanel().querySelector(`[data-field-id="${certId}"]`), 'Retire');
await sleep(10);
check('arming Retire sent nothing', mutations().length === 0, describe(mutations()));
click(metaPanel().querySelector(`[data-field-id="${certId}"]`), 'Retire');
await sleep(40);
check('Confirm POSTs retire once', mutations().length === 1 && mutations()[0].path.endsWith(`${certId}/retire`), describe(mutations()));
click(metaPanel().querySelector(`[data-field-id="${certId}"]`), 'Restore');
await sleep(40);
check('Restore POSTs restore', mutations().at(-1)?.path.endsWith(`${certId}/restore`), describe(mutations()));
check('no request ever deleted a field', !calls.some((c) => c.method === 'DELETE' && c.path.includes('service-meta')));

console.log('');
if (failures.length > 0) {
  console.error(`REGRESSION FAILED — ${failures.length} check(s) did not hold:`);
  for (const f of failures) console.error(`  - ${f}`);
  process.exit(1);
}
console.log('All Services → Settings checks passed.');
process.exit(0);
