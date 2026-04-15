<?php

namespace ProPhoto\Contracts\Enums\AI;

enum ProviderRole: string
{
    case IDENTITY_GENERATION = 'identity_generation';
    case REALTIME_GENERATION = 'realtime_generation';
    case ENHANCEMENT = 'enhancement';
    case COMMERCIAL_BACKGROUND = 'commercial_background';
}
