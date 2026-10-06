// Connections & Security lane — one card per registered provider.
//
// Non-secret configuration is shown and edited in place. A secret is a
// write-only input: it starts empty, a saved secret reads only "Saved", and
// the typed value is cleared from local state as soon as the save succeeds.
// Disconnect is armed in place with useInlineConfirm (contract §11). Only an
// administrator may change a secret or disconnect; for anyone else secret
// inputs are read-only and Disconnect is not offered (the server refuses too).
// Administrators also get the Security check: the server-side Phase 2 runtime
// validation, shown as its safe pass/fail report.

import { useState } from 'preact/hooks';
import type { VNode } from 'preact';
import { useInlineConfirm } from '@/hooks/useInlineConfirm';
import { useSettingsConnections, type SettingsConnectionsState } from '../useSettingsConnections';
import type { ConnectionProjection, ConnectionState } from '../types';

const STATE_LABEL: Record<ConnectionState, string> = {
  not_configured: 'Not configured',
  incomplete:     'Incomplete',
  configured:     'Configured',
};

const STATE_CLASS: Record<ConnectionState, string> = {
  not_configured: 'cz-settings-state',
  incomplete:     'cz-settings-state cz-settings-state--incomplete',
  configured:     'cz-settings-state cz-settings-state--configured',
};

function initialValues(connection: ConnectionProjection): Record<string, string> {
  const values: Record<string, string> = {};
  for (const field of connection.fields) if (field.type !== 'secret') values[field.key] = field.value;
  return values;
}

function ConnectionCard({ connection, tools, confirm }: {
  connection: ConnectionProjection;
  tools: SettingsConnectionsState;
  confirm: ReturnType<typeof useInlineConfirm<string>>;
}): VNode {
  const [values, setValues] = useState(() => initialValues(connection));
  const [secrets, setSecrets] = useState<Record<string, string>>({});
  const [clear, setClear] = useState<string[]>([]);
  const busy = tools.busyProvider === connection.provider;
  const error = tools.actionError?.provider === connection.provider ? tools.actionError.message : null;
  const armed = confirm.pendingId === connection.provider;
  const secretsLocked = !tools.canManageSecrets;

  const handleSave = async () => {
    const typed = Object.fromEntries(Object.entries(secrets).filter(([, value]) => value.trim() !== ''));
    const saved = await tools.save(connection.provider, { values, secrets: typed, clear });
    if (saved) { setSecrets({}); setClear([]); }
  };

  const handleDisconnect = () => confirm.run(connection.provider, async () => {
    if (await tools.disconnect(connection.provider)) {
      setValues({}); setSecrets({}); setClear([]);
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
          const id = `cz-settings-${connection.provider}-${field.key}`;
          return (
            <div class="cz-tf-field" key={field.key}>
              <label class={field.required ? 'cz-tf-label cz-tf-label--required' : 'cz-tf-label'} for={id}>{field.label}</label>
              {field.type === 'select' ? (
                <select id={id} class="cz-tf-control cz-tf-select" value={values[field.key] ?? ''} disabled={busy}
                  onChange={(e) => setValues((prev) => ({ ...prev, [field.key]: (e.target as HTMLSelectElement).value }))}>
                  <option value="">Not set</option>
                  {field.options.map((option) => <option key={option.value} value={option.value}>{option.label}</option>)}
                </select>
              ) : field.type === 'text' ? (
                <input id={id} type="text" class="cz-tf-control cz-tf-input" value={values[field.key] ?? ''} disabled={busy}
                  onInput={(e) => setValues((prev) => ({ ...prev, [field.key]: (e.target as HTMLInputElement).value }))} />
              ) : (
                <>
                  <input id={id} type="password" autocomplete="new-password" class="cz-tf-control cz-tf-input"
                    value={secrets[field.key] ?? ''} disabled={busy || secretsLocked || clear.includes(field.key)}
                    placeholder={clear.includes(field.key) ? 'Will be removed on save' : field.configured ? 'Saved — enter a new value to replace' : 'Not set'}
                    onInput={(e) => setSecrets((prev) => ({ ...prev, [field.key]: (e.target as HTMLInputElement).value }))} />
                  <span class="cz-tf-hint">
                    {field.configured ? 'Saved. The stored value is never shown again.' : 'Stored server-side and never shown again.'}
                    {secretsLocked && ' Only a site administrator can change it.'}
                    {field.configured && !secretsLocked && (
                      <button type="button" class="cz-settings-link" disabled={busy}
                        onClick={() => setClear((prev) => (prev.includes(field.key) ? prev.filter((k) => k !== field.key) : [...prev, field.key]))}>
                        {clear.includes(field.key) ? 'Keep saved value' : 'Remove saved value'}
                      </button>
                    )}
                  </span>
                </>
              )}
            </div>
          );
        })}
      </div>

      {error && <p class="cz-settings-error" role="alert">{error}</p>}

      <div class="cz-settings-actions">
        {armed ? (
          <span class="cz-settings-confirm">
            <span class="cz-settings-confirm__prompt">Remove the {connection.label} connection?</span>
            <button type="button" class="cz-settings-button" onClick={confirm.cancel} disabled={busy}>Cancel</button>
            <button type="button" class="cz-settings-button cz-settings-button--danger" onClick={handleDisconnect} disabled={busy}>Remove</button>
          </span>
        ) : (
          <>
            {connection.state !== 'not_configured' && !secretsLocked && (
              <button type="button" class="cz-settings-button" onClick={() => confirm.request(connection.provider)} disabled={busy}>Disconnect</button>
            )}
            <button type="button" class="cz-settings-button cz-settings-button--primary" onClick={handleSave} disabled={busy}>
              {busy ? 'Saving…' : 'Save connection'}
            </button>
          </>
        )}
      </div>
    </li>
  );
}

