// Account Brand editor — Name, Code, Logo, Favicon. No required field: Brand
// always settles cleanly on save (AccountBrand::settle). File upload is not
// one of the eight AdminFieldType controls (none exists in Admin Station), so
// the two media pickers are their own small dedicated control rather than a
// field definition.

import { useId } from 'preact/hooks';
import { AdminField } from '@/drawer-kit/fields';
import type { AccountMediaKind } from '../types';

export interface AccountBrandDraft {
  name: string;
  code: string;
  logoUrl: string | null;
  faviconUrl: string | null;
}

interface Props {
  draft: AccountBrandDraft;
  onChange: (patch: Partial<Pick<AccountBrandDraft, 'name' | 'code'>>) => void;
  onUploadMedia: (kind: AccountMediaKind, file: File) => void;
  uploading: AccountMediaKind | null;
}

function MediaPicker({
  label, kind, url, uploading, onUploadMedia,
}: {
  label: string;
  kind: AccountMediaKind;
  url: string | null;
  uploading: AccountMediaKind | null;
  onUploadMedia: (kind: AccountMediaKind, file: File) => void;
}) {
  const inputId = useId();
  const busy = uploading === kind;

  return (
    <div class="cz-tf-field">
      <label class="cz-tf-label" for={inputId}>{label}</label>
      {url && <img src={url} alt="" style="max-height:48px;display:block;margin-bottom:var(--cz-space-2)" />}
      <input
        id={inputId}
        type="file"
        accept="image/png,image/jpeg,image/gif,image/webp"
        disabled={busy}
        onChange={(event) => {
          const file = (event.target as HTMLInputElement).files?.[0];
          if (file) onUploadMedia(kind, file);
        }}
      />
      {busy && <p class="cz-tf-hint">Uploading…</p>}
    </div>
  );
}

export function AccountBrandEditor({ draft, onChange, onUploadMedia, uploading }: Props) {
  return (
    <div class="cz-tf-form">
      <AdminField
        def={{ id: 'cz-account-brand-name', type: 'text', label: 'Brand name' }}
        value={draft.name}
        onChange={(name) => onChange({ name })}
      />

      <AdminField
        def={{
          id: 'cz-account-brand-code',
          type: 'text',
          label: 'Brand code',
          hint: 'Up to six letters, no spaces.',
        }}
        value={draft.code}
        onChange={(code) => onChange({ code })}
      />

      <MediaPicker label="Logo" kind="logo" url={draft.logoUrl} uploading={uploading} onUploadMedia={onUploadMedia} />
      <MediaPicker label="Favicon" kind="favicon" url={draft.faviconUrl} uploading={uploading} onUploadMedia={onUploadMedia} />
    </div>
  );
}
