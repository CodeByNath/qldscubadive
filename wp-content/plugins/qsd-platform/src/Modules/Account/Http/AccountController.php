<?php

/*
 * FILE INDEX
 *
 * ACCOUNT_ROUTES   The qsd_account_station route registrations
 * IDENTITY         Read-only detail; bootstrap (identity only)
 * BRAND_HANDLERS   Draft save, settle, media upload/library (Phase B)
 * LIFECYCLE        Publish / Disable / Enable (Phase B)
 * PROJECTION       Wire shape shared by every handler
 * AUTHORIZATION    Permission callback
 *
 * OWNERSHIP
 * The single backend owner of the Account singleton hierarchy: its identity
 * bootstrap, its one Brand module (draft/settle), its Publish/Disable/Enable
 * mask, and its media. No Archive/Trash/Delete route exists or is planned
 * without a separate, explicit Owner decision — this is a deliberate
 * singleton carve-out from the Station/Drawer lifecycle contract's travel
 * table, not an oversight.
 */

namespace QSD\Platform\Modules\Account\Http;

use QSD\Platform\Modules\Account\Support\AccountBrand;
use QSD\Platform\Modules\Account\Support\AccountIdentity;
use QSD\Platform\Modules\Account\Support\AccountMedia;
use QSD\Platform\Modules\Account\Support\AccountRepository;
use QSD\Platform\Modules\Account\Support\AccountSchema;
use QSD\Platform\Modules\Account\Support\AccountStorageBusy;
use QSD\Platform\PlatformIdentifier\PlatformIdentifierConflict;
use QSD\Platform\PlatformIdentifier\PlatformIdentifierPolicy;
use QSD\Platform\PlatformIdentifier\PlatformIdentifierStation;

class AccountController
{
    private AccountIdentity $identity;
    private AccountRepository $repository;
    private AccountMedia $media;

    /** @param AccountMedia|null $media Test seam only; production uses a real AccountMedia. */
    public function __construct(private PlatformIdentifierStation $platformIdentifiers, ?AccountMedia $media = null)
    {
        $this->repository = new AccountRepository();
        $this->identity   = new AccountIdentity($this->platformIdentifiers, $this->repository);
        $this->media      = $media ?? new AccountMedia();
    }

    public function register(): void
    {
        add_action('rest_api_init', [$this, 'registerRoutes']);
    }

    public function registerRoutes(): void
    {
        // ===================================================================
        // SECTION: ACCOUNT_ROUTES
        // ===================================================================
        register_rest_route('qsd/v1', '/admin/account', [
            'methods'             => 'GET',
            'callback'            => [$this, 'fetchDetail'],
            'permission_callback' => [$this, 'requireAdmin'],
        ]);

        register_rest_route('qsd/v1', '/admin/account/bootstrap', [
            'methods'             => 'POST',
            'callback'            => [$this, 'bootstrap'],
            'permission_callback' => [$this, 'requireAdmin'],
        ]);

        register_rest_route('qsd/v1', '/admin/account/profile', [
            'methods'             => 'POST',
            'callback'            => [$this, 'saveProfile'],
            'permission_callback' => [$this, 'requireAdmin'],
        ]);

        register_rest_route('qsd/v1', '/admin/account/profile/settle', [
            'methods'             => 'POST',
            'callback'            => [$this, 'settleProfile'],
            'permission_callback' => [$this, 'requireAdmin'],
        ]);

        register_rest_route('qsd/v1', '/admin/account/profile/media', [
            'methods'             => 'POST',
            'callback'            => [$this, 'uploadBrandMedia'],
            'permission_callback' => [$this, 'requireAdmin'],
        ]);

        register_rest_route('qsd/v1', '/admin/account/profile/media/library', [
            'methods'             => 'GET',
            'callback'            => [$this, 'listBrandMedia'],
            'permission_callback' => [$this, 'requireAdmin'],
        ]);

        register_rest_route('qsd/v1', '/admin/account/status', [
            'methods'             => 'POST',
            'callback'            => [$this, 'updateStatus'],
            'permission_callback' => [$this, 'requireAdmin'],
        ]);
    }

    // ===================================================================
    // SECTION: IDENTITY
    // ===================================================================

    /** Read-only. Never reserves or binds an identifier. */
    public function fetchDetail(\WP_REST_Request $request): \WP_REST_Response
    {
        return rest_ensure_response(['success' => true, 'account' => $this->projection()]);
    }

