<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SubscriptionPlan extends Model
{
    protected $guarded = ['id', 'slug'];

    protected function casts(): array
    {
        return ['price' => 'decimal:2', 'features' => 'array', 'is_active' => 'boolean', 'duration_days' => 'integer'];
    }

    public function subscriptions()
    {
        return $this->hasMany(TechnicianSubscription::class);
    }
}
