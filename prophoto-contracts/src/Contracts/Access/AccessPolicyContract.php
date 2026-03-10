<?php

namespace ProPhoto\Contracts\Contracts\Access;

use ProPhoto\Contracts\DTOs\PermissionDecision;
use ProPhoto\Contracts\Enums\Ability;

interface AccessPolicyContract
{
    /**
     * Check if a user can perform an ability on a resource.
     */
    public function can(int $userId, Ability $ability, ?object $resource = null): PermissionDecision;

    /**
     * Check if a user cannot perform an ability on a resource.
     */
    public function cannot(int $userId, Ability $ability, ?object $resource = null): PermissionDecision;
}
