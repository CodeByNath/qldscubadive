<?php

declare(strict_types=1);

// Phase B contract: Brand field sanitization, the draft/settle/publish/
// disable/enable lifecycle rules in isolation, media upload validation with a
// real (non-HTTP) file on disk, and the full Account REST surface end to end
// against the same test doubles as tests/account-station.php.
$GLOBALS['qsd_test_options'] = [];
$GLOBALS['qsd_test_autoload'] = [];
$GLOBALS['qsd_test_caps'] = ['manage_qsd'];
$GLOBALS['qsd_test_uploads_dir'] = sys_get_temp_dir() . '/qsd-account-brand-test-' . bin2hex(random_bytes(6));

function add_option(string $key, mixed $value, string $deprecated = '', string|bool $autoload = 'yes'): bool
{
    if (array_key_exists($key, $GLOBALS['qsd_test_options'])) {
        return false;
    }
    $GLOBALS['qsd_test_options'][$key] = $value;
    $GLOBALS['qsd_test_autoload'][$key] = $autoload;
    return true;
}

function get_option(string $key, mixed $default = false): mixed
{
    return $GLOBALS['qsd_test_options'][$key] ?? $default;
}

function update_option(string $key, mixed $value, string|bool|null $autoload = null): bool
{
    $changed = !array_key_exists($key, $GLOBALS['qsd_test_options']) || $GLOBALS['qsd_test_options'][$key] !== $value;
    $GLOBALS['qsd_test_options'][$key] = $value;
    if ($autoload !== null) {
        $GLOBALS['qsd_test_autoload'][$key] = $autoload;
    }
    return $changed;
}

function current_user_can(string $cap): bool
{
    return in_array($cap, $GLOBALS['qsd_test_caps'], true);
}

function add_action(string $hook, callable $callback): void {}

function register_rest_route(string $namespace, string $route, array $args): bool
{
    return true;
}

function rest_ensure_response(mixed $value): WP_REST_Response
{
    return $value instanceof WP_REST_Response ? $value : new WP_REST_Response($value, 200);
}

function wp_upload_dir(): array
{
    return [
        'basedir' => $GLOBALS['qsd_test_uploads_dir'],
        'baseurl' => 'https://example.test/wp-content/uploads',
    ];
}

function wp_mkdir_p(string $dir): bool
{
    return is_dir($dir) || mkdir($dir, 0777, true);
}

function trailingslashit(string $value): string
{
    return rtrim($value, '/') . '/';
}

class WP_REST_Request
{
    /** @param array<string, mixed> $files */
    public function __construct(private array $params = [], private array $files = [])
    {
    }
    public function get_param(string $key): mixed
    {
        return $this->params[$key] ?? null;
    }
    public function has_param(string $key): bool
    {
        return array_key_exists($key, $this->params);
    }
    public function get_file_params(): array
    {
        return $this->files;
    }
}

class WP_REST_Response
{
    public function __construct(private mixed $data, private int $status)
    {
    }
    public function get_data(): mixed
    {
        return $this->data;
    }
    public function get_status(): int
    {
        return $this->status;
    }
}

class WP_Error
{
    public function __construct(private string $code, private string $message)
    {
    }
    public function get_error_message(): string
    {
        return $this->message;
    }
}

require_once __DIR__ . '/autoload.php';
require_once __DIR__ . '/support-account-repository.php';

use QSD\Platform\Modules\Account\Http\AccountController;
use QSD\Platform\Modules\Account\Support\AccountBrand;
use QSD\Platform\Modules\Account\Support\AccountMedia;
use QSD\Platform\Modules\Account\Support\AccountSchema;
use QSD\Platform\PlatformIdentifier\PlatformIdentifierStation;

function checkBrand(bool $condition, string $message): void
{
    if (!$condition) {
        fwrite(STDERR, "FAIL: {$message}\n");
        exit(1);
    }
    echo "  ok — {$message}\n";
}

// ─── Part 1: AccountBrand sanitization and lifecycle rules in isolation ────
checkBrand(AccountBrand::sanitizeName('  Reef & Co  ') === 'Reef & Co', 'name is trimmed');
checkBrand(mb_strlen(AccountBrand::sanitizeName(str_repeat('x', 90))) === 60, 'name is capped at sixty characters');
checkBrand(AccountBrand::sanitizeCode(' reef1! ') === 'REEF', 'code is uppercased and stripped of non-letters');
checkBrand(AccountBrand::sanitizeCode('ABCDEFGH') === 'ABCDEF', 'code is capped at six characters');

