<?php

namespace App\Services;

use App\Models\SubscriptionDemoPayment;
use App\Models\SubscriptionPlan;
use App\Models\TechnicianProfile;
use App\Models\TechnicianSubscription;
use App\Support\SubscriptionMoney as Money;
use Carbon\CarbonImmutable;

class SubscriptionRevenue
{
    public function summary(): array
    {
        $local = CarbonImmutable::now('Asia/Manila');
        $month = $local->startOfMonth()->utc();
        $year = $local->startOfYear()->utc();
        $eligible = TechnicianProfile::whereHas('user', fn ($q) => $q->where('role', 'technician')->where('account_status', 'active'));
        $pro = (clone $eligible)->whereHas('subscriptions', fn ($q) => $q->validPro())->count();
        $premium = (clone $eligible)->whereHas('subscriptions', fn ($q) => $q->validPremium())->count();
        $sum = fn ($query) => Money::decimal(Money::cents((string) $query->sum('amount')));
        $months = [];
        for ($i = 11; $i >= 0; $i--) {
            $start = $local->startOfMonth()->subMonths($i);
            $months[] = ['month' => $start->format('Y-m'), 'amount' => $sum(SubscriptionDemoPayment::revenue()->where('approved_at', '>=', $start->utc())->where('approved_at', '<', $start->addMonth()->utc()))];
        }

        return [
            'total' => $sum(SubscriptionDemoPayment::revenue()),
            'month' => $sum(SubscriptionDemoPayment::revenue()->where('approved_at', '>=', $month)->where('approved_at', '<', $local->startOfMonth()->addMonth()->utc())),
            'year' => $sum(SubscriptionDemoPayment::revenue()->where('approved_at', '>=', $year)->where('approved_at', '<', $local->startOfYear()->addYear()->utc())),
            'pro' => $pro, 'premium' => $premium, 'free' => $eligible->count() - $pro - $premium,
            'scheduled' => TechnicianSubscription::where('status', 'scheduled')->where('starts_at', '>', now())->count(),
            'by_plan' => collect(['pro', 'premium'])->mapWithKeys(fn ($slug) => [$slug => $sum(SubscriptionDemoPayment::revenue()->whereHas('subscription.plan', fn ($q) => $q->where('slug', $slug)))])->all(),
            'pending' => SubscriptionDemoPayment::where('status', 'pending')->count(),
            'approved' => SubscriptionDemoPayment::revenue()->count(),
            'rejected' => SubscriptionDemoPayment::where('status', 'rejected')->count(),
            'expired' => TechnicianSubscription::where(fn ($q) => $q->where('status', 'expired')->orWhere(fn ($q) => $q->whereIn('status', ['active', 'scheduled'])->where('expires_at', '<=', now())))->count(),
            'months' => $months,
        ];
    }

    public function projection(int $technicians, string $planSlug = 'pro'): array
    {
        abort_unless($technicians >= 0 && $technicians <= 1000000, 422);
        abort_unless(in_array($planSlug, ['pro', 'premium'], true), 422);
        $plan = SubscriptionPlan::where('slug', $planSlug)->firstOrFail();
        $price = $plan->price;
        $monthly = Money::cents($price) * $technicians;

        return ['technicians' => $technicians, 'plan' => $plan->name, 'slug' => $planSlug, 'price' => $price, 'monthly' => Money::decimal($monthly), 'annual' => Money::decimal($monthly * 12)];
    }
}
