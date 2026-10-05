<?php

declare(strict_types=1);

// Service Element composition contract: calls the REAL ServiceController
// handlers and the REAL Settings ServiceMetaSchema against the same in-memory
// WordPress post/meta/option stub the lifecycle test uses. Proves Service-child
// identity (top-level Element, Group child, Repeater row, gallery entry) is
// server-minted, matched by id only, survives rename/reorder/detach/restore
// and draft → settle, stays separate from Settings definition ids, and that
// Settings writes never touch Service values.

$__wpPosts    = [];
$__wpPostMeta = [];
$__wpPostTerms = [];
$__wpTerms     = []; // term_id => ['name' => ..., 'slug' => ...]
$__wpOptions   = [];
$__wpNextPostId = 900;
$__wpNextTermId = 1;
$__wpRejectPlatformMetaWrites = false;

if (!function_exists('sanitize_text_field')) {
    function sanitize_text_field(mixed $value): string { return trim(strip_tags((string) $value)); }
}
if (!function_exists('sanitize_textarea_field')) {
    function sanitize_textarea_field(mixed $value): string { return trim((string) $value); }
}
if (!function_exists('wp_kses_post')) {
    function wp_kses_post(mixed $value): string { return (string) $value; }
}
if (!function_exists('sanitize_title')) {
    function sanitize_title(mixed $value): string { return strtolower(trim((string) preg_replace('/[^a-zA-Z0-9]+/', '-', (string) $value), '-')); }
}
if (!function_exists('html_entity_decode')) {
    // Real PHP builtin — never redefine. Present only so the grep-based
    // function inventory above reads completely; no stub is registered.
}

