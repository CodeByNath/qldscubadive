// Contract: the drawer module ENTRY rule — platform-wide, for every drawer that
// presents modules, including every future Station's.
//
// One cycle, no exceptions:
//
//   the drawer opens on its Overview screen
//     → the module is readable, even when it is empty
//     → the module carries its own status pill from the shared 5-state vocabulary
//     → the pill opens that module's notification panel, which states what is missing
//     → the module offers Edit
//     → only Edit opens the module's inline editor
//
// A drawer may NOT greet the reader with an explanation block above its modules,
// and may NOT open an editor as its entry state — not for an empty record, and
// not for one that does not exist yet.
//
// Verified two ways: the real derivations are executed (status, pill, notes), and
// the compositions are read for the wiring those derivations need.
//
// A new Station adds its shells to SHELLS, its empty modules to ENTRY_STATES,
// and its composition checks below.

import { readFileSync } from 'node:fs';
import { resolve } from 'node:path';
import { PILL_META } from '../resources/ts/drawer-kit/schema/presentation';
import type { ModuleState } from '../resources/ts/drawer-kit/utils/moduleNotifications';
import {
  categoryOverviewModule,
  categoryServicesModule,
  evaluateModule,
  faqsModule,
  inclusionsModule,
  overviewModule,
} from '../resources/ts/drawer-kit/utils/moduleNotifications';
import type { ShellSchema } from '../resources/ts/drawer-kit/schema/types';
import type { ServiceItem } from '../resources/ts/api/types/service';
import {
  categoryOverviewShell,
  categoryServicesShell,
} from '../resources/ts/entity-drawers/schema/bindings/category';
import {
  serviceFaqsShell,
  serviceInclusionsShell,
  serviceOverviewShell,
} from '../resources/ts/service-station/drawer/schema/bindings/service';

const root = resolve(import.meta.dirname, '..');

function check(condition: unknown, message: string): asserts condition {
  if (!condition) throw new Error(`Drawer module entry contract: ${message}`);
}

function source(path: string): string {
  return readFileSync(resolve(root, path), 'utf8');
}

// ── Every declared shell, so a new one is covered the day it is written ────────

const SHELLS: Array<[string, ShellSchema<any>]> = [
  ['Category Overview', categoryOverviewShell],
  ['Category Services', categoryServicesShell],
  ['Service Overview', serviceOverviewShell],
  ['Service Inclusions', serviceInclusionsShell],
  ['Service FAQs', serviceFaqsShell],
];

// An editable module is always reachable from its readable card, and a card that
// advertises Edit always has an editor behind it.
for (const [name, shell] of SHELLS) {
  const advertisesEdit = shell.footer.actions.includes('edit');
  if (shell.editor) {
    check(!!shell.actions.edit, `${name} declares an editor, so it must declare an Edit action`);
    check(advertisesEdit, `${name} declares an editor, so Edit must be in its Footer Group`);
  }
  if (advertisesEdit) {
    check(!!shell.editor, `${name} advertises Edit, so it must declare the editor Edit opens`);
  }
}

// ── The entry state of every module a drawer can open empty ───────────────────
// Each must resolve INSIDE the pill vocabulary and read Pending, and carry at
// least one note, because the pill is the only guidance an empty module gets.

const EMPTY_SERVICE: ServiceItem = {
  id: 0, title: '', slug: '', excerpt: '', content: '', categories: [], inclusions: [], faqs: [],
  meta: {
    platform_status: 'disabled', previous_platform_status: '',
    module_status: { overview: 'not-configured', inclusions: 'not-configured', faqs: 'not-configured' },
  },
};
const NEW_RECORD = { platformStatus: 'disabled', moduleTransition: 'not-configured' } as const;

const ENTRY_STATES: Array<[string, ModuleState]> = [
  ['Service Overview, new record', evaluateModule(overviewModule, { service: EMPTY_SERVICE, draft: null }, NEW_RECORD)],
  ['Service Inclusions, empty', evaluateModule(inclusionsModule, [], NEW_RECORD)],
  ['Service FAQs, empty', evaluateModule(faqsModule, [], NEW_RECORD)],
  ['Category Overview, new record', evaluateModule(categoryOverviewModule, { name: '', description: '', slug: '' }, { ...NEW_RECORD, platformLabel: 'Category' })],
  ['Category Services, none assigned', evaluateModule(categoryServicesModule, { total: 0, active: 0, disabled: 0 }, { ...NEW_RECORD, platformLabel: 'Category' })],
];

for (const [name, state] of ENTRY_STATES) {
  const pill = PILL_META[state.status];
  check(pill !== undefined, `${name} resolves ${state.status}, which is outside the pill vocabulary`);
  check(pill.label === 'Pending', `${name} reads ${pill.label} while empty; an empty module is Pending`);
  check(state.notes.length > 0, `${name} carries no note, so its pill would open nothing`);
}

// ── Disabled is a user action, never a derivation ─────────────────────────────
// Service and Category store a never-published record as platform_status
// 'disabled' with an empty Disable mask. That is Pending, never Disabled.