$state = AccountSchema::defaultState();
$state = AccountBrand::applyDraft($state, ['name' => 'Reef Co', 'code' => 'REEF']);
checkBrand(
    $state['brand_draft']['name'] === 'Reef Co' && $state['brand_draft']['code'] === 'REEF',
    'applyDraft writes the submitted fields into the draft'
);
checkBrand($state['module_status'][AccountSchema::MODULE_BRAND] === AccountSchema::MODULE_PENDING, 'a draft save marks the module pending');
checkBrand($state['brand']['name'] === '', 'canonical brand is untouched by a draft save');

$state = AccountBrand::applyDraft($state, ['logo_media_id' => 'abc123.png']);
checkBrand(
    $state['brand_draft']['name'] === 'Reef Co' && $state['brand_draft']['logo_media_id'] === 'abc123.png',
    'a partial draft save merges onto the existing draft rather than replacing it'
);

$state = AccountBrand::settle($state);
checkBrand(
    $state['brand']['name'] === 'Reef Co' && $state['brand']['logo_media_id'] === 'abc123.png',
    'settle promotes the draft to canonical'
);
checkBrand($state['brand_draft'] === null, 'settle clears the draft');
checkBrand($state['module_status'][AccountSchema::MODULE_BRAND] === AccountSchema::MODULE_SETTLED, 'settle marks the module settled');

$state = AccountBrand::publish($state);
checkBrand($state['platform_status'] === AccountSchema::STATUS_ACTIVE, 'publish activates');

$disabled = AccountBrand::disable($state);
checkBrand($disabled !== null && $disabled['platform_status'] === AccountSchema::STATUS_DISABLED, 'disable masks an active state');
checkBrand($disabled['previous_platform_status'] === AccountSchema::STATUS_ACTIVE, 'disable captures what the state was before masking');

$reDisabled = AccountBrand::disable($disabled);
checkBrand(
    $reDisabled !== null && $reDisabled['previous_platform_status'] === AccountSchema::STATUS_ACTIVE,
    'disabling an already-masked state keeps the original captured value'
);

$enabled = AccountBrand::enable($disabled);
checkBrand(
    $enabled !== null && $enabled['platform_status'] === AccountSchema::STATUS_DISABLED && $enabled['previous_platform_status'] === '',
    'enable clears the mask but lands back on disabled, never straight to active'
);
checkBrand(AccountBrand::enable($state) === null, 'enabling a state that is not disabled is illegal');

// ─── Part 2: AccountMedia validation, with a real file and the upload-origin seam ──
$fixtureDir = $GLOBALS['qsd_test_uploads_dir'] . '-fixtures';
mkdir($fixtureDir, 0777, true);

// A valid 1x1 transparent PNG.
$pngBytes = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII=');
$pngPath = $fixtureDir . '/logo.png';
file_put_contents($pngPath, $pngBytes);

$textPath = $fixtureDir . '/not-an-image.txt';
file_put_contents($textPath, 'just text pretending to be an image');

$oversizedPath = $fixtureDir . '/oversized.png';
file_put_contents($oversizedPath, $pngBytes . random_bytes(AccountMedia::MAX_BYTES));

$alwaysUploaded = fn(string $path): bool => true;
$media = new AccountMedia($alwaysUploaded);

$firstUpload = $media->store(['tmp_name' => $pngPath]);
checkBrand(is_array($firstUpload) && str_ends_with($firstUpload['media_id'], '.png'), 'a real PNG is accepted and stored under its sniffed extension');
checkBrand(is_file($GLOBALS['qsd_test_uploads_dir'] . '/' . AccountMedia::DIRECTORY . '/' . $firstUpload['media_id']), 'the stored file exists on disk');

$secondUpload = $media->store(['tmp_name' => $pngPath]);
checkBrand(is_array($secondUpload) && $secondUpload['media_id'] === $firstUpload['media_id'], 'uploading the same bytes again resolves to the same content-addressed id, not a duplicate');

$rejected = $media->store(['tmp_name' => $textPath]);
checkBrand($rejected instanceof WP_Error, 'a non-image file is rejected by content sniffing, regardless of its name');

