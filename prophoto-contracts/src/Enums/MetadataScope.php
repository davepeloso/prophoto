<?php

namespace ProPhoto\Contracts\Enums;

enum MetadataScope: string
{
    case RAW = 'raw';
    case NORMALIZED = 'normalized';
    case BOTH = 'both';
}