if (!function_exists('get_post')) {
    function get_post(int $id): ?WP_Post
    {
        global $__wpPosts;
        return $__wpPosts[$id] ?? null;
    }
}
if (!function_exists('get_post_meta')) {
    function get_post_meta(int $id, string $key, bool $single = false): mixed
    {
        global $__wpPostMeta;
        $value = $__wpPostMeta[$id][$key] ?? '';
        return $single ? $value : ($value === '' ? [] : [$value]);
    }
}
if (!function_exists('add_option')) {
    function add_option(string $key, mixed $value, string $deprecated = '', string|bool $autoload = 'yes'): bool
    {
        global $__wpOptions;
        if (array_key_exists($key, $__wpOptions)) return false;
        $__wpOptions[$key] = $value;
        return true;
    }
}
if (!function_exists('get_option')) {
    function get_option(string $key, mixed $default = false): mixed
    {
        global $__wpOptions;
        return $__wpOptions[$key] ?? $default;
    }
}
if (!function_exists('update_option')) {
    function update_option(string $key, mixed $value, string|bool|null $autoload = null): bool
    {
        global $__wpOptions;
        $changed = !array_key_exists($key, $__wpOptions) || $__wpOptions[$key] !== $value;
        $__wpOptions[$key] = $value;
        return $changed;
    }
}
if (!function_exists('update_post_meta')) {
    function update_post_meta(int $id, string $key, mixed $value): bool
    {
        global $__wpPostMeta, $__wpRejectPlatformMetaWrites;
        if ($key === 'qsd_platform_id' && $__wpRejectPlatformMetaWrites) return false;
        $__wpPostMeta[$id][$key] = $value;
        return true;
    }
}
if (!function_exists('delete_post_meta')) {
    function delete_post_meta(int $id, string $key): bool
    {
        global $__wpPostMeta;
        unset($__wpPostMeta[$id][$key]);
        return true;
    }
}
if (!function_exists('wp_insert_post')) {
    function wp_insert_post(array $args, bool $wpError = false): int
    {
        global $__wpPosts, $__wpPostMeta, $__wpNextPostId, $__wpRejectPlatformMetaWrites;
        $id = $__wpNextPostId++;
        $__wpPosts[$id] = new WP_Post($id, (string) ($args['post_title'] ?? ''));
        $__wpPosts[$id]->post_excerpt = (string) ($args['post_excerpt'] ?? '');
        $__wpPosts[$id]->post_content = (string) ($args['post_content'] ?? '');
        $__wpPosts[$id]->post_status  = (string) ($args['post_status'] ?? 'publish');
        $__wpPosts[$id]->post_name    = 'svc-' . $id;
        foreach (($args['meta_input'] ?? []) as $key => $value) {
            if ($key === 'qsd_platform_id' && $__wpRejectPlatformMetaWrites) continue;
            $__wpPostMeta[$id][(string) $key] = $value;
        }
        return $id;
    }
}
if (!function_exists('wp_delete_post')) {
    function wp_delete_post(int $id, bool $force = false): WP_Post|false
    {
        global $__wpPosts, $__wpPostMeta, $__wpPostTerms;
        $post = $__wpPosts[$id] ?? false;
        unset($__wpPosts[$id], $__wpPostMeta[$id], $__wpPostTerms[$id]);
        return $post;
    }
}
if (!function_exists('get_posts')) {
    function get_posts(array $args = []): array
    {
        global $__wpPosts, $__wpPostMeta;
        $ids = [];
        foreach ($__wpPosts as $id => $post) {
            if (($args['post_type'] ?? null) !== null && $post->post_type !== $args['post_type']) continue;
            if (isset($args['meta_key']) && ($__wpPostMeta[$id][$args['meta_key']] ?? null) !== ($args['meta_value'] ?? null)) continue;
            $ids[] = ($args['fields'] ?? null) === 'ids' ? $id : $post;
            if (count($ids) >= (int) ($args['numberposts'] ?? PHP_INT_MAX)) break;
        }
        return $ids;
    }
}
if (!function_exists('wp_update_post')) {
    function wp_update_post(array $args): int
    {
        global $__wpPosts;
        $id = (int) $args['ID'];
        if (isset($args['post_title']))   { $__wpPosts[$id]->post_title   = (string) $args['post_title']; }
        if (isset($args['post_excerpt'])) { $__wpPosts[$id]->post_excerpt = (string) $args['post_excerpt']; }
        if (isset($args['post_content'])) { $__wpPosts[$id]->post_content = (string) $args['post_content']; }
        return $id;
    }
}
if (!function_exists('is_wp_error')) {
    function is_wp_error(mixed $thing): bool { return false; }
}
if (!function_exists('wp_set_object_terms')) {
    function wp_set_object_terms(int $id, array $termIds, string $taxonomy): array
    {
        global $__wpPostTerms;
        $__wpPostTerms[$id] = $termIds;
        return $termIds;
    }
}
if (!function_exists('wp_get_post_terms')) {
    function wp_get_post_terms(int $id, string $taxonomy, array $args = []): array
    {
        global $__wpPostTerms, $__wpTerms;
        $ids = $__wpPostTerms[$id] ?? [];
        if (($args['fields'] ?? null) === 'ids') { return $ids; }
        $out = [];
        foreach ($ids as $termId) {
            $t = $__wpTerms[$termId] ?? ['name' => "Term {$termId}", 'slug' => "term-{$termId}"];
            $out[] = (object) ['term_id' => $termId, 'name' => $t['name'], 'slug' => $t['slug']];
        }
        return $out;
    }
}
if (!function_exists('get_term_meta')) {
    function get_term_meta(int $termId, string $key, bool $single = false): mixed { return ''; }
}
if (!function_exists('rest_ensure_response')) {
    function rest_ensure_response(mixed $value): WP_REST_Response
    {
        return $value instanceof WP_REST_Response ? $value : new WP_REST_Response($value, 200);
    }
}

if (!class_exists('WP_Post')) {
    class WP_Post
    {
        public string $post_type    = 'qsd_service';
        public string $post_excerpt = '';
        public string $post_content = '';
        public string $post_status  = 'publish';
        public string $post_name    = '';
        public function __construct(public int $ID, public string $post_title) {}
    }
}
if (!class_exists('WP_REST_Request')) {
    class WP_REST_Request
    {
        public function __construct(private array $params = []) {}
        public function get_param(string $key): mixed { return $this->params[$key] ?? null; }
        public function has_param(string $key): bool { return array_key_exists($key, $this->params); }
        public function set_param(string $key, mixed $value): void { $this->params[$key] = $value; }
    }
}
if (!class_exists('WP_REST_Response')) {
    class WP_REST_Response
    {
        public function __construct(private mixed $data = null, private int $status = 200) {}
        public function get_data(): mixed { return $this->data; }
        public function get_status(): int { return $this->status; }
    }
}

