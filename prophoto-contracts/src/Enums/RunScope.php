<?php

namespace ProPhoto\Contracts\Enums;

enum RunScope: string
{
    case SINGLE_ASSET = 'single_asset';
    case BATCH = 'batch';
    case REINDEX = 'reindex';
    case MIGRATION = 'migration';
}
