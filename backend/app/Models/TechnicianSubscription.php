<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TechnicianSubscription extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['starts_at' => 'immutable_datetime', 'expires_at' => 'immutable_datetime'];
    }

    public function technician()
    {
        return $this->belongsTo(TechnicianProfile::class, 'technician_id');
    }

    public function plan()
    {
        return $this->belongsTo(SubscriptionPlan::class, 'subscription_plan_id');
    }

    public function payment()
    {
        return $this->hasOne(SubscriptionDemoPayment::class);
    }

    public function scopeValidPro($query)
    {
        return $query->validPaid()->whereHas('plan', fn ($q) => $q->where('slug', 'pro'));
    }

    public function scopeValidPremium($query)
    {
        return $query->validPaid()->whereHas('plan', fn ($q) => $q->where('slug', 'premium'));
    }

    public function scopeValidPaid($query)
    {
        // Scheduled periods grant access at their exact start even if cron is late.
        return $query->whereIn('status', ['active', 'scheduled'])->where('starts_at', '<=', now())->where('expires_at', '>', now())
            ->whereHas('plan', fn ($q) => $q->whereIn('slug', ['pro', 'premium']))
            ->whereHas('payment', fn ($q) => $q->where('status', 'approved')->where('is_demo', true));
    }
}
