<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\RepairResource;
use App\Models\Appointment;
use App\Models\RepairRequest;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function index(Request $request)
    {
        $query = RepairRequest::forUser($request->user());
        $counts = (clone $query)->selectRaw('status, count(*) as total')->groupBy('status')->pluck('total', 'status');
        // Read-only presentation aggregate; use the same ownership scope as the cards.
        $start = now('Asia/Manila')->toImmutable()->startOfDay()->subDays(13);
        $daily = (clone $query)->where('created_at', '>=', $start->utc())
            ->where('created_at', '<', $start->addDays(14)->utc())
            ->selectRaw("(created_at AT TIME ZONE 'Asia/Manila')::date as day, count(*) as total")
            ->groupByRaw('1')->pluck('total', 'day');
        $activity = collect(range(0, 13))->map(fn ($offset) => [
            'date' => $start->addDays($offset)->toDateString(),
            'count' => (int) ($daily[$start->addDays($offset)->toDateString()] ?? 0),
        ]);

        return response()->json(['data' => [
            'total' => (int) $counts->sum(), 'counts' => $counts,
            'activity' => $activity,
            'recent' => RepairResource::collection((clone $query)->with(['customer', 'technician.user', 'deviceType', 'deviceBrand', 'category'])->latest()->limit(5)->get()),
            'appointments' => Appointment::whereHas('repair', fn ($q) => $q->forUser($request->user()))->whereIn('status', ['proposed', 'confirmed'])->where('starts_at', '>=', now())->orderBy('starts_at')->limit(5)->get(),
            'average_rating' => $request->user()->role === 'technician' ? round((float) $request->user()->technicianProfile?->reviews()->avg('rating'), 1) : null,
        ]]);
    }

    public function notifications(Request $request)
    {
        return response()->json($request->user()->notifications()->latest()->paginate(20));
    }

    public function read(Request $request, string $notification)
    {
        $request->user()->notifications()->findOrFail($notification)->markAsRead();

        return response()->noContent();
    }
}
