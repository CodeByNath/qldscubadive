// Service Elements mounted regression.
//
// Mounts the REAL ServiceDrawerHost composition (esbuild + happy-dom + Preact
// render, same technique as the other mounted drawer regressions) against a
// fetch mock whose Element route mints ids the way the Service backend does:
// only for nodes sent without one.
//
// Proves the Elements module edits through the ordinary Service module editor
// and keeps Service-child identity:
//   - definitions come from Service's own Element route; retired definitions
//     cannot be added and their kept instances are sent back untouched;
//   - new Elements, Repeater rows and gallery entries are sent WITHOUT ids;
//   - after Save, edits, reorders and removals are sent BY the server-minted
//     ids; removal sends `detached` (never drops a saved node) and restore or
//     re-adding a removed field brings back the same id;
//   - Discard Draft reverts only the elements module; Publish/Settle settles
//     it through the existing settle-all path.
//
// Usage: npm run regression:service-elements
//    or: node scripts/service-elements-regression.mjs

import { createRequire } from 'node:module';
import { dirname, resolve } from 'node:path';
import { fileURLToPath, pathToFileURL } from 'node:url';
import { mkdirSync } from 'node:fs';
import { Window } from 'happy-dom';

const require = createRequire(import.meta.url);
const { build } = require('esbuild');

const root = resolve(dirname(fileURLToPath(import.meta.url)), '..');
const outFile = resolve(root, 'node_modules/.cache/cz-service-elements-bundle.mjs');
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

// ── Definitions (Settings-owned) and the mock Service Element route ──────
const SERVICE = { id: 931, platformId: 'QSDS9E7KM' };
const REEF = { id: 1, name: 'Reef', slug: 'reef', description: '' };
const DEF = {
  depth:     { id: 'fld_DEPTH00001', label: 'Max depth', type: 'number', help: '', required: false, status: 'active' },
  itinerary: { id: 'fld_ITIN000001', label: 'Itinerary', type: 'repeater', help: '', required: false, status: 'active', sub_fields: [
    { id: 'fld_TIME000001', label: 'Time', type: 'text', help: '', required: false, status: 'active' },
    { id: 'fld_ACTV000001', label: 'Activity', type: 'textarea', help: '', required: false, status: 'active' },
  ] },
  photos:    { id: 'fld_PHOTO00001', label: 'Photos', type: 'gallery', help: '', required: false, status: 'active' },
  profile:   { id: 'fld_PROF000001', label: 'Dive profile', type: 'group', help: '', required: false, status: 'active', sub_fields: [
    { id: 'fld_SITE000001', label: 'Site', type: 'text', help: '', required: false, status: 'active' },
  ] },
  old:       { id: 'fld_OLD0000001', label: 'Old field', type: 'text', help: '', required: false, status: 'retired' },
};
const KEPT = { id: 'el_KEPT000001', definition_id: DEF.old.id, status: 'active', value: 'legacy' };

const server = {
  settled: [KEPT],
  draft: null,
  moduleStatus: { overview: 'settled', inclusions: 'settled', faqs: 'settled', elements: 'settled' },
  minted: 0,
};
let mutations = [];
let gets = [];

function mint(prefix) {
  server.minted += 1;
  return `${prefix}M${String(server.minted).padStart(9, '0')}`;
}
/** The backend's identity rule, reduced to what the client can observe: ids only for new nodes. */
function assignIds(nodes, prefix) {
  return nodes.map((node) => {
    const next = { ...node, id: node.id ?? mint(prefix) };
    if (node.children) next.children = assignIds(node.children, 'el_');
    if (node.rows) next.rows = node.rows.map((row) => ({ ...row, id: row.id ?? mint('row_'), children: assignIds(row.children ?? [], 'el_') }));
    if (node.entries) next.entries = node.entries.map((entry) => ({ ...entry, id: entry.id ?? mint('ent_') }));
    return next;
  });
}

function jsonResponse(body, status = 200) {
  return Promise.resolve({
    ok: status >= 200 && status < 300,
    status,
    statusText: String(status),
    json: () => Promise.resolve(body),
    text: () => Promise.resolve(JSON.stringify(body)),
  });
}

const settledService = { id: SERVICE.id, platform_id: SERVICE.platformId, title: 'Reef Discovery', excerpt: 'Reef.', content: 'A guided reef dive.', categories: [REEF] };