require_once __DIR__ . '/autoload.php';

use QSD\Platform\Modules\Service\Http\ServiceController;
use QSD\Platform\Modules\Service\Support\ServiceElements;
use QSD\Platform\Modules\Service\Support\ServiceSchema;
use QSD\Platform\Modules\Settings\ServiceMeta\ServiceElementDefinitions;
use QSD\Platform\Modules\Settings\ServiceMeta\ServiceMetaSchema;
use QSD\Platform\PlatformIdentifier\PlatformIdentifierStation;

$checks = 0;
function check_el(bool $condition, string $message): void
{
    global $checks;
    $checks++;
    if (!$condition) {
        fwrite(STDERR, "FAIL: {$message}\n");
        exit(1);
    }
}

/** Index every node in a collection (any depth) by id. */
function nodes_by_id(array $nodes, array $acc = []): array
{
    foreach ($nodes as $node) {
        $acc[$node['id']] = $node;
        foreach (['children', 'rows', 'entries'] as $key) {
            if (isset($node[$key])) {
                $acc = nodes_by_id($node[$key], $acc);
            }
        }
    }
    return $acc;
}

function by_definition(array $elements, string $definitionId): array
{
    foreach ($elements as $element) {
        if ($element['definition_id'] === $definitionId) return $element;
    }
    throw new RuntimeException("no element for {$definitionId}");
}

// ── Settings: Element definitions ────────────────────────────────────────────
$schema = new ServiceMetaSchema();
$depth  = $schema->create(['label' => 'Max depth', 'type' => 'number']);
$cert   = $schema->create(['label' => 'Certification', 'type' => 'select', 'options' => ['Open Water', 'Advanced']]);
$photos = $schema->create(['label' => 'Photos', 'type' => 'gallery']);
$profile = $schema->create(['label' => 'Dive profile', 'type' => 'group', 'sub_fields' => [
    ['label' => 'Site', 'type' => 'text'],
    ['label' => 'Bottom time', 'type' => 'number'],
]]);
$itinerary = $schema->create(['label' => 'Itinerary', 'type' => 'repeater', 'sub_fields' => [
    ['label' => 'Time', 'type' => 'text'],
    ['label' => 'Activity', 'type' => 'textarea'],
]]);
[$siteDef, $bottomDef] = array_column($profile['sub_fields'], 'id');
[$timeDef, $activityDef] = array_column($itinerary['sub_fields'], 'id');
$definitionIds = [$depth['id'], $cert['id'], $photos['id'], $profile['id'], $itinerary['id'], $siteDef, $bottomDef, $timeDef, $activityDef];

$controller = new ServiceController(new PlatformIdentifierStation(), new ServiceElements(new ServiceElementDefinitions($schema)));

$__wpTerms[7] = ['name' => 'Reef', 'slug' => 'reef'];
$created = $controller->createService(new WP_REST_Request([
    'title' => 'Reef Dive', 'excerpt' => '', 'content' => 'Two tanks.', 'category_ids' => [7],
]))->get_data();
$serviceId  = $created['service']['id'];
$platformId = $created['service']['platform_id'];
check_el(str_starts_with($platformId, 'QSDS'), 'the owning Service carries its QSDS Platform identity');
check_el($created['service']['module_status']['elements'] === 'not-configured', 'a new Service starts with Elements not-configured');

$save = static fn(array $elements) => $controller->updateElements(new WP_REST_Request(['id' => $serviceId, 'elements' => $elements]));

