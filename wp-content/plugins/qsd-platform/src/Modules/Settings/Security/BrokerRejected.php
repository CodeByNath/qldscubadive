<?php

namespace QSD\Platform\Modules\Settings\Security;

/** A broker refusal, with a stable reason code. Messages never contain a key or secret. */
final class BrokerRejected extends \RuntimeException
{
    public const INVALID_REQUEST      = 'invalid_request';
    public const UNAUTHENTICATED      = 'unauthenticated';
    public const UNKNOWN_PROVIDER     = 'unknown_provider';
    public const UNDECLARED_SCOPE     = 'undeclared_scope';
    public const UNKNOWN_CALLER       = 'unknown_caller';
    public const NOT_CONFIGURED       = 'not_configured';
    public const UNKNOWN_KEY          = 'unknown_key';
    public const REPLAYED             = 'replayed';
    public const EXPIRED              = 'expired';
    public const BINDING_MISMATCH     = 'binding_mismatch';
    public const NO_OPERATION         = 'no_operation';
    public const SECRET_IN_RESULT     = 'secret_in_result';
    public const OPERATION_FAILED     = 'operation_failed';

    public function __construct(public readonly string $reason, string $message)
    {
        parent::__construct($message);
    }
}
