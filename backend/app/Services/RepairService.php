<?php

namespace App\Services;

use App\Models\ActivityLog;
use App\Models\RepairCategory;
use App\Models\RepairRequest;
use App\Models\TechnicianProfile;
use App\Models\User;
use App\Notifications\RepairUpdated;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class RepairService
{
    public function __construct(private RepairWorkflow $workflow, private AppointmentService $appointments) {}

    public function create(User $customer, array $data): RepairRequest
    {
        abort_unless($customer->role === 'customer' && $customer->account_status === 'active', 403);
        $paths = [];
        try {
            return DB::transaction(function () use ($customer, $data, &$paths) {
                $technician = TechnicianProfile::visible()->lockForUpdate()->findOrFail($data['technician_profile_id']);
                if ($technician->availability !== 'available') {
                    throw ValidationException::withMessages(['technician_profile_id' => 'This technician is not currently accepting requests.']);
                }
                $category = RepairCategory::where('active', true)->findOrFail($data['repair_category_id']);
                if (($category->device_type_id && $category->device_type_id != $data['device_type_id']) || ! $technician->deviceTypes()->where('device_types.id', $data['device_type_id'])->exists()) {
                    throw ValidationException::withMessages(['device_type_id' => 'This device and repair category are not supported by the selected technician.']);
                }
                if ($category->specialization_id && ! $technician->specializations()->where('specializations.id', $category->specialization_id)->exists()) {
                    throw ValidationException::withMessages(['repair_category_id' => 'Choose a technician specializing in this repair.']);
                }
                $repair = new RepairRequest(collect($data)->only(['technician_profile_id', 'device_type_id', 'device_brand_id', 'repair_category_id', 'model', 'serial_number', 'problem_description'])->all());
                $repair->customer_id = $customer->id;
                $repair->reference = 'ER-'.now()->format('ymd').'-'.strtoupper((string) Str::ulid());
                $repair->status = 'pending';
                $repair->save();
                $repair->history()->create(['previous_status' => null, 'new_status' => 'pending', 'updated_by' => $customer->id, 'remarks' => 'Customer submitted a repair request.']);
                $this->appointments->save($repair, $customer, $data);
                foreach ($data['photos'] ?? [] as $photo) {
                    $path = $photo->store('repairs', 'local');
                    $paths[] = $path;
                    $repair->attachments()->create(['uploaded_by' => $customer->id, 'path' => $path, 'original_name' => basename($photo->getClientOriginalName()), 'mime' => $photo->getMimeType(), 'size' => $photo->getSize()]);
                }
                $this->notify($repair, 'Repair request submitted');
                $this->log($customer, $repair, 'repair.created');

                return $repair;
            });
        } catch (\Throwable $e) {
            Storage::disk('local')->delete($paths);
            throw $e;
        }
    }

    public function transition(RepairRequest $repair, User $actor, string $next, ?string $remarks = null): RepairRequest
    {
        return DB::transaction(function () use ($repair, $actor, $next, $remarks) {
            $repair = RepairRequest::lockForUpdate()->findOrFail($repair->id);
            $owns = $actor->role === 'customer' ? $repair->customer_id === $actor->id : ($actor->role === 'technician' && $repair->technician->user_id === $actor->id);
            abort_unless($owns && $actor->account_status === 'active', 403);
            $this->workflow->ensure($repair->status, $next, $actor->role);
            if ($next === 'awaiting_customer_approval' && ! $repair->estimates()->where('status', 'pending')->exists()) {
                throw ValidationException::withMessages(['status' => 'Submit an estimate before requesting approval.']);
            }
            if ($next === 'in_repair' && $repair->estimates()->first()?->status !== 'approved') {
                throw ValidationException::withMessages(['status' => 'Approve the latest estimate before repair begins.']);
            }
            if (in_array($next, ['scheduled', 'diagnosing'], true) && ! $repair->appointments()->where('status', 'confirmed')->exists()) {
                throw ValidationException::withMessages(['status' => 'Confirm an appointment first.']);
            }
            $previous = $repair->status;
            $repair->status = $next;
            if ($next === 'canceled' && $previous === 'awaiting_customer_approval') {
                $repair->estimates()->where('status', 'pending')->update(['status' => 'rejected']);
            }
            if ($next === 'completed') {
                $repair->completed_at = now();
                $repair->appointments()->whereIn('status', ['proposed', 'confirmed'])->update(['status' => 'completed']);
            }
            $repair->save();
            if (in_array($next, ['canceled', 'rejected'])) {
                $repair->appointments()->whereIn('status', ['proposed', 'confirmed'])->update(['status' => 'canceled']);
            }
            $repair->history()->create(['previous_status' => $previous, 'new_status' => $next, 'updated_by' => $actor->id, 'remarks' => $remarks]);
            $this->log($actor, $repair, 'repair.'.$next);
            $messages = [
                'accepted' => 'Technician accepted the repair request', 'rejected' => 'Technician declined the repair request',
                'awaiting_customer_approval' => 'A repair estimate is ready for customer approval',
                'in_repair' => 'Customer approved the estimate; repair is in progress', 'ready_for_pickup' => 'Repair is ready for pickup',
                'completed' => 'Repair completed', 'canceled' => 'Repair request canceled',
            ];
            $this->notify($repair, $messages[$next] ?? 'Repair '.str_replace('_', ' ', $next));

            return $repair;
        });
    }

    public function note(RepairRequest $repair, User $actor, string $remarks): RepairRequest
    {
        return DB::transaction(function () use ($repair, $actor, $remarks) {
            $repair = RepairRequest::lockForUpdate()->findOrFail($repair->id);
            abort_unless($actor->role === 'technician' && $actor->account_status === 'active'
                && $repair->technician->user_id === $actor->id, 403);
            $repair->history()->create([
                'previous_status' => $repair->status, 'new_status' => $repair->status,
                'updated_by' => $actor->id, 'remarks' => $remarks,
            ]);
            $this->notify($repair, 'Technician added a repair note');
            $this->log($actor, $repair, 'repair.note_added');

            return $repair;
        });
    }

    public function notify(RepairRequest $repair, string $message): void
    {
        $repair->customer->notify(new RepairUpdated($repair->id, $message));
        $repair->technician->user->notify(new RepairUpdated($repair->id, $message));
    }

    public function log(User $actor, RepairRequest $repair, string $action): void
    {
        ActivityLog::create(['actor_id' => $actor->id, 'action' => $action, 'subject_type' => 'repair_request', 'subject_id' => $repair->id]);
    }
}
