// Publish activation guard mounted regression — Phase 6.4 (stop-on-settle-failure).
//
// Mounts the REAL Station hooks — useServiceStation (Service) and
// useCategoryStation (Category) — inside a minimal Preact probe (esbuild +
// happy-dom + Preact render, same technique as the other mounted regressions)
// against a fetch mock that records every request in order.
//
// Proves, for each Station's Publish:
//   - a settle that reports failure (2xx success:false) or errors (4xx) sends
//     no activation request, reports no Publish success, and leaves the record
//     non-activated;
//   - a successful settle is followed by exactly one `active` request;
//   - an already-active record with pending changes follows settle-success →
//     one idempotent `active` only (the Phase 6.3 compatibility safeguard);
//   - Publish never sends a Restore, Disable, or Enable request.
//
// Usage: npm run regression:publish-activation-guard
//    or: node scripts/publish-activation-guard-regression.mjs

import { createRequire } from 'node:module';
import { dirname, resolve } from 'node:path';
import { fileURLToPath, pathToFileURL } from 'node:url';
import { mkdirSync } from 'node:fs';
import { Window } from 'happy-dom';

const require = createRequire(import.meta.url);
const { build } = require('esbuild');

const root = resolve(dirname(fileURLToPath(import.meta.url)), '..');
const outFile = resolve(root, 'node_modules/.cache/cz-publish-activation-guard-bundle.mjs');
mkdirSync(dirname(outFile), { recursive: true });

// ── DOM shim ─────────────────────────────────────────────────────────────
const window = new Window({ url: 'https://cz-test.local/' });
globalThis.window = window;
globalThis.document = window.document;
Object.defineProperty(globalThis, 'navigator', { value: window.navigator, configurable: true });
globalThis.HTMLElement = window.HTMLElement;
globalThis.Node = window.Node;
globalThis.requestAnimationFrame = (cb) => setTimeout(() => cb(Date.now()), 0);
globalThis.cancelAnimationFrame = (id) => clearTimeout(id);

window.QSDConfig = { apiRoot: 'https://cz-test.local/wp-json/', nonce: 'test-nonce' };

// ── Fetch mock — records every request; settle outcome is per-scenario ──
const SERVICE = { id: 811, platformId: 'QSDS9P4WK' };
const CATEGORY = { id: 821, platformId: 'QSDC9R6TV' };

const server = {
  servicePlatformStatus: 'disabled',
  categoryPlatformStatus: 'disabled',
  settleOutcome: 'ok', // 'ok' | 'reported-failure' | 'http-error'
};

let calls = [];

function jsonResponse(body, status = 200) {
  return Promise.resolve({
    ok: status >= 200 && status < 300,
    status,
    statusText: String(status),
    json: () => Promise.resolve(body),
    text: () => Promise.resolve(JSON.stringify(body)),
  });
}

const serviceModuleStatus = () => ({ overview: 'settled', inclusions: 'settled', faqs: 'not-configured' });
const categoryProjection = () => ({
  id: CATEGORY.id, platform_id: CATEGORY.platformId, name: 'Reef Dives', slug: 'reef-dives',
  description: '', platform_status: server.categoryPlatformStatus, previous_platform_status: '',
  module_status: { overview: 'settled' }, has_draft: false, station_role: 'category', assigned_count: 0,
});

function settleResponse(body) {
  if (server.settleOutcome === 'http-error') return jsonResponse({ success: false, message: 'Settle failed.' }, 422);
  if (server.settleOutcome === 'reported-failure') return jsonResponse({ ...body, success: false });
  return jsonResponse(body);
}

globalThis.fetch = (url, init = {}) => {
  const path = String(url).replace('https://cz-test.local/wp-json/', '');
  const method = (init?.method ?? 'GET').toUpperCase();
  const body = init.body ? JSON.parse(init.body) : null;
  calls.push({ method, path, body });

  if (method === 'GET' && path === `admin/services/${SERVICE.id}`) {
    return jsonResponse({
      success: true, id: SERVICE.id, platform_id: SERVICE.platformId,
      title: 'Reef Discovery', excerpt: '', content: 'A guided reef dive.',
      categories: [{ id: 1, name: 'Reef', slug: 'reef' }],
      inclusions: [{ id: 'gear', label: 'Gear' }], faqs: [],
      platform_status: server.servicePlatformStatus, previous_platform_status: '',
      module_status: { overview: 'settled', inclusions: 'pending', faqs: 'not-configured' },
      drafts: { overview: null, inclusions: [{ id: 'gear', label: 'Gear' }], faqs: null },
    });
  }
  if (method === 'POST' && path === `admin/services/${SERVICE.id}/settle`) {
    return settleResponse({
      success: true, module_status: serviceModuleStatus(),
      service: { id: SERVICE.id, platform_id: SERVICE.platformId, title: 'Reef Discovery', excerpt: '', content: 'A guided reef dive.', categories: [{ id: 1, name: 'Reef', slug: 'reef' }] },
      inclusions: [{ id: 'gear', label: 'Gear' }], faqs: [],
    });
  }
  if (method === 'POST' && path === `admin/services/${SERVICE.id}/status`) {
    if (body?.platform_status === 'active') server.servicePlatformStatus = 'active';
    return jsonResponse({
      success: true,
      service: { id: SERVICE.id, platform_id: SERVICE.platformId, platform_status: server.servicePlatformStatus, previous_platform_status: '', module_status: serviceModuleStatus(), post_status: 'publish', is_active: server.servicePlatformStatus === 'active' },
    });
  }
  if (method === 'POST' && path === `admin/categories/${CATEGORY.id}/overview/settle`) {
    return settleResponse({ success: true, category: categoryProjection() });
  }
  if (method === 'PATCH' && path === `admin/categories/${CATEGORY.id}/status`) {
    if (body?.platform_status === 'active') server.categoryPlatformStatus = 'active';
    return jsonResponse({ success: true, category: categoryProjection() });
  }
  return Promise.reject(new Error(`Unexpected fetch in regression harness: ${method} ${path}`));
};

