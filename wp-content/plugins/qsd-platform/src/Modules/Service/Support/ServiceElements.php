<?php

/*
 * FILE INDEX
 *
 * ELEMENT_MERGE      Payload → stored collection, one identity law at every level
 * ELEMENT_CONTENT    Per-type value sanitising (scalar, gallery, group, repeater)
 * ELEMENT_IDENTITY   Service-child id minting and collection-wide uniqueness
 * ELEMENT_READ       Completeness
 *
 * Search: SECTION: ELEMENT_MERGE ... SECTION: ELEMENT_READ
 */

namespace QSD\Platform\Modules\Service\Support;

use QSD\Platform\Modules\Settings\ServiceMeta\ServiceElementDefinitions;

/**
 * ServiceElements — the Service-owned value side of Service Element
 * composition (docs/architecture/service-element-composition-contract.md).
 *
 * Settings owns Element DEFINITIONS (what an Element is). Service owns every
 * INSTANCE: which Element exists on this Service, its value, its order and its
 * detached state. An instance's durable address is the owning Service's `QSDS`
 * plus the instance's own Service-child id; the child id never replaces or
 * flattens `QSDS`, and is never a Platform ID (rung 2, parent-qualified).
 *
 * One law at every level — top-level Element, Group child, Repeater row and
 * gallery entry alike:
 *   - ids are minted HERE on first save (`el_`, `row_`, `ent_`); a payload may
 *     only name ids that already exist, so a client can never coin identity;
 *   - matching is by id only — never position, label, slug or sort order;
 *   - an instance keeps its definition reference for life;
 *   - nothing is deleted: an existing child the payload omits is carried
 *     forward as `detached` with its id and value intact, and can be restored
 *     by sending it back with `status: active` (a node sent without a status
 *     keeps the status it had);
 *   - an instance whose definition is retired (or missing) is frozen: it is
 *     carried forward verbatim and cannot be edited or newly added.
 *
 * Writes go to the Service elements draft; Publish settles the draft through
 * ServiceModules like every other Service module. Nothing here writes
 * Settings storage, and Settings has no path to this storage.
 */
final class ServiceElements
{
    public const STATUS_ACTIVE   = 'active';
    public const STATUS_DETACHED = 'detached';

    public const SCALAR_TYPES = ['text', 'textarea', 'number', 'boolean', 'select', 'image'];

    private const ID_ALPHABET = '23456789ABCDEFGHJKMNPQRSTVWXYZ';
    private const ID_LENGTH   = 10;

    public function __construct(private ServiceElementDefinitions $definitions) {}

    public function definitions(): ServiceElementDefinitions
    {
        return $this->definitions;
    }

    // =======================================================================
    // SECTION: ELEMENT_MERGE
    // =======================================================================

    /**
     * Merges a full-collection payload onto the stored (draft-preferred)
     * collection and returns the next collection to store.
     *
     * @param mixed                      $input  payload `elements`
     * @param list<array<string, mixed>> $stored
     * @return list<array<string, mixed>>
     */
    public function merge(mixed $input, array $stored): array
    {
        $taken = $this->collectIds($stored);
        return $this->mergeElements($input, $stored, null, $taken);
    }

    /**
     * One slot of Elements — the top level (definitions resolved through
     * Settings) or the children of a Group / Repeater row (definitions are the
     * container's sub-fields).
     *
     * @param list<array<string, mixed>>               $stored
     * @param array<string, array<string, mixed>>|null $subDefinitions null = top level
     * @param array<string, true>                      $taken
     * @return list<array<string, mixed>>
     */
    private function mergeElements(mixed $input, array $stored, ?array $subDefinitions, array &$taken): array
    {
        $activeByDefinition = [];

        return $this->mergeIdentified($input, $stored, 'el_', 'element', $taken,
            function (array $item, ?array $prior) use ($subDefinitions, &$activeByDefinition, &$taken): ?array {
                $definitionId = $prior !== null ? (string) $prior['definition_id'] : (string) ($item['definition_id'] ?? '');
                if ($prior !== null && isset($item['definition_id']) && (string) $item['definition_id'] !== $definitionId) {
                    throw new ServiceElementException('An element keeps its definition for life; detach it and add a new element instead.');
                }

                $definition = $subDefinitions === null
                    ? $this->definitions->find($definitionId)
                    : ($subDefinitions[$definitionId] ?? null);
                $frozen = $definition === null
                    || ($subDefinitions === null && !$this->definitions->isActive($definition));
                if ($frozen) {
                    if ($prior !== null) {
                        return null; // carried forward verbatim
                    }
                    throw new ServiceElementException($definition === null
                        ? 'Unknown element definition.'
                        : 'A retired definition cannot be added to a Service.');
                }

                $status = $this->status($item, $prior);
                if ($status === self::STATUS_ACTIVE) {
                    if (isset($activeByDefinition[$definitionId])) {
                        throw new ServiceElementException('Only one active element per definition is allowed here.');
                    }
                    $activeByDefinition[$definitionId] = true;
                }

                return ['definition_id' => $definitionId, 'status' => $status]
                    + $this->content($definition, $item, $prior, $taken);
            },
            function (array $prior) use ($subDefinitions): bool {
                $definition = $subDefinitions === null
                    ? $this->definitions->find((string) $prior['definition_id'])
                    : ($subDefinitions[(string) $prior['definition_id']] ?? null);
                return $definition === null
                    || ($subDefinitions === null && !$this->definitions->isActive($definition));
            },
        );
    }

