// Settings Home mounted regression — Settings foundation.
//
// Mounts the REAL SettingsDeck (esbuild + happy-dom + Preact render, same
// technique as the other mounted regressions) against a fetch mock of the
// Settings admin routes that records every request.
//
// Proves:
//   Connections & Security — a typed secret is sent once on save, then cleared
//   from the input; no secret value is ever rendered; Remove saved value sends
//   `clear`; Disconnect is armed (no request), cancellable, then one DELETE.
//   Service Meta — Add field POSTs a definition and the row shows the
//   server-minted id; edit keeps type fixed and offers no removal of an
//   existing option; move sends ids only; Retire is armed then POSTs retire;
//   Restore POSTs restore; there is no delete request.
//
// Usage: npm run regression:settings-home
//    or: node scripts/settings-home-regression.mjs

import { createRequire } from 'node:module';
import { dirname, resolve } from 'node:path';
import { fileURLToPath, pathToFileURL } from 'node:url';
import { mkdirSync } from 'node:fs';
import { Window } from 'happy-dom';

const require = createRequire(import.meta.url);
const { build } = require('esbuild');

const root = resolve(dirname(fileURLToPath(import.meta.url)), '..');
const outFile = resolve(root, 'node_modules/.cache/cz-settings-home-bundle.mjs');
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
const SECRET = 'rz-SECRET-71c9';
const server = { environment: '', apiKeyStored: false, fields: [], nextId: 0, encryptionAvailable: true, manageSecrets: true };
let calls = [];

const mintId = (prefix) => `${prefix}${String(++server.nextId).padStart(10, '2').replace(/[01]/g, '2')}`;
const connection = () => ({
  provider: 'rezdy', label: 'Rezdy', description: 'Booking and product supplier connection.',
  state: !server.environment && !server.apiKeyStored ? 'not_configured' : server.environment && server.apiKeyStored ? 'configured' : 'incomplete',
  updated_at: server.environment || server.apiKeyStored ? '2026-10-05T00:00:00+00:00' : null,
  fields: [
    { key: 'environment', label: 'Environment', type: 'select', required: true, value: server.environment,
      options: [{ value: 'production', label: 'Production' }, { value: 'staging', label: 'Staging (sandbox)' }] },
    { key: 'api_key', label: 'API key', type: 'secret', required: true, configured: server.apiKeyStored },
  ],
});

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

  if (method === 'GET' && path === 'admin/settings/connections') {
    return jsonResponse({ success: true, connections: [connection()], encryption: { available: server.encryptionAvailable }, permissions: { manage_secrets: server.manageSecrets } });
  }
  if (method === 'PUT' && path === 'admin/settings/connections/rezdy') {
    if (body.values?.environment !== undefined) server.environment = body.values.environment;
    if (body.secrets?.api_key) server.apiKeyStored = true;
    if (body.clear?.includes('api_key')) server.apiKeyStored = false;
    return jsonResponse({ success: true, connection: connection() });
  }
  if (method === 'DELETE' && path === 'admin/settings/connections/rezdy') {
    server.environment = ''; server.apiKeyStored = false;
    return jsonResponse({ success: true, connection: connection() });
  }

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

// ── Bundle the REAL deck ─────────────────────────────────────────────────
await build({
  entryPoints: [resolve(root, 'resources/ts/settings-station/presentation/SettingsDeck.tsx')],
  bundle: true, format: 'esm', outfile: outFile,
  jsx: 'automatic', jsxImportSource: 'preact',
  alias: { '@': resolve(root, 'resources/ts') },
  external: ['preact', 'preact/hooks', 'preact/jsx-runtime'],
  logLevel: 'silent',
});

const { SettingsDeck } = await import(pathToFileURL(outFile).href);
const { h, render } = await import('preact');

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
// Each user input is its own task in a browser; let Preact re-render before
// the next interaction reads the component's state.
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