globalThis.fetch = (url, init = {}) => {
  const path = String(url).replace('https://cz-test.local/wp-json/', '');
  const method = (init?.method ?? 'GET').toUpperCase();
  const body = init.body ? JSON.parse(init.body) : null;
  if (method === 'GET') gets.push(path); else mutations.push({ method, path, body });

  if (method === 'GET' && path === 'admin/services') {
    return jsonResponse({
      categories: [REEF],
      stations: [{
        id: SERVICE.id, platform_id: SERVICE.platformId, title: 'Reef Discovery', slug: 'reef-discovery',
        platform_status: 'active', previous_platform_status: '', module_status: server.moduleStatus,
        categories: [REEF], has_drafts: false, inclusion_count: 1, faq_count: 1,
      }],
    });
  }
  if (method === 'GET' && path === `admin/services/${SERVICE.id}`) {
    return jsonResponse({
      success: true, ...settledService,
      inclusions: [{ id: 'gear', label: 'Gear' }], faqs: [{ id: 'depth', question: 'Depth?', answer: '12 m.' }],
      elements: server.settled,
      platform_status: 'active', previous_platform_status: '', module_status: server.moduleStatus,
      drafts: { overview: null, inclusions: null, faqs: null, elements: server.draft },
    });
  }
  if (method === 'GET' && path === `admin/services/${SERVICE.id}/elements`) {
    return jsonResponse({
      success: true, id: SERVICE.id, platform_id: SERVICE.platformId,
      elements: server.settled, draft: server.draft, definitions: Object.values(DEF), module_status: server.moduleStatus,
    });
  }
  if (method === 'POST' && path === `admin/services/${SERVICE.id}/elements`) {
    server.draft = assignIds(body.elements, 'el_');
    server.moduleStatus = { ...server.moduleStatus, elements: 'pending' };
    return jsonResponse({ success: true, elements: server.draft, module_status: server.moduleStatus });
  }
  if (method === 'POST' && path === `admin/services/${SERVICE.id}/elements/revert`) {
    server.draft = null;
    server.moduleStatus = { ...server.moduleStatus, elements: 'settled' };
    return jsonResponse({ success: true, module: 'elements', module_status: server.moduleStatus });
  }
  if (method === 'POST' && path === `admin/services/${SERVICE.id}/settle`) {
    if (server.draft) server.settled = server.draft;
    server.draft = null;
    server.moduleStatus = { ...server.moduleStatus, elements: 'settled' };
    return jsonResponse({
      success: true, module_status: server.moduleStatus, service: settledService,
      inclusions: [{ id: 'gear', label: 'Gear' }], faqs: [{ id: 'depth', question: 'Depth?', answer: '12 m.' }],
      elements: server.settled,
    });
  }
  if (method === 'POST' && path === `admin/services/${SERVICE.id}/status`) {
    return jsonResponse({
      success: true,
      service: { id: SERVICE.id, platform_id: SERVICE.platformId, platform_status: 'active', previous_platform_status: '', module_status: server.moduleStatus, post_status: 'publish', is_active: true },
    });
  }
  return Promise.reject(new Error(`Unexpected fetch in regression harness: ${method} ${path}`));
};

