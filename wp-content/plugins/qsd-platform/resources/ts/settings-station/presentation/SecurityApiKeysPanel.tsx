// Security → API Keys — provider credential management for administrators.
//
// One card per registered provider (Rezdy today), driven by the provider's own
// field declaration, so no provider is named here. Shows only safe state:
// configured or not, the environment, whether this server can store keys
// securely, and whether this user may change keys.
//
// A key is write-only. It is typed into a password input that exists only
// while adding or replacing, sent once, and dropped from local state the
// moment the save succeeds; a saved key reads only "Saved" and is never shown
// again. Remove key and Disconnect are armed in place with useInlineConfirm
// (contract §11). Without administrator authority the panel is view-only:
// provider configuration is shown read-only and nothing is sent. (The server
// enforces secret authority either way.)
//
// QSD owns the encryption that protects these keys: the first save sets it up,
// and an administrator can rotate it here. No key material is ever entered or
// shown, and nothing asks for server setup. Test connection runs the
// server-side Security check, which makes one read-only Rezdy staging call; it
// shows the outcome and the checks, never a secret.

import { useState } from 'preact/hooks';
import type { VNode } from 'preact';
import { useInlineConfirm } from '@/hooks/useInlineConfirm';
import { useSettingsConnections, type SettingsConnectionsState } from '../useSettingsConnections';
import type { ConnectionProjection, ConnectionSecretField, ConnectionState, SecurityValidationReport } from '../types';

const STATE_LABEL: Record<ConnectionState, string> = {
  not_configured: 'Not set up',
  incomplete:     'Incomplete',
  configured:     'Ready',
};

const STATE_CLASS: Record<ConnectionState, string> = {
  not_configured: 'cz-settings-state',
  incomplete:     'cz-settings-state cz-settings-state--incomplete',
  configured:     'cz-settings-state cz-settings-state--configured',
};

const OUTCOME_TEXT: Record<string, string> = {
  authenticated:       'Connected. Rezdy accepted the saved API key.',
  unauthorized:        'Rezdy did not accept this API key. Check the key and the environment.',
  refused_environment: 'Connection tests run against the Rezdy staging (sandbox) environment only for now.',
  not_configured:      'Add an API key first.',
  rate_limited:        'Rezdy is limiting requests right now. Try again in a minute.',
  upstream_error:      'Rezdy returned an error. Try again later.',
  unexpected_response: 'Rezdy returned an unexpected response. Try again later.',
  network_error:       'The server could not reach Rezdy. Try again later.',
};

type Notice = { tone: 'ok' | 'error'; text: string } | null;

function SecretRow({ connection, field, tools, confirm, notify }: {
  connection: ConnectionProjection;
  field: ConnectionSecretField;
  tools: SettingsConnectionsState;
  confirm: ReturnType<typeof useInlineConfirm<string>>;
  notify: (notice: Notice) => void;
}): VNode {
  const [editing, setEditing] = useState(false);
  const [value, setValue] = useState('');
  const busy = tools.busyProvider === connection.provider;
  const canChange = tools.canManageSecrets && tools.encryptionAvailable !== false;
  const removeId = `${connection.provider}:${field.key}:remove`;
  const id = `cz-api-key-${connection.provider}-${field.key}`;

  const cancel = () => { setEditing(false); setValue(''); };
  const save = async () => {
    if (value.trim() === '') return;
    const saved = await tools.save(connection.provider, { secrets: { [field.key]: value } });
    if (saved) {
      cancel();
      notify({ tone: 'ok', text: `${connection.label} ${field.label} saved. It is stored encrypted and will not be shown again.` });
    }
  };
  const remove = () => confirm.run(removeId, async () => {
    if (await tools.save(connection.provider, { clear: [field.key] })) {
      notify({ tone: 'ok', text: `${connection.label} ${field.label} removed.` });
    }
  });

  return (
    <div class="cz-tf-field cz-api-keys__secret" data-secret-field={field.key}>
      <label class="cz-tf-label" for={id}>{field.label}</label>
      {editing ? (
        <>
          <input id={id} type="password" autocomplete="new-password" class="cz-tf-control cz-tf-input"
            value={value} disabled={busy} placeholder={`Paste the ${connection.label} ${field.label}`}
            onInput={(e) => setValue((e.target as HTMLInputElement).value)} />
          <span class="cz-tf-hint">Stored encrypted on the server. It is never shown again, here or anywhere.</span>
          <div class="cz-settings-actions cz-settings-actions--start">
            <button type="button" class="cz-settings-button" onClick={cancel} disabled={busy}>Cancel</button>
            <button type="button" class="cz-settings-button cz-settings-button--primary" onClick={save} disabled={busy || value.trim() === ''}>
              {busy ? 'Saving…' : `Save ${field.label}`}
            </button>
          </div>
        </>
      ) : confirm.pendingId === removeId ? (
        <span class="cz-settings-confirm">
          <span class="cz-settings-confirm__prompt">Remove the saved {connection.label} {field.label}? {connection.label} stops working until a new one is added.</span>
          <button type="button" class="cz-settings-button" onClick={confirm.cancel} disabled={busy}>Cancel</button>
          <button type="button" class="cz-settings-button cz-settings-button--danger" onClick={remove} disabled={busy}>Remove</button>
        </span>
      ) : (
        <div class="cz-api-keys__secret-state">
          <span class={field.configured ? 'cz-settings-state cz-settings-state--configured' : 'cz-settings-state'}>
            {field.configured ? 'Saved' : 'Not added'}
          </span>
          {canChange && (
            <>
              <button type="button" class="cz-settings-button" onClick={() => setEditing(true)} disabled={busy}>
                {field.configured ? 'Replace' : 'Add'}
              </button>
              {field.configured && (
                <button type="button" class="cz-settings-link" onClick={() => confirm.request(removeId)} disabled={busy}>Remove</button>
              )}
            </>
          )}
        </div>
      )}
    </div>
  );
}

