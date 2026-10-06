// Services → Settings mounted regression — Security Phase 2B/2C.
//
// Mounts the REAL Service Settings lane (esbuild + happy-dom + Preact render,
// same technique as the other mounted regressions) through the REAL Station
// Manager station-settings registry, with the real Settings registration and
// Service's creation-launcher contribution, against a fetch mock of the
// Settings admin routes that records every request.
//
// Proves:
//   Structure — Settings presents General | Tools | Security; General holds
//   the creation launchers (which dispatch the existing drawer intents) and
//   Service fields; Tools holds the Rezdy importer slot; Security holds API Keys.
//   API Keys — a key is write-only: typed into a password input that exists
//   only while adding, sent once, then gone; it never renders; Remove key and
//   Disconnect are armed, cancellable, confirmed once; environment saves alone;
//   without secure storage nothing can be added and no server step is named; a
//   platform manager sees safe state only, with configuration read-only and no
//   request sent; Test connection sends one bodyless
//   POST and shows the outcome and checks.
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
  if (method === 'POST' && path === 'admin/settings/security/broker-validation') {
    return jsonResponse({ success: false, validation: {
      passed: false,
      identity: { user_id: 5, caller: 'settings.security-validation', source: 'server' },
      provider_check: { provider: 'rezdy', environment: 'staging', outcome: 'unauthorized', http_status: 401, latency_ms: 212 },
      rotation: { previous_key_defined: false, current: ['rezdy:api_key'], previous: [], unreadable: [] },
      checks: [
        { check: 'a replayed key is refused', ok: true, detail: { reason: 'unknown_key' } },
        { check: 'the audit records this run\'s outcomes', ok: false },
      ],
    } });
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
const buttons = (el) => [...(el?.querySelectorAll('button') ?? [])].map((b) => b.textContent.trim());

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

const intents = [];
const container = document.createElement('div');
document.body.appendChild(container);
const mount = async () => {
  render(null, container);
  render(h(bundle.ServiceSettingsLane, { onIntent: (recordId, intentId) => intents.push([recordId, intentId]) }), container);
  await sleep(60);
};
await mount();

console.log('Services → Settings regression (Security Phase 2B/2C)');

console.log('\n1) Settings presents General | Tools | Security');
const sectionTabs = () => [...container.querySelectorAll('[role="tab"]')];
check('section tabs are General, Tools, Security', JSON.stringify(sectionTabs().map((t) => t.textContent.trim())) === '["General","Tools","Security"]', JSON.stringify(sectionTabs().map((t) => t.textContent.trim())));
check('General is selected first', sectionTabs()[0].getAttribute('aria-selected') === 'true');
const contribution = (id) => container.querySelector(`[data-settings-contribution="${id}"]`);
const general = container.querySelector('[data-settings-section="general"]');
check('General holds Create, then Service fields', JSON.stringify([...general.querySelectorAll('[data-settings-contribution]')].map((el) => el.getAttribute('data-settings-contribution'))) === '["service.create","settings.service-meta"]');
click(contribution('service.create'), 'Create Service');
click(contribution('service.create'), 'Create Category');
check('the launchers dispatch the existing creation intents at the new sentinel', JSON.stringify(intents) === '[["new","create-service"],["new","create-category"]]', JSON.stringify(intents));
check('Tools holds the Rezdy importer slot, honestly not available yet', contribution('settings.rezdy-importer')?.textContent.includes('Not available yet'));
sectionTabs()[2].dispatchEvent(new window.MouseEvent('click', { bubbles: true }));
await sleep(10);
check('selecting Security shows API Keys', sectionTabs()[2].getAttribute('aria-selected') === 'true' && contribution('settings.api-keys')?.querySelector('h4')?.textContent === 'API Keys');
check('loading read the connections and the Service fields once each',
  calls.filter((c) => c.path === 'admin/settings/connections').length === 1 && calls.filter((c) => c.path === 'admin/settings/service-meta/fields').length === 1, describe(calls));

console.log('\n2) API Keys — a key is write-only');
const keys = () => contribution('settings.api-keys');
const card = () => keys().querySelector('.cz-settings-connection[data-provider="rezdy"]');
const secret = () => card().querySelector('[data-secret-field="api_key"]');
const state = () => card().querySelector('.cz-settings-connection__header .cz-settings-state')?.textContent.trim();
check('secure storage reads Ready and access reads Administrator', keys().querySelector('[data-secure-storage="available"]')?.textContent.includes('Ready') && keys().querySelector('[data-key-permission="manage"]')?.textContent.includes('Administrator'));
check('Rezdy reads Not set up and its key Not added', state() === 'Not set up' && secret().textContent.includes('Not added'));
check('no password input exists until Add is chosen', card().querySelector('input[type="password"]') === null);
calls = [];
await choose(card().querySelector('select'), 'staging');
await sleep(30);
check('choosing the environment saves it alone', mutations().length === 1 && JSON.stringify(mutations()[0].body) === '{"values":{"environment":"staging"}}', describe(mutations()));
click(secret(), 'Add');
await sleep(10);
const input = () => secret().querySelector('input[type="password"]');
check('Add opens an empty password input with new-password autocomplete', input()?.value === '' && input()?.getAttribute('autocomplete') === 'new-password');
await type(input(), SECRET);
calls = [];
click(secret(), 'Save API key');
await sleep(40);
check('Save sends one PUT carrying only the typed key', mutations().length === 1 && JSON.stringify(mutations()[0].body) === JSON.stringify({ secrets: { api_key: SECRET } }), describe(mutations()));
check('the input is gone after save and the key reads Saved', input() == null && secret().textContent.includes('Saved'));
check('Rezdy now reads Ready', state() === 'Ready');
check('a safe success notice is shown', card().querySelector('[role="status"]')?.textContent.includes('will not be shown again'));
check('the key appears nowhere in the rendered page', !container.innerHTML.includes(SECRET));
calls = [];
click(secret(), 'Replace');
await sleep(10);
click(secret(), 'Cancel');
await sleep(10);
check('Replace then Cancel sends nothing', mutations().length === 0 && input() == null, describe(mutations()));

console.log('\n3) API Keys — Remove key is armed, cancellable, confirmed once');
calls = [];
click(secret(), 'Remove');
await sleep(10);
check('arming Remove sent nothing and asks to confirm', mutations().length === 0 && secret().textContent.includes('stops working'), describe(mutations()));
click(secret(), 'Cancel');
await sleep(10);
check('Cancel sent nothing', mutations().length === 0);
click(secret(), 'Remove');
await sleep(10);
click(secret(), 'Remove');
await sleep(40);
check('Confirm sends clear: [api_key] once', mutations().length === 1 && JSON.stringify(mutations()[0].body) === '{"clear":["api_key"]}', describe(mutations()));
check('Rezdy now reads Incomplete', state() === 'Incomplete');

console.log('\n4) API Keys — Disconnect is armed, cancellable, confirmed once');
calls = [];
click(card(), 'Disconnect Rezdy');
await sleep(10);
check('arming Disconnect sent nothing', mutations().length === 0, describe(mutations()));
click(card(), 'Cancel');
await sleep(10);
check('Cancel restored the action', mutations().length === 0 && buttons(card()).includes('Disconnect Rezdy'));
click(card(), 'Disconnect Rezdy');
await sleep(10);
click(card(), 'Disconnect');
await sleep(40);
check('Confirm sent exactly one DELETE', mutations().length === 1 && mutations()[0].method === 'DELETE', describe(mutations()));
check('Rezdy reads Not set up again', state() === 'Not set up');

console.log('\n5) Service fields — add a select field');
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

console.log('\n6) Service fields — edit keeps identity rules');
click(metaPanel().querySelector(`[data-field-id="${certId}"]`), 'Edit');
await sleep(10);
const editForm = () => metaPanel().querySelector(`[data-field-id="${certId}"] form`);
check('the type select is disabled on edit', editForm()?.querySelector('select')?.disabled === true);
check('existing options offer no Remove', !buttons(editForm()).includes('Remove'));
calls = [];
await type(editForm().querySelector('input[type="text"]'), 'Certification');
editForm().dispatchEvent(new window.Event('submit', { bubbles: true, cancelable: true }));
await sleep(40);
check('save PUTs the field by id with existing option ids', mutations().length === 1 && mutations()[0].path.endsWith(certId) && mutations()[0].body.options.every((o) => o.id?.startsWith('opt_')), describe(mutations()));

console.log('\n7) Service fields — reorder sends ids only; retire is armed; restore; no delete');
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

console.log('\n8) API Keys — without secure storage, nothing can be added');
server.encryptionAvailable = false;
await mount();
check('secure storage reads not set up, as a platform operator step', keys().querySelector('[data-secure-storage="unavailable"]')?.textContent.includes('platform operator'));
check('no Add control is offered', secret() != null && secret().textContent.includes('Not added') && !buttons(secret()).includes('Add'));
check('no server file, master key or shell step is named', !/wp-config|QSD_CREDENTIAL_KEY|SSH|WP-CLI/i.test(container.textContent));

console.log('\n9) API Keys — a platform manager sees safe state only');
server.encryptionAvailable = true;
server.manageSecrets = false;
server.apiKeyStored = true;
server.environment = 'staging';
await mount();
check('access reads View only', keys().querySelector('[data-key-permission="view"]')?.textContent.includes('View only'));
check('the key reads Saved with no Add, Replace or Remove', secret().textContent.includes('Saved') && !['Add', 'Replace', 'Remove'].some((b) => buttons(secret()).includes(b)));
check('Disconnect and Test connection are not offered', !buttons(keys()).includes('Disconnect Rezdy') && keys().querySelector('[data-connection-test]') === null);
check('the environment is shown but read-only', card().querySelector('select')?.value === 'staging' && card().querySelector('select')?.disabled === true);
calls = [];
await choose(card().querySelector('select'), 'production');
await sleep(30);
for (const b of [...keys().querySelectorAll('button')]) b.dispatchEvent(new window.MouseEvent('click', { bubbles: true }));
await sleep(30);
check('View only performs no mutation, whatever is changed or clicked', mutations().length === 0, describe(mutations()));

console.log('\n10) API Keys — an administrator tests the connection');
server.manageSecrets = true;
server.environment = 'staging';
await mount();
const test = () => keys().querySelector('[data-connection-test]');
calls = [];
click(test(), 'Test connection');
await sleep(40);
check('Test connection sends one POST with no body (identity is server-derived)', mutations().length === 1 && mutations()[0].path === 'admin/settings/security/broker-validation' && mutations()[0].body === null, describe(mutations()));
check('the outcome is shown in plain words', test().querySelector('[data-test-outcome]')?.textContent.includes('Rezdy did not accept this API key'));
check('the badge reads Needs attention and the checks summarise 1 of 2', test().textContent.includes('Needs attention') && test().querySelector('summary')?.textContent.includes('1 of 2 passed') && test().querySelectorAll('[data-security-checks] li').length === 2);

console.log('');
if (failures.length > 0) {
  console.error(`REGRESSION FAILED — ${failures.length} check(s) did not hold:`);
  for (const f of failures) console.error(`  - ${f}`);
  process.exit(1);
}
console.log('All Services → Settings checks passed.');
process.exit(0);
