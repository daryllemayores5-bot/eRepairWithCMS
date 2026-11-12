<script setup lang="ts">
import { computed, nextTick, ref, watch } from 'vue';
import { useQueryClient } from '@tanstack/vue-query';
import { useApiQuery } from '@/composables/query';
import { useAuthStore } from '@/stores/auth';
import { api, initializeCsrf } from '@/services/api';
import { refreshTechnicianViews } from '@/services/technician-cache';
import { errorMessage } from '@/lib/errors';
import { formatBusinessDate, formatPeso } from '@/lib/format';
import type { SubscriptionAnalytics, SubscriptionOverview, SubscriptionPlan } from '@/types/subscription';
import PageHeading from '@/components/common/PageHeading.vue';
import Loading from '@/components/common/Loading.vue';
import Failure from '@/components/common/Failure.vue';
import Empty from '@/components/common/Empty.vue';
import Pagination from '@/components/common/Pagination.vue';
import Button from '@/components/ui/Button.vue';
import SubscriptionHistory from '@/components/technician/SubscriptionHistory.vue';
import { fieldClass } from '@/components/ui/fields';

const auth = useAuthStore();
const cache = useQueryClient();
const page = ref(1), busy = ref(false), failure = ref(''), success = ref('');
const confirmation = ref<HTMLDialogElement>();
const checkoutKey = ref('');
const quotedPlan = ref<SubscriptionPlan>();
const rangeDays = ref(30);
const overview = useApiQuery<SubscriptionOverview>(() => ({
  queryKey: ['my-subscription', page.value], enabled: auth.user?.role === 'technician',
  queryFn: async ({ signal }) => (await api.get<{ data: SubscriptionOverview }>('/technician/subscription', { signal, params: { page: page.value } })).data.data,
  refetchInterval: 15000,
}));
const currentSlug = computed(() => overview.data?.current_plan_slug ?? (overview.data?.is_premium ? 'premium' : overview.data?.is_pro ? 'pro' : 'free'));
const analytics = useApiQuery<SubscriptionAnalytics>(() => ({
  queryKey: ['subscription-analytics', currentSlug.value, rangeDays.value], enabled: !!overview.data?.is_pro,
  queryFn: async ({ signal }) => (await api.get<{ data: SubscriptionAnalytics }>('/technician/subscription/analytics', { signal, params: overview.data?.is_premium ? { range_days: rangeDays.value } : undefined })).data.data,
  refetchInterval: 15000,
}));
watch(() => overview.data ? `${currentSlug.value}|${overview.data.expires_at}` : undefined, async (current, previous) => {
  if (current === undefined || previous === undefined || current === previous) return;
  await refreshTechnicianViews(cache);
  if (overview.data?.is_pro) {
    cache.removeQueries({ predicate: query => query.queryKey[0] === 'subscription-analytics' && query.queryKey[1] !== currentSlug.value });
    await cache.invalidateQueries({ queryKey: ['subscription-analytics'] });
  } else {
    await cache.cancelQueries({ queryKey: ['subscription-analytics'] });
    if (!overview.data?.is_pro) cache.removeQueries({ queryKey: ['subscription-analytics'] });
  }
});
async function openConfirmation(plan: SubscriptionPlan) {
  failure.value = ''; success.value = '';
  if (quotedPlan.value?.slug !== plan.slug) checkoutKey.value = '';
  if (!checkoutKey.value) quotedPlan.value = { ...plan };
  // Keep this key across ambiguous network retries. The database pending guard
  // also blocks duplicate requests after a refresh or in another browser tab.
  if (!checkoutKey.value) checkoutKey.value = crypto.randomUUID();
  await nextTick();
  confirmation.value?.showModal();
}
async function submitSubscription() {
  if (busy.value || !quotedPlan.value) return;
  busy.value = true; failure.value = ''; success.value = '';
  try {
    await initializeCsrf();
    const response = await api.post<{ message: string }>('/technician/subscription/demo-payments', {
      idempotency_key: checkoutKey.value, expected_price: quotedPlan.value.price, plan_slug: quotedPlan.value.slug, confirm_demo: true,
    });
    success.value = response.data.message;
    checkoutKey.value = '';
    confirmation.value?.close();
    await cache.invalidateQueries({ queryKey: ['my-subscription'] });
  } catch (error) {
    failure.value = errorMessage(error);
    if ((error as { response?: { status?: number } })?.response?.status === 422) checkoutKey.value = '';
  } finally {
    busy.value = false;
  }
}
</script>