// ── 1. First save mints Service-child ids at every level ─────────────────────
$first = $save([
    ['definition_id' => $depth['id'], 'value' => '18'],
    ['definition_id' => $cert['id'], 'value' => $cert['options'][0]['id']],
    ['definition_id' => $photos['id'], 'entries' => [['attachment' => 11], ['attachment' => '12']]],
    ['definition_id' => $profile['id'], 'children' => [
        ['definition_id' => $siteDef, 'value' => 'Flinders Reef'],
        ['definition_id' => $bottomDef, 'value' => 45],
    ]],
    ['definition_id' => $itinerary['id'], 'rows' => [
        ['children' => [['definition_id' => $timeDef, 'value' => '07:00'], ['definition_id' => $activityDef, 'value' => 'Briefing']]],
        ['children' => [['definition_id' => $timeDef, 'value' => '08:00'], ['definition_id' => $activityDef, 'value' => 'First dive']]],
    ]],
]);
check_el($first->get_status() === 200, 'a valid first Element save succeeds');
$v1 = $first->get_data()['elements'];
$all = nodes_by_id($v1);
check_el(count($all) === 15, 'every Element, group child, repeater row, row child and gallery entry gets an id (15 nodes)');
check_el(count(array_filter(array_keys($all), static fn($id) => preg_match('/^(el|row|ent)_[23456789A-HJKMNP-TV-Z]{10}$/', $id) === 1)) === 15, 'child ids are server-minted el_/row_/ent_ ids from the unambiguous alphabet');
check_el(array_intersect(array_keys($all), $definitionIds) === [], 'no Service instance id equals a Settings definition id');
check_el(count(array_filter(array_keys($all), static fn($id) => str_starts_with($id, 'QSD'))) === 0, 'Service-child ids are never Platform IDs');
check_el(by_definition($v1, $depth['id'])['value'] === 18, 'a number is stored as a number');
check_el(by_definition($v1, $cert['id'])['value'] === $cert['options'][0]['id'], 'a select stores the option id, never the label');
check_el(by_definition($v1, $photos['id'])['entries'][1]['attachment'] === 12, 'a gallery entry stores an attachment reference');
check_el($first->get_data()['module_status']['elements'] === 'pending', 'an Element save marks the elements module pending');
check_el(get_post_meta($serviceId, ServiceSchema::META_ELEMENTS, true) === '', 'the draft save leaves canonical Elements untouched');

// ── 2. Client cannot coin identity; definition reference is immutable ────────
check_el($save([['id' => 'el_2222222222', 'definition_id' => $depth['id'], 'value' => 3]])->get_status() === 422, 'a client-coined element id is refused');
check_el($save([['definition_id' => 'fld_ZZZZZZZZZZ', 'value' => 'x']])->get_status() === 422, 'an unknown definition is refused');
$depthEl = by_definition($v1, $depth['id']);
check_el($save([['id' => $depthEl['id'], 'definition_id' => $cert['id']]])->get_status() === 422, 'an instance cannot be re-pointed at another definition');
check_el($save([['definition_id' => $depth['id'], 'value' => 1], ['definition_id' => $depth['id'], 'value' => 2]])->get_status() === 422, 'two active instances of one definition in one slot are refused');
check_el($save([['definition_id' => $cert['id'], 'value' => 'Open Water']])->get_status() === 422, 'a select label (not option id) is refused');
check_el($save([['definition_id' => $photos['id'], 'entries' => [['attachment' => 'cat.jpg']]]])->get_status() === 422, 'a non-attachment gallery entry is refused');
check_el($save([['id' => $depthEl['id'], 'value' => 5], ['id' => $depthEl['id'], 'value' => 6]])->get_status() === 422, 'an id may appear only once');
check_el($controller->updateElements(new WP_REST_Request(['id' => $serviceId, 'elements' => [], 'platform_id' => $platformId]))->get_status() === 422, 'the Element route rejects a platform_id payload');
check_el(nodes_by_id(ServiceElements::listFrom(get_post_meta($serviceId, ServiceSchema::DRAFT_ELEMENTS, true))) == $all, 'refused saves leave the draft exactly as it was');

// ── 3. Rename + reorder at every level keeps every id ────────────────────────
$schema->update($profile['id'], ['label' => 'Profile (renamed)', 'sub_fields' => array_reverse($profile['sub_fields'])]);
$schema->reorder(array_reverse(array_column($schema->fields(), 'id')));
$group = by_definition($v1, $profile['id']);
$rep   = by_definition($v1, $itinerary['id']);
$gal   = by_definition($v1, $photos['id']);
$reordered = $save([
    ['id' => $rep['id'], 'rows' => array_reverse($rep['rows'])],
    ['id' => $group['id'], 'children' => array_reverse($group['children'])],
    ['id' => $gal['id'], 'entries' => array_reverse($gal['entries'])],
    by_definition($v1, $cert['id']),
    ['id' => $depthEl['id'], 'value' => 21],
])->get_data()['elements'];
$v2 = nodes_by_id($reordered);
check_el(array_keys($v2) !== array_keys($all) && count($v2) === 15 && array_diff(array_keys($all), array_keys($v2)) === [], 'reordering every level keeps the same 15 ids');
check_el($reordered[0]['id'] === $rep['id'] && $reordered[0]['rows'][0]['id'] === $rep['rows'][1]['id'], 'order is presentation only: repeater row ids follow their rows');
check_el($v2[$rep['rows'][0]['id']]['children'][1]['value'] === 'Briefing', 'a repeater row keeps its own children after reorder');
check_el($v2[$group['children'][0]['id']]['value'] === 'Flinders Reef', 'a group child keeps its value after definition rename and reorder');
check_el($v2[$gal['entries'][0]['id']]['attachment'] === 11, 'a gallery entry keeps its attachment after reorder');
check_el($v2[$depthEl['id']]['value'] === 21, 'an edit lands on the instance named by id');

