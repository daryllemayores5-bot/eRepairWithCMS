<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RepairRequest extends Model
{
    protected $guarded = ['id', 'status', 'reference', 'customer_id', 'completed_at'];

    protected function casts(): array
    {
        return ['completed_at' => 'datetime', 'serial_number' => 'encrypted'];
    }

    public function customer()
    {
        return $this->belongsTo(User::class, 'customer_id');
    }

    public function technician()
    {
        return $this->belongsTo(TechnicianProfile::class, 'technician_profile_id');
    }

    public function deviceType()
    {
        return $this->belongsTo(DeviceType::class);
    }

    public function deviceBrand()
    {
        return $this->belongsTo(DeviceBrand::class);
    }

    public function category()
    {
        return $this->belongsTo(RepairCategory::class, 'repair_category_id');
    }

    public function attachments()
    {
        return $this->hasMany(RepairAttachment::class);
    }

    public function history()
    {
        return $this->hasMany(RepairStatusHistory::class)->oldest('id');
    }

    public function estimates()
    {
        return $this->hasMany(RepairEstimate::class)->latest('id');
    }

    public function appointments()
    {
        return $this->hasMany(Appointment::class)->latest('starts_at');
    }

    public function review()
    {
        return $this->hasOne(Review::class);
    }

    public function scopeForUser($query, User $user)
    {
        return $user->role === 'technician'
            ? $query->whereHas('technician', fn ($q) => $q->where('user_id', $user->id))
            : $query->where('customer_id', $user->id);
    }
}
