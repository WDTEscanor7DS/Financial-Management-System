<?php

namespace App\Policies;

use App\Models\User;

class UserPolicy
{
    /**
     * True if $target is an active Administrator and would be the last one
     * remaining after the change being attempted -- i.e. removing,
     * disabling, suspending, or demoting them would leave the college with
     * zero people who can administer the system (Section 34).
     */
    public function wouldRemoveLastAdministrator(User $target): bool
    {
        if (! $target->role || $target->role->slug !== 'administrator' || ! $target->isActive()) {
            return false;
        }

        $activeAdminCount = User::query()
            ->whereHas('role', fn ($q) => $q->where('slug', 'administrator'))
            ->where('status', 'Active')
            ->count();

        return $activeAdminCount <= 1;
    }
}
