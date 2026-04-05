<?php

namespace ProPhoto\Contracts\Enums;

enum SessionAssociationSource: string
{
    case AUTO = 'auto';
    case MANUAL = 'manual';
    case PROPOSAL = 'proposal';
    case NONE = 'none';
}

