<?php

namespace App\Services;

use App\Models\ActivityLog;
use App\Models\TechnicianProfile;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class TechnicianVerification
{
    public function decide(User $admin, TechnicianProfile $profile, string $decision, ?string $notes): void
    {
        abort_unless($admin->role === 'admin' && $admin->super && $admin->account_status === 'active', 403);
        if (! in_array($decision, ['approved', 'rejected', 'pending'], true)) {
            throw ValidationException::withMessages(['decision' => 'Invalid verification decision.']);
        }
        DB::transaction(function () use ($admin, $profile, $decision, $notes) {
            $profile = TechnicianProfile::lockForUpdate()->findOrFail($profile->id);
            if ($decision === 'approved' && (! $profile->biography || ! $profile->deviceTypes()->where('active', true)->exists() || ! $profile->specializations()->where('active', true)->exists())) {
                throw ValidationException::withMessages(['decision' => 'Complete the biography and select active supported devices and specializations before approval.']);
            }
            if ($decision === 'approved' && $profile->publicCoordinates() === null) {
                $coordinates = app(ServiceLocation::class)->resolve($profile->city, $profile->province);
                if ($coordinates === null) {
                    throw ValidationException::withMessages(['decision' => 'The city and province could not be located automatically. Correct the location or save an approximate public map location before approval.']);
                }
                [$profile->latitude, $profile->longitude] = $coordinates;
            }
            $profile->verification_status = $decision;
            $profile->verified_by = $admin->id;
            $profile->verified_at = $decision === 'approved' ? now() : null;
            $profile->verification_notes = $notes;
            $profile->save();
            ActivityLog::create(['actor_id' => $admin->id, 'action' => 'technician.'.$decision, 'subject_type' => 'technician_profile', 'subject_id' => $profile->id, 'metadata' => ['notes' => $notes]]);
        });
    }
}
