// Account → Settings mounted regression — Security Phase 2B/2C relocated.
//
// Mounts the REAL AccountSettingsSection (esbuild + happy-dom + Preact
// render, same technique as the other mounted regressions) through the REAL
// Station Manager station-settings registry, with the real Settings
// registration, against a fetch mock of the Settings admin routes that
// records every request.
//
// Phase D correction: global Connections (Tools) and Security moved from
// Services to Account — not an additive second path. This file carries the
// detailed Tools/Security behavioural proof that used to run through
// Service's Settings lane (scripts/services-settings-regression.mjs), now
// mounted through Account's own Settings section instead, against the exact
// same panels, same data, same backend. Account has no General section (no
// Account contribution registers one — Service fields stays Services-only),
// so this harness proves only Tools | Security.
//
// Proves:
//   Structure — Account's Settings section presents Tools | Security; Tools
//   holds the Rezdy importer slot; Security holds API Keys.
//   API Keys — a key is write-only: typed into a password input that exists
//   only while adding, sent once, then gone; it never renders; Remove key and
//   Disconnect are armed, cancellable, confirmed once; environment saves alone;
//   without secure storage nothing can be added and no server step is named; a
//   platform manager sees safe state only, with configuration read-only and no
//   request sent; Test connection sends one bodyless
//   POST and shows the outcome and checks.
//
// Usage: npm run regression:account-settings

import { createRequire } from 'node:module';
import { dirname, resolve } from 'node:path';
import { fileURLToPath, pathToFileURL } from 'node:url';
import { mkdirSync } from 'node:fs';
import { Window } from 'happy-dom';

const require = createRequire(import.meta.url);
const { build } = require('esbuild');

const root = resolve(dirname(fileURLToPath(import.meta.url)), '..');
const outFile = resolve(root, 'node_modules/.cache/cz-account-settings-bundle.mjs');
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
const server = { environment: '', apiKeyStored: false, encryptionAvailable: true, manageSecrets: true, rotationRefused: false };
let calls = [];

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
      rotation: { generations: 1, current: ['rezdy:api_key'], previous: [], unreadable: [] },
      checks: [
        { check: 'a replayed key is refused', ok: true, detail: { reason: 'unknown_key' } },
        { check: 'the audit records this run\'s outcomes', ok: false },
      ],
    } });
  }
  if (method === 'POST' && path === 'admin/settings/security/rotation') {
    return server.rotationRefused
      ? jsonResponse({ success: false, rotation: { resealed: 0, unreadable: ['rezdy:api_key'] }, message: 'Some saved API keys cannot be opened. Replace or remove them, then rotate again. Nothing was changed.' }, 409)
      : jsonResponse({ success: true, rotation: { resealed: 1, unreadable: [] } });
  }
  return Promise.reject(new Error(`Unexpected fetch in regression harness: ${method} ${path}`));
};