<template>
<Empty v-if="auth.user?.role !== 'technician'" title="Technician account required" description="Customers can search and submit inquiries without a subscription." />
<template v-else>
<PageHeading eyebrow="Optional technician benefits" title="My Subscription" description="Compare Free, Pro and Premium for the visibility and profile insights you need. Your subscription does not change technician verification." />
<p class="my-6 rounded-xl border border-slate-200 bg-slate-50 p-4 text-sm text-slate-600">DEMO simulation only. Online payments are not connected. Subscription requests do not charge you. Paid plans require administrator approval.</p>
<Loading v-if="overview.isPending" />
<Failure v-else-if="overview.isError" :error="overview.error" :retry="() => overview.refetch()" />
<template v-else-if="overview.data">
<section class="surface-card p-5 sm:p-6" aria-labelledby="current-subscription">
  <h2 id="current-subscription" class="section-title">Current plan: {{ overview.data.current_plan }}</h2>
  <dl class="mt-5 grid gap-5 text-sm sm:grid-cols-2 lg:grid-cols-4">
    <div><dt class="text-slate-500">Status</dt><dd class="mt-1 font-semibold">{{ overview.data.status === 'expired' ? 'Free access · previous plan expired' : overview.data.status === 'free' ? 'Free access' : 'Active' }}</dd></div>
    <div><dt class="text-slate-500">{{ overview.data.status === 'expired' ? 'Previous start' : 'Start' }} (Philippine time)</dt><dd class="mt-1">{{ overview.data.starts_at ? formatBusinessDate(overview.data.starts_at) : 'Free access — no start date required' }}</dd></div>
    <div><dt class="text-slate-500">{{ overview.data.status === 'expired' ? 'Previous expiration' : 'Expiration' }} (Philippine time)</dt><dd class="mt-1">{{ overview.data.expires_at ? formatBusinessDate(overview.data.expires_at) : 'No active paid subscription' }}</dd></div>
    <div><dt class="text-slate-500">Days remaining</dt><dd class="mt-1 font-semibold">{{ overview.data.days_remaining }}</dd></div>
  </dl>
  <p v-if="overview.data.pending_payment" role="status" class="mt-5 rounded-lg bg-blue-50 p-4 text-sm">Pending Admin Approval · {{ overview.data.pending_payment.display_reference }} · {{ formatPeso(overview.data.pending_payment.amount) }}. Your current plan stays available while this request is reviewed.</p>
  <p v-if="overview.data.scheduled_subscription" role="status" class="mt-5 rounded-lg bg-violet-50 p-4 text-sm text-violet-900">Scheduled plan: {{ overview.data.scheduled_subscription.plan.name }}. Starts {{ formatBusinessDate(overview.data.scheduled_subscription.starts_at!) }} and expires {{ formatBusinessDate(overview.data.scheduled_subscription.expires_at!) }}. Your current paid plan remains active until then.</p>