function ProviderCard({ connection, tools, confirm }: {
  connection: ConnectionProjection;
  tools: SettingsConnectionsState;
  confirm: ReturnType<typeof useInlineConfirm<string>>;
}): VNode {
  const [notice, setNotice] = useState<Notice>(null);
  const busy = tools.busyProvider === connection.provider;
  const viewOnly = !tools.canManageSecrets;
  const error = tools.actionError?.provider === connection.provider ? tools.actionError.message : null;
  const disconnectId = `${connection.provider}:disconnect`;

  const setConfig = async (key: string, value: string) => {
    if (viewOnly) return;
    setNotice(null);
    if (await tools.save(connection.provider, { values: { [key]: value } })) {
      setNotice({ tone: 'ok', text: 'Saved.' });
    }
  };
  const disconnect = () => confirm.run(disconnectId, async () => {
    if (await tools.disconnect(connection.provider)) {
      setNotice({ tone: 'ok', text: `${connection.label} disconnected. Its API key and settings were removed.` });
    }
  });

  return (
    <li class="cz-settings-connection" data-provider={connection.provider}>
      <div class="cz-settings-connection__header">
        <h4 class="cz-settings-connection__title">{connection.label}</h4>
        <span class={STATE_CLASS[connection.state]}>{STATE_LABEL[connection.state]}</span>
      </div>
      <p class="cz-settings-muted">{connection.description}</p>

      <div class="cz-settings-connection__fields">
        {connection.fields.map((field) => {
          if (field.type === 'secret') {
            return <SecretRow key={field.key} connection={connection} field={field} tools={tools} confirm={confirm} notify={setNotice} />;
          }
          const id = `cz-api-keys-${connection.provider}-${field.key}`;
          return (
            <div class="cz-tf-field" key={field.key}>
              <label class="cz-tf-label" for={id}>{field.label}</label>
              {field.type === 'select' ? (
                <select id={id} class="cz-tf-control cz-tf-select" value={field.value} disabled={busy || viewOnly}
                  onChange={(e) => setConfig(field.key, (e.target as HTMLSelectElement).value)}>
                  <option value="">Not set</option>
                  {field.options.map((option) => <option key={option.value} value={option.value}>{option.label}</option>)}
                </select>
              ) : (
                <input id={id} type="text" class="cz-tf-control cz-tf-input" value={field.value} disabled={busy || viewOnly} readOnly={viewOnly}
                  onChange={(e) => setConfig(field.key, (e.target as HTMLInputElement).value)} />
              )}
            </div>
          );
        })}
      </div>

      {error && <p class="cz-settings-error" role="alert">{error}</p>}
      {!error && notice && <p class={notice.tone === 'ok' ? 'cz-settings-notice' : 'cz-settings-error'} role="status">{notice.text}</p>}

      {tools.canManageSecrets && connection.state !== 'not_configured' && (
        <div class="cz-settings-actions">
          {confirm.pendingId === disconnectId ? (
            <span class="cz-settings-confirm">
              <span class="cz-settings-confirm__prompt">Disconnect {connection.label}? This removes its API key and settings.</span>
              <button type="button" class="cz-settings-button" onClick={confirm.cancel} disabled={busy}>Cancel</button>
              <button type="button" class="cz-settings-button cz-settings-button--danger" onClick={disconnect} disabled={busy}>Disconnect</button>
            </span>
          ) : (
            <button type="button" class="cz-settings-button" onClick={() => confirm.request(disconnectId)} disabled={busy}>Disconnect {connection.label}</button>
          )}
        </div>
      )}
    </li>
  );
}

