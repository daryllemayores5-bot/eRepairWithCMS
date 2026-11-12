<?php

namespace App\Policies;

use App\Models\RepairRequest;
use App\Models\User;

class RepairRequestPolicy
{
    public function view(User $user, RepairRequest $repair): bool
    {
        return $user->account_status === 'active' && ($user->role === 'admin'
            || ($user->role === 'customer' && $repair->customer_id === $user->id)
            || ($user->role === 'technician' && $repair->technician->user_id === $user->id));
    }

    public function create(User $user): bool
    {
        return $user->role === 'customer' && $user->account_status === 'active';
    }

    public function update(User $user, RepairRequest $repair): bool
    {
        return $this->view($user, $repair);
    }
}
