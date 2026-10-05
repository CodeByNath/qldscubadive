<?php

/*
 * FILE INDEX
 *
 * META_SCHEMA_READ      Field list and lookup
 * META_SCHEMA_WRITE     Create, update, reorder, retire, restore
 * META_SCHEMA_NORMALISE Definition validation (type, options, sub-fields)
 * META_SCHEMA_IDENTITY  Stable internal id minting
 * META_SCHEMA_STORAGE   Option read/write
 *
 * Search: SECTION: META_SCHEMA_READ
 *         SECTION: META_SCHEMA_WRITE
 *         SECTION: META_SCHEMA_NORMALISE
 *         SECTION: META_SCHEMA_IDENTITY
 *         SECTION: META_SCHEMA_STORAGE
 */

namespace QSD\Platform\Modules\Settings\ServiceMeta;

/**
 * ServiceMetaSchema — the Settings-owned definition of configurable Service
 * metadata fields. The sole reader/writer of its option.
 *
 * Ownership: Settings owns field DEFINITIONS only. Per-Service values belong
 * to Service Station and must flow through Service drafts/settle/projection
 * (docs/architecture/service-meta-schema-contract.md). Nothing here reads or
 * writes Service storage.
 *
 * Identity (three-rung audit): a field definition is a rung-2 scoped child of
 * this schema — addressable only inside it, never a Platform ID. Each field,
 * select option and container sub-field carries a stable internal id minted
 * here (`fld_…`, `opt_…`) and never derived from label, slug or position.
 * Array order is presentation order only. A definition says WHAT an Element
 * is; which instance exists on a Service is Service Station's own child id
 * (docs/architecture/service-element-composition-contract.md).
 *
 * Non-destructive policy: a field is never deleted — it is RETIRED (kept, with
 * its id, so stored Service values stay matchable) and can be restored. A
 * field's type is immutable, and existing option / sub-field ids cannot be
 * dropped, because either would orphan stored values. Permanent removal waits
 * for an approved Service-value recovery rule.
 */
final class ServiceMetaSchema
{
    public const OPTION = 'qsd_settings_service_meta_schema';

    public const TYPES = ['text', 'textarea', 'number', 'boolean', 'select', 'image', 'gallery', 'group', 'repeater'];
    /** Container types: a group carries one set of child Elements, a repeater carries repeated rows of them. */
    public const CONTAINER_TYPES = ['group', 'repeater'];
    /** A container's sub-fields may be any non-container type (one level of nesting today). */
    public const SUB_FIELD_TYPES = ['text', 'textarea', 'number', 'boolean', 'select', 'image', 'gallery'];

    public const STATUS_ACTIVE  = 'active';
    public const STATUS_RETIRED = 'retired';

    private const ID_ALPHABET = '23456789ABCDEFGHJKMNPQRSTVWXYZ';
    private const ID_LENGTH   = 10;
    private const LABEL_MAX   = 80;

    // =======================================================================
    // SECTION: META_SCHEMA_READ
    // =======================================================================

    /** @return list<array<string, mixed>> */
    public function fields(): array
    {
        return $this->load();
    }

    /** @return array<string, mixed> */
    public function find(string $id): array
    {
        foreach ($this->load() as $field) {
            if ($field['id'] === $id) {
                return $field;
            }
        }
        throw new ServiceMetaSchemaException('Unknown Service Meta field.', 404);
    }

    // =======================================================================
    // SECTION: META_SCHEMA_WRITE
    // =======================================================================

    /** @param array<string, mixed> $input */
    public function create(array $input): array
    {
        $fields = $this->load();
        $type = (string) ($input['type'] ?? '');
        if (!in_array($type, self::TYPES, true)) {
            throw new ServiceMetaSchemaException('Choose a supported field type.');
        }

        $taken = $this->takenIds($fields);
        $field = $this->normaliseDefinition($input, $type, null, $taken, self::SUB_FIELD_TYPES);
        $field = ['id' => $this->mintId('fld_', $taken)] + $field + [
            'status'     => self::STATUS_ACTIVE,
            'created_at' => gmdate('c'),
        ];

        $fields[] = $field;
        $this->save($fields);
        return $field;
    }