const container = document.createElement('div');
document.body.appendChild(container);
render(h(SettingsDeck, { items: [], loading: false, error: null, onIntent: () => {}, refetch: () => {} }), container);
await sleep(60);

console.log('Settings Home regression (Settings foundation)');

console.log('\n1) The deck presents its two Settings Tools');
const tabs = [...container.querySelectorAll('[role="tab"]')].map((t) => t.textContent.trim());
check('tabs are Connections & Security and Service Meta', JSON.stringify(tabs) === JSON.stringify(['Connections & Security', 'Service Meta']), JSON.stringify(tabs));
check('loading read the connections and the Service Meta fields once each',
  calls.filter((c) => c.path === 'admin/settings/connections').length === 1 && calls.filter((c) => c.path === 'admin/settings/service-meta/fields').length === 1, describe(calls));

console.log('\n2) Connections — a secret is write-only');
const card = () => container.querySelector('.cz-settings-connection[data-provider="rezdy"]');
check('Rezdy reads Not configured', card()?.querySelector('.cz-settings-state')?.textContent.trim() === 'Not configured');
check('no encryption warning shows when the server can seal secrets', !container.textContent.includes('no credential encryption key'));
const secretInput = () => card().querySelector('input[type="password"]');
check('the API key input is a password input that starts empty', secretInput()?.value === '');
await choose(card().querySelector('select'), 'staging');
await type(secretInput(), SECRET);
calls = [];
click(card(), 'Save connection');
await sleep(40);
const put = mutations()[0];
check('save sends one PUT with the environment and the typed secret', mutations().length === 1 && put.method === 'PUT' && put.body.values.environment === 'staging' && put.body.secrets.api_key === SECRET, describe(mutations()));
check('Rezdy now reads Configured', card()?.querySelector('.cz-settings-state')?.textContent.trim() === 'Configured');
check('the secret input is cleared after save', secretInput()?.value === '');
check('the secret value appears nowhere in the rendered page', !container.innerHTML.includes(SECRET));
check('a saved secret reads as saved, never its value', secretInput().getAttribute('placeholder')?.startsWith('Saved'));

calls = [];
click(card(), 'Save connection');
await sleep(40);
check('saving again without typing sends no secret', mutations().length === 1 && Object.keys(mutations()[0].body.secrets).length === 0, describe(mutations()));

calls = [];
click(card(), 'Remove saved value');
await sleep(10);
click(card(), 'Save connection');
await sleep(40);
check('Remove saved value sends clear: [api_key]', JSON.stringify(mutations()[0]?.body.clear) === '["api_key"]', describe(mutations()));
check('Rezdy now reads Incomplete', card()?.querySelector('.cz-settings-state')?.textContent.trim() === 'Incomplete');

console.log('\n3) Connections — Disconnect is armed, cancellable, confirmed once');
calls = [];
click(card(), 'Disconnect');
await sleep(10);
check('arming Disconnect sent nothing', mutations().length === 0, describe(mutations()));
click(card(), 'Cancel');
await sleep(10);
check('Cancel sent nothing and restored the actions', mutations().length === 0 && [...card().querySelectorAll('button')].some((b) => b.textContent.trim() === 'Disconnect'));
click(card(), 'Disconnect');
await sleep(10);
click(card(), 'Remove');
await sleep(40);
check('Confirm sent exactly one DELETE', mutations().length === 1 && mutations()[0].method === 'DELETE', describe(mutations()));
check('Rezdy reads Not configured again', card()?.querySelector('.cz-settings-state')?.textContent.trim() === 'Not configured');

console.log('\n4) Service Meta — add a select field');
const metaPanel = () => [...container.querySelectorAll('[role="tabpanel"]')][1];
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

