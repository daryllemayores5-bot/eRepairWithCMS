<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SubscriptionDemoPayment extends Model
{
    protected $guarded = ['id'];

    protected $appends = ['display_reference'];

    public function getDisplayReferenceAttribute(): string
    {
        return 'SUB-'.str_pad((string) $this->id, 6, '0', STR_PAD_LEFT);
    }

    protected function casts(): array
    {
        return ['amount' => 'decimal:2', 'is_demo' => 'boolean', 'is_seeded_demo' => 'boolean', 'duration_days' => 'integer', 'approved_at' => 'immutable_datetime', 'rejected_at' => 'immutable_datetime'];
    }

    public function technician()
    {
        return $this->belongsTo(TechnicianProfile::class, 'technician_id');
    }

    public function subscription()
    {
        return $this->belongsTo(TechnicianSubscription::class, 'technician_subscription_id');
    }

    public function approver()
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function scopeRevenue($query)
    {
        return $query->where('status', 'approved')->where('is_demo', true);
    }
}
