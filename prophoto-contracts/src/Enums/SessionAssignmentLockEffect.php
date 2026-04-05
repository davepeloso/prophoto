<?php

namespace ProPhoto\Contracts\Enums;

enum SessionAssignmentLockEffect: string
{
    case NONE = 'none';
    case LOCK_ASSIGNED = 'lock_assigned';
    case LOCK_UNASSIGNED = 'lock_unassigned';
    case UNLOCK = 'unlock';
}

