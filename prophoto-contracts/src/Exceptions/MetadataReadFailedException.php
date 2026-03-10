<?php

namespace ProPhoto\Contracts\Exceptions;

use Exception;

class MetadataReadFailedException extends Exception
{
    public function __construct(string $filePath, ?string $reason = null)
    {
        $message = "Failed to read metadata from: {$filePath}";
        if ($reason) {
            $message .= " ({$reason})";
        }
        parent::__construct($message);
    }
}
