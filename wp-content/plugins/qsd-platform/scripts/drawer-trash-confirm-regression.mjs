// Drawer Move to Trash confirmation mounted regression — Phase 6.5.
//
// Mounts the REAL ServiceDrawerHost and CategoryDrawerHost compositions
// (esbuild + happy-dom + Preact render, same technique as the other mounted
// drawer regressions) against a fetch mock that records every mutation.
//
// Proves, for a saved Service:
//   - footer Move to Trash only arms a confirmation dialog and sends nothing;
//   - Cancel sends no Trash request and leaves the drawer open;
//   - Confirm sends exactly one `trashed` status write (a double click too) and
//     closes through the existing terminal path;
//   - a failed Trash keeps the dialog open with an error and never closes;
//   - the local `new` composition keeps its discard-by-close path: no dialog,
//     no server write.
// And for a saved Category, that the existing destructive confirmation stays
// intact: arm → Cancel sends nothing; Confirm sends one `trashed` write.
//
// Usage: npm run regression:drawer-trash-confirm
//    or: node scripts/drawer-trash-confirm-regression.mjs

import { createRequire } from 'node:module';
import { dirname, resolve } from 'node:path';
import { fileURLToPath, pathToFileURL } from 'node:url';
import { mkdirSync } from 'node:fs';
import { Window } from 'happy-dom';

const require = createRequire(import.meta.url);
const { build } = require('esbuild');

const root = resolve(dirname(fileURLToPath(import.meta.url)), '..');
const outFile = resolve(root, 'node_modules/.cache/cz-drawer-trash-confirm-bundle.mjs');
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

// ── Fetch mock — records every mutation; Trash outcome is per-scenario ───
const SERVICE = { id: 911, platformId: 'QSDS9T3KM' };
const CATEGORY = { id: 921, platformId: 'QSDC9T4WR' };
const REEF = { id: 1, name: 'Reef', slug: 'reef', description: '' };

const server = { serviceStatus: 'active', categoryStatus: 'active', trashFails: false };
let mutations = [];

function jsonResponse(body, status = 200) {
  return Promise.resolve({
    ok: status >= 200 && status < 300,
    status,
    statusText: String(status),
    json: () => Promise.resolve(body),
    text: () => Promise.resolve(JSON.stringify(body)),
  });
}

const serviceModuleStatus = { overview: 'settled', inclusions: 'settled', faqs: 'settled' };
const categoryItem = () => ({
  id: CATEGORY.id, platform_id: CATEGORY.platformId, name: 'Reef Dives', slug: 'reef-dives', description: 'Reef trips.',
  platform_status: server.categoryStatus, previous_platform_status: '', module_status: { overview: 'settled' },
  has_draft: false, station_role: 'category', assigned_count: 0, group_id: null,
});

globalThis.fetch = (url, init = {}) => {
  const path = String(url).replace('https://cz-test.local/wp-json/', '');
  const method = (init?.method ?? 'GET').toUpperCase();
  const body = init.body ? JSON.parse(init.body) : null;
  if (method !== 'GET') mutations.push({ method, path, body });

  if (method === 'GET' && path === 'admin/services') {
    return jsonResponse({
      categories: [REEF],
      stations: [{
        id: SERVICE.id, platform_id: SERVICE.platformId, title: 'Reef Discovery', slug: 'reef-discovery',
        platform_status: server.serviceStatus, previous_platform_status: '', module_status: serviceModuleStatus,
        categories: [REEF], has_drafts: false, inclusion_count: 1, faq_count: 1,
      }],
    });
  }
  if (method === 'GET' && path === `admin/services/${SERVICE.id}`) {
    return jsonResponse({
      success: true, id: SERVICE.id, platform_id: SERVICE.platformId,
      title: 'Reef Discovery', excerpt: 'Reef.', content: 'A guided reef dive.', categories: [REEF],
      inclusions: [{ id: 'gear', label: 'Gear' }], faqs: [{ id: 'depth', question: 'Depth?', answer: '12 m.' }],
      platform_status: server.serviceStatus, previous_platform_status: '', module_status: serviceModuleStatus,
      drafts: { overview: null, inclusions: null, faqs: null },
    });
  }
  if (method === 'GET' && path === 'admin/categories') {
    return jsonResponse({ categories: [categoryItem()] });
  }
  if (method === 'POST' && path === `admin/services/${SERVICE.id}/status`) {
    if (body?.platform_status !== 'trashed') return Promise.reject(new Error(`Unexpected Service status write: ${init.body}`));
    if (server.trashFails) return jsonResponse({ success: false, message: 'Trash rejected.' }, 422);
    server.serviceStatus = 'trashed';
    return jsonResponse({
      success: true,
      service: { id: SERVICE.id, platform_id: SERVICE.platformId, platform_status: 'trashed', previous_platform_status: 'active', module_status: serviceModuleStatus, post_status: 'publish', is_active: false },
    });
  }
  if (method === 'PATCH' && path === `admin/categories/${CATEGORY.id}/status`) {
    if (body?.platform_status !== 'trashed') return Promise.reject(new Error(`Unexpected Category status write: ${init.body}`));
    server.categoryStatus = 'trashed';
    return jsonResponse({ success: true, category: categoryItem() });
  }
  return Promise.reject(new Error(`Unexpected fetch in regression harness: ${method} ${path}`));
};

