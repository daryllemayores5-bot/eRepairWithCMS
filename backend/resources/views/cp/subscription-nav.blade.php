<p class="demo-notice">DEMO / SIMULATED REVENUE — NO REAL PAYMENTS</p>
<nav class="ops-nav" aria-label="eRepair administration">
    <a href="{{ cp_route('erepair.index') }}">Operations</a>
    <a href="{{ cp_route('erepair.subscriptions') }}">Subscription Management</a>
    <a href="{{ cp_route('erepair.revenue') }}">Revenue Dashboard</a>
</nav>
@if(session('success'))<p role="status">{{ session('success') }}</p>@endif
@if($errors->any())<div role="alert">@foreach($errors->all() as $error)<p>{{ $error }}</p>@endforeach</div>@endif
