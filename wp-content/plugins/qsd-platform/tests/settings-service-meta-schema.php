<?php

declare(strict_types=1);

// Runs the real Service Meta schema and its controller against an in-memory
// option boundary. Proves stable field identity (server-minted, independent
// of label and order), every initial field type, and the non-destructive
// policy: retire/restore instead of delete, immutable type, and no dropping of
// option or sub-field ids that stored Service values may reference.

$__metaOptions = [];

function sanitize_text_field(mixed $value): string { return trim(strip_tags((string) $value)); }
function sanitize_textarea_field(mixed $value): string { return trim((string) $value); }
function add_action(string $hook, callable $callback): void {}
function register_rest_route(string $namespace, string $route, array $args): bool { return true; }
function current_user_can(string $cap): bool { return $cap === 'manage_qsd'; }
function add_option(string $key, mixed $value, string $deprecated = '', string|bool $autoload = 'yes'): bool {
    global $__metaOptions;
    if (array_key_exists($key, $__metaOptions)) return false;
    $__metaOptions[$key] = $value;
    return true;
}
function get_option(string $key, mixed $default = false): mixed {
    global $__metaOptions;
    return $__metaOptions[$key] ?? $default;
}
function update_option(string $key, mixed $value, string|bool|null $autoload = null): bool {
    global $__metaOptions;
    $__metaOptions[$key] = $value;
    return true;
}
function rest_ensure_response(mixed $value): WP_REST_Response {
    return $value instanceof WP_REST_Response ? $value : new WP_REST_Response($value, 200);
}

class WP_REST_Request {
    public function __construct(private array $params = []) {}
    public function get_param(string $key): mixed { return $this->params[$key] ?? null; }
    public function get_json_params(): array { return $this->params; }
}
class WP_REST_Response {
    public function __construct(private mixed $data, private int $status) {}
    public function get_data(): mixed { return $this->data; }
    public function get_status(): int { return $this->status; }
}

require_once __DIR__ . '/autoload.php';

use QSD\Platform\Modules\Settings\Http\ServiceMetaSchemaController;
use QSD\Platform\Modules\Settings\ServiceMeta\ServiceMetaSchema;

function check_meta(bool $condition, string $message): void
{
    if (!$condition) {
        fwrite(STDERR, "FAIL: {$message}\n");
        exit(1);
    }
    echo "  ok — {$message}\n";
}

$schema = new ServiceMetaSchema();
$api = new ServiceMetaSchemaController($schema);
$create = static fn(array $body): WP_REST_Response => $api->createField(new WP_REST_Request($body));
$update = static fn(string $id, array $body): WP_REST_Response => $api->updateField(new WP_REST_Request(['field_id' => $id] + $body));

echo "Service Meta schema\n";

// ── Every initial type, server-minted identity ───────────────────────────
$made = [];
foreach (['text', 'textarea', 'number', 'boolean', 'image', 'gallery'] as $type) {
    $response = $create(['label' => ucfirst($type) . ' field', 'type' => $type, 'id' => 'fld_CLIENTCHOSEN']);
    check_meta($response->get_status() === 201, "a {$type} field is created");
    $made[$type] = $response->get_data()['field'];
}
$select = $create(['label' => 'Certification level', 'type' => 'select', 'options' => [['label' => 'Open Water'], ['label' => 'Advanced']]])->get_data()['field'];
$repeater = $create(['label' => 'Itinerary', 'type' => 'repeater', 'sub_fields' => [
    ['label' => 'Time', 'type' => 'text'],
    ['label' => 'Activity', 'type' => 'textarea'],
    ['label' => 'Photos', 'type' => 'gallery'],
]])->get_data()['field'];
$made['select'] = $select;
$made['repeater'] = $repeater;

$ids = array_column($made, 'id');
check_meta(count($ids) === 8 && count(array_unique($ids)) === 8, 'eight types created with eight distinct ids');
check_meta(array_filter($ids, static fn($id) => preg_match('/^fld_[23456789ABCDEFGHJKMNPQRSTVWXYZ]{10}$/', $id) !== 1) === [], 'every field id is fld_ + 10 server-minted characters');
check_meta(!in_array('fld_CLIENTCHOSEN', $ids, true), 'a client-supplied id is ignored');
check_meta(array_column($made, 'status') === array_fill(0, 8, 'active'), 'new fields are active');
check_meta(count(array_unique(array_column($select['options'], 'id'))) === 2 && str_starts_with($select['options'][0]['id'], 'opt_'), 'select options get distinct stable opt_ ids');
$subIds = array_column($repeater['sub_fields'], 'id');
check_meta(count($subIds) === 3 && count(array_unique(array_merge($subIds, $ids))) === 11, 'repeater sub-fields get fld_ ids unique schema-wide');

// ── Identity is independent of label and order ───────────────────────────
$renamed = $update($made['text']['id'], ['label' => 'Max depth (renamed)'])->get_data()['field'];
check_meta($renamed['id'] === $made['text']['id'] && $renamed['label'] === 'Max depth (renamed)', 'renaming keeps the id');
$duplicateLabel = $create(['label' => 'Max depth (renamed)', 'type' => 'text'])->get_data()['field'];
check_meta($duplicateLabel['id'] !== $renamed['id'], 'two fields with the same label stay two identities');

