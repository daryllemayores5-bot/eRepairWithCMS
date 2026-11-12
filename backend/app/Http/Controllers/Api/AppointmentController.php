<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Appointment;
use App\Models\RepairRequest;
use App\Services\AppointmentService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class AppointmentController extends Controller
{
    public function index(Request $request)
    {
        return response()->json(Appointment::whereHas('repair', fn ($q) => $q->forUser($request->user()))->with('repair:id,reference,model,status')->orderBy('starts_at')->paginate(20));
    }

    public function store(Request $request, AppointmentService $service)
    {
        $data = $this->validated($request);
        $repair = RepairRequest::findOrFail($data['repair_request_id']);
        Gate::authorize('update', $repair);

        return response()->json(['data' => $service->save($repair, $request->user(), $data)], 201);
    }

    public function update(Request $request, Appointment $appointment, AppointmentService $service)
    {
        Gate::authorize('update', $appointment->repair);

        return response()->json(['data' => $service->save($appointment->repair, $request->user(), $this->validated($request, false), $appointment)]);
    }

    private function validated(Request $request, bool $create = true): array
    {
        return $request->validate([
            'repair_request_id' => [$create ? 'required' : 'sometimes', 'integer', 'exists:repair_requests,id'],
            'starts_at' => ['required', 'date', 'after:now'], 'ends_at' => ['required', 'date', 'after:starts_at'],
            'service_location' => ['required', 'string', 'max:500'],
        ]);
    }
}
