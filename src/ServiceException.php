<?php

declare(strict_types=1);

namespace AccessSwitch;

use InvalidArgumentException;

final class ServiceException extends InvalidArgumentException
{
    public const UNKNOWN = 'unknown';
    public const INVALID_ID = 'invalid_id';
    public const NOT_FOUND = 'not_found';
    public const ALREADY_EXISTS = 'already_exists';
    public const NOT_IN_ENV = 'not_in_env';
    public const CANNOT_MANAGE_DEFAULT = 'cannot_manage_default';

    public function __construct(
        public readonly string $reason,
        string $message = '',
    ) {
        parent::__construct($message !== '' ? $message : $reason);
    }
}
