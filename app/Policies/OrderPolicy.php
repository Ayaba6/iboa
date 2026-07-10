<?php

namespace App\Policies;

use App\Models\Order;
use App\Models\User;

class OrderPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('orders.view');
    }

    public function view(User $user, Order $order): bool
    {
        return $user->can('orders.view');
    }

    public function create(User $user): bool
    {
        return $user->can('orders.create');
    }

    /**
     * [CDC §13.1] Après validation financière (confirme et au-delà), les prix
     * sont verrouillés : seul un porteur de orders.edit_validated (responsable
     * commercial, direction) peut encore modifier la commande.
     */
    public function update(User $user, Order $order): bool
    {
        if (! $user->can('orders.edit')) {
            return false;
        }

        if (in_array($order->status, ['brouillon', 'en_attente_validation'], true)) {
            return true;
        }

        return $user->can('orders.edit_validated');
    }

    public function delete(User $user, Order $order): bool
    {
        return $user->can('orders.delete');
    }

    public function validate(User $user, Order $order): bool
    {
        return $user->can('orders.validate');
    }

    /** [CDC §réouverture] Seul le responsable hiérarchique peut réouvrir une commande annulée. */
    public function reopen(User $user, Order $order): bool
    {
        return $user->can('orders.reopen');
    }
}
