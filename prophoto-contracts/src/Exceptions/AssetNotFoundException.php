<?php

namespace ProPhoto\Contracts\Exceptions;

use Exception;
use ProPhoto\Contracts\DTOs\AssetId;

class AssetNotFoundException extends Exception
{
    public function __construct(AssetId $assetId, ?string $message = null)
    {
        $message = $message ?? "Asset not found: {$assetId->toString()}";
        parent::__construct($message);
    }
}
