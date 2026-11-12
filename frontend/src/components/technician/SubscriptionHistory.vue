<script setup lang="ts">
import { formatBusinessDate, formatPeso } from '@/lib/format';
import type { DemoPayment } from '@/types/subscription';
defineProps<{ payments: DemoPayment[] }>();

function accessLabel(payment: DemoPayment): string {
  const cycle = payment.subscription;
  if (cycle.status === 'cancelled') return payment.status === 'approved' ? 'Replaced by renewal' : 'Not activated';
  if (cycle.status === 'pending') return 'Awaiting approval';
  if ((cycle.status === 'active' || cycle.status === 'scheduled') && cycle.expires_at && new Date(cycle.expires_at).getTime() <= Date.now()) return 'Expired';
  if (cycle.status === 'scheduled') return cycle.starts_at && new Date(cycle.starts_at).getTime() <= Date.now() ? 'Active' : 'Scheduled';
  return cycle.status === 'active' ? 'Active' : 'Expired';
}
function requestLabel(payment: DemoPayment): string {
  return payment.status === 'pending' ? 'Pending Admin Approval' : payment.status === 'approved' ? 'Approved' : 'Rejected';
}
const date = (value: string | null) => value ? formatBusinessDate(value) : 'Not set';
</script>

<template>
<ul class="mt-5 space-y-4 lg:hidden" role="list" aria-label="Subscription request cards">
  <li v-for="payment in payments" :key="payment.id" class="rounded-xl border border-slate-200 p-4">
    <div class="flex flex-wrap items-start justify-between gap-3">
      <div class="min-w-0"><h3 class="break-words font-semibold text-navy">{{ payment.plan_name }}</h3><p class="mt-1 break-all text-xs text-slate-500">{{ payment.display_reference }} · {{ payment.duration_days }} days</p></div>
      <div><p class="text-xs text-slate-500">Simulated amount</p><p class="mt-1 text-lg font-semibold text-navy">{{ formatPeso(payment.amount) }}</p></div>
    </div>
    <dl class="mt-4 space-y-3 text-sm">
      <div><dt class="text-xs text-slate-500">Request status</dt><dd class="mt-1 font-medium" :class="payment.status === 'pending' ? 'text-amber-800' : payment.status === 'approved' ? 'text-emerald-700' : 'text-red-700'">{{ requestLabel(payment) }}</dd></div>
      <div><dt class="text-xs text-slate-500">Plan access</dt><dd class="mt-1 font-medium">{{ accessLabel(payment) }}</dd></div>
      <div class="grid gap-3 sm:grid-cols-2"><div><dt class="text-xs text-slate-500">Requested · Philippine time</dt><dd class="mt-1">{{ date(payment.created_at) }}</dd></div><div><dt class="text-xs text-slate-500">Approved · Philippine time</dt><dd class="mt-1">{{ payment.approved_at ? date(payment.approved_at) : 'Not approved' }}</dd></div></div>
      <div class="grid gap-3 sm:grid-cols-2"><div><dt class="text-xs text-slate-500">Access starts</dt><dd class="mt-1">{{ date(payment.subscription.starts_at) }}</dd></div><div><dt class="text-xs text-slate-500">Access expires</dt><dd class="mt-1">{{ date(payment.subscription.expires_at) }}</dd></div></div>
    </dl>
  </li>
</ul>
<div class="mt-5 hidden lg:block">
  <p class="mb-3 text-xs text-slate-500">Scroll the table to see all dates. All times are Philippine time.</p>
  <div class="data-scroll rounded-xl" tabindex="0" role="region" aria-label="Subscription history table">
    <table class="w-full min-w-[900px] text-left text-sm">
      <caption class="sr-only">Your subscription requests and access periods</caption>
      <thead><tr class="border-b text-slate-500"><th scope="col" class="p-3">Reference / plan</th><th scope="col" class="p-3">Simulated amount</th><th scope="col" class="p-3">Request status</th><th scope="col" class="p-3">Plan access</th><th scope="col" class="p-3">Requested / approved</th><th scope="col" class="p-3">Start / expiration</th></tr></thead>
      <tbody><tr v-for="payment in payments" :key="payment.id" class="border-b"><td class="p-3"><span class="break-all">{{ payment.display_reference }}</span><br>{{ payment.plan_name }} · {{ payment.duration_days }} days</td><td class="whitespace-nowrap p-3">{{ formatPeso(payment.amount) }}</td><td class="p-3">{{ requestLabel(payment) }}</td><td class="p-3">{{ accessLabel(payment) }}</td><td class="p-3">{{ date(payment.created_at) }}<br>{{ payment.approved_at ? date(payment.approved_at) : 'Not approved' }}</td><td class="p-3">{{ date(payment.subscription.starts_at) }}<br>{{ date(payment.subscription.expires_at) }}</td></tr></tbody>
    </table>
  </div>
</div>
</template>
