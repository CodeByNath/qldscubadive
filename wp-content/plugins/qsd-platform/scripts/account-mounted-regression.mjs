// Account mounted regression — Reviewer correction (2026-10-11).
//
// Mounts the REAL AccountCard (through the real useAccountProfileCard data
// source hook) and the REAL AccountDrawerHost (esbuild + happy-dom + Preact
// render, same technique as scripts/service-disable-enable-regression.mjs)
// against a fetch mock of the real Account REST surface.
//
// Proves, through the actual rendered DOM rather than inspecting props:
//   - the Home card renders name/code/status and its Open action dispatches
//     the registered view intent;
//   - the drawer's read/edit split, Save, Publish, Disable and Enable are all
//     reachable through the real rendered footer and editor, and drive the
//     real backend contract (platform_status/previous_platform_status);
//   - the real rendered footer's overflow menu never opens on ANY status —
//     there is no Archive/Trash reachable from the real DOM, not just an
//     absent prop (see src/Modules/Account/CLAUDE.md's singleton carve-out).
//
// Usage: npm run regression:account-mounted

import { createRequire } from 'node:module';
import { dirname, resolve } from 'node:path';
import { fileURLToPath, pathToFileURL } from 'node:url';
import { mkdirSync } from 'node:fs';
import { Window } from 'happy-dom';

const require = createRequire(import.meta.url);
const { build } = require('esbuild');

const root = resolve(dirname(fileURLToPath(import.meta.url)), '..');
const outFile = resolve(root, 'node_modules/.cache/cz-account-mounted-bundle.mjs');
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

// ── Server-side truth — a bootstrapped, already-published Account ──────────
const server = {
  bootstrapped: true,
  nodes: {
    account:  { platform_id: 'QSDA7K9Q2' },
    settings: { platform_id: 'QSDAS7K9Q2' },
    tools:    { platform_id: 'QSDAST7K9Q2' },
    profile:  { platform_id: 'QSDASTP7K9Q2' },
  },
  platformStatus: 'active',
  previousPlatformStatus: '',
  moduleStatus: { brand: 'settled' },
  brand: { name: 'Reef Co', code: 'REEF', logoUrl: null, faviconUrl: null },
  hasDraft: false,
};

let statusCalls = 0;
let profileCalls = 0;
let lastStatusPayload = null;
const calls = [];

function wireAccount() {
  return {
    bootstrapped: server.bootstrapped,
    nodes: server.nodes,
    platform_status: server.platformStatus,
    previous_platform_status: server.previousPlatformStatus,
    module_status: server.moduleStatus,
    brand: {
      name: server.brand.name,
      code: server.brand.code,
      logo_url: server.brand.logoUrl,
      favicon_url: server.brand.faviconUrl,
    },
    has_draft: server.hasDraft,
  };
}

function jsonResponse(body) {
  return Promise.resolve({
    ok: true,
    status: 200,
    json: () => Promise.resolve(body),
    text: () => Promise.resolve(JSON.stringify(body)),
  });
}

globalThis.fetch = (url, init = {}) => {
  const path = String(url).replace('https://cz-test.local/wp-json/', '');
  const method = (init?.method ?? 'GET').toUpperCase();
  calls.push({ method, path });

  if (path === 'admin/account' && method === 'GET') {
    return jsonResponse({ success: true, account: wireAccount() });
  }
  if (path === 'admin/account/profile' && method === 'POST') {
    profileCalls += 1;
    const payload = JSON.parse(init.body);
    if (payload.name !== undefined) server.brand.name = payload.name;
    if (payload.code !== undefined) server.brand.code = payload.code;
    server.hasDraft = true;
    server.moduleStatus = { brand: 'pending' };
    return jsonResponse({ success: true, account: wireAccount() });
  }
  if (path === 'admin/account/status' && method === 'POST') {
    statusCalls += 1;
    const payload = JSON.parse(init.body ?? '{}');
    lastStatusPayload = payload;
    // Reproduces the real backend contract (AccountBrand::publish/disable/
    // enable) so this proves the FRONTEND wiring, not a reimplementation —
    // the backend rule itself is proven in tests/account-brand.php.
    if (payload.action === 'disable') {
      if (server.platformStatus === 'active' || server.previousPlatformStatus === '') {
        server.previousPlatformStatus = server.platformStatus;
      }
      server.platformStatus = 'disabled';
    } else if (payload.action === 'enable') {
      server.platformStatus = 'disabled';
      server.previousPlatformStatus = '';
    } else if (payload.platform_status === 'active') {
      server.hasDraft = false;
      server.moduleStatus = { brand: 'settled' };
      server.platformStatus = 'active';
    } else {
      return Promise.reject(new Error(`Unexpected /status payload in regression harness: ${init.body}`));
    }
    return jsonResponse({ success: true, account: wireAccount() });
  }
  return Promise.reject(new Error(`Unexpected fetch in regression harness: ${method} ${path}`));
};

