<form method="get">
    <label>Search technician or reference <input name="search" maxlength="100" value="{{ request('search') }}"></label>
    <label>Payment status <select name="status"><option value="">All</option>@foreach(['pending','approved','rejected'] as $status)<option value="{{ $status }}" @selected(request('status')===$status)>{{ ucfirst($status) }}</option>@endforeach</select></label>
    <button type="submit">Filter payments</button>
</form>
<div class="subscription-table"><table>
    <caption>DEMO payment history — approved transactions alone count as simulated revenue</caption>
    <thead><tr><th>Reference</th><th>Technician / plan</th><th>DEMO amount</th><th>Status</th><th>Requested (Philippine time)</th><th>Approved (Philippine time)</th>@if($manage ?? false)<th>Review</th>@endif</tr></thead>
    <tbody>@forelse($payments as $payment)<tr>
        <td>{{ $payment->display_reference }}<br><small>{{ $payment->transaction_reference }}</small>@if($payment->is_seeded_demo)<br><small>DEMO SETUP SAMPLE</small>@endif</td>
        <td>{{ $payment->technician->user->name }}<br>{{ $payment->plan_name }} · {{ $payment->duration_days }} days</td>
        <td>{{ \App\Support\SubscriptionMoney::format($payment->amount) }}</td>
        <td>{{ ucfirst($payment->status) }}</td>
        <td>{{ $payment->created_at->timezone('Asia/Manila')->format('M d Y h:i A') }}</td>
        <td>{{ $payment->approved_at?->timezone('Asia/Manila')->format('M d Y h:i A') ?? '—' }}@if($payment->approver)<br>{{ $payment->approver->name }}@endif</td>
        @if($manage ?? false)<td>@if($payment->status==='pending')
        <form method="post" action="{{ cp_route('erepair.subscriptions.decide', ['payment'=>$payment->id]) }}">@csrf
            <button name="decision" value="approved">Approve DEMO payment</button>
            <button name="decision" value="rejected">Reject DEMO payment</button>
        </form>@else Finalized @endif</td>@endif
    </tr>@empty<tr><td colspan="7">No DEMO payments match these filters.</td></tr>@endforelse</tbody>
</table></div>
{{ $payments->links() }}
