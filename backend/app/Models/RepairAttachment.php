<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RepairAttachment extends Model
{
    protected $guarded = ['id'];

    protected $hidden = ['path'];

    public function repair()
    {
        return $this->belongsTo(RepairRequest::class, 'repair_request_id');
    }
}