// ── Bundle the REAL hooks ────────────────────────────────────────────────
await build({
  stdin: {
    contents: `
      export { useServiceStation } from '@/service-station/useServiceStation';
      export { useCategoryStation } from '@/hooks/useCategoryStation';
    `,
    resolveDir: root,
    loader: 'ts',
  },
  bundle: true,
  format: 'esm',
  outfile: outFile,
  jsx: 'automatic',
  jsxImportSource: 'preact',
  alias: { '@': resolve(root, 'resources/ts') },
  external: ['preact', 'preact/hooks', 'preact/jsx-runtime'],
  logLevel: 'silent',
});

const { useServiceStation, useCategoryStation } = await import(pathToFileURL(outFile).href);
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

const serviceItem = (platformStatus) => ({
  id: SERVICE.id, platformId: SERVICE.platformId, title: 'Reef Discovery', slug: 'reef-discovery',
  excerpt: '', content: 'A guided reef dive.', categories: [{ id: 1, name: 'Reef', slug: 'reef' }],
  inclusions: [], faqs: [],
  meta: { platform_status: platformStatus, previous_platform_status: '', module_status: { overview: 'settled', inclusions: 'pending', faqs: 'not-configured' } },
});
const categoryItem = (platformStatus) => ({
  id: CATEGORY.id, platformId: CATEGORY.platformId, name: 'Reef Dives', slug: 'reef-dives', description: '',
  platform_status: platformStatus, previous_platform_status: '', module_status: { overview: 'pending' },
  has_draft: true, assigned_count: 0,
});

// Mount one hook in a probe and hand back its live station object.
async function mountStation(useHook, item) {
  const container = document.createElement('div');
  document.body.appendChild(container);
  const ref = { current: null };
  function Probe() {
    ref.current = useHook(item);
    return null;
  }
  render(h(Probe, {}), container);
  await sleep(30);
  return { ref, unmount: () => render(null, container) };
}

const describe = (list) => list.map((c) => `${c.method} ${c.path}${c.body ? ' ' + JSON.stringify(c.body) : ''}`).join(' | ');
const activationCalls = () => calls.filter((c) => c.path.endsWith('/status') && c.body?.platform_status === 'active');
const forbiddenCalls = () => calls.filter((c) => c.path.endsWith('/restore') || c.body?.action === 'disable' || c.body?.action === 'enable');

async function runPublish(publish) {
  try {
    return { result: await publish(), error: null };
  } catch (error) {
    return { result: undefined, error };
  }
}

async function scenario({ label, useHook, item, publishKey, settleOutcome, statusKey, startStatus, expectActivation }) {
  console.log(`\n${label}`);
  server.servicePlatformStatus = startStatus;
  server.categoryPlatformStatus = startStatus;
  server.settleOutcome = settleOutcome;
  const { ref, unmount } = await mountStation(useHook, item(startStatus));
  calls = [];

  const { result, error } = await runPublish(() => ref.current[publishKey]());
  await sleep(20);

  const settleIndex = calls.findIndex((c) => c.path.endsWith('/settle'));
  const activations = activationCalls();
  check('Publish settles first', settleIndex === 0, describe(calls));
  if (expectActivation) {
    check('a successful settle is followed by exactly one active request', activations.length === 1 && calls.indexOf(activations[0]) > settleIndex, describe(calls));
    check('Publish reports success', result != null && error == null, String(error));
    check('the record is now active', server[statusKey] === 'active');
  } else {
    check('a failed settle sends no activation request', activations.length === 0, describe(calls));
    check('a failed settle sends nothing after the settle request', calls.length === 1, describe(calls));
    check('Publish reports no success', result == null, JSON.stringify(result));
    check('the record is left in its prior state', server[statusKey] === startStatus, server[statusKey]);
  }
  check('Publish sends no Restore, Disable, or Enable request', forbiddenCalls().length === 0, describe(forbiddenCalls()));
  unmount();
}

console.log('Publish activation guard regression (Phase 6.4)');

const STATIONS = [
  { name: 'Service', useHook: (item) => useServiceStation(item), item: serviceItem, publishKey: 'publishService', statusKey: 'servicePlatformStatus' },
  { name: 'Category', useHook: (item) => useCategoryStation(item), item: categoryItem, publishKey: 'publishCategory', statusKey: 'categoryPlatformStatus' },
];

for (const station of STATIONS) {
  await scenario({ ...station, label: `${station.name}: settle reports failure (success:false)`, settleOutcome: 'reported-failure', startStatus: 'disabled', expectActivation: false });
  await scenario({ ...station, label: `${station.name}: settle request errors (422)`, settleOutcome: 'http-error', startStatus: 'disabled', expectActivation: false });
  await scenario({ ...station, label: `${station.name}: settle succeeds on a Pending record`, settleOutcome: 'ok', startStatus: 'disabled', expectActivation: true });
  await scenario({ ...station, label: `${station.name}: already-active republish with pending changes`, settleOutcome: 'ok', startStatus: 'active', expectActivation: true });
  await scenario({ ...station, label: `${station.name}: already-active republish whose settle fails`, settleOutcome: 'reported-failure', startStatus: 'active', expectActivation: false });
}

console.log('');
if (failures.length > 0) {
  console.error(`REGRESSION FAILED — ${failures.length} check(s) did not hold:`);
  for (const f of failures) console.error(`  - ${f}`);
  process.exit(1);
}
console.log('All Publish activation guard checks passed.');
process.exit(0);
