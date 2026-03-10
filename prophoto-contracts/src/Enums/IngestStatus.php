<?php

namespace ProPhoto\Contracts\Enums;

enum IngestStatus: string
{
    case QUEUED = 'queued';
    case PROCESSING = 'processing';
    case COMPLETE = 'complete';
    case FAILED = 'failed';
}