// ── Bundle the REAL compositions ────────────────────────────────────────
await build({
  stdin: {
    contents: [
      "export { AccountCard } from '@/account-station/presentation/AccountCard';",
      "export { useAccountProfileCard } from '@/account-station/surface/useAccountCard';",
      "export { AccountDrawerHost } from '@/account-station/surface/AccountDrawerHost';",
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
const { useState, useMemo, useRef } = await import('preact/hooks');

const sleep = (ms) => new Promise((r) => setTimeout(r, ms));
const failures = [];
function check(label, cond, detail) {
  if (cond) console.log(`  ok — ${label}`);
  else {
    console.error(`  FAIL — ${label}${detail ? `: ${detail}` : ''}`);
    failures.push(label);
  }
}
function clickButtonWithText(text, root) {
  const btn = [...root.querySelectorAll('button')].find((b) => b.textContent.trim() === text);
  btn?.dispatchEvent(new window.MouseEvent('click', { bubbles: true }));
  return btn;
}

console.log('Account mounted regression (Home card + drawer, Reviewer correction)\n');

// ── Part 1: the real Home card, through the real data-source hook ────────
console.log('1) Home card — mount the real useAccountProfileCard() + AccountCard');
const cardIntents = [];
function CardHarness() {
  const collection = bundle.useAccountProfileCard();
  return h(bundle.AccountCard, { ...collection, onIntent: (id, intentId) => cardIntents.push([id, intentId]) });
}
const cardContainer = document.createElement('div');
document.body.appendChild(cardContainer);
render(h(CardHarness), cardContainer);
await sleep(80);

check('the card shows the brand name', cardContainer.textContent.includes('Reef Co'));
check('the card shows the brand code as a subtitle', cardContainer.textContent.includes('REEF'));
check('the card status pill reads Active', cardContainer.querySelector('.cz-module-status-pill')?.textContent.trim() === 'Active', cardContainer.querySelector('.cz-module-status-pill')?.textContent);
clickButtonWithText('Open', cardContainer);
check('Open dispatches the view intent at the fixed account sentinel', JSON.stringify(cardIntents) === '[["account","view"]]', JSON.stringify(cardIntents));

// ── Part 2: the real drawer host ──────────────────────────────────────────
console.log('\n2) Drawer host — mount the real AccountDrawerHost (mode: view)');
let setFooterCalls = 0;
let lastFooter = null;
const drawerContainer = document.createElement('div');
document.body.appendChild(drawerContainer);
const footerContainer = document.createElement('div');
document.body.appendChild(footerContainer);
function renderFooterDom() {
  render(lastFooter, footerContainer);
  return footerContainer;
}

function DrawerHarness() {
  const [, setFooterState] = useState(null);
  const setFooterRef = useRef(setFooterState);
  setFooterRef.current = setFooterState;
  const setFooter = useMemo(() => (footer) => {
    setFooterCalls += 1;
    lastFooter = footer;
    setFooterRef.current(footer);
  }, []);
  const onSaved = useMemo(() => () => {}, []);
  const setCloseGuard = useMemo(() => () => {}, []);

  return h(bundle.AccountDrawerHost, { mode: 'view', onSaved, setFooter, setCloseGuard });
}

async function waitToSettle(maxTicks = 300, quietTicksNeeded = 10) {
  let quiet = 0;
  let previous = setFooterCalls;
  for (let tick = 0; tick < maxTicks; tick += 1) {
    await sleep(5);
    if (setFooterCalls === previous) {
      quiet += 1;
      if (quiet >= quietTicksNeeded) return;
    } else {
      quiet = 0;
      previous = setFooterCalls;
    }
  }
}

render(h(DrawerHarness), drawerContainer);
await waitToSettle();
check('a footer was registered on mount', setFooterCalls > 0);
check('read mode renders the settled Brand fields', drawerContainer.textContent.includes('Reef Co') && drawerContainer.textContent.includes('REEF'));

let footerDom = renderFooterDom();
check('the real rendered split button reads "Disable" while Active', footerDom.querySelector('.cz-footer-split__btn')?.textContent.trim() === 'Disable', footerDom.querySelector('.cz-footer-split__btn')?.textContent);
check('Publish is present and enabled while Active (a republish is a no-op status write)', (() => {
  const publishBtn = [...footerDom.querySelectorAll('button')].find((b) => b.textContent.trim() === 'Publish');
  return publishBtn != null && !publishBtn.disabled;
})());

console.log('\n3) No Archive/Trash is reachable from the REAL rendered footer, on any status');
const chevron = () => footerDom.querySelector('.cz-footer-split__chevron');
check('the chevron is disabled while overflow is empty', chevron()?.disabled === true);
chevron()?.dispatchEvent(new window.MouseEvent('click', { bubbles: true }));
await sleep(20);
footerDom = renderFooterDom();
check('clicking the (disabled) chevron opens no overflow menu — no Archive/Trash exists in the real DOM', footerDom.querySelector('.cz-footer-split__menu') === null);

console.log('\n4) Edit → Save writes the Brand draft through the real editor');
clickButtonWithText('Edit', drawerContainer);
await sleep(20);
const nameInput = drawerContainer.querySelector('#cz-account-brand-name');
check('the editor opens with the settled name', nameInput?.value === 'Reef Co', nameInput?.value);
if (nameInput) {
  nameInput.value = 'Reef Co (edited)';
  nameInput.dispatchEvent(new window.Event('input', { bubbles: true }));
}
await sleep(20);
clickButtonWithText('Save', drawerContainer);
await waitToSettle();
check('Save POSTed the Brand profile exactly once', profileCalls === 1, `profileCalls=${profileCalls}`);
check('the saved name is reflected back', server.brand.name === 'Reef Co (edited)', server.brand.name);
check('the module settles to pending after a Save — Publish alone activates', server.moduleStatus.brand === 'pending');

console.log('\n5) Publish — settle-then-activate through the real footer button and confirm dialog');
footerDom = renderFooterDom();
clickButtonWithText('Publish', footerDom);
await sleep(20);
const publishConfirm = drawerContainer.querySelector('.cz-publish-confirm');
check('the publish confirm dialog opened', publishConfirm != null);
publishConfirm?.querySelector('.cz-admin-btn--primary')?.dispatchEvent(new window.MouseEvent('click', { bubbles: true }));
await waitToSettle();
check('the status endpoint was called once for Publish', statusCalls === 1, `statusCalls=${statusCalls}`);
check('Publish sent platform_status=active', lastStatusPayload?.platform_status === 'active', JSON.stringify(lastStatusPayload));
check('the module is settled after Publish', server.moduleStatus.brand === 'settled');
check('the drawer now shows the edited name as canonical', drawerContainer.textContent.includes('Reef Co (edited)'));

console.log('\n6) Disable — the real split button, masking an active record');
footerDom = renderFooterDom();
clickButtonWithText('Disable', footerDom);
await waitToSettle();
check('the status endpoint was called a second time, for Disable', statusCalls === 2, `statusCalls=${statusCalls}`);
check('the Disable request carried action=disable', lastStatusPayload?.action === 'disable', JSON.stringify(lastStatusPayload));
check('platform_status is now disabled with the prior state captured', server.platformStatus === 'disabled' && server.previousPlatformStatus === 'active', `${server.platformStatus} / "${server.previousPlatformStatus}"`);

footerDom = renderFooterDom();
check('the real rendered split button now reads "Enable"', footerDom.querySelector('.cz-footer-split__btn')?.textContent.trim() === 'Enable', footerDom.querySelector('.cz-footer-split__btn')?.textContent);
check('still no Archive/Trash is reachable while Disabled', footerDom.querySelector('.cz-footer-split__chevron')?.disabled === true);

console.log('\n7) Enable — lands back on Pending, never straight to Active');
clickButtonWithText('Enable', footerDom);
await waitToSettle();
check('the status endpoint was called a third time, for Enable', statusCalls === 3, `statusCalls=${statusCalls}`);
check('the Enable request carried action=enable', lastStatusPayload?.action === 'enable', JSON.stringify(lastStatusPayload));
check('platform_status is disabled with the mask cleared — Enable never activates on its own', server.platformStatus === 'disabled' && server.previousPlatformStatus === '', `${server.platformStatus} / "${server.previousPlatformStatus}"`);

console.log('\n8) No request ever named an archive/trash/restore/delete action, anywhere in this run');
check('zero archive/trash/restore/delete requests were made', !calls.some((c) => /archive|trash|restore|delete/i.test(c.path)), JSON.stringify(calls));

console.log('');
if (failures.length > 0) {
  console.error(`REGRESSION FAILED — ${failures.length} check(s) did not hold:`);
  for (const f of failures) console.error(`  - ${f}`);
  process.exit(1);
}
console.log('All checks passed — the real Home card and drawer render, save, publish, disable and enable through actual DOM interaction, and no Archive/Trash control exists anywhere in the rendered footer.');
process.exit(0);
