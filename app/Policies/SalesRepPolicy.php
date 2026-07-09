<?php

namespace App\Policies;

use App\Models\SalesRep;
use App\Models\User;

class SalesRepPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermissionTo('clients.view');
    }

    public function view(User $user, SalesRep $salesRep): bool
    {
        return $user->hasPermissionTo('clients.view');
    }

    public function create(User $user): bool
    {
        return $user->hasPermissionTo('clients.create');
    }

    public function update(User $user, SalesRep $salesRep): bool
    {
        return $user->hasPermissionTo('clients.edit');
    }

    public function delete(User $user, SalesRep $salesRep): bool
    {
        return $user->hasPermissionTo('clients.delete');
    }
}