// ── Bundle the REAL lane, registry and registrations ─────────────────────
await build({
  stdin: {
    contents: [
      "export { registerSettingsStation } from '@/settings-station/register';",
      "export { finalizeStationRegistry } from '@/station-manager/registry/boot';",
      "export { AccountSettingsSection } from '@/account-station/presentation/AccountSettingsSection';",
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
const mount = async () => {
  render(null, container);
  render(h(bundle.AccountSettingsSection, { onIntent: () => {} }), container);
  await sleep(60);
};
await mount();

console.log('Account → Settings regression (Phase D correction / Security Phase 2B/2C relocated)');

console.log('\n1) Account Settings presents Tools | Security — no General (Service fields stays Services-only)');
const sectionTabs = () => [...container.querySelectorAll('[role="tab"]')];
check('section tabs are Tools, Security only', JSON.stringify(sectionTabs().map((t) => t.textContent.trim())) === '["Tools","Security"]', JSON.stringify(sectionTabs().map((t) => t.textContent.trim())));
check('Tools is selected first', sectionTabs()[0].getAttribute('aria-selected') === 'true');
const contribution = (id) => container.querySelector(`[data-settings-contribution="${id}"]`);
check('Tools holds the Rezdy importer slot, honestly not available yet', contribution('settings.rezdy-importer')?.textContent.includes('Not available yet'));
sectionTabs()[1].dispatchEvent(new window.MouseEvent('click', { bubbles: true }));
await sleep(10);
check('selecting Security shows API Keys', sectionTabs()[1].getAttribute('aria-selected') === 'true' && contribution('settings.api-keys')?.querySelector('h4')?.textContent === 'API Keys');
check('loading read the connections once', calls.filter((c) => c.path === 'admin/settings/connections').length === 1, describe(calls));
check('no Service-meta request was ever made — Account never touches that endpoint', !calls.some((c) => c.path.includes('service-meta')), describe(calls));

console.log('\n2) API Keys — a key is write-only');
const keys = () => container;
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

console.log('\n5) API Keys — without secure storage, nothing can be added');
server.encryptionAvailable = false;
await mount();
sectionTabs()[1].dispatchEvent(new window.MouseEvent('click', { bubbles: true }));
await sleep(10);
const storage = container.querySelector('[data-secure-storage="unavailable"]')?.textContent ?? '';
check('secure storage reads unavailable in plain words, with no setup task for anyone', storage.includes('Unavailable on this site') && !/operator|set up|setup|server|hosting/i.test(storage), storage);
check('no Add control is offered', secret() != null && secret().textContent.includes('Not added') && !buttons(secret()).includes('Add'));
check('Rotate encryption key is offered but disabled', [...container.querySelectorAll('[data-key-rotation] button')].some((b) => b.textContent.includes('Rotate encryption key') && b.disabled));
check('no server file, master key, shell step or operator task is named', !/wp-config|QSD_CREDENTIAL_KEY|SSH|WP-CLI|platform operator/i.test(container.textContent));

console.log('\n6) API Keys — a platform manager sees safe state only');
server.encryptionAvailable = true;
server.manageSecrets = false;
server.apiKeyStored = true;
server.environment = 'staging';
await mount();
sectionTabs()[1].dispatchEvent(new window.MouseEvent('click', { bubbles: true }));
await sleep(10);
check('access reads View only', container.querySelector('[data-key-permission="view"]')?.textContent.includes('View only'));
check('the key reads Saved with no Add, Replace or Remove', secret().textContent.includes('Saved') && !['Add', 'Replace', 'Remove'].some((b) => buttons(secret()).includes(b)));
check('Disconnect, Test connection and Rotate encryption key are not offered', !buttons(container).includes('Disconnect Rezdy') && container.querySelector('[data-connection-test]') === null && container.querySelector('[data-key-rotation]') === null);
check('the environment is shown but read-only', card().querySelector('select')?.value === 'staging' && card().querySelector('select')?.disabled === true);
calls = [];
await choose(card().querySelector('select'), 'production');
await sleep(30);
for (const b of [...container.querySelectorAll('button')]) b.dispatchEvent(new window.MouseEvent('click', { bubbles: true }));
await sleep(30);
check('View only performs no mutation, whatever is changed or clicked', mutations().length === 0, describe(mutations()));

console.log('\n7) API Keys — an administrator tests the connection');
server.manageSecrets = true;
server.environment = 'staging';
await mount();
sectionTabs()[1].dispatchEvent(new window.MouseEvent('click', { bubbles: true }));
await sleep(10);
const test = () => container.querySelector('[data-connection-test]');
calls = [];
click(test(), 'Test connection');
await sleep(40);
check('Test connection sends one POST with no body (identity is server-derived)', mutations().length === 1 && mutations()[0].path === 'admin/settings/security/broker-validation' && mutations()[0].body === null, describe(mutations()));
check('the outcome is shown in plain words', test().querySelector('[data-test-outcome]')?.textContent.includes('Rezdy did not accept this API key'));
check('the badge reads Needs attention and the checks summarise 1 of 2', test().textContent.includes('Needs attention') && test().querySelector('summary')?.textContent.includes('1 of 2 passed') && test().querySelectorAll('[data-security-checks] li').length === 2);

console.log('\n8) API Keys — an administrator rotates the encryption key');
const rotation = () => container.querySelector('[data-key-rotation]');
check('the rotation section explains it in plain words, naming no key or server step', rotation()?.textContent.includes('a key this site manages for you') && !/wp-config|QSD_CREDENTIAL_KEY|SSH|WP-CLI|operator/i.test(rotation().textContent));
calls = [];
click(rotation(), 'Rotate encryption key');
await sleep(10);
check('arming Rotate sent nothing', mutations().length === 0 && rotation().textContent.includes('Saved API keys keep working'), describe(mutations()));
click(rotation(), 'Rotate');
await sleep(40);
check('Rotate sends one POST with no body', mutations().length === 1 && mutations()[0].path === 'admin/settings/security/rotation' && mutations()[0].body === null, describe(mutations()));
check('the result reads as a count only', rotation().querySelector('[data-rotation-result]')?.textContent.includes('1 saved API key was re-encrypted'));
server.rotationRefused = true;
click(rotation(), 'Rotate encryption key');
await sleep(10);
click(rotation(), 'Rotate');
await sleep(40);
check('a refused rotation shows the server reason and no success', rotation().querySelector('[role="alert"]')?.textContent.includes('Replace or remove them') && rotation().querySelector('[data-rotation-result]') === null);
server.rotationRefused = false;

console.log('');
if (failures.length > 0) {
  console.error(`REGRESSION FAILED — ${failures.length} check(s) did not hold:`);
  for (const f of failures) console.error(`  - ${f}`);
  process.exit(1);
}
console.log('All Account → Settings checks passed.');
process.exit(0);
