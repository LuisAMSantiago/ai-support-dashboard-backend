<?php

namespace App\Policies;

use App\Models\Ticket;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class TicketPolicy
{
    use HandlesAuthorization;

    /**
     * Global before check to allow admins in the future.
     */
    public function before(?User $user, $ability)
    {
        if ($user && isset($user->is_admin) && $user->is_admin) {
            return true;
        }
    }

    public function view(User $user, Ticket $ticket): bool
    {
        return $user->id === $ticket->created_by;
    }

    public function update(User $user, Ticket $ticket): bool
    {
        return $user->id === $ticket->created_by;
    }

    public function delete(User $user, Ticket $ticket): bool
    {
        return $user->id === $ticket->created_by;
    }
}
