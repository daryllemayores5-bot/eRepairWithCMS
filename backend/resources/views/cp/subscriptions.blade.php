@extends('statamic::layout')
@section('title', 'Subscription Management')
@section('content')
<div class="erepair-ops" v-pre>
<h1>Subscription Management</h1>
@include('cp.subscription-nav')
<p>Customers and Free technicians keep all existing discovery, inquiry and repair functionality. Pro and Premium badges identify subscriptions, never verification or qualifications.</p>
<h2>Review DEMO payments</h2>
@include('cp.subscription-payments', ['manage'=>true])
<h2>Technician plans</h2>
<form method="get"><label>Technician name <input name="search" maxlength="100" value="{{ request('search') }}"></label><label>Current tier <select name="tier"><option value="">All technicians</option><option value="free" @selected(request('tier')==='free')>Free</option><option value="pro" @selected(request('tier')==='pro')>Active Pro</option><option value="premium" @selected(request('tier')==='premium')>Active Premium</option></select></label><button>Filter technicians</button></form>
<div class="subscription-table"><table><thead><tr><th>Technician</th><th>Current tier</th><th>Verification</th><th>Account</th></tr></thead><tbody>
@forelse($technicians as $technician)<tr><td>{{ $technician->user->name }}</td><td>{{ $technician->is_premium ? 'Premium — active subscription' : ($technician->is_pro ? 'Pro — active subscription' : 'Free') }}</td><td>{{ ucfirst($technician->verification_status) }}</td><td>{{ ucfirst($technician->user->account_status) }}</td></tr>@empty<tr><td colspan="4">No technicians match.</td></tr>@endforelse
</tbody></table></div>{{ $technicians->links() }}
<h2>Subscription transaction history</h2>
<p>Each request creates a historical cycle. Same-plan renewals carry remaining time forward. A paid plan change starts after the current paid period ends. Scheduled plans grant no early benefits; approved DEMO amounts count once at approval. Rejected requests are cancelled without benefits.</p>
<form method="get"><label>Technician name <input name="search" value="{{ request('search') }}" maxlength="100"></label><label>Subscription status <select name="subscription_status"><option value="">All</option>@foreach(['pending','active','scheduled','expired','cancelled'] as $status)<option value="{{ $status }}" @selected(request('subscription_status')===$status)>{{ ucfirst($status) }}</option>@endforeach</select></label><button>Filter subscriptions</button></form>
<div class="subscription-table"><table><thead><tr><th>Technician</th><th>Plan / reference</th><th>Status</th><th>Start (Philippine time)</th><th>Expiration (Philippine time)</th></tr></thead><tbody>
@forelse($subscriptions as $subscription)<tr><td>{{ $subscription->technician->user->name }}</td><td>{{ $subscription->payment?->plan_name ?? $subscription->plan->name }}<br>{{ $subscription->payment?->transaction_reference }}</td><td>{{ ucfirst(in_array($subscription->status, ['active','scheduled']) && $subscription->expires_at?->lessThanOrEqualTo(now()) ? 'expired' : ($subscription->status === 'scheduled' && $subscription->starts_at?->lessThanOrEqualTo(now()) ? 'active' : $subscription->status)) }}</td><td>{{ $subscription->starts_at?->timezone('Asia/Manila')->format('M d Y h:i A') ?? 'Awaiting approval' }}</td><td>{{ $subscription->expires_at?->timezone('Asia/Manila')->format('M d Y h:i A') ?? '—' }}</td></tr>@empty<tr><td colspan="5">No subscription cycles match.</td></tr>@endforelse
</tbody></table></div>{{ $subscriptions->links() }}
<h2>Configure subscription plans</h2>
<p>Prices and descriptions apply to new requests. Existing quotes remain unchanged. Pro and Premium cycles last 30 days. Free functionality is always available.</p>
@foreach($plans as $plan)<article><h3>{{ $plan->name }}</h3>
<form method="post" action="{{ cp_route('erepair.subscriptions.plan', ['plan'=>$plan->id]) }}">@csrf
    <label>Plan name <input name="name" value="{{ $plan->name }}" maxlength="100" required></label>
    <label>Description <textarea name="description" maxlength="2000" required>{{ $plan->description }}</textarea></label>
    <label>DEMO price (PHP) <input name="price" type="number" min="0" max="999999.99" step="0.01" value="{{ $plan->price }}" @readonly($plan->slug==='free') required></label>
    <label>Features (one per line) <textarea name="features" rows="6" maxlength="3000" required>{{ implode("\n", $plan->features) }}</textarea></label>
    <label>Available for new requests <select name="is_active"><option value="1" @selected($plan->is_active)>Yes</option>@if($plan->slug!=='free')<option value="0" @selected(!$plan->is_active)>No</option>@endif</select></label>
    <button>Save plan</button>
</form></article>@endforeach
</div>
@endsection