</section>
<div class="my-6 grid gap-5 md:grid-cols-2 xl:grid-cols-3">
  <section v-for="plan in overview.data.plans" :key="plan.id" :class="['surface-card flex min-w-0 flex-col p-5 sm:p-6', plan.slug === currentSlug ? 'border-blue-400 ring-2 ring-blue-100' : plan.slug === 'premium' ? 'border-violet-200' : '']" :aria-label="`${plan.name} plan`">
    <h2 class="section-title">{{ plan.name }}</h2>
    <p v-if="plan.slug === currentSlug" class="mt-2 text-sm font-semibold text-blue-700">Your active plan</p>
    <p class="mt-3 text-2xl font-bold text-navy">{{ formatPeso(plan.price) }} <span class="text-sm font-normal text-slate-500">{{ plan.slug === 'free' ? '· always free' : `/ ${plan.duration_days} days` }}</span></p>
    <p class="mt-3 text-sm leading-6 text-slate-600">{{ plan.description }}</p>
    <ul class="my-5 list-disc space-y-2 pl-5 text-sm text-slate-600"><li v-for="feature in plan.features" :key="feature">{{ feature }}</li></ul>
    <Button v-if="plan.slug !== 'free'" class="mt-auto w-full" :disabled="!plan.is_active || !!overview.data.pending_payment || !!overview.data.scheduled_subscription || busy" @click="() => openConfirmation(plan)">{{ !plan.is_active ? 'New requests unavailable' : plan.slug === currentSlug ? 'Renew Subscription' : currentSlug === 'premium' ? 'Schedule Pro' : plan.slug === 'premium' ? 'Upgrade to Premium' : 'Upgrade to Pro' }}</Button>
    <p v-else class="text-sm text-slate-500">Free access remains available without a payment request.</p>
  </section>
</div>
<p v-if="success" role="status" class="mb-5 rounded-xl bg-green-50 p-4 text-sm text-green-800">{{ success }}</p>
<p v-if="failure && !confirmation?.open" role="alert" class="mb-5 rounded-xl bg-red-50 p-4 text-sm text-red-800">{{ failure }}</p>
<dialog ref="confirmation" aria-labelledby="subscription-confirm-title" class="m-auto max-h-[calc(100dvh_-_2rem)] w-[calc(100%_-_2rem)] max-w-lg overflow-y-auto rounded-2xl border border-slate-200 bg-white p-5 shadow-xl backdrop:bg-slate-950/50 sm:p-6" @cancel="event => { if (busy) event.preventDefault(); }">
  <h2 id="subscription-confirm-title" class="section-title">Confirm your subscription</h2>
  <p class="mt-4 text-sm leading-7">{{ quotedPlan?.name }} · {{ quotedPlan ? formatPeso(quotedPlan.price) : '' }} for {{ quotedPlan?.duration_days }} days. This creates a pending DEMO transaction for administrator review. No payment will be collected and you will not be charged.</p>
  <p class="mt-3 text-sm leading-7">Renewing the same plan adds time from the later of your current expiration or approval. Changing paid plans schedules the new plan after your current paid period ends. Your remaining approved time is preserved; no refunds or price adjustments are simulated.</p>
  <p v-if="failure" role="alert" class="mt-4 rounded-lg bg-red-50 p-3 text-sm text-red-800">{{ failure }}</p>
  <div class="mt-6 flex flex-wrap gap-3"><Button :disabled="busy" @click="submitSubscription">{{ busy ? 'Submitting request…' : 'Simulate Payment' }}</Button><Button variant="outline" :disabled="busy" @click="confirmation?.close()">Cancel</Button></div>
</dialog>
<section class="surface-card p-5 sm:p-6" aria-labelledby="subscription-history">
  <h2 id="subscription-history" class="section-title">Subscription history</h2>
  <p class="mt-3 text-xs leading-6 text-slate-500">Each request is a separate cycle. Same-plan renewals carry remaining time into the renewed cycle. Approved plan changes are scheduled after the current paid period. Rejected requests provide no paid benefits.</p>
  <SubscriptionHistory v-if="overview.data.payments.data.length" :payments="overview.data.payments.data" />
  <Empty v-else title="No subscription requests yet" description="Your Free plan is ready to use. Upgrade and renewal requests will appear here." />
  <Pagination :page="overview.data.payments" :on-change="value => page = value" />