    /**
     * Idempotent. Safe to call repeatedly or concurrently: a node already
     * bound is re-affirmed, not re-minted; a losing concurrent caller sees
     * PlatformIdentifierConflict and should retry.
     */
    public function bootstrap(\WP_REST_Request $request): \WP_REST_Response
    {
        if ($failure = $this->mintIdentity()) {
            return $failure;
        }

        return rest_ensure_response(['success' => true, 'account' => $this->projection()]);
    }

    // ===================================================================
    // SECTION: BRAND_HANDLERS
    // ===================================================================

    /** Bootstraps identity on first call, then writes the Brand draft. One commit for the draft. */
    public function saveProfile(\WP_REST_Request $request): \WP_REST_Response
    {
        if ($rejection = $this->rejectIdentityMutation($request)) {
            return $rejection;
        }
        if ($failure = $this->mintIdentity()) {
            return $failure;
        }

        $input = [];
        foreach (['name', 'code', 'logo_media_id', 'favicon_media_id'] as $field) {
            if ($request->has_param($field)) {
                $input[$field] = $request->get_param($field);
            }
        }

        try {
            $this->repository->commit(static fn(array $state): array => AccountBrand::applyDraft($state, $input));
        } catch (AccountStorageBusy $error) {
            return new \WP_REST_Response(['success' => false, 'message' => $error->getMessage()], 503);
        }

        return rest_ensure_response(['success' => true, 'account' => $this->projection()]);
    }

    /** Promotes the draft to canonical. Brand has no required field, so this always settles. */
    public function settleProfile(\WP_REST_Request $request): \WP_REST_Response
    {
        if ($notReady = $this->requireBootstrapped()) {
            return $notReady;
        }

        try {
            $this->repository->commit(static fn(array $state): array => AccountBrand::settle($state));
        } catch (AccountStorageBusy $error) {
            return new \WP_REST_Response(['success' => false, 'message' => $error->getMessage()], 503);
        }

        return rest_ensure_response(['success' => true, 'account' => $this->projection()]);
    }

    public function uploadBrandMedia(\WP_REST_Request $request): \WP_REST_Response
    {
        $kind = (string) $request->get_param('kind');
        if (!in_array($kind, ['logo', 'favicon'], true)) {
            return new \WP_REST_Response(['success' => false, 'message' => 'kind must be "logo" or "favicon".'], 422);
        }

        $files = $request->get_file_params();
        $file  = $files['file'] ?? null;
        if (!is_array($file)) {
            return new \WP_REST_Response(['success' => false, 'message' => 'No file was uploaded.'], 422);
        }

        $stored = $this->media->store($file);
        if ($stored instanceof \WP_Error) {
            return new \WP_REST_Response(['success' => false, 'message' => $stored->get_error_message()], 422);
        }

        try {
            $this->repository->commit(static function (array $state) use ($stored): array {
                $state['media'][$stored['media_id']] = [
                    'mime'       => $stored['mime'],
                    'size'       => $stored['size'],
                    'created_at' => gmdate('c'),
                ];

                return $state;
            });
        } catch (AccountStorageBusy $error) {
            return new \WP_REST_Response(['success' => false, 'message' => $error->getMessage()], 503);
        }

        return rest_ensure_response([
            'success' => true,
            'media'   => [
                'media_id' => $stored['media_id'],
                'kind'     => $kind,
                'url'      => $this->media->url($stored['media_id']),
                'mime'     => $stored['mime'],
                'size'     => $stored['size'],
            ],
        ]);
    }

    public function listBrandMedia(\WP_REST_Request $request): \WP_REST_Response
    {
        $media = $this->repository->state()['media'] ?? [];
        $items = [];
        foreach ($media as $mediaId => $entry) {
            $items[] = [
                'media_id'   => $mediaId,
                'url'        => $this->media->url((string) $mediaId),
                'mime'       => $entry['mime'] ?? '',
                'size'       => $entry['size'] ?? 0,
                'created_at' => $entry['created_at'] ?? null,
            ];
        }

        return rest_ensure_response(['success' => true, 'media' => $items]);
    }

    // ===================================================================
    // SECTION: LIFECYCLE
    // ===================================================================

