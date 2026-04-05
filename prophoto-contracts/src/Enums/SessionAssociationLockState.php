<?php

namespace ProPhoto\Contracts\Enums;

enum SessionAssociationLockState: string
{
    case NONE = 'none';
    case MANUAL_ASSIGNED_LOCK = 'manual_assigned_lock';
    case MANUAL_UNASSIGNED_LOCK = 'manual_unassigned_lock';
}

