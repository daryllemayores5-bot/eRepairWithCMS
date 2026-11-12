<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TechnicianProfile extends Model
{
    use HasFactory;

    protected $guarded = ['id', 'verification_status', 'verified_by', 'verified_at'];

    protected function casts(): array
    {
        return ['years_experience' => 'integer', 'verified_at' => 'datetime'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function specializations(): BelongsToMany
    {
        return $this->belongsToMany(Specialization::class, 'technician_specializations');
    }

    public function deviceTypes(): BelongsToMany
    {
        return $this->belongsToMany(DeviceType::class, 'technician_device_types');
    }

    public function serviceAreas(): HasMany
    {
        return $this->hasMany(TechnicianServiceArea::class);
    }

    public function repairs(): HasMany
    {
        return $this->hasMany(RepairRequest::class);
    }

    public function reviews(): HasMany
    {
        return $this->hasMany(Review::class)->where('hidden', false);
    }

    public function subscriptions(): HasMany
    {
        return $this->hasMany(TechnicianSubscription::class, 'technician_id');
    }

    public function demoPayments(): HasMany
    {
        return $this->hasMany(SubscriptionDemoPayment::class, 'technician_id');
    }

    /** Legacy Pro-benefits check: Premium includes all Pro access. */
    public function hasProSubscription(): bool
    {
        // Query at request time; an overdue scheduler must never prolong access.
        return $this->subscriptions()->validPaid()->exists();
    }

    public function hasPremiumSubscription(): bool
    {
        return $this->subscriptions()->validPremium()->exists();
    }

    public function scopeVisible($query)
    {
        return $query->where('verification_status', 'approved')->whereHas('user', fn ($q) => $q->where('account_status', 'active')->where('role', 'technician'));
    }

    public function publicCoordinates(): ?array
    {
        if ($this->latitude === null || $this->longitude === null) {
            return null;
        }
        $latitude = (float) $this->latitude;
        $longitude = (float) $this->longitude;
        if (! is_finite($latitude) || ! is_finite($longitude) || $latitude < -90 || $latitude > 90 || $longitude < -180 || $longitude > 180) {
            return null;
        }

        return [round($latitude, 2), round($longitude, 2)];
    }
}
