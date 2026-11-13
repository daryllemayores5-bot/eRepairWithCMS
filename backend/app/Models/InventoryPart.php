<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class InventoryPart extends Model
{
    use HasFactory;

    protected $guarded = ['id', 'technician_id', 'quantity'];

    protected function casts(): array
    {
        return ['quantity' => 'integer', 'reorder_level' => 'integer', 'unit_cost' => 'decimal:2', 'active' => 'boolean'];
    }

    public function technician()
    {
        return $this->belongsTo(TechnicianProfile::class, 'technician_id');
    }

    public function movements()
    {
        return $this->hasMany(InventoryMovement::class);
    }
}
