<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class InventoryMovement extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['quantity' => 'integer', 'unit_cost' => 'decimal:2'];
    }

    public function part()
    {
        return $this->belongsTo(InventoryPart::class, 'inventory_part_id');
    }

    public function repair()
    {
        return $this->belongsTo(RepairRequest::class, 'repair_request_id');
    }
}
