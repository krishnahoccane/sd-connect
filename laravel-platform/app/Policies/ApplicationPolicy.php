<?php

namespace App\Policies;

use App\Models\Application;
use App\Models\Organisation;
use App\Models\User;

class ApplicationPolicy
{
    public function view(User $user, Application $application): bool
    {
        return $user->is_super_admin || $user->hasActiveMembershipIn($application->organisation_id);
    }

    public function manage(User $user, Organisation $organisation, ?Application $application = null): bool
    {
        if ($application !== null && $application->organisation_id !== $organisation->id) {
            return false;
        }

        return $user->is_super_admin || $user->hasActiveMembershipIn($organisation->id);
    }
}
