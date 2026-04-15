<?php

namespace ProPhoto\Contracts\Enums\AI;

enum TrainingStatus: string
{
    case PENDING = 'pending';
    case TRAINING = 'training';
    case TRAINED = 'trained';
    case FAILED = 'failed';
    case EXPIRED = 'expired';
}
