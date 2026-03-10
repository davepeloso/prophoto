<?php

namespace ProPhoto\Contracts\Exceptions;

use Exception;

class PermissionDeniedException extends Exception
{
    public function __construct(?string $reason = null)
    {
        $message = $reason ?? 'Permission denied';
        parent::__construct($message);
    }
}