    /**
     * The shared identity law. `$build` returns the node body (everything but
     * `id`) or null to carry the prior node verbatim; `$isFrozen` decides
     * whether an omitted prior node is kept as-is rather than detached.
     *
     * @param list<array<string, mixed>> $stored
     * @param array<string, true>        $taken
     * @return list<array<string, mixed>>
     */
    private function mergeIdentified(
        mixed $input,
        array $stored,
        string $prefix,
        string $noun,
        array &$taken,
        callable $build,
        ?callable $isFrozen = null,
    ): array {
        if ($input === null) {
            $input = [];
        }
        if (!is_array($input) || !array_is_list($input)) {
            throw new ServiceElementException("The {$noun} list must be an array.");
        }

        $storedById = [];
        foreach ($stored as $node) {
            if (is_array($node) && isset($node['id'])) {
                $storedById[(string) $node['id']] = $node;
            }
        }

        $seen = [];
        $out  = [];
        foreach ($input as $item) {
            if (!is_array($item)) {
                throw new ServiceElementException("Each {$noun} must be an object.");
            }
            $id = (string) ($item['id'] ?? '');
            $prior = null;
            if ($id !== '') {
                if (!isset($storedById[$id])) {
                    throw new ServiceElementException("Unknown {$noun} id; ids are minted by the Service, never by the client.");
                }
                if (isset($seen[$id])) {
                    throw new ServiceElementException("A {$noun} id may appear only once.");
                }
                $prior = $storedById[$id];
            }

            $body = $build($item, $prior);
            if ($body === null) {
                $out[] = $prior;
            } else {
                $id = $id !== '' ? $id : $this->mintId($prefix, $taken);
                $out[] = ['id' => $id] + $body;
            }
            $seen[$id] = true;
        }

        // Non-destructive: nothing the payload omits is lost.
        foreach ($storedById as $id => $prior) {
            if (isset($seen[$id])) {
                continue;
            }
            $out[] = ($isFrozen !== null && $isFrozen($prior))
                ? $prior
                : ['status' => self::STATUS_DETACHED] + $prior;
        }

        return array_map(static function (array $node): array {
            // Keep `id` first for readable storage; `+` above put status first on detached carries.
            return ['id' => $node['id']] + $node;
        }, $out);
    }

    // =======================================================================
    // SECTION: ELEMENT_CONTENT
    // =======================================================================

    /**
     * @param array<string, mixed>      $definition
     * @param array<string, mixed>      $item
     * @param array<string, mixed>|null $prior
     * @param array<string, true>       $taken
     * @return array<string, mixed>
     */
    private function content(array $definition, array $item, ?array $prior, array &$taken): array
    {
        $type = (string) $definition['type'];

        if (in_array($type, self::SCALAR_TYPES, true)) {
            $raw = array_key_exists('value', $item) ? $item['value'] : ($prior['value'] ?? null);
            return ['value' => $this->scalar($definition, $raw)];
        }

        if ($type === 'gallery') {
            $priorEntries = $prior['entries'] ?? [];
            $input = array_key_exists('entries', $item) ? $item['entries'] : $priorEntries;
            return ['entries' => $this->mergeIdentified($input, $priorEntries, 'ent_', 'gallery entry', $taken,
                function (array $entry, ?array $priorEntry): array {
                    $raw = array_key_exists('attachment', $entry) ? $entry['attachment'] : ($priorEntry['attachment'] ?? null);
                    $attachment = $this->attachment($raw);
                    if ($attachment === null) {
                        throw new ServiceElementException('Each gallery entry needs an image.');
                    }
                    return ['status' => $this->status($entry, $priorEntry), 'attachment' => $attachment];
                },
            )];
        }

        $subDefinitions = [];
        foreach ($definition['sub_fields'] ?? [] as $sub) {
            $subDefinitions[(string) $sub['id']] = $sub;
        }

        if ($type === 'group') {
            $priorChildren = $prior['children'] ?? [];
            $input = array_key_exists('children', $item) ? $item['children'] : $priorChildren;
            return ['children' => $this->mergeElements($input, $priorChildren, $subDefinitions, $taken)];
        }

        if ($type === 'repeater') {
            $priorRows = $prior['rows'] ?? [];
            $input = array_key_exists('rows', $item) ? $item['rows'] : $priorRows;
            return ['rows' => $this->mergeIdentified($input, $priorRows, 'row_', 'repeater row', $taken,
                function (array $row, ?array $priorRow) use ($subDefinitions, &$taken): array {
                    $priorChildren = $priorRow['children'] ?? [];
                    $children = array_key_exists('children', $row) ? $row['children'] : $priorChildren;
                    return [
                        'status'   => $this->status($row, $priorRow),
                        'children' => $this->mergeElements($children, $priorChildren, $subDefinitions, $taken),
                    ];
                },
            )];
        }

        throw new ServiceElementException('Unsupported element type.');
    }

