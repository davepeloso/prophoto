<?php

namespace ProPhoto\Contracts\Enums;

enum DerivativeType: string
{
    case ORIGINAL = 'original';
    case THUMBNAIL = 'thumbnail';
    case PREVIEW = 'preview';
    case WEB = 'web';
    case PRINT = 'print';
}