const NEVER_ACTIVATED = { platformStatus: 'disabled', moduleTransition: 'settled' } as const;
const NAMED_CATEGORY = { name: 'Reef Dives', description: '', slug: 'reef-dives' };

check(
  evaluateModule(categoryOverviewModule, NAMED_CATEGORY, { ...NEVER_ACTIVATED, platformLabel: 'Category' }).status !== 'disabled',
  'a never-published Category reads Pending, not Disabled',
);
check(
  evaluateModule(inclusionsModule, [{ id: 'gear', label: 'Full gear hire' }], NEVER_ACTIVATED).status !== 'disabled',
  'never-published Service Inclusions read Pending, not Disabled',
);

// The other half: an EXPLICIT disable (ctx.disabled, the mask) does read
// Disabled on every module, so the pill agrees with the footer.
for (const [name, state] of [
  ['Category Overview', evaluateModule(categoryOverviewModule, NAMED_CATEGORY, { platformStatus: 'disabled', disabled: true })],
  ['Service Inclusions', evaluateModule(inclusionsModule, [], { platformStatus: 'disabled', disabled: true })],
  ['Service FAQs', evaluateModule(faqsModule, [], { platformStatus: 'disabled', disabled: true })],
] as const) {
  check(state.status === 'disabled', `an explicitly disabled ${name} reads Disabled`);
}

// A published, complete record must leave Pending behind.
check(
  PILL_META[evaluateModule(categoryOverviewModule, NAMED_CATEGORY, { platformStatus: 'active', moduleTransition: 'settled' }).status]?.label === 'Active',
  'a published Category Overview reads Active',
);

// ── The compositions that open these modules ─────────────────────────────────
// `useState(false)` / `useState<…>(null)` is the entry state itself; a truthy
// initial editor state would mean the drawer opens in its editor.

const serviceEditing = source('resources/ts/service-station/drawer/useServiceModuleEditing.ts');
check(
  serviceEditing.includes('useState<ServiceEditingSection>(null)'),
  'the Service drawer opens readable — no module editor is pre-selected on entry',
);
const serviceContent = source('resources/ts/service-station/drawer/ServiceDrawerContent.tsx');
check(serviceContent.includes('onTogglePanel='), 'the Service drawer wires module notification panels to their pills');

const categoryController = source('resources/ts/entity-drawers/category/useCategoryDrawerController.ts');
check(
  categoryController.includes('const [editing, setEditing] = useState(false)'),
  'the Category drawer opens readable, never in its editor',
);
const cancelBody = categoryController.slice(
  categoryController.indexOf('const cancelEdit = useCallback'),
  categoryController.indexOf('const cancelEdit = useCallback') + 400,
);
check(cancelBody.includes('setEditing(false)'), 'Category Cancel returns to the readable module rather than closing the drawer');
const categoryContent = source('resources/ts/entity-drawers/category/CategoryDrawerContent.tsx');
check(categoryContent.includes('onTogglePanel='), 'the Category drawer wires its module notification panel to its pill');

// Create launchers open the SAME mature drawers readable, at the 'new' sentinel.
const adminRegister = source('resources/ts/admin-station/register.ts');
check(
  adminRegister.includes("{ id: 'create-service', target: 'drawer', mode: 'view', drawerTemplateKey: 'service' }")
    && adminRegister.includes("{ id: 'create-category', target: 'drawer', mode: 'view', drawerTemplateKey: 'category' }"),
  'Create Service / Create Category open their drawers readable (mode: view), never pre-entered into an editor',
);

// ── Saved-record Move to Trash goes through the drawer's own dialog ─────────
// Both conforming drawers arm a confirmation from the footer and run the
// Station's Trash action only from that dialog's confirm handler; the local
// `new` composition discards by closing. Behaviour is proven in
// scripts/drawer-trash-confirm-regression.mjs; this pins the wiring.
const serviceController = source('resources/ts/service-station/drawer/useServiceDrawerController.ts');
const serviceDialogs = source('resources/ts/service-station/drawer/ServiceDrawerDialogs.tsx');
check(serviceController.includes('handleTrash: requestTrash'), 'the Service footer Move to Trash arms the confirmation, never the Trash action directly');
check(serviceDialogs.includes('c.trashConfirm &&') && serviceDialogs.includes('onClick={c.handleConfirmTrash}'), 'the Service Trash confirmation lives in ServiceDrawerDialogs and confirms through the controller');
const categoryDialogs = source('resources/ts/entity-drawers/category/CategoryDrawerDialogs.tsx');
check(categoryController.includes("setConfirmDialog('trash')"), 'the Category footer Move to Trash arms the confirmation');
check(categoryDialogs.includes('onClick={c.handleConfirmDestructive}'), 'the Category Trash confirmation confirms through the controller');

console.log(`Drawer module entry contract passed: ${SHELLS.length} shells, ${ENTRY_STATES.length} entry states.`);