// ── 4. Omit = detach (non-destructive); send back active = restore ───────────
$detached = $save([
    ['id' => $rep['id'], 'rows' => [$rep['rows'][1]]],
    ['id' => $gal['id'], 'entries' => [$gal['entries'][1]]],
    ['id' => $group['id']],
    ['id' => $depthEl['id']],
])->get_data()['elements'];
$v3 = nodes_by_id($detached);
check_el(count($v3) === 15, 'omitting children deletes nothing');
check_el($v3[$rep['rows'][0]['id']]['status'] === 'detached' && $v3[$rep['rows'][0]['id']]['children'][0]['value'] === '07:00', 'an omitted repeater row is detached with its id and values');
check_el($v3[$gal['entries'][0]['id']]['status'] === 'detached' && $v3[$gal['entries'][0]['id']]['attachment'] === 11, 'an omitted gallery entry is detached with its attachment');
$certEl = by_definition($v1, $cert['id']);
check_el($v3[$certEl['id']]['status'] === 'detached', 'an omitted top-level Element is detached, not deleted');
$resent = nodes_by_id($save([
    ['id' => $rep['id'], 'rows' => [['id' => $rep['rows'][0]['id']], $rep['rows'][1]]],
    ['id' => $certEl['id']],
    ['id' => $gal['id']], ['id' => $group['id']], ['id' => $depthEl['id']],
])->get_data()['elements']);
check_el($resent[$rep['rows'][0]['id']]['status'] === 'detached' && $resent[$certEl['id']]['status'] === 'detached', 'a detached node sent back without a status stays detached — only an explicit active restores');
$restored = $save([
    ['id' => $rep['id'], 'rows' => [['id' => $rep['rows'][0]['id'], 'status' => 'active'], $rep['rows'][1]]],
    ['id' => $gal['id'], 'entries' => [['id' => $gal['entries'][0]['id'], 'status' => 'active'], $gal['entries'][1]]],
    ['id' => $certEl['id'], 'status' => 'active'],
    ['id' => $group['id']],
    ['id' => $depthEl['id']],
])->get_data()['elements'];
$v4 = nodes_by_id($restored);
check_el($v4[$rep['rows'][0]['id']]['status'] === 'active' && $v4[$rep['rows'][0]['id']]['children'][1]['value'] === 'Briefing', 'a detached repeater row restores under its original id with its values');
check_el($v4[$gal['entries'][0]['id']]['status'] === 'active' && $v4[$gal['entries'][0]['id']]['attachment'] === 11, 'a detached gallery entry restores under its original id');
check_el($v4[$certEl['id']]['status'] === 'active' && $v4[$certEl['id']]['value'] === $cert['options'][0]['id'], 'a detached Element restores under its original id with its value');

// ── 5. Draft → settle preserves identity exactly ─────────────────────────────
$detail = $controller->fetchDetail(new WP_REST_Request(['id' => $serviceId]))->get_data();
check_el($detail['elements'] === [] && nodes_by_id($detail['drafts']['elements']) == $v4, 'detail projects empty settled Elements and the full Element draft');
$settled = $controller->settleAll(new WP_REST_Request(['id' => $serviceId]))->get_data();
check_el(nodes_by_id($settled['elements']) == $v4 && $settled['elements'] === $restored, 'Publish settle copies the draft verbatim — every child id survives');
check_el($settled['module_status']['elements'] === 'settled', 'settled Elements with an active instance read settled');
check_el(get_post_meta($serviceId, ServiceSchema::DRAFT_ELEMENTS, true) === '', 'settle clears the Element draft');
$detail = $controller->fetchDetail(new WP_REST_Request(['id' => $serviceId]))->get_data();
check_el($detail['drafts']['elements'] === null && $detail['elements'] === $restored, 'detail now projects settled Elements and no draft');
$active = $controller->updateStatus(new WP_REST_Request(['id' => $serviceId, 'platform_status' => 'active']))->get_data();
check_el($active['service']['module_status']['elements'] === 'settled', 'activation resolves the Elements module from canonical');

