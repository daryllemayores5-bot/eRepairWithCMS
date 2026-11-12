<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RepairEstimate extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['amount' => 'decimal:2'];
    }

    public function repair()
    {
        return $this->belongsTo(RepairRequest::class, 'repair_request_id');
    }
}
