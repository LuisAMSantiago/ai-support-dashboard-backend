<?php

namespace App\Policies;

use App\Models\Ticket;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class TicketPolicy
{
    use HandlesAuthorization;

    public function before(?User $user, $ability)
    {
        if ($user && $user->is_admin) {
            return true;
        }
    }

    public function viewAny(User $user): bool
    {
        return true;
    }

    public function create(User $user): bool
    {
        return true;
    }

    public function view(User $user, Ticket $ticket): bool
    {
        return $ticket->created_by === $user->id;
    }

    public function update(User $user, Ticket $ticket): bool
    {
        return $ticket->created_by === $user->id;
    }

    public function delete(User $user, Ticket $ticket): bool
    {
        return $ticket->created_by === $user->id;
    }

    public function restore(User $user, Ticket $ticket): bool
    {
        return $ticket->created_by === $user->id;
    }

    public function forceDelete(User $user, Ticket $ticket): bool
    {
        return $ticket->created_by === $user->id;
    }
}
