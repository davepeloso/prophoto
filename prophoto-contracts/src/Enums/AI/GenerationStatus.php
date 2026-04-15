<?php

namespace ProPhoto\Contracts\Enums\AI;

enum GenerationStatus: string
{
    case PENDING = 'pending';
    case PROCESSING = 'processing';
    case COMPLETED = 'completed';
    case FAILED = 'failed';
}