    public function updateStatus(\WP_REST_Request $request): \WP_REST_Response
    {
        if ($notReady = $this->requireBootstrapped()) {
            return $notReady;
        }

        $action = $request->has_param('action') ? (string) $request->get_param('action') : null;
        $target = $request->has_param('platform_status') ? (string) $request->get_param('platform_status') : null;

        $mutator = match (true) {
            $action === 'disable'               => static fn(array $state): ?array => AccountBrand::disable($state),
            $action === 'enable'                => static fn(array $state): ?array => AccountBrand::enable($state),
            $target === AccountSchema::STATUS_ACTIVE => static fn(array $state): ?array => AccountBrand::publish($state),
            default                              => null,
        };

        if ($mutator === null) {
            return new \WP_REST_Response([
                'success' => false,
                'message' => 'Provide action "disable"/"enable", or platform_status "active" to publish.',
            ], 422);
        }

        $illegal = false;
        try {
            $this->repository->commit(function (array $state) use ($mutator, &$illegal): array {
                $next = $mutator($state);
                if ($next === null) {
                    $illegal = true;
                    return $state;
                }

                return $next;
            });
        } catch (AccountStorageBusy $error) {
            return new \WP_REST_Response(['success' => false, 'message' => $error->getMessage()], 503);
        }

        if ($illegal) {
            return new \WP_REST_Response(['success' => false, 'message' => 'That transition is not legal from the current status.'], 422);
        }

        return rest_ensure_response(['success' => true, 'account' => $this->projection()]);
    }

    // ===================================================================
    // SECTION: PROJECTION
    // ===================================================================

    private function projection(): array
    {
        $state = $this->repository->state();
        $nodes = [];
        foreach (AccountSchema::NODE_ORDER as $entityType) {
            $nodes[$entityType] = $state['nodes'][$entityType]['platform_id'] ?? null;
        }
        $bootstrapped = !in_array(null, $nodes, true);

        $brand = $state['brand'] ?? AccountSchema::emptyBrand();

        return [
            'bootstrapped'             => $bootstrapped,
            'nodes'                    => [
                'account'  => ['platform_id' => $nodes[PlatformIdentifierPolicy::ACCOUNT] ?? null],
                'settings' => ['platform_id' => $nodes[PlatformIdentifierPolicy::ACCOUNT_SETTINGS] ?? null],
                'tools'    => ['platform_id' => $nodes[PlatformIdentifierPolicy::ACCOUNT_TOOLS] ?? null],
                'profile'  => ['platform_id' => $nodes[PlatformIdentifierPolicy::ACCOUNT_PROFILE] ?? null],
            ],
            'platform_status'          => $state['platform_status'] ?? AccountSchema::STATUS_DISABLED,
            'previous_platform_status' => (string) ($state['previous_platform_status'] ?? ''),
            'module_status'            => $state['module_status'] ?? [AccountSchema::MODULE_BRAND => AccountSchema::MODULE_NOT_CONFIGURED],
            'brand'                    => [
                'name'        => $brand['name'] ?? '',
                'code'        => $brand['code'] ?? '',
                'logo_url'    => !empty($brand['logo_media_id']) ? $this->media->url($brand['logo_media_id']) : null,
                'favicon_url' => !empty($brand['favicon_media_id']) ? $this->media->url($brand['favicon_media_id']) : null,
            ],
            'has_draft'                => ($state['brand_draft'] ?? null) !== null,
        ];
    }

    /** @return \WP_REST_Response|null a 500/503 response, or null on success */
    private function mintIdentity(): ?\WP_REST_Response
    {
        try {
            $this->identity->bootstrap();
        } catch (AccountStorageBusy $error) {
            return new \WP_REST_Response(['success' => false, 'message' => $error->getMessage()], 503);
        } catch (PlatformIdentifierConflict) {
            return new \WP_REST_Response([
                'success' => false,
                'message' => 'Could not establish the Account Station identity. Please retry.',
            ], 500);
        }

        return null;
    }

    private function requireBootstrapped(): ?\WP_REST_Response
    {
        if (!$this->identity->isBootstrapped()) {
            return new \WP_REST_Response([
                'success' => false,
                'message' => 'Account Station has not been set up yet. Save Brand first.',
            ], 422);
        }

        return null;
    }

    private function rejectIdentityMutation(\WP_REST_Request $request): ?\WP_REST_Response
    {
        foreach (['platform_id', 'platformId'] as $field) {
            if ($request->has_param($field)) {
                return new \WP_REST_Response([
                    'success' => false,
                    'message' => 'Platform identifiers are immutable and output-only.',
                ], 422);
            }
        }

        return null;
    }

    // ===================================================================
    // SECTION: AUTHORIZATION
    // ===================================================================
    public function requireAdmin(): bool
    {
        return current_user_can(\QSD\Platform\Core\PlatformAccess::CAP);
    }
}
