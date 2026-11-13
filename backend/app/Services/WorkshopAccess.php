<?php

namespace App\Services;

use App\Models\TechnicianProfile;
use App\Models\User;

class WorkshopAccess
{
    public function profile(User $user): TechnicianProfile
    {
        abort_unless($user->role === 'technician' && $user->account_status === 'active', 403);

        return $user->technicianProfile()->firstOrFail();
    }

    public function requirePlan(User $user, string $minimum = 'pro'): TechnicianProfile
    {
        $profile = $this->profile($user);
        $allowed = $minimum === 'premium' ? $profile->hasPremiumSubscription() : $profile->hasProSubscription();
        abort_unless($allowed, 403, 'An active '.ucfirst($minimum).' subscription is required for this tool. Your saved records are retained.');

        return $profile;
    }

    public function capabilities(User $user): array
    {
        $profile = $this->profile($user);
        $premium = $profile->hasPremiumSubscription();
        $paid = $premium || $profile->hasProSubscription();

        return ['tier' => $premium ? 'premium' : ($paid ? 'pro' : 'free'), 'documents' => $paid, 'templates' => $paid, 'inventory' => $premium, 'business_reports' => $premium];
    }
}