</section>
<section class="surface-card mt-6 p-5 sm:p-6" aria-labelledby="pro-analytics">
  <h2 id="pro-analytics" class="section-title">Advanced profile analytics</h2>
  <p class="mt-3 text-xs leading-6 text-slate-500">Profile views are approximate daily visits, deduplicated by network and profile. Self-views are excluded. Shared networks can undercount and automated visits can overcount. Tracking starts when this feature is installed; inquiries are actual repair requests, not completed jobs.</p>
  <Empty v-if="!overview.data.is_pro" title="Upgrade to Pro for analytics" description="Your existing free dashboard, customer inquiries and repair tools remain available." />
  <Loading v-else-if="analytics.isPending" />
  <Failure v-else-if="analytics.isError" :error="analytics.error" :retry="() => analytics.refetch()" />
  <template v-else-if="analytics.data">
    <section v-if="overview.data.is_premium" class="my-5 rounded-xl border border-violet-200 bg-violet-50 p-5" aria-labelledby="premium-analytics">
      <h3 id="premium-analytics" class="font-semibold text-violet-900">Premium engagement statistics</h3>
      <label class="mt-4 block text-sm">Date range <select v-model="rangeDays" :class="`${fieldClass} mt-2 sm:max-w-xs`"><option :value="7">Last 7 days</option><option :value="30">Last 30 days</option><option :value="90">Last 90 days</option></select></label>
      <template v-if="analytics.data.premium">
        <p class="mt-3 text-xs">{{ analytics.data.premium.from }} to {{ analytics.data.premium.through }} · Philippine time</p>
        <dl class="mt-4 grid gap-4 sm:grid-cols-2"><div><dt>Profile views in range</dt><dd class="text-2xl font-bold">{{ analytics.data.premium.views }}</dd><p class="text-xs">Previous period: {{ analytics.data.premium.previous_period.views }}</p></div><div><dt>Customer inquiries in range</dt><dd class="text-2xl font-bold">{{ analytics.data.premium.inquiries }}</dd><p class="text-xs">Previous period: {{ analytics.data.premium.previous_period.inquiries }}</p></div></dl>
        <div class="data-scroll mt-5 max-h-80" tabindex="0" role="region" aria-label="Daily engagement data"><table class="w-full text-left text-sm"><caption class="sr-only">Daily profile views and inquiries for the selected date range</caption><thead><tr><th scope="col" class="p-2">Date</th><th scope="col" class="p-2">Views</th><th scope="col" class="p-2">Inquiries</th></tr></thead><tbody><tr v-for="day in analytics.data.premium.daily" :key="day.date"><td class="whitespace-nowrap p-2">{{ day.date }}</td><td class="p-2">{{ day.views }}</td><td class="p-2">{{ day.inquiries }}</td></tr></tbody></table></div>
      </template>
    </section>
    <dl class="my-5 grid gap-4 sm:grid-cols-2"><div class="rounded-xl bg-blue-50 p-5"><dt>Total profile views</dt><dd class="mt-2 text-2xl font-bold">{{ analytics.data.total_profile_views }}</dd></div><div class="rounded-xl bg-blue-50 p-5"><dt>Total customer inquiries</dt><dd class="mt-2 text-2xl font-bold">{{ analytics.data.total_customer_inquiries }}</dd></div></dl>
    <div class="data-scroll" tabindex="0" role="region" aria-label="Monthly engagement data"><table class="w-full text-left text-sm"><caption class="py-3 text-left font-semibold">Monthly engagement · Philippine time</caption><thead><tr class="border-b"><th scope="col" class="p-3">Month</th><th scope="col" class="p-3">Profile views</th><th scope="col" class="p-3">Customer inquiries</th></tr></thead><tbody><tr v-for="month in analytics.data.months" :key="month.month" class="border-b"><td class="whitespace-nowrap p-3">{{ month.month }}</td><td class="p-3">{{ month.views }}</td><td class="p-3">{{ month.inquiries }}</td></tr></tbody></table></div>
    <h3 class="mt-6 font-semibold">Recent profile activity</h3><ul v-if="analytics.data.recent_activity.length" class="mt-3 space-y-2 text-sm"><li v-for="day in analytics.data.recent_activity" :key="day.viewed_on">{{ day.viewed_on }} · {{ day.views }} view(s)</li></ul><p v-else class="mt-3 text-sm text-slate-500">No profile visits recorded yet.</p>
  </template>
</section>
</template>
</template>
</template>