function SecurityCheck({ tools }: { tools: SettingsConnectionsState }): VNode {
  const report = tools.validation;
  return (
    <section class="cz-settings-connection" data-security-check>
      <div class="cz-settings-connection__header">
        <h4 class="cz-settings-connection__title">Security check</h4>
        {report && (
          <span class={report.passed ? 'cz-settings-state cz-settings-state--configured' : 'cz-settings-state cz-settings-state--incomplete'}>
            {report.passed ? 'Passed' : 'Failed'}
          </span>
        )}
      </div>
      <p class="cz-settings-muted">
        Checks encrypted storage, single-use request keys and the audit trail on this server, and makes one read-only Rezdy staging connection check. No secret is shown.
      </p>
      {tools.validationError && <p class="cz-settings-error" role="alert">{tools.validationError}</p>}
      {report && (
        <>
          {report.providerCheck && (
            <p class="cz-settings-muted" data-provider-outcome>
              Rezdy ({report.providerCheck.environment ?? 'no environment'}): {report.providerCheck.outcome ?? 'no result'}
              {report.providerCheck.httpStatus !== null && ` · HTTP ${report.providerCheck.httpStatus}`}
              {report.providerCheck.latencyMs !== null && ` · ${report.providerCheck.latencyMs} ms`}
            </p>
          )}
          <ul class="cz-settings-list" data-security-checks>
            {report.checks.map((c) => (
              <li key={c.check} class={c.ok ? 'cz-settings-muted' : 'cz-settings-error'}>{c.ok ? '✓' : '✗'} {c.check}</li>
            ))}
          </ul>
        </>
      )}
      <div class="cz-settings-actions">
        <button type="button" class="cz-settings-button" onClick={tools.runValidation} disabled={tools.validating}>
          {tools.validating ? 'Checking…' : 'Run security check'}
        </button>
      </div>
    </section>
  );
}

export function SettingsConnectionsLane(): VNode {
  const tools = useSettingsConnections();
  const confirm = useInlineConfirm<string>();

  if (tools.loading) return <p class="cz-station-empty">Loading connections…</p>;
  if (tools.error) return <p class="cz-station-empty" role="alert">{tools.error}</p>;

  return (
    <div class="cz-settings-lane">
      <p class="cz-settings-muted">
        Provider credentials are kept on the server, encrypted. Saved secrets are never sent back to this screen.
      </p>
      {tools.encryptionAvailable === false && (
        <p class="cz-settings-error" role="alert">
          This server has no credential encryption key yet, so secret values cannot be saved. Ask the site administrator to configure it.
        </p>
      )}
      <ul class="cz-settings-list">
        {tools.connections.map((connection) => (
          <ConnectionCard key={connection.provider} connection={connection} tools={tools} confirm={confirm} />
        ))}
      </ul>
      {tools.canManageSecrets && <SecurityCheck tools={tools} />}
    </div>
  );
}
