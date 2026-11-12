<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\DeviceBrand;
use App\Models\DeviceType;
use App\Models\RepairCategory;
use App\Models\Specialization;

class LookupController extends Controller
{
    public function index()
    {
        return response()->json(['data' => [
            'device_types' => DeviceType::where('active', true)->get(), 'device_brands' => DeviceBrand::where('active', true)->get(),
            'repair_categories' => RepairCategory::where('active', true)->get(), 'specializations' => Specialization::where('active', true)->get(),
        ]]);
    }

    public function devices()
    {
        return response()->json(['data' => DeviceType::where('active', true)->get()]);
    }

    public function specializations()
    {
        return response()->json(['data' => Specialization::where('active', true)->get()]);
    }
}