    /** @param array<string, mixed> $input */
    public function update(string $id, array $input): array
    {
        $fields = $this->load();
        $index = $this->indexOf($fields, $id);
        $existing = $fields[$index];

        if (isset($input['type']) && $input['type'] !== $existing['type']) {
            throw new ServiceMetaSchemaException('A field type cannot change once created; retire it and add a new field.');
        }

        $taken = $this->takenIds($fields);
        $next = $this->normaliseDefinition($input, $existing['type'], $existing, $taken, self::SUB_FIELD_TYPES);
        $fields[$index] = ['id' => $existing['id']] + $next + [
            'status'     => $existing['status'],
            'created_at' => $existing['created_at'],
        ] + (isset($existing['retired_at']) ? ['retired_at' => $existing['retired_at']] : []);

        $this->save($fields);
        return $fields[$index];
    }

    /**
     * Reorders by id. `$ids` must name every field (active and retired) exactly
     * once; only array order changes, never an id.
     *
     * @param list<string> $ids
     */
    public function reorder(array $ids): array
    {
        $fields = $this->load();
        $byId = [];
        foreach ($fields as $field) {
            $byId[$field['id']] = $field;
        }
        $ids = array_values(array_map('strval', $ids));
        if (count($ids) !== count($byId) || count(array_unique($ids)) !== count($ids) || array_diff($ids, array_keys($byId))) {
            throw new ServiceMetaSchemaException('Reorder must list every field id exactly once.');
        }

        $ordered = array_map(static fn(string $id) => $byId[$id], $ids);
        $this->save($ordered);
        return $ordered;
    }

    public function retire(string $id): array
    {
        return $this->setStatus($id, self::STATUS_RETIRED);
    }

    public function restore(string $id): array
    {
        return $this->setStatus($id, self::STATUS_ACTIVE);
    }

    private function setStatus(string $id, string $status): array
    {
        $fields = $this->load();
        $index = $this->indexOf($fields, $id);
        $fields[$index]['status'] = $status;
        if ($status === self::STATUS_RETIRED) {
            $fields[$index]['retired_at'] = gmdate('c');
        } else {
            unset($fields[$index]['retired_at']);
        }
        $this->save($fields);
        return $fields[$index];
    }

    // =======================================================================
    // SECTION: META_SCHEMA_NORMALISE
    // =======================================================================

    /**
     * Validates the editable part of a definition (label, help, required,
     * options, sub_fields). `$existing` is the stored definition on update, so
     * kept child ids are verified rather than re-minted.
     *
     * @param array<string, mixed>      $input
     * @param array<string, mixed>|null $existing
     * @param array<string, true>       $taken    ids already used in the schema (mutated as ids are minted)
     * @param list<string>              $subTypes
     * @return array<string, mixed>
     */
    private function normaliseDefinition(array $input, string $type, ?array $existing, array &$taken, array $subTypes): array
    {
        $label = sanitize_text_field((string) ($input['label'] ?? ($existing['label'] ?? '')));
        if ($label === '') {
            throw new ServiceMetaSchemaException('A field label is required.');
        }
        if (mb_strlen($label) > self::LABEL_MAX) {
            throw new ServiceMetaSchemaException('A field label must be 80 characters or fewer.');
        }

        $definition = [
            'label'    => $label,
            'type'     => $type,
            'help'     => sanitize_textarea_field((string) ($input['help'] ?? ($existing['help'] ?? ''))),
            'required' => (bool) ($input['required'] ?? ($existing['required'] ?? false)),
        ];

        $hasOptions = array_key_exists('options', $input) && $input['options'] !== null && $input['options'] !== [];
        $hasSubFields = array_key_exists('sub_fields', $input) && $input['sub_fields'] !== null && $input['sub_fields'] !== [];

        if ($type === 'select') {
            $definition['options'] = $this->normaliseOptions(
                array_key_exists('options', $input) ? $input['options'] : ($existing['options'] ?? []),
                $existing['options'] ?? [],
            );
        } elseif ($hasOptions) {
            throw new ServiceMetaSchemaException('Only a select field has options.');
        }

        if (in_array($type, self::CONTAINER_TYPES, true)) {
            $definition['sub_fields'] = $this->normaliseSubFields(
                array_key_exists('sub_fields', $input) ? $input['sub_fields'] : ($existing['sub_fields'] ?? []),
                $existing['sub_fields'] ?? [],
                $taken,
                $subTypes,
            );
        } elseif ($hasSubFields) {
            throw new ServiceMetaSchemaException('Only a group or repeater field has sub-fields.');
        }

        return $definition;
    }

