<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\RepairRequest;
use App\Models\Review;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ReviewController extends Controller
{
    public function store(Request $request)
    {
        abort_unless($request->user()->role === 'customer', 403);
        $data = $request->validate(['repair_request_id' => ['required', 'integer'], 'rating' => ['required', 'integer', 'between:1,5'], 'body' => ['required', 'string', 'min:10', 'max:2000']]);
        $review = DB::transaction(function () use ($request, $data) {
            $repair = RepairRequest::lockForUpdate()->findOrFail($data['repair_request_id']);
            abort_unless($repair->customer_id === $request->user()->id, 403);
            if ($repair->status !== 'completed' || $repair->review()->exists()) {
                throw ValidationException::withMessages(['review' => 'Review a completed repair only once.']);
            }

            return Review::create([...$data, 'customer_id' => $request->user()->id, 'technician_profile_id' => $repair->technician_profile_id]);
        });

        return response()->json(['data' => $review], 201);
    }
}
