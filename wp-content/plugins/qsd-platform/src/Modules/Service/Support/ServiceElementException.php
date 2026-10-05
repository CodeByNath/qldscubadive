<?php

namespace QSD\Platform\Modules\Service\Support;

/** A Service Element payload that breaks the composition contract; carries its REST status. */
final class ServiceElementException extends \RuntimeException
{
    public function __construct(string $message, private int $status = 422)
    {
        parent::__construct($message);
    }

    public function status(): int
    {
        return $this->status;
    }
}
