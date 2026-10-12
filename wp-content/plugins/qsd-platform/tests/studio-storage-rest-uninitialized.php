<?php

declare(strict_types=1);

// StudioStorageController over a fresh, never-initialized StudioStorage:
// writes must be refused, not silently accepted. Split into its own process
// so the one configured root this file resolves to is guaranteed never to
// have been initialized by an earlier scenario.

$__configuredRoot = sys_get_temp_dir() . '/qsd-studio-rest-uninit-test-' . bin2hex(random_bytes(6));
mkdir($__configuredRoot, 0755, true);
define('QSD_STUDIO_PRIVATE_ROOT', $__configuredRoot);

function wp_json_encode(mixed $value): string|false { return json_encode($value); }

$__currentUserCan = true;
function current_user_can(string $cap): bool { global $__currentUserCan; return $__currentUserCan; }
function get_current_user_id(): int { return 1; }

class WP_REST_Response
{
    public function __construct(public array $data, public int $status = 200) {}
}
function rest_ensure_response(array $data): WP_REST_Response { return new WP_REST_Response($data, 200); }

class WP_REST_Request
{
    public function __construct(private string $method, private string $body = '') {}
    public function get_method(): string { return $this->method; }
    public function get_body(): string { return $this->body; }
}

require_once __DIR__ . '/../src/Modules/StudioHost/StudioStorage.php';
require_once __DIR__ . '/../src/Core/PlatformAccess.php';
require_once __DIR__ . '/../src/Modules/StudioHost/Http/StudioStorageController.php';

use QSD\Platform\Modules\StudioHost\Http\StudioStorageController;
use QSD\Platform\Modules\StudioHost\StudioStorage;

$failures = [];
function check_studio_rest_uninit(bool $condition, string $label, mixed $detail = null): void
{
    global $failures;
    if ($condition) {
        echo "  ok — {$label}\n";
        return;
    }
    $failures[] = $label;
    echo '  FAIL — ' . $label . ($detail !== null ? ': ' . json_encode($detail) : '') . "\n";
}

echo "1) a controller over a fresh, never-initialized storage refuses writes\n";
{
    $controller = new StudioStorageController(new StudioStorage());

    $status = $controller->status();
    check_studio_rest_uninit($status->data === ['initialized' => false], 'status() confirms this root has never been initialized', $status->data);

    $response = $controller->identity(new WP_REST_Request('PUT', '{"a":1}'));
    check_studio_rest_uninit($response->status === 409, 'a PUT against an uninitialized store is rejected with 409, not silently accepted', $response->status);

    $get = $controller->identity(new WP_REST_Request('GET'));
    check_studio_rest_uninit($get->data === ['identity' => null], 'GET against an uninitialized store returns identity:null, not an error swallowed into empty data');
}

if ($failures !== []) {
    fwrite(STDERR, "\n" . count($failures) . " check(s) failed.\n");
    exit(1);
}
echo "\nStudio storage REST uninitialized-store checks passed.\n";
