import { accountBrandModule } from '@/drawer-kit/utils/moduleNotifications';
import type { ShellActionSchema, ShellSchema } from '@/drawer-kit/schema/types';
import type { TextValue } from '@/drawer-kit/schema/elements/library';
import type { AccountMediaKind } from '@/account-station/types';
import { AccountBrandEditor } from '../../editors/AccountBrandEditor';
import type { AccountBrandDraft } from '../../editors/AccountBrandEditor';

// No 'discard-draft' action: Account's backend has no revert endpoint for
// Brand (AccountController.php's four Brand routes are read/bootstrap/save/
// settle — settle always promotes whatever draft exists rather than
// discarding it) — offering one here would either need a new backend route
// or silently no-op, a false success.
const BRAND_ACTIONS: Record<string, ShellActionSchema> = {
  edit: { id: 'edit', label: 'Edit', intent: 'secondary' },
};

export interface AccountBrandShellData {
  platformId: string;
  bootstrapped: boolean;
  name: string;
  code: string;
  logoUrl: string | null;
  faviconUrl: string | null;
}

export const accountBrandShell: ShellSchema<AccountBrandShellData> = {
  archetype: 'overview',
  dna: accountBrandModule,
  header: {
    title: 'Brand',
    subtitle: 'Logo, Favicon, and the Brand Name/Code shown across the platform.',
    icon: 'overview',
    scopeClass: 'drawerOverview',
  },
  content: [
    {
      id: 'platform-id', element: 'text', label: 'Platform ID',
      bind: (data): TextValue => ({
        value: data.platformId,
        fallback: data.bootstrapped ? 'Not assigned' : 'Assigned after Save',
      }),
    },
    { id: 'name', element: 'text', label: 'Brand Name', bind: (data): TextValue => ({ value: data.name, fallback: 'Not set' }) },
    { id: 'code', element: 'text', label: 'Brand Code', bind: (data): TextValue => ({ value: data.code, fallback: 'Not set' }) },
    { id: 'logo', element: 'text', label: 'Logo', bind: (data): TextValue => ({ value: data.logoUrl ? 'Set' : '', fallback: 'Not set' }) },
    { id: 'favicon', element: 'text', label: 'Favicon', bind: (data): TextValue => ({ value: data.faviconUrl ? 'Set' : '', fallback: 'Not set' }) },
  ],
  footer: { actions: ['edit'] },
  actions: BRAND_ACTIONS,
  editor: {
    render: (session) => (
      <AccountBrandEditor
        draft={session.draft as AccountBrandDraft}
        onChange={(patch) => session.patch?.(patch)}
        onUploadMedia={session.extras?.onUploadMedia as (kind: AccountMediaKind, file: File) => void}
        uploading={(session.extras?.uploading ?? null) as AccountMediaKind | null}
      />
    ),
  },
};