    /**
     * @param mixed                        $input
     * @param list<array{id: string, label: string}> $existing
     * @return list<array{id: string, label: string}>
     */
    private function normaliseOptions(mixed $input, array $existing): array
    {
        if (!is_array($input) || $input === []) {
            throw new ServiceMetaSchemaException('A select field needs at least one option.');
        }
        $existingIds = array_column($existing, 'id');
        $usedIds = array_fill_keys($existingIds, true);
        $seen = [];
        $options = [];

        foreach ($input as $option) {
            $label = sanitize_text_field((string) (is_array($option) ? ($option['label'] ?? '') : $option));
            if ($label === '') {
                throw new ServiceMetaSchemaException('Every option needs a label.');
            }
            $id = is_array($option) ? (string) ($option['id'] ?? '') : '';
            if ($id !== '') {
                if (!in_array($id, $existingIds, true) || isset($seen[$id])) {
                    throw new ServiceMetaSchemaException('An option id must name an existing option of this field once.');
                }
            } else {
                $id = $this->mintId('opt_', $usedIds);
            }
            $seen[$id] = true;
            $options[] = ['id' => $id, 'label' => $label];
        }

        if (array_diff($existingIds, array_keys($seen))) {
            throw new ServiceMetaSchemaException('Existing options cannot be removed: stored Service values may use them.');
        }
        return $options;
    }

    /**
     * @param mixed                      $input
     * @param list<array<string, mixed>> $existing
     * @param array<string, true>        $taken
     * @param list<string>               $subTypes
     * @return list<array<string, mixed>>
     */
    private function normaliseSubFields(mixed $input, array $existing, array &$taken, array $subTypes): array
    {
        if (!is_array($input) || $input === []) {
            throw new ServiceMetaSchemaException('A group or repeater needs at least one sub-field.');
        }
        $existingById = [];
        foreach ($existing as $sub) {
            $existingById[$sub['id']] = $sub;
        }
        $seen = [];
        $subFields = [];

        foreach ($input as $sub) {
            if (!is_array($sub)) {
                throw new ServiceMetaSchemaException('Each sub-field must be an object.');
            }
            $id = (string) ($sub['id'] ?? '');
            if ($id !== '') {
                if (!isset($existingById[$id]) || isset($seen[$id])) {
                    throw new ServiceMetaSchemaException('A sub-field id must name an existing sub-field of this field once.');
                }
                $prior = $existingById[$id];
                if (isset($sub['type']) && $sub['type'] !== $prior['type']) {
                    throw new ServiceMetaSchemaException('A sub-field type cannot change once created.');
                }
                $type = $prior['type'];
            } else {
                $type = (string) ($sub['type'] ?? '');
                if (!in_array($type, $subTypes, true)) {
                    throw new ServiceMetaSchemaException('A sub-field must be a supported non-container type.');
                }
                $prior = null;
                $id = $this->mintId('fld_', $taken);
            }
            $seen[$id] = true;
            $noNested = [];
            $subFields[] = ['id' => $id] + $this->normaliseDefinition($sub, $type, $prior, $taken, $noNested);
        }

        if (array_diff(array_keys($existingById), array_keys($seen))) {
            throw new ServiceMetaSchemaException('Existing sub-fields cannot be removed: stored Service values may use them.');
        }
        return $subFields;
    }

    // =======================================================================
    // SECTION: META_SCHEMA_IDENTITY
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
     * Every field and sub-field id in the schema, so a minted id is unique
     * schema-wide.
     *
     * @param list<array<string, mixed>> $fields
     * @return array<string, true>
     */
    private function takenIds(array $fields): array
    {
        $taken = [];
        foreach ($fields as $field) {
            $taken[$field['id']] = true;
            foreach ($field['sub_fields'] ?? [] as $sub) {
                $taken[$sub['id']] = true;
            }
        }
        return $taken;
    }

    /** @param list<array<string, mixed>> $fields */
    private function indexOf(array $fields, string $id): int
    {
        foreach ($fields as $index => $field) {
            if ($field['id'] === $id) {
                return $index;
            }
        }
        throw new ServiceMetaSchemaException('Unknown Service Meta field.', 404);
    }

    // =======================================================================
    // SECTION: META_SCHEMA_STORAGE
    // =======================================================================

    /** @return list<array<string, mixed>> */
    private function load(): array
    {
        $value = get_option(self::OPTION, []);
        $fields = is_array($value) ? ($value['fields'] ?? []) : [];
        return is_array($fields) ? array_values($fields) : [];
    }

    /** @param list<array<string, mixed>> $fields */
    private function save(array $fields): void
    {
        $value = ['version' => 1, 'fields' => array_values($fields)];
        if (!add_option(self::OPTION, $value, '', 'no')) {
            update_option(self::OPTION, $value, false);
        }
    }
}
