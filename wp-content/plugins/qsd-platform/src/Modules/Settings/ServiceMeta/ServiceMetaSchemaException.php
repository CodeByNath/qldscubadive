<?php

namespace QSD\Platform\Modules\Settings\ServiceMeta;

/** A rejected schema operation, carrying the REST status the controller returns. */
final class ServiceMetaSchemaException extends \RuntimeException
{
    public function __construct(string $message, public readonly int $status = 422)
    {
        parent::__construct($message);
    }
}
