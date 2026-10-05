<?php

namespace QSD\Platform\Modules\Settings\ServiceMeta;

/**
 * ServiceElementDefinitions — the Settings-owned READ capability Service
 * Station consumes to validate and render its Element instances.
 *
 * Settings owns Element definitions (what an Element is); Service owns every
 * instance (which Element exists on which Service, and its value). Service
 * never constructs ServiceMetaSchema and never writes the schema option — it
 * receives this read-only view, which exposes no mutation.
 *
 * `find()` returns retired definitions too: a retired definition still
 * describes the instances Service keeps for it, it just cannot be newly
 * instantiated or edited.
 */
final class ServiceElementDefinitions
{
    public function __construct(private ServiceMetaSchema $schema) {}

    /** @return array<string, mixed>|null */
    public function find(string $id): ?array
    {
        foreach ($this->schema->fields() as $field) {
            if (($field['id'] ?? null) === $id) {
                return $field;
            }
        }
        return null;
    }

    public function isActive(array $definition): bool
    {
        return ($definition['status'] ?? null) === ServiceMetaSchema::STATUS_ACTIVE;
    }

    /**
     * Every definition in presentation order, retired ones included and
     * flagged by `status`, so an editor can show kept instances of a retired
     * definition read-only.
     *
     * @return list<array<string, mixed>>
     */
    public function all(): array
    {
        return $this->schema->fields();
    }
}
