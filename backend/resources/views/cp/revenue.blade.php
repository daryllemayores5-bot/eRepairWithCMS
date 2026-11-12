@extends('statamic::layout')
@section('title', 'Revenue Dashboard')
@section('content')
<div class="erepair-ops" v-pre>
<h1>Revenue Dashboard</h1>
@include('cp.subscription-nav')
<p>Database totals from approved DEMO subscription payments. Dates use Philippine time. Expiration does not remove historical simulated revenue. These amounts are not real earnings.</p>
<div class="ops-stats">
@foreach(['total'=>'Total Simulated Revenue','month'=>'Simulated Revenue This Month','year'=>'Simulated Revenue This Year','pro'=>'Active Pro Technicians','premium'=>'Active Premium Technicians','free'=>'Free Technicians','scheduled'=>'Scheduled Plan Changes','pending'=>'Pending Demo Payments','approved'=>'Approved Demo Payments','rejected'=>'Rejected Demo Payments','expired'=>'Expired Subscriptions'] as $key=>$label)
<div><strong>{{ in_array($key,['total','month','year']) ? \App\Support\SubscriptionMoney::format($summary[$key]) : $summary[$key] }}</strong><p>{{ $label }}</p></div>
@endforeach
</div>
<p>Technician distribution counts active marketplace accounts, including technicians awaiting verification. Subscription status does not bypass verification.</p>
<h2>Approved simulated revenue by plan</h2>
<div class="subscription-table"><table><thead><tr><th>Paid plan</th><th>Approved DEMO revenue</th></tr></thead><tbody>@foreach($summary['by_plan'] as $slug=>$amount)<tr><td>{{ ucfirst($slug) }}</td><td>{{ \App\Support\SubscriptionMoney::format($amount) }}</td></tr>@endforeach</tbody></table></div>
<h2>Monthly simulated subscription revenue</h2>
@php($max = max(1, ...array_map(fn($month) => \App\Support\SubscriptionMoney::cents($month['amount']), $summary['months'])))
<div class="subscription-chart"><svg viewBox="0 0 720 230" role="img" aria-label="Monthly approved DEMO revenue for the last twelve months in Philippine pesos">
<line x1="15" y1="185" x2="710" y2="185" stroke="#CBD5E1"/>
@foreach($summary['months'] as $i=>$month)
@php($height = intdiv(\App\Support\SubscriptionMoney::cents($month['amount']) * 150, $max))
<rect x="{{ 20+$i*58 }}" y="{{ 185-$height }}" width="36" height="{{ $height }}" fill="#2563EB"><title>{{ $month['month'] }}: {{ \App\Support\SubscriptionMoney::format($month['amount']) }}</title></rect>
<text x="{{ 38+$i*58 }}" y="{{ 175-$height }}" text-anchor="middle" font-size="9" fill="#0B1F41">{{ \App\Support\SubscriptionMoney::format($month['amount']) }}</text>
<text x="{{ 38+$i*58 }}" y="208" text-anchor="middle" font-size="10" fill="#475569">{{ $month['month'] }}</text>
@endforeach</svg></div>
<details><summary>View monthly revenue data</summary><div class="subscription-table"><table><thead><tr><th>Month</th><th>Approved DEMO revenue</th></tr></thead><tbody>@foreach($summary['months'] as $month)<tr><td>{{ $month['month'] }}</td><td>{{ \App\Support\SubscriptionMoney::format($month['amount']) }}</td></tr>@endforeach</tbody></table></div></details>
<h2>Subscription distribution</h2>
@php($total = $summary['pro']+$summary['premium']+$summary['free'])
<div class="subscription-chart"><svg viewBox="0 0 180 150" role="img" aria-label="Subscription distribution: {{ $summary['free'] }} Free, {{ $summary['pro'] }} active Pro and {{ $summary['premium'] }} active Premium technicians" style="max-width:240px">
<circle cx="90" cy="70" r="48" fill="none" stroke="#CBD5E1" stroke-width="20"/>
@if($total)<circle cx="90" cy="70" r="48" fill="none" stroke="#2563EB" stroke-width="20" pathLength="100" stroke-dasharray="{{ $summary['pro']/$total*100 }} 100" transform="rotate(-90 90 70)"/>@endif
@if($total && $summary['premium'])<circle cx="90" cy="70" r="48" fill="none" stroke="#7C3AED" stroke-width="20" pathLength="100" stroke-dasharray="{{ $summary['premium']/$total*100 }} 100" stroke-dashoffset="{{ -$summary['pro']/$total*100 }}" transform="rotate(-90 90 70)"/>@endif
<text x="90" y="76" text-anchor="middle" fill="#0B1F41" font-size="24">{{ $total }}</text></svg>
<p>Free: <strong>{{ $summary['free'] }}</strong> · Active Pro: <strong>{{ $summary['pro'] }}</strong> · Active Premium: <strong>{{ $summary['premium'] }}</strong></p>@if(!$total)<p>No eligible technician accounts yet.</p>@endif</div>
<h2>Revenue Projection</h2>
<p>Projected gross subscription revenue, separate from approved DEMO revenue. Assumes the same number of paying technicians each month at the selected plan price. Excludes costs, taxes and profit calculations.</p>
<form method="get" id="revenue-projection" data-price-cents="{{ \App\Support\SubscriptionMoney::cents($projection['price']) }}">
<label>Number of paying technicians <input id="projection-count" name="paying_technicians" type="number" min="0" max="1000000" step="1" required value="{{ $projection['technicians'] }}"></label>
<label>Plan <select id="projection-plan" name="projection_plan">@foreach($projection_plans as $plan)<option value="{{ $plan->slug }}" data-price-cents="{{ \App\Support\SubscriptionMoney::cents($plan->price) }}" data-name="{{ $plan->name }}" @selected($projection['slug']===$plan->slug)>{{ $plan->name }}</option>@endforeach</select></label>
<p>Configured <span id="projection-plan-name">{{ $projection['plan'] }}</span> price: <span id="projection-price">{{ \App\Support\SubscriptionMoney::format($projection['price']) }}</span> / 30 days</p><button>Calculate projection</button>
<p aria-live="polite" aria-atomic="true">Projected monthly gross revenue: <strong id="projection-monthly">{{ \App\Support\SubscriptionMoney::format($projection['monthly']) }}</strong><br>Projected annual gross revenue: <strong id="projection-annual">{{ \App\Support\SubscriptionMoney::format($projection['annual']) }}</strong></p>
</form>
<h2>Recent DEMO transactions</h2>
@include('cp.subscription-payments')
</div>
@endsection
