<?php

namespace ProPhoto\Contracts\Enums;

enum SessionContextReliability: string
{
    case HIGH = 'high';
    case MEDIUM = 'medium';
    case LOW = 'low';
    case NONE = 'none';
}

