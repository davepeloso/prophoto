<?php

namespace ProPhoto\Intelligence\Planning;

enum PlannerDecisionReason: string
{
    case DISABLED_BY_CONFIG = 'disabled_by_config';
    case UNSUPPORTED_MEDIA_KIND = 'unsupported_media_kind';
    case ASSET_NOT_READY = 'asset_not_ready';
    case MATCHING_COMPLETED_RUN_EXISTS = 'matching_completed_run_exists';
    case ACTIVE_RUN_EXISTS = 'active_run_exists';
    case SESSION_CONTEXT_REQUIRED_BUT_MISSING = 'session_context_required_but_missing';
    case SESSION_CONTEXT_LOCKED_UNASSIGNED = 'session_context_locked_unassigned';
    case SESSION_CONTEXT_RELIABILITY_TOO_LOW = 'session_context_reliability_too_low';
}
