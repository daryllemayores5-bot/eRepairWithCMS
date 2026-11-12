<?php

namespace App\Services;

use App\Models\ActivityLog;
use App\Models\SubscriptionDemoPayment;
use App\Models\SubscriptionPlan;
use App\Models\TechnicianProfile;
use App\Models\TechnicianSubscription;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class SubscriptionService
{
    public function request(User $user, string $key, string $expectedPrice, bool $seeded = false): SubscriptionDemoPayment
    {
        return $this->requestPlan($user, $key, $expectedPrice, 'pro', $seeded);
    }

    public function requestPlan(User $user, string $key, string $expectedPrice, string $planSlug, bool $seeded = false): SubscriptionDemoPayment
    {
        Gate::forUser($user)->authorize('request', TechnicianSubscription::class);
        if (! in_array($planSlug, ['pro', 'premium'], true)) {
            throw ValidationException::withMessages(['plan_slug' => 'Select an available Pro or Premium plan.']);
        }

        return DB::transaction(function () use ($user, $key, $expectedPrice, $planSlug, $seeded) {
            // All lifecycle operations lock the profile first, preventing overlapping
            // checkouts/approvals/renewals even when different payment IDs are used.
            $profile = $user->technicianProfile()->lockForUpdate()->firstOrFail();
            if ($existing = $profile->demoPayments()->where('idempotency_key', $key)->first()) {
                if ($existing->subscription->plan->slug !== $planSlug) {
                    throw ValidationException::withMessages(['idempotency_key' => 'This request reference belongs to a different plan.']);
                }

                return $existing;
            }
            $this->reconcile($profile, CarbonImmutable::now());
            if ($profile->demoPayments()->where('status', 'pending')->exists()) {
                throw ValidationException::withMessages(['subscription' => 'A subscription request is already pending administrator approval.']);
            }
            if ($profile->subscriptions()->where('status', 'scheduled')->exists()) {
                throw ValidationException::withMessages(['subscription' => 'A paid plan change is already scheduled. Wait until it starts before requesting another cycle.']);
            }
            $plan = SubscriptionPlan::where('slug', $planSlug)->where('is_active', true)->lockForUpdate()->firstOrFail();
            if ($plan->price !== $expectedPrice) {
                throw ValidationException::withMessages(['expected_price' => 'The plan price changed. Refresh and confirm the updated subscription price.']);
            }
            $subscription = $profile->subscriptions()->create(['subscription_plan_id' => $plan->id, 'status' => 'pending']);
            $payment = $profile->demoPayments()->create([
                'technician_subscription_id' => $subscription->id, 'amount' => $plan->price,
                'duration_days' => $plan->duration_days, 'plan_name' => $plan->name,
                'transaction_reference' => 'DEMO-'.Str::upper((string) Str::ulid()),
                'idempotency_key' => $key, 'payment_method' => 'demo_simulation',
                'is_demo' => true, 'is_seeded_demo' => $seeded, 'status' => 'pending',
            ]);
            ActivityLog::create(['actor_id' => $user->id, 'action' => 'subscription.demo_requested', 'subject_type' => 'subscription_demo_payment', 'subject_id' => $payment->id]);

            return $payment;
        }, 3);
    }

    public function decide(User $admin, SubscriptionDemoPayment $payment, string $decision): SubscriptionDemoPayment
    {
        Gate::forUser($admin)->authorize('administer', TechnicianSubscription::class);
        abort_unless(in_array($decision, ['approved', 'rejected'], true), 422);

        return DB::transaction(function () use ($admin, $payment, $decision) {
            $profile = TechnicianProfile::lockForUpdate()->findOrFail($payment->technician_id);
            $payment = SubscriptionDemoPayment::lockForUpdate()->findOrFail($payment->id);
            // Repeated or conflicting decisions cannot change a terminal payment.
            if ($payment->status !== 'pending') {
                return $payment;
            }
            $subscription = $payment->subscription()->lockForUpdate()->firstOrFail();
            if ($decision === 'rejected') {
                $subscription->update(['status' => 'cancelled']);
                $payment->update(['status' => 'rejected', 'rejected_at' => now()]);
            } else {
                $now = CarbonImmutable::now();
                $this->reconcile($profile, $now);
                $current = $profile->subscriptions()->validPaid()->with('plan')->lockForUpdate()->first();
                if ($profile->subscriptions()->where('status', 'scheduled')->exists()) {
                    throw ValidationException::withMessages(['subscription' => 'Another paid plan change is already scheduled.']);
                }
                $payment->update(['status' => 'approved', 'approved_by' => $admin->id, 'approved_at' => $now]);
                if ($current && $current->subscription_plan_id !== $subscription->subscription_plan_id) {
                    $subscription->update(['status' => 'scheduled', 'starts_at' => $current->expires_at, 'expires_at' => $current->expires_at->addDays($payment->duration_days)]);
                    ActivityLog::create(['actor_id' => $admin->id, 'action' => 'subscription.demo_approved', 'subject_type' => 'subscription_demo_payment', 'subject_id' => $payment->id]);

                    return $payment->fresh();
                }
                $base = $current && $current->expires_at->greaterThan($now) ? $current->expires_at : $now;
                // Keep every purchased cycle. A renewed cycle replaces the active
                // entitlement while its end includes all remaining approved time.
                $profile->subscriptions()->where('status', 'active')->get()->each(fn ($old) => $old->update([
                    'status' => $old->expires_at->lessThanOrEqualTo($now) ? 'expired' : 'cancelled',
                ]));
                $subscription->update(['status' => 'active', 'starts_at' => $now, 'expires_at' => $base->addDays($payment->duration_days)]);
            }
            ActivityLog::create(['actor_id' => $admin->id, 'action' => 'subscription.demo_'.$decision, 'subject_type' => 'subscription_demo_payment', 'subject_id' => $payment->id]);

            return $payment->fresh();
        }, 3);
    }

    public function expire(): int
    {
        $expired = 0;
        TechnicianProfile::whereHas('subscriptions', fn ($q) => $q->where(fn ($q) => $q->where('status', 'active')->where('expires_at', '<=', now()))
            ->orWhere(fn ($q) => $q->where('status', 'scheduled')->where('starts_at', '<=', now())))
            ->chunkById(100, function ($profiles) use (&$expired) {
                foreach ($profiles as $profile) {
                    $expired += DB::transaction(function () use ($profile) {
                        $locked = TechnicianProfile::lockForUpdate()->findOrFail($profile->id);

                        return $this->reconcile($locked, CarbonImmutable::now());
                    }, 3);
                }
            });

        return $expired;
    }

    private function reconcile(TechnicianProfile $profile, CarbonImmutable $now): int
    {
        $expired = $profile->subscriptions()->whereIn('status', ['active', 'scheduled'])->where('expires_at', '<=', $now)->update(['status' => 'expired']);
        $due = $profile->subscriptions()->where('status', 'scheduled')->validPaid()->lockForUpdate()->first();
        if ($due) {
            $due->update(['status' => 'active']);
        }

        return $expired;
    }
}
