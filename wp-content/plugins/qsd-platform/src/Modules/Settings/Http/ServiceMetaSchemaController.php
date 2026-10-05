<?php

namespace QSD\Platform\Modules\Settings\Http;

use QSD\Platform\Core\PlatformAccess;
use QSD\Platform\Modules\Settings\ServiceMeta\ServiceMetaSchema;
use QSD\Platform\Modules\Settings\ServiceMeta\ServiceMetaSchemaException;

/**
 * ServiceMetaSchemaController — the Settings-owned admin REST family for
 * Service Meta field DEFINITIONS. It never touches Service values.
 *
 * Fields are addressed by their stable internal id (`fld_…`). There is no
 * delete route: retire/restore is the non-destructive removal policy.
 */
class ServiceMetaSchemaController
{
    private const FIELD_ROUTE = '/admin/settings/service-meta/fields/(?P<field_id>fld_[A-Z0-9]+)';

    public function __construct(private ServiceMetaSchema $schema) {}

    public function register(): void
    {
        add_action('rest_api_init', [$this, 'registerRoutes']);
    }

    public function registerRoutes(): void
    {
        $auth = [$this, 'requireAdmin'];

        register_rest_route('qsd/v1', '/admin/settings/service-meta/fields', [
            ['methods' => 'GET',  'callback' => [$this, 'listFields'],  'permission_callback' => $auth],
            ['methods' => 'POST', 'callback' => [$this, 'createField'], 'permission_callback' => $auth],
        ]);
        register_rest_route('qsd/v1', '/admin/settings/service-meta/fields/order', [
            'methods' => 'POST', 'callback' => [$this, 'reorderFields'], 'permission_callback' => $auth,
        ]);
        register_rest_route('qsd/v1', self::FIELD_ROUTE, [
            'methods' => 'PUT', 'callback' => [$this, 'updateField'], 'permission_callback' => $auth,
        ]);
        register_rest_route('qsd/v1', self::FIELD_ROUTE . '/retire', [
            'methods' => 'POST', 'callback' => [$this, 'retireField'], 'permission_callback' => $auth,
        ]);
        register_rest_route('qsd/v1', self::FIELD_ROUTE . '/restore', [
            'methods' => 'POST', 'callback' => [$this, 'restoreField'], 'permission_callback' => $auth,
        ]);
    }

    public function listFields(\WP_REST_Request $request): \WP_REST_Response
    {
        return rest_ensure_response(['success' => true, 'fields' => $this->schema->fields()]);
    }

    public function createField(\WP_REST_Request $request): \WP_REST_Response
    {
        // A client-supplied id is ignored: identity is minted server-side.
        $input = $request->get_json_params();
        unset($input['id']);
        return $this->field(fn() => $this->schema->create($input), 201);
    }

    public function updateField(\WP_REST_Request $request): \WP_REST_Response
    {
        $input = $request->get_json_params();
        unset($input['id'], $input['status']);
        return $this->field(fn() => $this->schema->update((string) $request->get_param('field_id'), $input));
    }

    public function reorderFields(\WP_REST_Request $request): \WP_REST_Response
    {
        $ids = $request->get_param('ids');
        if (!is_array($ids)) {
            return new \WP_REST_Response(['success' => false, 'message' => 'ids must be a list of field ids.'], 422);
        }
        try {
            return rest_ensure_response(['success' => true, 'fields' => $this->schema->reorder($ids)]);
        } catch (ServiceMetaSchemaException $e) {
            return new \WP_REST_Response(['success' => false, 'message' => $e->getMessage()], $e->status);
        }
    }

    public function retireField(\WP_REST_Request $request): \WP_REST_Response
    {
        return $this->field(fn() => $this->schema->retire((string) $request->get_param('field_id')));
    }

    public function restoreField(\WP_REST_Request $request): \WP_REST_Response
    {
        return $this->field(fn() => $this->schema->restore((string) $request->get_param('field_id')));
    }

    /** @param callable(): array<string, mixed> $operation */
    private function field(callable $operation, int $status = 200): \WP_REST_Response
    {
        try {
            return new \WP_REST_Response(['success' => true, 'field' => $operation()], $status);
        } catch (ServiceMetaSchemaException $e) {
            return new \WP_REST_Response(['success' => false, 'message' => $e->getMessage()], $e->status);
        }
    }

    public function requireAdmin(): bool
    {
        return current_user_can(PlatformAccess::CAP);
    }
}
