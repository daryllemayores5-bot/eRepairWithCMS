<?php

namespace App\Http\Controllers\Cp;

use App\Http\Controllers\Controller;
use App\Http\Requests\UpdateSubscriptionPlan;
use App\Models\ActivityLog;
use App\Models\SubscriptionDemoPayment;
use App\Models\SubscriptionPlan;
use App\Models\TechnicianProfile;
use App\Models\TechnicianSubscription;
use App\Services\CmsAccount;
use App\Services\SubscriptionRevenue;
use App\Services\SubscriptionService;
use App\Support\SubscriptionMoney as Money;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class SubscriptionController extends Controller
{
    private function filters(Request $request): array
    {
        return $request->validate([
            'search' => ['nullable', 'string', 'max:100'], 'status' => ['nullable', 'in:pending,approved,rejected'],
            'subscription_status' => ['nullable', 'in:pending,active,scheduled,expired,cancelled'],
            'tier' => ['nullable', 'in:free,pro,premium'], 'page' => ['nullable', 'integer', 'min:1', 'max:100000'],
            'projection_plan' => ['nullable', 'in:pro,premium'],
            'subscriptions_page' => ['nullable', 'integer', 'min:1', 'max:100000'],
            'technicians_page' => ['nullable', 'integer', 'min:1', 'max:100000'],
            'paying_technicians' => ['nullable', 'integer', 'min:0', 'max:1000000'],
        ]);
    }

    private function payments(array $filters)
    {
        return SubscriptionDemoPayment::with(['technician.user', 'subscription.plan', 'approver'])
            ->when($filters['status'] ?? null, fn ($q, $status) => $q->where('status', $status))
            ->when($filters['search'] ?? null, function ($query, $search) {
                $query->where(function ($query) use ($search) {
                    $query->where('transaction_reference', 'ilike', '%'.$search.'%')
                        ->orWhereHas('technician.user', fn ($query) => $query->where('name', 'ilike', '%'.$search.'%'));
                    if (preg_match('/^SUB-([0-9]{1,18})$/iD', $search, $matches)) {
                        $query->orWhere('id', (int) $matches[1]);
                    }
                });
            })
            ->latest('id')->paginate(15)->withQueryString();
    }

    public function index(Request $request)
    {
        $filters = $this->filters($request);
        $technicians = TechnicianProfile::with('user')->withExists(['subscriptions as is_pro' => fn ($q) => $q->validPaid(), 'subscriptions as is_premium' => fn ($q) => $q->validPremium()])
            ->whereHas('user', fn ($q) => $q->where('role', 'technician')->where('name', 'ilike', '%'.($filters['search'] ?? '').'%'));
        if ($tier = $filters['tier'] ?? null) {
            if ($tier === 'free') {
                $technicians->whereDoesntHave('subscriptions', fn ($q) => $q->validPaid());
            } else {
                $technicians->whereHas('subscriptions', fn ($q) => $tier === 'premium' ? $q->validPremium() : $q->validPro());
            }
        }
        $subscriptions = TechnicianSubscription::with(['technician.user', 'plan', 'payment'])
            ->when($filters['search'] ?? null, fn ($q, $search) => $q->whereHas('technician.user', fn ($q) => $q->where('name', 'ilike', '%'.$search.'%')));
        if ($status = $filters['subscription_status'] ?? null) {
            if ($status === 'active') {
                $subscriptions->validPaid();
            } elseif ($status === 'scheduled') {
                $subscriptions->where('status', 'scheduled')->where('starts_at', '>', now());
            } elseif ($status === 'expired') {
                $subscriptions->where(fn ($q) => $q->where('status', 'expired')->orWhere(fn ($q) => $q->whereIn('status', ['active', 'scheduled'])->where('expires_at', '<=', now())));
            } else {
                $subscriptions->where('status', $status);
            }
        }

        return view('cp.subscriptions', [
            'payments' => $this->payments($filters), 'plans' => SubscriptionPlan::orderBy('price')->get(),
            'subscriptions' => $subscriptions->latest('id')->paginate(15, ['*'], 'subscriptions_page')->withQueryString(),
            'technicians' => $technicians->orderBy('id')->paginate(15, ['*'], 'technicians_page')->withQueryString(),
        ]);
    }

    public function decide(Request $request, SubscriptionDemoPayment $payment, SubscriptionService $service)
    {
        $data = $request->validate(['decision' => ['required', 'in:approved,rejected']]);
        $service->decide(CmsAccount::identity($request->user('cp'))->operator, $payment, $data['decision']);

        return back()->with('success', 'DEMO payment decision saved. Finalized transactions cannot be processed twice. No money was processed.');
    }

    public function plan(UpdateSubscriptionPlan $request, SubscriptionPlan $plan)
    {
        $data = $request->validated();
        if ($plan->slug === 'free' && (Money::cents($data['price']) !== 0 || ! $data['is_active'])) {
            throw ValidationException::withMessages(['price' => 'The Free plan must remain active at ₱0.']);
        }
        DB::transaction(function () use ($plan, $data, $request) {
            $plan->update([
                'name' => $data['name'], 'description' => $data['description'], 'price' => Money::decimal(Money::cents($data['price'])),
                'features' => array_values(array_filter(array_map('trim', preg_split('/\R/', $data['features'])))),
                'is_active' => (bool) $data['is_active'],
            ]);
            ActivityLog::create(['actor_id' => CmsAccount::identity($request->user('cp'))->user_id, 'action' => 'subscription.plan_updated', 'subject_type' => 'subscription_plan', 'subject_id' => $plan->id]);
        });

        return back()->with('success', 'Plan updated. Existing DEMO payment quotes and approved time are preserved.');
    }

    public function revenue(Request $request, SubscriptionRevenue $revenue)
    {
        $filters = $this->filters($request);

        return view('cp.revenue', [
            'summary' => $revenue->summary(), 'projection' => $revenue->projection((int) ($filters['paying_technicians'] ?? 10), $filters['projection_plan'] ?? 'pro'),
            'projection_plans' => SubscriptionPlan::whereIn('slug', ['pro', 'premium'])->orderBy('price')->get(),
            'payments' => $this->payments($filters),
        ]);
    }
}
