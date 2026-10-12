<?php

declare(strict_types=1);

// StudioStorageController: the minimum authorized host handshake for WEX's
// bootstrap — permission gating, storage status/init, and identity readback.
// Exercises the real controller against a real StudioStorage over a real
// temp directory; only WordPress's own REST/user primitives are stubbed.

$__wpRoot = sys_get_temp_dir() . '/qsd-studio-rest-test-' . bin2hex(random_bytes(6)) . '/wordpress';
mkdir($__wpRoot, 0755, true);
define('ABSPATH', $__wpRoot . '/');

function wp_json_encode(mixed $value): string|false { return json_encode($value); }

$__currentUserCan = true;
$__currentUserId  = 7;
function current_user_can(string $cap): bool { global $__currentUserCan; return $__currentUserCan; }
function get_current_user_id(): int { global $__currentUserId; return $__currentUserId; }

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
function check_studio_rest(bool $condition, string $label, mixed $detail = null): void
{
    global $failures;
    if ($condition) {
        echo "  ok — {$label}\n";
        return;
    }
    $failures[] = $label;
    echo '  FAIL — ' . $label . ($detail !== null ? ': ' . json_encode($detail) : '') . "\n";
}

$controller = new StudioStorageController(new StudioStorage());

// ── 1) Permission gate ─────────────────────────────────────────────────────
echo "1) the handshake is gated on the shared manage_qsd capability\n";
{
    global $__currentUserCan;
    $__currentUserCan = false;
    check_studio_rest($controller->requireAdmin() === false, 'requireAdmin() denies a caller without the capability');
    $__currentUserCan = true;
    check_studio_rest($controller->requireAdmin() === true, 'requireAdmin() allows a caller with the capability');
}

// ── 2) Status before initialization ────────────────────────────────────────
echo "\n2) status reflects an uninitialized root truthfully\n";
{
    $response = $controller->status();
    check_studio_rest($response->data === ['initialized' => false], 'status() reports initialized:false before any init call', $response->data);
}

// ── 3) Init is explicit, idempotent, and reports which case occurred ──────
echo "\n3) init is an explicit action, idempotent, and distinguishes first-time from repeat\n";
{
    $first = $controller->init();
    check_studio_rest($first->data === ['initialized' => true, 'alreadyInitialized' => false], 'first init() reports alreadyInitialized:false', $first->data);

    $second = $controller->init();
    check_studio_rest($second->data === ['initialized' => true, 'alreadyInitialized' => true], 'a repeat init() reports alreadyInitialized:true, never re-provisioning', $second->data);

    $status = $controller->status();
    check_studio_rest($status->data === ['initialized' => true], 'status() now reflects the initialized root');
}

// ── 4) Identity readback: empty, then roundtrip, then rejected payloads ───
echo "\n4) identity readback roundtrips a valid payload and rejects invalid ones\n";
{
    $empty = $controller->identity(new WP_REST_Request('GET'));
    check_studio_rest($empty->data === ['identity' => null], 'GET before any write returns identity:null', $empty->data);

    $put = $controller->identity(new WP_REST_Request('PUT', '{"deviceId":"xyz"}'));
    check_studio_rest($put->data === ['success' => true], 'a valid PUT succeeds', $put->data);

    $get = $controller->identity(new WP_REST_Request('GET'));
    check_studio_rest($get->data === ['identity' => ['deviceId' => 'xyz']], 'GET after a valid PUT returns the decoded stored value', $get->data);

    $badPut = $controller->identity(new WP_REST_Request('PUT', 'not json'));
    check_studio_rest($badPut->status === 409, 'an invalid PUT body is rejected with 409', $badPut->status);
    check_studio_rest($badPut->data['success'] === false, 'the rejected response reports success:false');

    $getAfterBad = $controller->identity(new WP_REST_Request('GET'));
    check_studio_rest($getAfterBad->data === ['identity' => ['deviceId' => 'xyz']], 'the previously-stored good value survives a rejected bad write');
}

// Writes before any init() call are covered by
// tests/studio-storage-rest-uninitialized.php — ABSPATH is a constant, so a
// second, never-touched root cannot be obtained within this same process
// once scenario 3 above has already initialized the one root it resolves to.

if ($failures !== []) {
    fwrite(STDERR, "\n" . count($failures) . " check(s) failed.\n");
    exit(1);
}
echo "\nStudio storage REST handshake checks passed.\n";
