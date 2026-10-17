<?php

namespace QSD\Platform\Modules\StudioHost;

/**
 * StudioStagingDiagnostic — a single read-only report of what
 * `StudioStagingPointer`/`StudioReleaseLock` actually resolve to on the
 * exact server it runs on, for the one gap no amount of local testing or CI
 * file-presence checking can substitute for: real-server activation proof.
 *
 * Exposes nothing secret — every field here is already either a WordPress
 * environment classification, a non-secret CI-written deployment marker, or
 * a release id/sequence WEX itself publishes at a public URL. Invoked only
 * from the CLI entry point at `app/cli/studio-staging-diagnostic.php`
 * (guarded to refuse any non-CLI SAPI), never from an HTTP route — this is
 * deliberately not a new REST diagnostic endpoint.
 */
final class StudioStagingDiagnostic
{
    /**
     * @param (\Closure(string): array{status: ?int, body: ?string})|null $transport Test seam only; passed through to both the pointer and the release lock.
     * @param (\Closure(): string)|null $environmentType Test seam only.
     * @param (\Closure(): ?array)|null $deployMarker Test seam only.
     * @return array{
     *     environmentType: string,
     *     deploymentProven: bool,
     *     gateActive: bool,
     *     storedSequence: ?int,
     *     pinSource: 'static-pin'|'pointer',
     *     releaseId: string,
     *     manifestVerified: bool,
     *     fallbackReason: string
     * }
     */
    public static function report(?\Closure $transport = null, ?\Closure $environmentType = null, ?\Closure $deployMarker = null): array
    {
        $pointer = new StudioStagingPointer($transport, $environmentType, $deployMarker);
        $gate    = $pointer->describeGate();

        $staticPin = StudioReleaseLock::staticFallback();
        $resolved  = $pointer->resolvePin($staticPin);
        $pinSource = $resolved['releaseId'] === $staticPin['releaseId'] ? 'static-pin' : 'pointer';

        $lock     = new StudioReleaseLock($resolved, $transport);
        $verified = $lock->verifiedRelease();

        if (!$gate['active']) {
            $fallbackReason = 'environment gate inactive (wp_get_environment_type() is not staging, and no deployment-proof marker is present)';
        } elseif ($verified === null) {
            $fallbackReason = 'selected manifest could not be verified (outage or tamper)';
        } elseif ($pinSource === 'pointer') {
            $fallbackReason = 'pointer-driven release active (approved advance adopted with no QSD code commit)';
        } else {
            $fallbackReason = 'static pin (no newer verified pointer-driven release has been adopted)';
        }

        return [
            'environmentType'  => $gate['environmentType'],
            'deploymentProven' => $gate['deploymentProven'],
            'gateActive'       => $gate['active'],
            'storedSequence'   => $pointer->storedSequence(),
            'pinSource'        => $pinSource,
            'releaseId'        => $resolved['releaseId'],
            'manifestVerified' => $verified !== null,
            'fallbackReason'   => $fallbackReason,
        ];
    }
}
