<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\RequestDemoSubscription;
use App\Models\SubscriptionPlan;
use App\Models\TechnicianProfile;
use App\Services\SubscriptionService;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class SubscriptionController extends Controller
{
    public function plans(): JsonResponse
    {
        return response()->json(['data' => SubscriptionPlan::where('is_active', true)
            ->orderBy('price')->orderBy('id')
            ->get(['id', 'slug', 'name', 'description', 'price', 'duration_days', 'features', 'is_active'])]);
    }

    public function index(Request $request)
    {
        $request->validate(['page' => ['nullable', 'integer', 'min:1', 'max:100000']]);
        $profile = $request->user()->technicianProfile()->firstOrFail();
        $current = $profile->subscriptions()->validPaid()->with('plan')->first();
        $queued = $profile->subscriptions()->where('status', 'scheduled')->where('starts_at', '>', now())->with('plan')->first();
        $lastPeriod = $profile->subscriptions()->whereNotNull('expires_at')->where('starts_at', '<=', now())->latest('id')->first();

        return response()->json(['data' => [
            'is_demo' => true, 'is_pro' => (bool) $current,
            'is_premium' => $current?->plan->slug === 'premium',
            'current_plan_slug' => $current?->plan->slug ?? 'free',
            'scheduled_subscription' => $queued,
            'current_plan' => $current?->plan->name ?? SubscriptionPlan::where('slug', 'free')->value('name'),
            'status' => $current ? 'active' : ($lastPeriod && $lastPeriod->expires_at->lessThanOrEqualTo(now()) ? 'expired' : 'free'),
            'starts_at' => $current?->starts_at ?? $lastPeriod?->starts_at, 'expires_at' => $current?->expires_at ?? $lastPeriod?->expires_at,
            'days_remaining' => $current ? (int) ceil(now()->diffInSeconds($current->expires_at, false) / 86400) : 0,
            'plans' => SubscriptionPlan::orderBy('price')->get(),
            'pending_payment' => $profile->demoPayments()->where('status', 'pending')->first(),
            'payments' => $profile->demoPayments()->with('subscription')->latest('id')->paginate(10),
        ]]);
    }

    public function store(RequestDemoSubscription $request, SubscriptionService $service)
    {
        $payment = $service->requestPlan($request->user(), $request->validated('idempotency_key'), $request->validated('expected_price'), $request->validated('plan_slug', 'pro'));

        return response()->json(['message' => $payment->status === 'pending' ? 'Subscription request submitted. Pending Admin Approval. You have not been charged.' : 'This subscription request was already processed.', 'data' => $payment]);
    }

    public function analytics(Request $request)
    {
        $filters = $request->validate(['range_days' => ['sometimes', 'integer', 'in:7,30,90']]);
        $profile = $request->user()->technicianProfile()->firstOrFail();
        abort_unless($profile->hasProSubscription(), 403, 'An active Pro subscription is required for advanced analytics.');
        $premium = $profile->hasPremiumSubscription();
        abort_if(isset($filters['range_days']) && ! $premium, 403, 'Premium is required for date-range analytics.');
        $months = [];
        $local = CarbonImmutable::now('Asia/Manila')->startOfMonth();
        for ($i = 5; $i >= 0; $i--) {
            $start = $local->subMonths($i);
            $months[] = [
                'month' => $start->format('Y-m'),
                'views' => DB::table('technician_profile_views')->where('technician_id', $profile->id)->where('viewed_on', '>=', $start->toDateString())->where('viewed_on', '<', $start->addMonth()->toDateString())->count(),
                'inquiries' => $profile->repairs()->where('created_at', '>=', $start->utc())->where('created_at', '<', $start->addMonth()->utc())->count(),
            ];
        }

        $data = [
            'total_profile_views' => DB::table('technician_profile_views')->where('technician_id', $profile->id)->count(),
            'total_customer_inquiries' => $profile->repairs()->count(), 'months' => $months,
            'recent_activity' => DB::table('technician_profile_views')->where('technician_id', $profile->id)->select('viewed_on')->selectRaw('count(*) as views')->groupBy('viewed_on')->orderByDesc('viewed_on')->limit(14)->get(),
        ];
        if ($premium) {
            $days = (int) ($filters['range_days'] ?? 30);
            $end = CarbonImmutable::now('Asia/Manila')->startOfDay()->addDay();
            $start = $end->subDays($days);
            $previous = $start->subDays($days);
            $views = DB::table('technician_profile_views')->where('technician_id', $profile->id)
                ->where('viewed_on', '>=', $previous->toDateString())->where('viewed_on', '<', $end->toDateString())
                ->select('viewed_on')->selectRaw('count(*) as views')->groupBy('viewed_on')->pluck('views', 'viewed_on');
            $inquiries = $profile->repairs()->where('created_at', '>=', $previous->utc())->where('created_at', '<', $end->utc())
                ->selectRaw("DATE(created_at AT TIME ZONE 'Asia/Manila') as day, count(*) as inquiries")
                ->groupByRaw("DATE(created_at AT TIME ZONE 'Asia/Manila')")->pluck('inquiries', 'day');
            $daily = [];
            $prior = ['views' => 0, 'inquiries' => 0];
            for ($day = $previous; $day->lessThan($end); $day = $day->addDay()) {
                $date = $day->toDateString();
                $point = ['date' => $date, 'views' => (int) ($views[$date] ?? 0), 'inquiries' => (int) ($inquiries[$date] ?? 0)];
                if ($day->lessThan($start)) {
                    $prior['views'] += $point['views'];
                    $prior['inquiries'] += $point['inquiries'];
                } else {
                    $daily[] = $point;
                }
            }
            $data['premium'] = ['range_days' => $days, 'from' => $start->toDateString(), 'through' => $end->subDay()->toDateString(),
                'views' => array_sum(array_column($daily, 'views')), 'inquiries' => array_sum(array_column($daily, 'inquiries')),
                'previous_period' => $prior, 'daily' => $daily];
        }

        return response()->json(['data' => $data]);
    }

    public function view(Request $request, int $technician)
    {
        $profile = TechnicianProfile::visible()->findOrFail($technician);
        if ($request->user()?->id === $profile->user_id) {
            return response()->noContent();
        }
        $day = now('Asia/Manila')->toDateString();
        // Daily rotating HMAC: no raw IP, user ID, email or cross-day visitor ID.
        // Counts are approximate daily visits, not verified unique people.
        $hash = hash_hmac('sha256', $technician.'|'.$day.'|'.$request->ip(), (string) config('app.key'));
        DB::table('technician_profile_views')->insertOrIgnore(['technician_id' => $technician, 'viewed_on' => $day, 'visitor_hash' => $hash]);

        return response()->noContent();
    }
}
