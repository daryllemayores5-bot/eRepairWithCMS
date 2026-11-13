<?php

namespace App\Services;

use App\Models\ActivityLog;
use App\Models\InventoryMovement;
use App\Models\InventoryPart;
use App\Models\RepairRequest;
use App\Models\User;
use App\Support\SubscriptionMoney;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class InventoryService
{
    public function __construct(private WorkshopAccess $access) {}

    public function create(User $user, array $data): InventoryPart
    {
        return DB::transaction(function () use ($user, $data) {
            $profile = $this->access->requirePlan($user, 'premium');
            $profile->newQuery()->whereKey($profile->id)->lockForUpdate()->firstOrFail();
            if (InventoryPart::where('technician_id', $profile->id)->where('sku', $data['sku'])->exists()) {
                throw ValidationException::withMessages(['sku' => 'This SKU is already in your inventory.']);
            }
            if (InventoryMovement::where('technician_id', $profile->id)->where('idempotency_key', $data['idempotency_key'])->exists()) {
                throw ValidationException::withMessages(['idempotency_key' => 'This request key already belongs to a stock movement.']);
            }
            $part = new InventoryPart;
            $part->fill(collect($data)->only(['sku', 'name', 'unit_cost', 'reorder_level'])->all());
            $part->technician_id = $profile->id;
            $part->quantity = $data['quantity'];
            $part->save();
            if ($part->quantity > 0) {
                $part->movements()->create(['technician_id' => $profile->id, 'actor_id' => $user->id, 'kind' => 'opening', 'quantity' => $part->quantity, 'unit_cost' => $part->unit_cost, 'idempotency_key' => $data['idempotency_key'], 'note' => 'Opening stock']);
            }
            ActivityLog::create(['actor_id' => $user->id, 'action' => 'inventory.part_created', 'subject_type' => 'inventory_part', 'subject_id' => $part->id]);

            return $part;
        }, 3);
    }

    public function move(User $user, int $partId, array $data): array
    {
        return DB::transaction(function () use ($user, $partId, $data) {
            $profile = $this->access->requirePlan($user, 'premium');
            // Serialize this technician's mutations, including repeated keys for different parts.
            $profile->newQuery()->whereKey($profile->id)->lockForUpdate()->firstOrFail();
            $part = InventoryPart::where('technician_id', $profile->id)->lockForUpdate()->findOrFail($partId);
            $repairId = $data['kind'] === 'usage' ? (int) $data['repair_request_id'] : null;
            $existing = InventoryMovement::where('technician_id', $profile->id)->where('idempotency_key', $data['idempotency_key'])->first();
            if ($existing) {
                $matches = $existing->inventory_part_id === $part->id && $existing->kind === $data['kind']
                    && $existing->quantity === (int) $data['quantity'] && $existing->repair_request_id === $repairId
                    && ($existing->note ?? '') === ($data['note'] ?? '');
                if ($data['kind'] === 'restock') {
                    $matches = $matches && SubscriptionMoney::cents($existing->unit_cost) === SubscriptionMoney::cents((string) $data['unit_cost']);
                }
                if (! $matches) {
                    throw ValidationException::withMessages(['idempotency_key' => 'This request key already belongs to a different stock movement.']);
                }

                return ['part' => $part, 'movement' => $existing, 'replayed' => true];
            }
            if (! $part->active) {
                throw ValidationException::withMessages(['part' => 'Reactivate this part before recording stock.']);
            }
            if ($repairId) {
                $repair = RepairRequest::where('technician_profile_id', $profile->id)->lockForUpdate()->findOrFail($repairId);
                if ($repair->status !== 'in_repair') {
                    throw ValidationException::withMessages(['repair_request_id' => 'Parts can be used only on your repairs currently in progress.']);
                }
            }
            $quantity = (int) $data['quantity'];
            if ($data['kind'] === 'usage' && $part->quantity < $quantity) {
                throw ValidationException::withMessages(['quantity' => 'Insufficient stock. No parts were deducted.']);
            }
            if ($data['kind'] === 'restock' && $part->quantity + $quantity > 1000000) {
                throw ValidationException::withMessages(['quantity' => 'Stock cannot exceed 1,000,000 units.']);
            }
            $cost = $data['kind'] === 'restock' ? SubscriptionMoney::decimal(SubscriptionMoney::cents((string) $data['unit_cost'])) : $part->unit_cost;
            $part->quantity += $data['kind'] === 'usage' ? -$quantity : $quantity;
            if ($data['kind'] === 'restock') {
                $part->unit_cost = $cost;
            }
            $part->save();
            $movement = $part->movements()->create([
                'technician_id' => $profile->id, 'actor_id' => $user->id, 'repair_request_id' => $repairId,
                'kind' => $data['kind'], 'quantity' => $quantity, 'unit_cost' => $cost,
                'note' => $data['note'] ?? null, 'idempotency_key' => $data['idempotency_key'],
            ]);
            ActivityLog::create(['actor_id' => $user->id, 'action' => 'inventory.'.$data['kind'], 'subject_type' => 'inventory_movement', 'subject_id' => $movement->id]);

            return ['part' => $part->fresh(), 'movement' => $movement, 'replayed' => false];
        }, 3);
    }
}
