<?php

namespace App\Policies;

use App\Models\TechnicianSubscription;
use App\Models\User;

class TechnicianSubscriptionPolicy
{
    public function request(User $user): bool
    {
        return $user->role === 'technician' && $user->account_status === 'active' && $user->technicianProfile()->exists();
    }

    public function view(User $user, TechnicianSubscription $subscription): bool
    {
        return $this->request($user) && $subscription->technician->user_id === $user->id;
    }

    public function administer(User $user): bool
    {
        return $user->role === 'admin' && $user->super && $user->account_status === 'active';
    }
}
