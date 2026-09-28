// ModuleState parity harness (Schema architecture S2 — permanent).
//
// Bundles the Station DNA engine (utils/moduleNotifications/, organised by
// domain with an export-preserving barrel) and evaluates every exported ModuleDefinition against
// a fixed fixture matrix. The resulting { status, notes } outputs are written
// to scripts/__snapshots__/module-state.v1.json on first run and compared
// byte-for-byte on every later run — any schema-phase change that alters DNA
// behaviour fails this script.
//
// Usage:  node scripts/module-state-snapshot.mjs            (compare; exit 1 on drift)
//         node scripts/module-state-snapshot.mjs --update   (rewrite baseline)

import { mkdirSync, readFileSync, writeFileSync, existsSync } from 'node:fs';
import { dirname, resolve } from 'node:path';
import { fileURLToPath, pathToFileURL } from 'node:url';
import { createRequire } from 'node:module';

const require   = createRequire(import.meta.url);
const { build } = require('esbuild'); // vite's own esbuild — no new dependency

const root    = resolve(dirname(fileURLToPath(import.meta.url)), '..');
const outFile = resolve(root, 'node_modules/.cache/qsd-module-state-bundle.mjs');
const snapFile = resolve(root, 'scripts/__snapshots__/module-state.v1.json');

mkdirSync(dirname(outFile), { recursive: true });

await build({
  entryPoints: [resolve(root, 'resources/ts/drawer-kit/utils/moduleNotifications/index.ts')],
  bundle: true,
  format: 'esm',
  outfile: outFile,
  jsx: 'automatic',
  jsxImportSource: 'preact',
  alias: { '@': resolve(root, 'resources/ts') },
  logLevel: 'silent',
});

const dna = await import(pathToFileURL(outFile).href);

// ── Fixture matrix ────────────────────────────────────────────────────────────
// Deterministic inputs per ModuleDefinition; contexts cover the engine flow:
// parent gate → empty prompt → problems → lifecycle tail.

const svc = (over = {}) => ({
  title: 'Cloud Backup', excerpt: '', content: 'Managed backup.',
  categories: [{ id: 3, name: 'Storage', slug: 'storage' }],
  ...over,
});
const draft = (over = {}) => ({
  title: 'Cloud Backup', excerpt: '', content: 'Managed backup.', category_ids: [3],
  ...over,
});
const cat = (over = {}) => ({
  name: 'Cloud Solutions', description: 'All cloud services.', slug: 'cloud-solutions',
  ...over,
});

const CTX = {
  activeSettled:   { platformStatus: 'active',   moduleTransition: 'settled' },
  activePending:   { platformStatus: 'active',   moduleTransition: 'pending', hasDraft: true },
  disabledSettled: { platformStatus: 'disabled', moduleTransition: 'settled' },
  notConfigured:   { platformStatus: 'disabled', moduleTransition: 'not-configured' },
};

const cases = [
  ['overview.complete.active',    dna.overviewModule, { service: svc() },                                        CTX.activeSettled],
  ['overview.complete.pending',   dna.overviewModule, { service: svc(), draft: draft() },                        CTX.activePending],
  ['overview.incomplete',         dna.overviewModule, { service: svc({ title: '', content: '' }) },              CTX.activeSettled],
  ['overview.draft.incomplete',   dna.overviewModule, { service: svc(), draft: draft({ category_ids: [] }) },    CTX.activePending],
  ['overview.notconfigured',      dna.overviewModule, { service: svc({ title: '', content: '', categories: [] }) }, CTX.notConfigured],

  // inclusions/faqs have no resolveStatus, so their snapshot status is always the
  // evaluateModule default ('pending-dim'); the notes are the meaningful signal.
  // UI presentation status for these modules is resolved by the caller.
  ['inclusions.empty',            dna.inclusionsModule, [],                                                      CTX.activeSettled],
  ['inclusions.unlabelled',       dna.inclusionsModule, [{ id: 'a', label: '' }, { id: 'b', label: 'SSL' }],     CTX.activeSettled],
  ['inclusions.complete.draft',   dna.inclusionsModule, [{ id: 'a', label: 'SSL' }],                             CTX.activePending],
  ['inclusions.complete.offline', dna.inclusionsModule, [{ id: 'a', label: 'SSL' }],                             CTX.disabledSettled],

  ['faqs.empty',                  dna.faqsModule, [],                                                            CTX.activeSettled],
  ['faqs.gaps',                   dna.faqsModule, [{ id: 'f1', question: '', answer: 'Yes' }, { id: 'f2', question: 'How?', answer: '' }], CTX.activeSettled],
  ['faqs.complete',               dna.faqsModule, [{ id: 'f1', question: 'How?', answer: 'Easily.' }],           CTX.activeSettled],

  // ── Category modules (S6) ──────────────────────────────────────────────────
  // categoryOverviewModule — the canonical 5-state resolution (blueprint D4).
  ['categoryOverview.complete.active',   dna.categoryOverviewModule, cat(),                              CTX.activeSettled],
  ['categoryOverview.complete.pending',  dna.categoryOverviewModule, cat(),                              CTX.activePending],
  ['categoryOverview.incomplete',        dna.categoryOverviewModule, cat({ description: '' }),           CTX.activeSettled],
  ['categoryOverview.notconfigured',     dna.categoryOverviewModule, cat({ name: '', description: '' }), CTX.notConfigured],
  ['categoryOverview.platformInactive',  dna.categoryOverviewModule, cat(),                              CTX.disabledSettled],
  ['categoryServices.empty',             dna.categoryServicesModule, { total: 0, active: 0, disabled: 0 }, CTX.activeSettled],
  ['categoryServices.offline',           dna.categoryServicesModule, { total: 2, active: 1, disabled: 1 }, CTX.disabledSettled],

  // ── Explicit Disable mask ─────────────────────────────────────────────────
  // Only the owning Station's explicit mask (ctx.disabled) reads Disabled; it
  // masks every module, empty ones included.
  ['overview.explicitlyDisabled',        dna.overviewModule, { service: svc() },                        { ...CTX.disabledSettled, disabled: true }],
  ['inclusions.explicitlyDisabled',      dna.inclusionsModule, [],                                      { ...CTX.disabledSettled, disabled: true }],
  ['categoryOverview.explicitlyDisabled', dna.categoryOverviewModule, cat(),                            { ...CTX.disabledSettled, disabled: true, platformLabel: 'Category' }],
];

const snapshot = {};
for (const [name, def, data, ctx] of cases) {
  snapshot[name] = dna.evaluateModule(def, data, ctx);
}
const serialized = JSON.stringify(snapshot, null, 2) + '\n';

if (process.argv.includes('--update') || !existsSync(snapFile)) {
  mkdirSync(dirname(snapFile), { recursive: true });
  writeFileSync(snapFile, serialized);
  console.log(`ModuleState snapshot written: ${snapFile} (${cases.length} cases)`);
} else {
  const previous = readFileSync(snapFile, 'utf8');
  if (previous === serialized) {
    console.log(`ModuleState parity OK — ${cases.length} cases byte-identical.`);
  } else {
    console.error('ModuleState DRIFT DETECTED — DNA behaviour changed. Diff the snapshot:');
    console.error(snapFile);
    process.exit(1);
  }
}