    private function scalar(array $definition, mixed $raw): mixed
    {
        switch ($definition['type']) {
            case 'text':
                return sanitize_text_field((string) ($raw ?? ''));
            case 'textarea':
                return sanitize_textarea_field((string) ($raw ?? ''));
            case 'number':
                if ($raw === null || $raw === '') {
                    return null;
                }
                if (!is_numeric($raw)) {
                    throw new ServiceElementException("{$definition['label']} must be a number.");
                }
                return $raw + 0;
            case 'boolean':
                if ($raw === null || $raw === '') {
                    return null;
                }
                $bool = filter_var($raw, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
                if ($bool === null) {
                    throw new ServiceElementException("{$definition['label']} must be yes or no.");
                }
                return $bool;
            case 'select':
                if ($raw === null || $raw === '') {
                    return null;
                }
                // A stored select value is the option id, never its label.
                if (!in_array((string) $raw, array_column($definition['options'] ?? [], 'id'), true)) {
                    throw new ServiceElementException("Choose one of the options for {$definition['label']}.");
                }
                return (string) $raw;
            case 'image':
                return $this->attachment($raw);
        }
        throw new ServiceElementException('Unsupported element type.');
    }

    /** An attachment reference: a positive WordPress attachment id, or null. */
    private function attachment(mixed $raw): ?int
    {
        if ($raw === null || $raw === '' || $raw === 0 || $raw === '0') {
            return null;
        }
        if (is_int($raw) && $raw > 0) {
            return $raw;
        }
        if (is_string($raw) && ctype_digit($raw)) {
            return (int) $raw;
        }
        throw new ServiceElementException('An image must be a media library attachment.');
    }

    /** An omitted status keeps the node's prior status; a new node defaults to active. */
    private function status(array $item, ?array $prior): string
    {
        $status = (string) ($item['status'] ?? $prior['status'] ?? self::STATUS_ACTIVE);
        if (!in_array($status, [self::STATUS_ACTIVE, self::STATUS_DETACHED], true)) {
            throw new ServiceElementException('Status must be active or detached.');
        }
        return $status;
    }

    // =======================================================================
    // SECTION: ELEMENT_IDENTITY
    // =======================================================================

    /** @param array<string, true> $taken mutated: the minted id is added */
    private function mintId(string $prefix, array &$taken): string
    {
        $max = strlen(self::ID_ALPHABET) - 1;
        do {
            $suffix = '';
            for ($i = 0; $i < self::ID_LENGTH; $i++) {
                $suffix .= self::ID_ALPHABET[random_int(0, $max)];
            }
            $id = $prefix . $suffix;
        } while (isset($taken[$id]));
        $taken[$id] = true;
        return $id;
    }

    /**
     * Every child id already present anywhere in the collection, so a minted
     * id is unique within the owning Service.
     *
     * @param list<array<string, mixed>> $nodes
     * @param array<string, true>        $taken
     * @return array<string, true>
     */
    private function collectIds(array $nodes, array $taken = []): array
    {
        foreach ($nodes as $node) {
            if (!is_array($node)) {
                continue;
            }
            if (isset($node['id'])) {
                $taken[(string) $node['id']] = true;
            }
            foreach (['children', 'rows', 'entries'] as $key) {
                if (isset($node[$key]) && is_array($node[$key])) {
                    $taken = $this->collectIds($node[$key], $taken);
                }
            }
        }
        return $taken;
    }

    // =======================================================================
    // SECTION: ELEMENT_READ
    // =======================================================================

    /** A settled collection is complete when at least one top-level Element is active. */
    public static function isComplete(array $elements): bool
    {
        foreach ($elements as $element) {
            if (is_array($element) && ($element['status'] ?? null) === self::STATUS_ACTIVE) {
                return true;
            }
        }
        return false;
    }

    /**
     * Reads an elements meta value (`{version, elements}`) as a list.
     *
     * @return list<array<string, mixed>>
     */
    public static function listFrom(mixed $stored): array
    {
        $elements = is_array($stored) ? ($stored['elements'] ?? []) : [];
        return is_array($elements) ? array_values($elements) : [];
    }

    /** @param list<array<string, mixed>> $elements */
    public static function wrap(array $elements): array
    {
        return ['version' => 1, 'elements' => array_values($elements)];
    }
}