// ── 6. Edits after settle re-enter the draft; revert drops only the draft ────
$save([['id' => $depthEl['id'], 'value' => 30]]);
check_el(ServiceModules_isPending($controller, $serviceId), 'a post-publish Element edit is a pending draft');
$reverted = $controller->revertModule(new WP_REST_Request(['id' => $serviceId, 'module' => 'elements']))->get_data();
check_el($reverted['module_status']['elements'] === 'settled' && get_post_meta($serviceId, ServiceSchema::DRAFT_ELEMENTS, true) === '', 'revert drops the Element draft and returns to settled');
check_el(ServiceElements::listFrom(get_post_meta($serviceId, ServiceSchema::META_ELEMENTS, true)) === $restored, 'revert never touches settled Elements');

function ServiceModules_isPending(ServiceController $controller, int $serviceId): bool
{
    return $controller->fetchDetail(new WP_REST_Request(['id' => $serviceId]))->get_data()['module_status']['elements'] === 'pending';
}

// ── 7. Retired definitions never erase stored Service data ───────────────────
$schema->retire($cert['id']);
check_el($save([['definition_id' => $cert['id'], 'value' => $cert['options'][1]['id']]])->get_status() === 422, 'a retired definition cannot be newly instantiated');
$afterRetire = $save([
    ['id' => $certEl['id'], 'value' => $cert['options'][1]['id'], 'status' => 'detached'],
    ['id' => $depthEl['id']],
])->get_data()['elements'];
$v5 = nodes_by_id($afterRetire);
check_el($v5[$certEl['id']] === $v4[$certEl['id']], 'an instance of a retired definition is frozen: payload edits and detach are ignored');
$omitted = $save([['id' => $depthEl['id']]])->get_data()['elements'];
check_el(nodes_by_id($omitted)[$certEl['id']] === $v4[$certEl['id']], 'omitting a retired-definition instance keeps it verbatim (not detached)');
$schema->restore($cert['id']);
$edited = $save([['id' => $certEl['id'], 'value' => $cert['options'][1]['id']], ['id' => $depthEl['id']]])->get_data()['elements'];
check_el(nodes_by_id($edited)[$certEl['id']]['value'] === $cert['options'][1]['id'], 'restoring the definition makes the same instance editable again');

// ── 8. Settings writes never mutate Service values ───────────────────────────
$before = $__wpPostMeta[$serviceId];
$schema->create(['label' => 'Wetsuit', 'type' => 'text']);
$schema->update($itinerary['id'], ['label' => 'Day plan']);
$schema->reorder(array_column($schema->fields(), 'id'));
$schema->retire($photos['id']);
$schema->restore($photos['id']);
$schema->retire($depth['id']);
check_el($__wpPostMeta[$serviceId] === $before, 'Settings create/update/reorder/retire/restore leave every Service meta value byte-identical');

// ── 9. Element read route ────────────────────────────────────────────────────
$read = $controller->fetchElements(new WP_REST_Request(['id' => $serviceId]))->get_data();
check_el($read['platform_id'] === $platformId && $read['elements'] === $restored, 'the Element read projects the owning QSDS with its settled Elements');
$retiredDefs = array_filter($read['definitions'], static fn($d) => $d['status'] === 'retired');
check_el(count($retiredDefs) === 1 && array_values($retiredDefs)[0]['id'] === $depth['id'], 'the read carries retired definitions flagged, so kept instances still render');
check_el($controller->fetchElements(new WP_REST_Request(['id' => 4242]))->get_status() === 404, 'an unknown Service is 404 on the Element read');

echo "Service Element composition checks passed: {$checks} checks.\n";
