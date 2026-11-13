<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class NoteTemplate extends Model
{
    use HasFactory;

    protected $guarded = ['id', 'technician_id'];

    public function technician()
    {
        return $this->belongsTo(TechnicianProfile::class, 'technician_id');
    }
}
