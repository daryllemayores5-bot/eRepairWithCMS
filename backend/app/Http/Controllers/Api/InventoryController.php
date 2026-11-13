<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\InventoryMovement;
use App\Models\InventoryPart;
use App\Services\InventoryService;
use App\Services\WorkshopAccess;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class InventoryController extends Controller
{
    public function __construct(private WorkshopAccess $access) {}

    public function index(Request $request)
    {
        $profile = $this->access->requirePlan($request->user(), 'premium');
        $filters = $request->validate(['page' => ['sometimes', 'integer', 'min:1', 'max:100000'], 'search' => ['nullable', 'string', 'max:100'], 'low_stock' => ['sometimes', 'boolean']]);
        $parts = InventoryPart::where('technician_id', $profile->id);
        $low = (clone $parts)->where('active', true)->whereColumn('quantity', '<=', 'reorder_level')->count();
        if ($search = $filters['search'] ?? null) {
            $parts->where(fn ($q) => $q->where('name', 'ilike', '%'.$search.'%')->orWhere('sku', 'ilike', '%'.$search.'%'));
        }
        if ($request->boolean('low_stock')) {
            $parts->where('active', true)->whereColumn('quantity', '<=', 'reorder_level');
        }

        return response()->json(['data' => $parts->orderBy('name')->orderBy('id')->paginate(20), 'low_stock_count' => $low]);
    }

    public function store(Request $request, InventoryService $service)
    {
        $this->access->requirePlan($request->user(), 'premium');
        $data = $request->validate([
            'sku' => ['required', 'string', 'max:64', 'regex:/^[A-Za-z0-9._-]+$/D'],
            'name' => ['required', 'string', 'max:120'], 'quantity' => ['required', 'integer', 'min:0', 'max:1000000'],
            'reorder_level' => ['required', 'integer', 'min:0', 'max:1000000'],
            'unit_cost' => ['required', 'numeric', 'decimal:0,2', 'min:0', 'max:9999999999.99'],
            'idempotency_key' => ['required', 'uuid'],
        ]);

        return response()->json(['data' => $service->create($request->user(), $data)], 201);
    }

    public function update(Request $request, int $part)
    {
        $profile = $this->access->requirePlan($request->user(), 'premium');
        $record = InventoryPart::where('technician_id', $profile->id)->findOrFail($part);
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'reorder_level' => ['required', 'integer', 'min:0', 'max:1000000'], 'active' => ['required', 'boolean'],
            'quantity' => ['prohibited'], 'technician_id' => ['prohibited'], 'unit_cost' => ['prohibited'],
        ]);
        $record->update($data);

        return response()->json(['data' => $record->fresh()]);
    }

    public function move(Request $request, int $part, InventoryService $service)
    {
        $this->access->requirePlan($request->user(), 'premium');
        $data = $request->validate([
            'kind' => ['required', Rule::in(['restock', 'usage'])],
            'quantity' => ['required', 'integer', 'min:1', 'max:1000000'],
            'repair_request_id' => ['required_if:kind,usage', 'prohibited_unless:kind,usage', 'integer', 'min:1'],
            'unit_cost' => ['required_if:kind,restock', 'prohibited_unless:kind,restock', 'numeric', 'decimal:0,2', 'min:0', 'max:9999999999.99'],
            'note' => ['nullable', 'string', 'max:500'], 'idempotency_key' => ['required', 'uuid'],
        ]);

        return response()->json(['data' => $service->move($request->user(), $part, $data)]);
    }

    public function movements(Request $request)
    {
        $profile = $this->access->requirePlan($request->user(), 'premium');
        $request->validate(['page' => ['sometimes', 'integer', 'min:1', 'max:100000']]);

        return response()->json(['data' => InventoryMovement::where('technician_id', $profile->id)
            ->with(['part:id,name,sku', 'repair:id,reference'])->latest('id')->paginate(20)]);
    }
}