// ── Bundle the REAL drawer host ──────────────────────────────────────────
await build({
  stdin: {
    contents: `export { ServiceDrawerHost } from '@/service-station/surface/ServiceDrawerHost';`,
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

const { ServiceDrawerHost } = await import(pathToFileURL(outFile).href);
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

const state = { footer: null };
function Harness() {
  const [, setFooterState] = useState(null);
  const setRef = useRef(setFooterState);
  setRef.current = setFooterState;
  const setFooter = useMemo(() => (footer) => { if (footer) state.footer = footer; setRef.current(footer); }, []);
  const noop = useMemo(() => () => {}, []);
  return h(ServiceDrawerHost, { recordId: SERVICE.id, mode: 'view', onClose: noop, onModeChange: noop, onSaved: noop, setFooter, setCloseGuard: noop });
}
const container = document.createElement('div');
document.body.appendChild(container);

// ── DOM helpers ──────────────────────────────────────────────────────────
const clickEl = (el) => { el?.dispatchEvent(new window.MouseEvent('click', { bubbles: true })); return el; };
const buttons = (scope = container) => [...scope.querySelectorAll('button')];
const byText = (text, scope) => buttons(scope).find((b) => b.textContent.trim() === text);
const byLabel = (label, scope = container) => scope.querySelector(`[aria-label="${label}"]`);
const allByLabel = (label) => [...container.querySelectorAll(`[aria-label="${label}"]`)];
// Every interaction waits a render tick, as a person would: handlers read the
// draft from the last render.
async function setValue(el, value, event = 'input') {
  if (!el) return;
  el.value = value;
  el.dispatchEvent(new window.Event(event, { bubbles: true }));
  await sleep(10);
}
function findModule(titleText) {
  return [...container.querySelectorAll('.drawerModule')]
    .find((el) => el.querySelector('.drawerModule__title')?.textContent.trim().startsWith(titleText)) ?? null;
}
const elementsText = () => findModule('Service Elements')?.textContent ?? '';
async function openElementsEditor() {
  clickEl(byText('Edit', findModule('Service Elements')));
  await sleep(30);
}
async function addElement(label) {
  const select = byLabel('Element to add');
  const option = [...(select?.querySelectorAll('option') ?? [])].find((o) => o.textContent === label);
  await setValue(select, option?.value ?? '', 'change');
  clickEl(byText('Add'));
  await sleep(20);
}
async function save() {
  clickEl(byText('Save'));
  await sleep(80);
}
const lastElementsPost = () => [...mutations].reverse().find((m) => m.method === 'POST' && m.path === `admin/services/${SERVICE.id}/elements`);
const idsIn = (nodes) => JSON.stringify(nodes).match(/"id":/g)?.length ?? 0;
const byDefinition = (nodes, id) => nodes.find((n) => n.definition_id === id);

console.log('Service Elements mounted regression');

// ── 1. Mount: definitions via Service's own route; kept retired instance ──
render(h(Harness), container);
await sleep(200);
console.log('\n1) The Elements module mounts beside the existing modules');
check('the Service Elements module renders in the drawer', findModule('Service Elements') != null);
check('definitions were read through the Service Element route', gets.includes(`admin/services/${SERVICE.id}/elements`), gets.join(', '));
check('a kept instance of a retired definition reads as retired', elementsText().includes('Old field (retired field): legacy'), elementsText());

// ── 2. First save: new nodes carry no id ─────────────────────────────────
console.log('\n2) Adding Elements sends new nodes without ids');
await openElementsEditor();
const choices = [...(byLabel('Element to add')?.querySelectorAll('option') ?? [])].map((o) => o.textContent);
check('only active definitions can be added', choices.includes('Max depth') && !choices.includes('Old field'), choices.join(', '));
check('the retired instance shows read-only', container.textContent.includes('Kept read-only'));
await addElement('Max depth');
await setValue(byLabel('Max depth'), '18');
await addElement('Itinerary');
clickEl(byText('+ Add row')); await sleep(10);
clickEl(byText('+ Add row')); await sleep(10);
await setValue(allByLabel('Time')[0], '07:00');
await setValue(allByLabel('Time')[1], '08:00');
await addElement('Photos');
clickEl(byText('+ Add image')); await sleep(10);
await setValue(byLabel('Photos image 1 attachment ID'), '11');
await addElement('Dive profile');
await setValue(byLabel('Site'), 'Flinders Reef');
await sleep(10);
await save();
const first = lastElementsPost()?.body?.elements ?? [];
check('the save posts the full collection', first.length === 5, JSON.stringify(first));
check('the kept retired instance is sent back untouched', JSON.stringify(byDefinition(first, DEF.old.id)) === JSON.stringify(KEPT));
check('no new node carries an id (the server mints them)', idsIn(first) === 1, JSON.stringify(first));
check('a number is sent as a number', byDefinition(first, DEF.depth.id)?.value === 18);
check('two repeater rows are sent with their children', byDefinition(first, DEF.itinerary.id)?.rows?.length === 2 && byDefinition(first, DEF.itinerary.id).rows[1].children[0]?.value === '08:00');
check('a gallery entry is sent as an attachment reference', byDefinition(first, DEF.photos.id)?.entries?.[0]?.attachment === 11);
check('a group child is sent under its sub-field definition', byDefinition(first, DEF.profile.id)?.children?.[0]?.definition_id === 'fld_SITE000001');
check('the read view shows the new Elements', ['Max depth: 18', 'Itinerary: 2 rows', 'Photos: 1 image', 'Dive profile: Site: Flinders Reef'].every((t) => elementsText().includes(t)), elementsText());
check('the elements module now offers Discard Draft', byText('Discard Draft', findModule('Service Elements')) != null);

const minted = server.draft;
const depthId = byDefinition(minted, DEF.depth.id).id;
const rowIds = byDefinition(minted, DEF.itinerary.id).rows.map((r) => r.id);
const entryId = byDefinition(minted, DEF.photos.id).entries[0].id;

// ── 3. Edits, reorder and removal travel by id ───────────────────────────
console.log('\n3) After Save, edits, reorders and removals are addressed by id');
await openElementsEditor();
await setValue(byLabel('Max depth'), '21');
clickEl(allByLabel('Move row down')[0]); await sleep(10);
clickEl(byLabel('Remove image')); await sleep(10);
await save();
const second = lastElementsPost()?.body?.elements ?? [];
check('the edited Element is sent under its minted id', byDefinition(second, DEF.depth.id)?.id === depthId && byDefinition(second, DEF.depth.id).value === 21);
check('reordered rows keep their ids in the new order', JSON.stringify(byDefinition(second, DEF.itinerary.id)?.rows?.map((r) => r.id)) === JSON.stringify([rowIds[1], rowIds[0]]));
const removedEntries = byDefinition(second, DEF.photos.id)?.entries ?? [];
check('a removed saved gallery entry is sent as detached under its id, not dropped', removedEntries.length === 1 && removedEntries[0].id === entryId && removedEntries[0].status === 'detached' && removedEntries[0].attachment === 11, JSON.stringify(removedEntries));
check('no saved node lost its id', idsIn(second) === idsIn(minted), `${idsIn(second)} vs ${idsIn(minted)}`);

// ── 4. Restore and re-add bring back the same identity ───────────────────
console.log('\n4) Restore and re-adding a removed field bring back the same id');
await openElementsEditor();
clickEl(byText('Restore')); await sleep(10);
clickEl(byLabel('Remove Max depth')); await sleep(10);
await addElement('Max depth');
await save();
const third = lastElementsPost()?.body?.elements ?? [];
check('a restored gallery entry is sent active under its original id', byDefinition(third, DEF.photos.id)?.entries?.[0]?.id === entryId && byDefinition(third, DEF.photos.id).entries[0].status === 'active');
const depths = third.filter((n) => n.definition_id === DEF.depth.id);
check('re-adding a removed field revives the same instance, not a new one', depths.length === 1 && depths[0].id === depthId && depths[0].status === 'active' && depths[0].value === 21, JSON.stringify(depths));

// ── 5. Discard Draft reverts only the elements module ────────────────────
console.log('\n5) Discard Draft reverts the elements draft');
const beforeDiscard = mutations.length;
clickEl(byText('Discard Draft', findModule('Service Elements'))); await sleep(20);
const discardDialog = [...container.querySelectorAll('.cz-publish-confirm')].find((d) => d.textContent.includes('Discard draft?'));
clickEl(byText('Discard Draft', discardDialog)); await sleep(60);
const discardCalls = mutations.slice(beforeDiscard);
check('exactly the elements revert route was called', discardCalls.length === 1 && discardCalls[0].path === `admin/services/${SERVICE.id}/elements/revert`, JSON.stringify(discardCalls));
check('the read view returns to the settled Elements', elementsText().includes('Old field (retired field): legacy') && !elementsText().includes('Max depth'), elementsText());

// ── 6. Settle through the existing settle-all path ───────────────────────
console.log('\n6) Publish/Settle settles the elements draft through settle-all');
await openElementsEditor();
await addElement('Max depth');
await setValue(byLabel('Max depth'), '30');
await save();
state.footer?.props?.onPublish?.();
await sleep(30);
const publishDialog = [...container.querySelectorAll('.cz-publish-confirm')].find((d) => d.textContent.includes('Service Elements:'));
check('the settle confirmation summarises Service Elements', publishDialog != null);
const beforeSettle = mutations.length;
clickEl(byText('Settle', publishDialog));
await sleep(120);
const settleCalls = mutations.slice(beforeSettle);
check('Settle uses the existing settle-all route (no per-element route)', settleCalls.some((m) => m.path === `admin/services/${SERVICE.id}/settle`) && !settleCalls.some((m) => m.path.includes('/elements')), JSON.stringify(settleCalls));
check('the settled Elements show and the draft is gone', elementsText().includes('Max depth: 30') && byText('Discard Draft', findModule('Service Elements')) == null, elementsText());
check('settled identity is the identity minted on save', byDefinition(server.settled, DEF.depth.id)?.id?.startsWith('el_M'));

render(null, container);
console.log('');
if (failures.length > 0) {
  console.error(`REGRESSION FAILED — ${failures.length} check(s) did not hold:`);
  for (const f of failures) console.error(`  - ${f}`);
  process.exit(1);
}
console.log('All Service Elements checks passed.');
process.exit(0);
