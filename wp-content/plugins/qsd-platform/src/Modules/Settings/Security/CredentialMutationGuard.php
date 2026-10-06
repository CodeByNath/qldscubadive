<?php

namespace QSD\Platform\Modules\Settings\Security;

/**
 * The one Security-owned mutual exclusion for writes to stored credentials.
 *
 * Every write to the connection option (secret save, configuration save,
 * disconnect) and every key rotation runs inside `hold()`, so a save can never
 * seal under a generation while rotation retires it, and rotation can never
 * reach its commit while a save is in flight. Not re-entrant.
 */
interface CredentialMutationGuard
{
    /**
     * Runs `$critical` while holding the guard.
     *
     * @template T
     * @param \Closure(): T $critical
     * @return T
     * @throws CredentialMutationBusy when the guard cannot be acquired in time; nothing has run
     */
    public function hold(\Closure $critical): mixed;
}
