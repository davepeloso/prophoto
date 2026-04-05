<?php

namespace ProPhoto\Contracts\Enums;

enum SessionMatchConfidenceTier: string
{
    case HIGH = 'high';
    case MEDIUM = 'medium';
    case LOW = 'low';
}