console.log('\n5) Service Meta — edit keeps identity rules');
click(metaPanel().querySelector(`[data-field-id="${certId}"]`), 'Edit');
await sleep(10);
const editForm = () => metaPanel().querySelector(`[data-field-id="${certId}"] form`);
check('the type select is disabled on edit', editForm()?.querySelector('select')?.disabled === true);
check('existing options offer no Remove', ![...editForm().querySelectorAll('button')].some((b) => b.textContent.trim() === 'Remove'));
calls = [];
await type(editForm().querySelector('input[type="text"]'), 'Certification');
editForm().dispatchEvent(new window.Event('submit', { bubbles: true, cancelable: true }));
await sleep(40);
check('save PUTs the field by id with existing option ids', mutations().length === 1 && mutations()[0].path.endsWith(certId) && mutations()[0].body.options.every((o) => o.id?.startsWith('opt_')), describe(mutations()));

console.log('\n6) Service Meta — reorder sends ids only');
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
const rowOrder = [...metaPanel().querySelectorAll('[data-field-id]')].map((el) => el.getAttribute('data-field-id'));
check('rows render in the new order with unchanged ids', JSON.stringify(rowOrder) === JSON.stringify([depthId, certId]), JSON.stringify(rowOrder));

console.log('\n7) Service Meta — retire is armed; restore; no delete');
calls = [];
click(metaPanel().querySelector(`[data-field-id="${certId}"]`), 'Retire');
await sleep(10);
check('arming Retire sent nothing', mutations().length === 0, describe(mutations()));
click(metaPanel().querySelector(`[data-field-id="${certId}"]`), 'Retire');
await sleep(40);
check('Confirm POSTs retire once', mutations().length === 1 && mutations()[0].path.endsWith(`${certId}/retire`), describe(mutations()));
check('the row reads Retired and stays listed', metaPanel().querySelector(`[data-field-id="${certId}"] .cz-settings-state`)?.textContent.trim() === 'Retired');
click(metaPanel().querySelector(`[data-field-id="${certId}"]`), 'Restore');
await sleep(40);
check('Restore POSTs restore', mutations().at(-1)?.path.endsWith(`${certId}/restore`), describe(mutations()));
check('no request ever deleted a field', !calls.some((c) => c.method === 'DELETE' && c.path.includes('service-meta')));

console.log('\n8) Connections — without an encryption key, the lane says so');
render(null, container);
server.encryptionAvailable = false;
render(h(SettingsDeck, { items: [], loading: false, error: null, onIntent: () => {}, refetch: () => {} }), container);
await sleep(60);
const warning = [...container.querySelectorAll('[role="alert"]')].find((el) => el.textContent.includes('no credential encryption key'));
check('the lane warns that secret values cannot be saved on this server', warning != null, container.textContent.slice(0, 300));

console.log('\n9) Connections — a platform manager cannot change secrets');
render(null, container);
server.encryptionAvailable = true;
server.manageSecrets = false;
server.apiKeyStored = true;
render(h(SettingsDeck, { items: [], loading: false, error: null, onIntent: () => {}, refetch: () => {} }), container);
await sleep(60);
check('the secret input is read-only for a platform manager', secretInput()?.disabled === true);
check('the lane says only a site administrator can change it', card()?.textContent.includes('Only a site administrator can change it.'));
check('no Remove saved value control is offered', ![...card().querySelectorAll('button')].some((b) => b.textContent.includes('Remove saved value')));
check('Disconnect is not offered', ![...card().querySelectorAll('button')].some((b) => b.textContent.trim() === 'Disconnect'));
calls = [];
await choose(card().querySelector('select'), 'production');
click(card(), 'Save connection');
await sleep(40);
check('saving configuration sends no secret and no clear', mutations().length === 1 && mutations()[0].body.values.environment === 'production' && Object.keys(mutations()[0].body.secrets ?? {}).length === 0 && (mutations()[0].body.clear ?? []).length === 0, describe(mutations()));

console.log('');
if (failures.length > 0) {
  console.error(`REGRESSION FAILED — ${failures.length} check(s) did not hold:`);
  for (const f of failures) console.error(`  - ${f}`);
  process.exit(1);
}
console.log('All Settings Home checks passed.');
process.exit(0);