function ConnectionTest({ tools }: { tools: SettingsConnectionsState }): VNode {
  const report: SecurityValidationReport | null = tools.validation;
  const outcome = report?.providerCheck?.outcome ?? null;
  const firstFailure = report?.checks.find((c) => !c.ok)?.check;
  const passedCount = report ? report.checks.filter((c) => c.ok).length : 0;

  return (
    <section class="cz-settings-connection" data-connection-test>
      <div class="cz-settings-connection__header">
        <h4 class="cz-settings-connection__title">Connection test</h4>
        {report && (
          <span class={report.passed ? 'cz-settings-state cz-settings-state--configured' : 'cz-settings-state cz-settings-state--incomplete'}>
            {report.passed ? 'Passed' : 'Needs attention'}
          </span>
        )}
      </div>
      <p class="cz-settings-muted">
        Checks the saved Rezdy API key with one read-only request, and checks that this server stores and uses keys securely. No key is shown.
      </p>
      {tools.validationError && <p class="cz-settings-error" role="alert">{tools.validationError}</p>}
      {report && (
        <>
          <p class={outcome === 'authenticated' ? 'cz-settings-notice' : 'cz-settings-error'} role="status" data-test-outcome>
            {outcome ? OUTCOME_TEXT[outcome] ?? 'The test finished with an unrecognised result.' : `The test could not reach Rezdy: ${firstFailure ?? 'unknown reason'}.`}
          </p>
          <details class="cz-api-keys__checks">
            <summary>Security checks: {passedCount} of {report.checks.length} passed</summary>
            <ul class="cz-settings-list" data-security-checks>
              {report.checks.map((c) => (
                <li key={c.check} class={c.ok ? 'cz-settings-muted' : 'cz-settings-error'}>{c.ok ? '✓' : '✗'} {c.check}</li>
              ))}
            </ul>
          </details>
        </>
      )}
      <div class="cz-settings-actions">
        <button type="button" class="cz-settings-button cz-settings-button--primary" onClick={tools.runValidation} disabled={tools.validating}>
          {tools.validating ? 'Testing…' : 'Test connection'}
        </button>
      </div>
    </section>
  );
}

function KeyRotation({ tools, confirm }: {
  tools: SettingsConnectionsState;
  confirm: ReturnType<typeof useInlineConfirm<string>>;
}): VNode {
  const rotateId = 'encryption:rotate';
  const rotate = () => confirm.run(rotateId, tools.rotateKey);
  const count = tools.rotation?.resealed ?? 0;

  return (
    <section class="cz-settings-connection" data-key-rotation>
      <div class="cz-settings-connection__header">
        <h4 class="cz-settings-connection__title">Encryption key</h4>
      </div>
      <p class="cz-settings-muted">
        Saved API keys are encrypted with a key this site manages for you. Rotating replaces it and re-encrypts every saved API key. They keep working, and nothing is shown.
      </p>
      {tools.rotationError && <p class="cz-settings-error" role="alert">{tools.rotationError}</p>}
      {tools.rotation && (
        <p class="cz-settings-notice" role="status" data-rotation-result>
          Encryption key rotated. {count === 1 ? '1 saved API key was' : `${count} saved API keys were`} re-encrypted.
        </p>
      )}
      <div class="cz-settings-actions">
        {confirm.pendingId === rotateId ? (
          <span class="cz-settings-confirm">
            <span class="cz-settings-confirm__prompt">Rotate the encryption key now? Saved API keys keep working.</span>
            <button type="button" class="cz-settings-button" onClick={confirm.cancel} disabled={tools.rotating}>Cancel</button>
            <button type="button" class="cz-settings-button cz-settings-button--primary" onClick={rotate} disabled={tools.rotating}>Rotate</button>
          </span>
        ) : (
          <button type="button" class="cz-settings-button" onClick={() => confirm.request(rotateId)} disabled={tools.rotating || tools.encryptionAvailable === false}>
            {tools.rotating ? 'Rotating…' : 'Rotate encryption key'}
          </button>
        )}
      </div>
    </section>
  );
}

export function SecurityApiKeysPanel(): VNode {
  const tools = useSettingsConnections();
  const confirm = useInlineConfirm<string>();

  if (tools.loading) return <p class="cz-station-empty">Loading API keys…</p>;
  if (tools.error) return <p class="cz-station-empty" role="alert">{tools.error}</p>;

  return (
    <div class="cz-settings-lane" data-api-keys>
      <ul class="cz-api-keys__status">
        <li data-secure-storage={tools.encryptionAvailable === false ? 'unavailable' : 'available'}>
          <strong>Secure storage:</strong>{' '}
          {tools.encryptionAvailable === false
            ? 'Unavailable on this site right now, so API keys cannot be saved. Saved keys are kept and nothing is shown.'
            : 'Ready. API keys are encrypted before they are stored.'}
        </li>
        <li data-key-permission={tools.canManageSecrets ? 'manage' : 'view'}>
          <strong>Your access:</strong>{' '}
          {tools.canManageSecrets
            ? 'Administrator. You can add, replace and remove API keys.'
            : 'View only. Only a site administrator can add, replace or remove API keys.'}
        </li>
      </ul>
      <ul class="cz-settings-list">
        {tools.connections.map((connection) => (
          <ProviderCard key={connection.provider} connection={connection} tools={tools} confirm={confirm} />
        ))}
      </ul>
      {tools.canManageSecrets && <ConnectionTest tools={tools} />}
      {tools.canManageSecrets && <KeyRotation tools={tools} confirm={confirm} />}
    </div>
  );
}
