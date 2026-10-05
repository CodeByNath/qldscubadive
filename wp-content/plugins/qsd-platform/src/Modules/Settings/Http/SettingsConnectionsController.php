<?php

/*
 * FILE INDEX
 *
 * CONNECTION_ROUTES      REST route registration
 * CONNECTION_HANDLERS    List, save, disconnect
 * CONNECTION_PROJECTION  Safe projection — never a secret value
 * CONNECTION_AUTH        Permission callback
 *
 * Search: SECTION: CONNECTION_ROUTES
 *         SECTION: CONNECTION_HANDLERS
 *         SECTION: CONNECTION_PROJECTION
 *         SECTION: CONNECTION_AUTH
 */

namespace QSD\Platform\Modules\Settings\Http;

use QSD\Platform\Core\PlatformAccess;
use QSD\Platform\Modules\Settings\Connections\ConnectionProviderDefinition;
use QSD\Platform\Modules\Settings\Connections\ConnectionProviders;
use QSD\Platform\Modules\Settings\Connections\ConnectionStore;

/**
 * SettingsConnectionsController — the Connections/Security Tool's admin REST
 * family. Settings owns provider configuration; it writes through
 * ConnectionStore and projects only safe connection state.
 *
 * Secret contract: a secret value enters on PUT and is never returned. The
 * projection reports a secret field only as `configured: true|false`.
 * An empty or omitted secret leaves the stored value unchanged; `clear` names
 * secret fields to remove.
 */
class SettingsConnectionsController
{
    public function __construct(private ConnectionStore $store) {}

    public function register(): void
    {
        add_action('rest_api_init', [$this, 'registerRoutes']);
    }

    public function registerRoutes(): void
    {
        // ===================================================================
        // SECTION: CONNECTION_ROUTES
        // ===================================================================
        register_rest_route('qsd/v1', '/admin/settings/connections', [
            'methods'             => 'GET',
            'callback'            => [$this, 'listConnections'],
            'permission_callback' => [$this, 'requireAdmin'],
        ]);

        register_rest_route('qsd/v1', '/admin/settings/connections/(?P<provider>[a-z0-9-]+)', [
            [
                'methods'             => 'PUT',
                'callback'            => [$this, 'saveConnection'],
                'permission_callback' => [$this, 'requireAdmin'],
            ],
            [
                'methods'             => 'DELETE',
                'callback'            => [$this, 'disconnect'],
                'permission_callback' => [$this, 'requireAdmin'],
            ],
        ]);
    }

    // =======================================================================
    // SECTION: CONNECTION_HANDLERS
    // =======================================================================

    public function listConnections(\WP_REST_Request $request): \WP_REST_Response
    {
        $connections = [];
        foreach (ConnectionProviders::all() as $definition) {
            $connections[] = $this->project($definition);
        }
        return rest_ensure_response(['success' => true, 'connections' => $connections]);
    }

    public function saveConnection(\WP_REST_Request $request): \WP_REST_Response
    {
        $definition = ConnectionProviders::find((string) $request->get_param('provider'));
        if ($definition === null) {
            return $this->error('Unknown connection provider.', 404);
        }

        $values  = $request->get_param('values') ?? [];
        $secrets = $request->get_param('secrets') ?? [];
        $clear   = $request->get_param('clear') ?? [];
        if (!is_array($values) || !is_array($secrets) || !is_array($clear)) {
            return $this->error('values, secrets and clear must be objects/lists.', 422);
        }

        $stored = $this->store->read($definition->key);
        $config = $stored['config'];
        $vault  = $stored['secrets'];

        foreach ($values as $key => $value) {
            $field = $definition->field((string) $key);
            if ($field === null || $definition->isSecret((string) $key)) {
                return $this->error("'{$key}' is not a configuration field of {$definition->label}.", 422);
            }
            $value = sanitize_text_field((string) $value);
            if ($value === '') {
                unset($config[$key]);
                continue;
            }
            if ($field['type'] === ConnectionProviderDefinition::FIELD_SELECT && !array_key_exists($value, $field['options'] ?? [])) {
                return $this->error("'{$value}' is not a valid {$field['label']}.", 422);
            }
            $config[$key] = $value;
        }

        foreach ($secrets as $key => $value) {
            if (!$definition->isSecret((string) $key)) {
                return $this->error("'{$key}' is not a secret field of {$definition->label}.", 422);
            }
            $value = trim((string) $value);
            if ($value !== '') {
                $vault[$key] = $value;
            }
        }

        foreach ($clear as $key) {
            if (!$definition->isSecret((string) $key)) {
                return $this->error("'{$key}' is not a secret field of {$definition->label}.", 422);
            }
            unset($vault[$key]);
        }

        $this->store->write($definition->key, ['config' => $config, 'secrets' => $vault]);
        return rest_ensure_response(['success' => true, 'connection' => $this->project($definition)]);
    }

    public function disconnect(\WP_REST_Request $request): \WP_REST_Response
    {
        $definition = ConnectionProviders::find((string) $request->get_param('provider'));
        if ($definition === null) {
            return $this->error('Unknown connection provider.', 404);
        }
        $this->store->remove($definition->key);
        return rest_ensure_response(['success' => true, 'connection' => $this->project($definition)]);
    }

    // =======================================================================
    // SECTION: CONNECTION_PROJECTION
    // =======================================================================

    /**
     * The only shape that leaves this controller. Secret fields carry
     * `configured` and never a `value`.
     */
    private function project(ConnectionProviderDefinition $definition): array
    {
        $stored = $this->store->read($definition->key);
        $fields = [];
        $missingRequired = false;
        $anyStored = false;

        foreach ($definition->fields as $field) {
            $out = ['key' => $field['key'], 'label' => $field['label'], 'type' => $field['type'], 'required' => $field['required']];
            if ($field['type'] === ConnectionProviderDefinition::FIELD_SECRET) {
                $present = ($stored['secrets'][$field['key']] ?? '') !== '';
                $out['configured'] = $present;
            } else {
                $value = (string) ($stored['config'][$field['key']] ?? '');
                $present = $value !== '';
                $out['value'] = $value;
                if (isset($field['options'])) {
                    $out['options'] = array_map(
                        static fn(string $value, string $label) => ['value' => $value, 'label' => $label],
                        array_keys($field['options']),
                        array_values($field['options']),
                    );
                }
            }
            $anyStored = $anyStored || $present;
            $missingRequired = $missingRequired || ($field['required'] && !$present);
            $fields[] = $out;
        }

        return [
            'provider'    => $definition->key,
            'label'       => $definition->label,
            'description' => $definition->description,
            'state'       => !$anyStored ? 'not_configured' : ($missingRequired ? 'incomplete' : 'configured'),
            'updated_at'  => $anyStored ? $stored['updated_at'] : null,
            'fields'      => $fields,
        ];
    }

    private function error(string $message, int $status): \WP_REST_Response
    {
        return new \WP_REST_Response(['success' => false, 'message' => $message], $status);
    }

    // =======================================================================
    // SECTION: CONNECTION_AUTH
    // =======================================================================

    public function requireAdmin(): bool
    {
        return current_user_can(PlatformAccess::CAP);
    }
}
