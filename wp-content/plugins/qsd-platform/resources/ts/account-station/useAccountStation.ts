// Account Station — detail fetch, busy/error state, and every mutation.
//
// There is no `service: ServiceItem | null` input here: Account has no
// per-record identity to receive, because exactly one Account ever exists.
// The first Save bootstraps identity server-side (AccountController::
// saveProfile); this hook does not special-case a "new" sentinel.
//
// Save writes the Brand draft only (module stays Pending). Settling is never
// a separate user action — Publish settles and activates in one backend
// call, matching the batch's "no separate user-facing Settle control".

import { useCallback, useEffect, useState } from 'preact/hooks';
import {
  bootstrapAccount,
  disableAccount,
  enableAccount,
  fetchAccountDetail,
  publishAccount,
  saveAccountProfile,
  uploadAccountMedia,
} from './api';
import type { AccountDetail, AccountMediaKind, AccountProfileInput } from './types';

export interface UseAccountStationResult {
  detail: AccountDetail | null;
  loading: boolean;
  error: string | null;
  busy: boolean;
  refetch: () => void;
  bootstrap: () => Promise<void>;
  saveBrand: (input: AccountProfileInput) => Promise<void>;
  publish: () => Promise<void>;
  disable: () => Promise<void>;
  enable: () => Promise<void>;
  uploadMedia: (kind: AccountMediaKind, file: File) => Promise<string>;
}

export function useAccountStation(): UseAccountStationResult {
  const [detail, setDetail] = useState<AccountDetail | null>(null);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);
  const [busy, setBusy] = useState(false);
  const [retryKey, setRetryKey] = useState(0);

  useEffect(() => {
    let cancelled = false;
    setLoading(true);
    setError(null);

    fetchAccountDetail()
      .then((next) => {
        if (!cancelled) setDetail(next);
      })
      .catch((err: unknown) => {
        if (!cancelled) setError(err instanceof Error ? err.message : 'An unexpected error occurred.');
      })
      .finally(() => {
        if (!cancelled) setLoading(false);
      });

    return () => {
      cancelled = true;
    };
  }, [retryKey]);

  const refetch = useCallback(() => setRetryKey((key) => key + 1), []);

  const runMutation = useCallback(async (mutate: () => Promise<AccountDetail>): Promise<void> => {
    setBusy(true);
    setError(null);
    try {
      const next = await mutate();
      setDetail(next);
    } catch (err) {
      setError(err instanceof Error ? err.message : 'An unexpected error occurred.');
      throw err;
    } finally {
      setBusy(false);
    }
  }, []);

  const bootstrap = useCallback(() => runMutation(bootstrapAccount), [runMutation]);
  const saveBrand = useCallback(
    (input: AccountProfileInput) => runMutation(() => saveAccountProfile(input)),
    [runMutation],
  );
  const publish = useCallback(() => runMutation(publishAccount), [runMutation]);
  const disable = useCallback(() => runMutation(disableAccount), [runMutation]);
  const enable = useCallback(() => runMutation(enableAccount), [runMutation]);

  const uploadMedia = useCallback(async (kind: AccountMediaKind, file: File): Promise<string> => {
    setBusy(true);
    setError(null);
    try {
      const media = await uploadAccountMedia(kind, file);
      return media.mediaId;
    } catch (err) {
      setError(err instanceof Error ? err.message : 'An unexpected error occurred.');
      throw err;
    } finally {
      setBusy(false);
    }
  }, []);

  return { detail, loading, error, busy, refetch, bootstrap, saveBrand, publish, disable, enable, uploadMedia };
}