$tooLarge = $media->store(['tmp_name' => $oversizedPath]);
checkBrand($tooLarge instanceof WP_Error, 'a file over the size cap is rejected before its bytes are fully read');

$notUploaded = new AccountMedia(fn(string $path): bool => false);
$spoofed = $notUploaded->store(['tmp_name' => $pngPath]);
checkBrand($spoofed instanceof WP_Error, 'a path that was not an actual HTTP upload is refused regardless of its content');

// ─── Part 3: the full REST surface end to end ───────────────────────────────
$GLOBALS['qsd_test_options'] = [];
$GLOBALS['qsd_test_autoload'] = [];
$table = new AccountOptionsTable();
$GLOBALS['wpdb'] = new AccountWpdb($table);
$station = new PlatformIdentifierStation();
$controller = new AccountController($station, new AccountMedia($alwaysUploaded));

$respond = static fn(WP_REST_Response $response): array => (array) $response->get_data();

$notReady = $respond($controller->settleProfile(new WP_REST_Request()));
checkBrand($notReady['success'] === false, 'settle before any Save is rejected: identity is not bootstrapped yet');

$saved = $respond($controller->saveProfile(new WP_REST_Request(['name' => 'Reef Co', 'code' => 'REEF'])));
checkBrand($saved['success'] === true && $saved['account']['bootstrapped'] === true, 'the first Save bootstraps identity and succeeds');
checkBrand($saved['account']['has_draft'] === true, 'the first Save leaves a pending draft');
checkBrand($saved['account']['module_status']['brand'] === AccountSchema::MODULE_PENDING, 'module_status reflects the pending draft');

$blocked = $respond($controller->saveProfile(new WP_REST_Request(['platform_id' => 'QSDA12345'])));
checkBrand($blocked['success'] === false, 'a payload carrying platform_id is rejected rather than silently ignored');

$settled = $respond($controller->settleProfile(new WP_REST_Request()));
checkBrand(
    $settled['account']['brand']['name'] === 'Reef Co' && $settled['account']['module_status']['brand'] === AccountSchema::MODULE_SETTLED,
    'settle promotes the draft through the real route'
);
checkBrand($settled['account']['has_draft'] === false, 'settle clears the draft through the real route');

$prePublishNotReady = $respond($controller->updateStatus(new WP_REST_Request()));
checkBrand($prePublishNotReady['success'] === false, 'the status route refuses a request with neither action nor a publish target');

$published = $respond($controller->updateStatus(new WP_REST_Request(['platform_status' => 'active'])));
checkBrand($published['account']['platform_status'] === 'active', 'publish activates through the real route');

$disabledResponse = $respond($controller->updateStatus(new WP_REST_Request(['action' => 'disable'])));
checkBrand(
    $disabledResponse['account']['platform_status'] === 'disabled' && $disabledResponse['account']['previous_platform_status'] === 'active',
    'disable masks and captures the prior state through the real route'
);

$illegalEnable = $respond($controller->updateStatus(new WP_REST_Request(['action' => 'disable'])));
checkBrand($illegalEnable['success'] === true, 'disabling an already-disabled Account is accepted (idempotent mask)');

$enabledResponse = $respond($controller->updateStatus(new WP_REST_Request(['action' => 'enable'])));
checkBrand(
    $enabledResponse['account']['platform_status'] === 'disabled' && $enabledResponse['account']['previous_platform_status'] === '',
    'enable lands back on disabled (Pending) through the real route, never straight to active'
);

$uploadResponse = $respond($controller->uploadBrandMedia(new WP_REST_Request(['kind' => 'logo'], ['file' => ['tmp_name' => $pngPath]])));
checkBrand($uploadResponse['success'] === true && str_ends_with($uploadResponse['media']['media_id'], '.png'), 'uploading media through the real route succeeds');

$libraryResponse = $respond($controller->listBrandMedia(new WP_REST_Request()));
checkBrand(count($libraryResponse['media']) === 1, 'the library route reports the uploaded media');

$badKind = $respond($controller->uploadBrandMedia(new WP_REST_Request(['kind' => 'banner'], ['file' => ['tmp_name' => $pngPath]])));
checkBrand($badKind['success'] === false, 'an unrecognised media kind is rejected');

echo "Account Brand (Phase B): PASS\n";
