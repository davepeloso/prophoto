<?php

namespace ProPhoto\Contracts\Enums;

enum SessionAssignmentDecisionType: string
{
    case AUTO_ASSIGN = 'auto_assign';
    case PROPOSE = 'propose';
    case NO_MATCH = 'no_match';
    case MANUAL_ASSIGN = 'manual_assign';
    case MANUAL_UNASSIGN = 'manual_unassign';
}