// ── Bundle the REAL drawer hosts ─────────────────────────────────────────
await build({
  stdin: {
    contents: `
      export { ServiceDrawerHost } from '@/service-station/surface/ServiceDrawerHost';
      export { CategoryDrawerHost } from '@/admin-station/stations/serviceCategory/CategoryDrawerHost';
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

const { ServiceDrawerHost, CategoryDrawerHost } = await import(pathToFileURL(outFile).href);
const { h, render } = await import('preact');
const { useMemo, useRef, useState } = await import('preact/hooks');

const sleep = (ms) => new Promise((r) => setTimeout(r, ms));
const failures = [];
function check(label, cond, detail) {
  if (cond) console.log(`  ok — ${label}`);
  else {
    console.error(`  FAIL — ${label}${detail ? `: ${detail}` : ''}`);
    failures.push(label);
  }
}

// Mount one drawer host; the registered footer VNode is captured so the test
// drives the footer's real onTrash handler.
async function mountDrawer(Host, recordId) {
  const state = { footer: null, closes: 0 };
  function Harness() {
    const [, setFooterState] = useState(null);
    const setRef = useRef(setFooterState);
    setRef.current = setFooterState;
    const setFooter = useMemo(() => (footer) => { if (footer) state.footer = footer; setRef.current(footer); }, []);
    const onClose = useMemo(() => () => { state.closes += 1; }, []);
    const noop = useMemo(() => () => {}, []);
    return h(Host, { recordId, mode: 'view', onClose, onModeChange: noop, onSaved: noop, setFooter, setCloseGuard: noop });
  }
  const container = document.createElement('div');
  document.body.appendChild(container);
  render(h(Harness), container);
  await sleep(150);
  return { container, state, unmount: () => { render(null, container); container.remove(); } };
}

const dialogTitle = (container) => [...container.querySelectorAll('.cz-publish-confirm__title')].map((el) => el.textContent.trim());
const trashDialog = (container) => [...container.querySelectorAll('.cz-publish-confirm')]
  .find((el) => /to Trash\?$/.test(el.querySelector('.cz-publish-confirm__title')?.textContent.trim() ?? '')) ?? null;
function click(dialog, text) {
  const btn = [...(dialog?.querySelectorAll('button') ?? [])].find((b) => b.textContent.trim() === text);
  btn?.dispatchEvent(new window.MouseEvent('click', { bubbles: true }));
  return btn;
}
const trashWrites = () => mutations.filter((m) => m.path.endsWith('/status') && m.body?.platform_status === 'trashed');
const describe = (list) => list.map((m) => `${m.method} ${m.path} ${JSON.stringify(m.body)}`).join(' | ') || '(none)';

console.log('Drawer Move to Trash confirmation regression (Phase 6.5)');

// ── Service: arm, cancel, confirm ────────────────────────────────────────
console.log('\nService: footer Move to Trash arms a confirmation and sends nothing');
server.serviceStatus = 'active'; server.trashFails = false; mutations = [];
let svc = await mountDrawer(ServiceDrawerHost, SERVICE.id);
check('the saved Service footer exposes onTrash', typeof svc.state.footer?.props?.onTrash === 'function');
svc.state.footer.props.onTrash();
await sleep(30);
check('the Trash confirmation dialog is open', trashDialog(svc.container) != null, JSON.stringify(dialogTitle(svc.container)));
check('arming sent no request', mutations.length === 0, describe(mutations));

console.log('\nService: Cancel performs no mutation');
click(trashDialog(svc.container), 'Cancel');
await sleep(30);
check('the dialog closed', trashDialog(svc.container) == null);
check('Cancel sent no Trash request', mutations.length === 0, describe(mutations));
check('the drawer stayed open', svc.state.closes === 0);

console.log('\nService: Confirm trashes exactly once and closes');
svc.state.footer.props.onTrash();
await sleep(30);
const svcDialog = trashDialog(svc.container);
click(svcDialog, 'Move to Trash');
click(svcDialog, 'Move to Trash'); // double click before the first resolves
await sleep(80);
check('exactly one Trash status write was sent', trashWrites().length === 1 && mutations.length === 1, describe(mutations));
check('the write targets the saved Service', trashWrites()[0]?.path === `admin/services/${SERVICE.id}/status`, describe(mutations));
check('the drawer closed through the terminal path once', svc.state.closes === 1, `closes=${svc.state.closes}`);
check('no Restore, Disable, or Enable request', !mutations.some((m) => m.path.endsWith('/restore') || m.body?.action));
svc.unmount();

// ── Service: failure keeps the dialog open ───────────────────────────────
console.log('\nService: a failed Trash does not fake success or close');
server.serviceStatus = 'active'; server.trashFails = true; mutations = [];
svc = await mountDrawer(ServiceDrawerHost, SERVICE.id);
svc.state.footer.props.onTrash();
await sleep(30);
click(trashDialog(svc.container), 'Move to Trash');
await sleep(80);
check('one Trash request was attempted', trashWrites().length === 1, describe(mutations));
check('the dialog stays open', trashDialog(svc.container) != null);
check('the dialog shows the failure', trashDialog(svc.container)?.querySelector('[role="alert"]') != null, trashDialog(svc.container)?.textContent);
check('the drawer did not close', svc.state.closes === 0, `closes=${svc.state.closes}`);
check('the record was left in its prior state', server.serviceStatus === 'active', server.serviceStatus);
svc.unmount();

// ── Service: local `new` keeps discard-by-close ──────────────────────────
console.log('\nService: the local new composition discards by closing, never a server write');
server.trashFails = false; mutations = [];
svc = await mountDrawer(ServiceDrawerHost, 'new');
if (typeof svc.state.footer?.props?.onTrash === 'function') {
  svc.state.footer.props.onTrash();
  await sleep(30);
  check('no Trash confirmation dialog opened for new', trashDialog(svc.container) == null, JSON.stringify(dialogTitle(svc.container)));
  check('new discard closed the drawer', svc.state.closes === 1, `closes=${svc.state.closes}`);
} else {
  check('the new composition offers no record-level Trash footer', true);
}
check('new sent no request', mutations.length === 0, describe(mutations));
svc.unmount();

// ── Category: existing destructive confirmation intact ───────────────────
console.log('\nCategory: Move to Trash confirmation remains intact');
server.categoryStatus = 'active'; mutations = [];
const cat = await mountDrawer(CategoryDrawerHost, CATEGORY.id);
check('the saved Category footer exposes onTrash', typeof cat.state.footer?.props?.onTrash === 'function');
cat.state.footer.props.onTrash();
await sleep(30);
check('the Category Trash confirmation dialog is open', trashDialog(cat.container) != null, JSON.stringify(dialogTitle(cat.container)));
check('arming sent no request', mutations.length === 0, describe(mutations));
click(trashDialog(cat.container), 'Cancel');
await sleep(30);
check('Cancel closed the dialog and sent nothing', trashDialog(cat.container) == null && mutations.length === 0, describe(mutations));
cat.state.footer.props.onTrash();
await sleep(30);
click(trashDialog(cat.container), 'Move to Trash');
await sleep(80);
check('Confirm sent exactly one Category Trash write', trashWrites().length === 1 && mutations.length === 1, describe(mutations));
check('the Category drawer closed once', cat.state.closes === 1, `closes=${cat.state.closes}`);
cat.unmount();

console.log('');
if (failures.length > 0) {
  console.error(`REGRESSION FAILED — ${failures.length} check(s) did not hold:`);
  for (const f of failures) console.error(`  - ${f}`);
  process.exit(1);
}
console.log('All drawer Move to Trash confirmation checks passed.');
process.exit(0);
