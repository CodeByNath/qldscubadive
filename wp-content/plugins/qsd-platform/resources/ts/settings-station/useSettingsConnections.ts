// Connections/Security Tool state — the provider connection list and its
// save/disconnect actions. Presentation calls these handlers, never ./api.
//
// A saved secret is never held here after the request: the backend projection
// returns only `configured`, and this hook keeps no copy of what was sent.

import { useCallback, useEffect, useState } from 'preact/hooks';
import { disconnectConnection, fetchConnections, rotateEncryptionKey, runSecurityValidation, saveConnection } from './api';
import { errorMessage } from './errorMessage';
import type { ConnectionProjection, ConnectionSavePayload, KeyRotationResult, SecurityValidationReport } from './types';

export interface SettingsConnectionsState {
  connections:  ConnectionProjection[];
  // False when this site cannot store keys securely (the backend refuses
  // rather than storing plaintext). There is nothing to set up from here.
  encryptionAvailable: boolean | null;
  // Only an administrator may set, replace, clear or disconnect secrets; a
  // platform manager edits non-secret configuration only.
  canManageSecrets: boolean;
  loading:      boolean;
  error:        string | null;
  busyProvider: string | null;
  actionError:  { provider: string; message: string } | null;
  save:         (provider: string, payload: ConnectionSavePayload) => Promise<boolean>;
  disconnect:   (provider: string) => Promise<boolean>;
  // Security Phase 2 runtime check (administrators only; makes one provider call).
  validation:      SecurityValidationReport | null;
  validating:      boolean;
  validationError: string | null;
  runValidation:   () => Promise<void>;
  // Rotate encryption key (administrators only; re-encrypts every saved key).
  rotation:      KeyRotationResult | null;
  rotating:      boolean;
  rotationError: string | null;
  rotateKey:     () => Promise<void>;
}

export function useSettingsConnections(): SettingsConnectionsState {
  const [connections, setConnections] = useState<ConnectionProjection[]>([]);
  const [encryptionAvailable, setEncryptionAvailable] = useState<boolean | null>(null);
  const [canManageSecrets, setCanManageSecrets] = useState(false);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);
  const [busyProvider, setBusyProvider] = useState<string | null>(null);
  const [actionError, setActionError] = useState<SettingsConnectionsState['actionError']>(null);
  const [validation, setValidation] = useState<SecurityValidationReport | null>(null);
  const [validating, setValidating] = useState(false);
  const [validationError, setValidationError] = useState<string | null>(null);
  const [rotation, setRotation] = useState<KeyRotationResult | null>(null);
  const [rotating, setRotating] = useState(false);
  const [rotationError, setRotationError] = useState<string | null>(null);

  useEffect(() => {
    fetchConnections()
      .then((result) => {
        setConnections(result.connections);
        setEncryptionAvailable(result.encryptionAvailable);
        setCanManageSecrets(result.canManageSecrets);
      })
      .catch((err: unknown) => setError(errorMessage(err, 'Could not load connections.')))
      .finally(() => setLoading(false));
  }, []);

  const run = useCallback(async (provider: string, operation: () => Promise<ConnectionProjection>, fallback: string) => {
    setBusyProvider(provider);
    setActionError(null);
    try {
      const next = await operation();
      setConnections((prev) => prev.map((c) => (c.provider === next.provider ? next : c)));
      return true;
    } catch (err) {
      setActionError({ provider, message: errorMessage(err, fallback) });
      return false;
    } finally {
      setBusyProvider(null);
    }
  }, []);

  const save = useCallback(
    (provider: string, payload: ConnectionSavePayload) =>
      run(provider, () => saveConnection(provider, payload), 'The connection could not be saved.'),
    [run],
  );
  const disconnect = useCallback(
    (provider: string) => run(provider, () => disconnectConnection(provider), 'The connection could not be removed.'),
    [run],
  );

  const runValidation = useCallback(async () => {
    setValidating(true);
    setValidationError(null);
    try {
      setValidation(await runSecurityValidation());
    } catch (err) {
      setValidation(null);
      setValidationError(errorMessage(err, 'The security check could not be run.'));
    } finally {
      setValidating(false);
    }
  }, []);

  const rotateKey = useCallback(async () => {
    setRotating(true);
    setRotation(null);
    setRotationError(null);
    try {
      setRotation(await rotateEncryptionKey());
    } catch (err) {
      setRotationError(errorMessage(err, 'The encryption key could not be rotated.'));
    } finally {
      setRotating(false);
    }
  }, []);

  return {
    connections, encryptionAvailable, canManageSecrets, loading, error, busyProvider, actionError, save, disconnect,
    validation, validating, validationError, runValidation,
    rotation, rotating, rotationError, rotateKey,
  };
}
