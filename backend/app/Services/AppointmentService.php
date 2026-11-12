<?php

namespace App\Services;

use App\Models\Appointment;
use App\Models\RepairRequest;
use App\Models\TechnicianProfile;
use App\Models\User;
use App\Notifications\RepairUpdated;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class AppointmentService
{
    public function save(RepairRequest $repair, User $actor, array $data, ?Appointment $appointment = null): Appointment
    {
        return DB::transaction(function () use ($repair, $actor, $data, $appointment) {
            // Lock ordering is consistently repair -> technician -> appointment.
            $repair = RepairRequest::lockForUpdate()->findOrFail($repair->id);
            TechnicianProfile::whereKey($repair->technician_profile_id)->lockForUpdate()->firstOrFail();
            abort_unless($repair->customer_id === $actor->id || $repair->technician->user_id === $actor->id, 403);
            if (! in_array($repair->status, ['pending', 'accepted', 'scheduled'])) {
                throw ValidationException::withMessages(['appointment' => 'Appointments cannot change after diagnosis begins.']);
            }
            $start = CarbonImmutable::parse($data['starts_at'], 'Asia/Manila')->utc();
            $end = CarbonImmutable::parse($data['ends_at'], 'Asia/Manila')->utc();
            if ($start->isPast() || $end->lessThanOrEqualTo($start) || $start->diffInMinutes($end) > 240) {
                throw ValidationException::withMessages(['starts_at' => 'Choose a future appointment lasting up to four hours.']);
            }
            if (Appointment::whereHas('repair', fn ($q) => $q->where('technician_profile_id', $repair->technician_profile_id))
                ->whereIn('status', ['proposed', 'confirmed'])->when($appointment, fn ($q) => $q->whereKeyNot($appointment->id))
                ->where('starts_at', '<', $end)->where('ends_at', '>', $start)->exists()) {
                throw ValidationException::withMessages(['starts_at' => 'This technician already has an appointment during that time.']);
            }
            if ($appointment) {
                abort_unless($appointment->repair_request_id === $repair->id, 403);
            } elseif ($repair->appointments()->whereIn('status', ['proposed', 'confirmed'])->exists()) {
                throw ValidationException::withMessages(['appointment' => 'Update the existing appointment instead.']);
            }
            $appointment ??= new Appointment(['repair_request_id' => $repair->id]);
            $appointment->fill(['starts_at' => $start, 'ends_at' => $end, 'service_location' => $data['service_location'],
                'status' => $actor->role === 'technician' ? 'confirmed' : 'proposed']);
            $appointment->save();
            $recipient = $actor->role === 'customer' ? $repair->technician->user : $repair->customer;
            $recipient->notify(new RepairUpdated($repair->id, 'Appointment '.$appointment->status));

            return $appointment;
        });
    }
}