$order = array_reverse(array_column($schema->fields(), 'id'));
$reordered = $api->reorderFields(new WP_REST_Request(['ids' => $order]));
check_meta($reordered->get_status() === 200 && array_column($reordered->get_data()['fields'], 'id') === $order, 'reorder by id changes only order');
check_meta($schema->find($made['select']['id'])['options'] === $select['options'], 'reorder leaves child ids untouched');
check_meta($api->reorderFields(new WP_REST_Request(['ids' => array_slice($order, 1)]))->get_status() === 422, 'a reorder missing a field is refused');
check_meta($api->reorderFields(new WP_REST_Request(['ids' => [...$order, $order[0]]]))->get_status() === 422, 'a reorder repeating a field is refused');

// ── Option and sub-field identity on edit ────────────────────────────────
$selectId = $select['id'];
$withNew = $update($selectId, ['options' => [
    ['id' => $select['options'][1]['id'], 'label' => 'Advanced Open Water'],
    ['id' => $select['options'][0]['id'], 'label' => 'Open Water'],
    ['label' => 'Rescue'],
]])->get_data()['field'];
check_meta(array_column($withNew['options'], 'id') === [$select['options'][1]['id'], $select['options'][0]['id'], $withNew['options'][2]['id']] && $withNew['options'][0]['label'] === 'Advanced Open Water', 'options relabel/reorder keep ids and a new option is minted');
check_meta($update($selectId, ['options' => [['id' => $select['options'][0]['id'], 'label' => 'Open Water']]])->get_status() === 422, 'dropping an existing option is refused');
check_meta($update($selectId, ['options' => [['id' => 'opt_FORGED', 'label' => 'x']]])->get_status() === 422, 'a forged option id is refused');

$repeaterId = $repeater['id'];
$subs = $repeater['sub_fields'];
$moreSubs = $update($repeaterId, ['sub_fields' => [$subs[2], $subs[0], $subs[1], ['label' => 'Depth', 'type' => 'number']]])->get_data()['field'];
check_meta(array_slice(array_column($moreSubs['sub_fields'], 'id'), 0, 3) === [$subs[2]['id'], $subs[0]['id'], $subs[1]['id']], 'sub-field reorder keeps ids');
check_meta(str_starts_with($moreSubs['sub_fields'][3]['id'], 'fld_'), 'a new sub-field gets its own id');
check_meta($update($repeaterId, ['sub_fields' => [$subs[0]]])->get_status() === 422, 'dropping an existing sub-field is refused');
check_meta($update($repeaterId, ['sub_fields' => [['id' => $subs[0]['id'], 'label' => 'Time', 'type' => 'number'], $subs[1], $subs[2], $moreSubs['sub_fields'][3]]])->get_status() === 422, 'changing a sub-field type is refused');
check_meta($create(['label' => 'Nested', 'type' => 'repeater', 'sub_fields' => [['label' => 'Inner', 'type' => 'repeater']]])->get_status() === 422, 'a repeater cannot nest a repeater');

// ── Type and shape rules ─────────────────────────────────────────────────
check_meta($update($made['number']['id'], ['type' => 'text'])->get_status() === 422, 'a field type cannot change');
check_meta($create(['label' => 'Bad', 'type' => 'wysiwyg-scuba'])->get_status() === 422, 'an unsupported type is refused');
check_meta($create(['label' => '', 'type' => 'text'])->get_status() === 422, 'a label is required');
check_meta($create(['label' => 'Level', 'type' => 'select', 'options' => []])->get_status() === 422, 'a select needs an option');
check_meta($create(['label' => 'Rows', 'type' => 'repeater'])->get_status() === 422, 'a repeater needs a sub-field');
check_meta($create(['label' => 'Flag', 'type' => 'boolean', 'options' => [['label' => 'x']]])->get_status() === 422, 'a non-select cannot carry options');

// ── Retire / restore — never delete ──────────────────────────────────────
$galleryId = $made['gallery']['id'];
$countBefore = count($schema->fields());
$retired = $api->retireField(new WP_REST_Request(['field_id' => $galleryId]))->get_data()['field'];
check_meta($retired['status'] === 'retired' && isset($retired['retired_at']) && count($schema->fields()) === $countBefore, 'retire keeps the definition and its id');
$stillRetired = $update($galleryId, ['label' => 'Gallery (archived)', 'status' => 'active'])->get_data()['field'];
check_meta($stillRetired['status'] === 'retired', 'an edit cannot change status');
$restored = $api->restoreField(new WP_REST_Request(['field_id' => $galleryId]))->get_data()['field'];
check_meta($restored['id'] === $galleryId && $restored['status'] === 'active' && !isset($restored['retired_at']), 'restore returns the same field to active');
check_meta($api->retireField(new WP_REST_Request(['field_id' => 'fld_ZZZZZZZZZZ']))->get_status() === 404, 'an unknown field id is 404');
check_meta(!method_exists($api, 'deleteField') && !method_exists($schema, 'delete'), 'there is no delete operation');

echo "All Service Meta schema checks passed.\n";
