<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreRepairRequest;
use App\Http\Resources\RepairResource;
use App\Models\RepairAttachment;
use App\Models\RepairRequest;
use App\Services\RepairService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class RepairController extends Controller
{
    public function index(Request $request)
    {
        $filter = $request->validate(['status' => ['nullable', 'in:all,active,history,pending']]);
        $query = RepairRequest::forUser($request->user());
        if (($filter['status'] ?? 'all') === 'active') {
            $query->whereIn('status', ['accepted', 'scheduled', 'diagnosing', 'awaiting_customer_approval', 'in_repair', 'ready_for_pickup']);
        }
        if (($filter['status'] ?? 'all') === 'history') {
            $query->whereIn('status', ['completed', 'canceled', 'rejected']);
        }
        if (($filter['status'] ?? 'all') === 'pending') {
            $query->where('status', 'pending');
        }

        return RepairResource::collection($query->with(['customer', 'technician.user', 'deviceType', 'deviceBrand', 'category'])->latest()->paginate(15));
    }

    public function store(StoreRepairRequest $request, RepairService $service)
    {
        return (new RepairResource($service->create($request->user(), $request->validated())))->response()->setStatusCode(201);
    }

    public function show(RepairRequest $repairRequest)
    {
        Gate::authorize('view', $repairRequest);

        return new RepairResource($repairRequest->load(['customer', 'technician.user', 'deviceType', 'deviceBrand', 'category', 'history.actor:id,name', 'estimates', 'appointments', 'attachments', 'review']));
    }

    public function update(Request $request, RepairRequest $repairRequest)
    {
        Gate::authorize('update', $repairRequest);
        $data = $request->validate(['problem_description' => ['required', 'string', 'min:15', 'max:5000']]);
        DB::transaction(function () use ($request, $repairRequest, $data) {
            $repair = RepairRequest::lockForUpdate()->findOrFail($repairRequest->id);
            abort_unless($request->user()->id === $repair->customer_id && $repair->status === 'pending', 403);
            $repair->update($data);
        });

        return $this->show($repairRequest->fresh());
    }

    public function transition(Request $request, RepairRequest $repairRequest, RepairService $service)
    {
        Gate::authorize('update', $repairRequest);
        $status = $request->validate(['status' => ['required', 'string', 'max:40'], 'remarks' => ['nullable', 'string', 'max:2000']]);
        $service->transition($repairRequest, $request->user(), $status['status'], $status['remarks'] ?? null);

        return $this->show($repairRequest->fresh());
    }

    public function accept(Request $r, RepairRequest $repairRequest, RepairService $s)
    {
        abort_unless($r->user()->role === 'technician', 403);
        $s->transition($repairRequest, $r->user(), 'accepted');

        return $this->show($repairRequest->fresh());
    }

    public function decline(Request $r, RepairRequest $repairRequest, RepairService $s)
    {
        abort_unless($r->user()->role === 'technician', 403);
        $d = $r->validate(['remarks' => ['required', 'string', 'max:2000']]);
        $s->transition($repairRequest, $r->user(), 'rejected', $d['remarks']);

        return $this->show($repairRequest->fresh());
    }

    public function cancel(Request $r, RepairRequest $repairRequest, RepairService $s)
    {
        abort_unless($r->user()->role === 'customer', 403);
        $s->transition($repairRequest, $r->user(), 'canceled');

        return $this->show($repairRequest->fresh());
    }

    public function history(RepairRequest $repairRequest)
    {
        Gate::authorize('view', $repairRequest);

        return response()->json(['data' => $repairRequest->history()->with('actor:id,name')->get()]);
    }

    public function estimate(Request $request, RepairRequest $repairRequest, RepairService $service)
    {
        Gate::authorize('update', $repairRequest);
        abort_unless($request->user()->role === 'technician', 403);
        $data = $request->validate(['amount' => ['required', 'numeric', 'min:0.01', 'max:999999.99', 'decimal:0,2'], 'description' => ['required', 'string', 'min:10', 'max:2000']]);
        DB::transaction(function () use ($repairRequest, $data, $request, $service) {
            $repair = RepairRequest::lockForUpdate()->findOrFail($repairRequest->id);
            if ($repair->status !== 'diagnosing') {
                throw ValidationException::withMessages(['estimate' => 'Estimates are submitted during diagnosis.']);
            }
            $repair->estimates()->where('status', 'pending')->update(['status' => 'superseded']);
            $repair->estimates()->create($data);
            $service->transition($repair, $request->user(), 'awaiting_customer_approval', 'Estimate submitted for customer approval.');
        });

        return $this->show($repairRequest->fresh());
    }

    public function approve(Request $request, RepairRequest $repairRequest, RepairService $service)
    {
        Gate::authorize('update', $repairRequest);
        abort_unless($request->user()->id === $repairRequest->customer_id, 403);
        DB::transaction(function () use ($repairRequest, $request, $service) {
            $repair = RepairRequest::lockForUpdate()->findOrFail($repairRequest->id);
            $estimate = $repair->estimates()->first();
            if ($repair->status !== 'awaiting_customer_approval' || ! $estimate || $estimate->status !== 'pending') {
                throw ValidationException::withMessages(['estimate' => 'There is no pending estimate to approve.']);
            }
            $estimate->update(['status' => 'approved']);
            $service->transition($repair, $request->user(), 'in_repair', 'Customer approved the current estimate.');
        });

        return $this->show($repairRequest->fresh());
    }

    public function attach(Request $request, RepairRequest $repairRequest)
    {
        Gate::authorize('update', $repairRequest);
        $request->validate(['file' => ['required', 'file', 'mimes:jpg,jpeg,png,webp,pdf', 'max:5120']]);
        $file = $request->file('file');
        $path = $file->store('repairs', 'local');
        try {
            $attachment = $repairRequest->attachments()->create(['uploaded_by' => $request->user()->id, 'path' => $path, 'original_name' => basename($file->getClientOriginalName()), 'mime' => $file->getMimeType(), 'size' => $file->getSize()]);
        } catch (\Throwable $e) {
            Storage::disk('local')->delete($path);
            throw $e;
        }

        return response()->json(['data' => $attachment], 201);
    }

    public function download(RepairAttachment $attachment)
    {
        Gate::authorize('view', $attachment->repair);

        return Storage::disk('local')->download($attachment->path, $attachment->original_name, ['X-Content-Type-Options' => 'nosniff']);
    }

    public function note(Request $request, RepairRequest $repairRequest, RepairService $service)
    {
        Gate::authorize('update', $repairRequest);
        abort_unless($request->user()->role === 'technician', 403);
        $data = $request->validate(['remarks' => ['required', 'string', 'max:2000']]);
        $service->note($repairRequest, $request->user(), $data['remarks']);

        return $this->show($repairRequest->fresh());
    }
}
