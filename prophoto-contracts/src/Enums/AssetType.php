<?php

namespace ProPhoto\Contracts\Enums;

enum AssetType: string
{
    case RAW = 'raw';
    case JPEG = 'jpeg';
    case HEIC = 'heic';
    case PNG = 'png';
    case VIDEO = 'video';
    case UNKNOWN = 'unknown';
}
