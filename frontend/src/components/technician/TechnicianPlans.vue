<script setup lang="ts">
import { computed } from 'vue';
import { RouterLink } from 'vue-router';
import { Check, ArrowRight } from 'lucide-vue-next';
import { useApiQuery } from '@/composables/query';
import { useAuthStore } from '@/stores/auth';
import { api } from '@/services/api';
import { formatPeso } from '@/lib/format';
import type { SubscriptionPlan } from '@/types/subscription';
import SectionHeading from '@/components/common/SectionHeading.vue';
import Loading from '@/components/common/Loading.vue';
import Failure from '@/components/common/Failure.vue';
import Empty from '@/components/common/Empty.vue';
import Button from '@/components/ui/Button.vue';

const auth = useAuthStore();
const plans = useApiQuery<SubscriptionPlan[]>(() => ({
  queryKey: ['subscription-plans'],
  queryFn: async ({ signal }) => (await api.get<{ data: SubscriptionPlan[] }>('/subscription-plans', { signal })).data.data,
}));
const technician = computed(() => auth.user?.role === 'technician');
</script>

<template>
<section id="technician-plans" aria-label="Technician subscription plans" class="scroll-mt-24 border-y border-slate-200/80 bg-slate-50/70">
  <div class="mx-auto max-w-7xl px-5 py-12 sm:px-8 sm:py-16">
    <SectionHeading eyebrow="For technicians" title="Start free. Grow at your own pace." description="Optional plans for your repair business. Customers can search and request repairs without a subscription." />
    <p class="mt-3 text-sm leading-6 text-muted-foreground">Repair costs are quoted separately by your technician.</p>
    <Loading v-if="plans.isPending" />
    <Failure v-else-if="plans.isError" :error="plans.error" :retry="() => plans.refetch()" />
    <Empty v-else-if="!plans.data?.length" title="Plan details are currently unavailable" description="You can still browse the technician directory. Please check back for subscription options." />
    <template v-else>
      <div class="mt-7 grid gap-5 md:grid-cols-2 xl:grid-cols-3">
        <article v-for="plan in plans.data" :key="plan.id" :aria-label="`${plan.name} subscription plan`" :class="['surface-card flex min-w-0 flex-col p-6 sm:p-7', plan.slug === 'premium' ? 'border-violet-200 ring-1 ring-violet-100' : plan.slug === 'pro' ? 'border-blue-200' : '']">
          <p :class="['text-xs font-bold uppercase tracking-widest', plan.slug === 'premium' ? 'text-violet-700' : 'text-primary']">{{ plan.slug }}</p>
          <h3 class="mt-3 text-xl font-semibold text-navy">{{ plan.name }}</h3>
          <p class="mt-5 flex flex-wrap items-baseline gap-x-2 gap-y-1"><span class="text-3xl font-bold tracking-tight text-navy">{{ formatPeso(plan.price) }}</span><span class="text-sm text-muted-foreground">{{ plan.slug === 'free' ? 'No subscription fee' : `/ ${plan.duration_days} days` }}</span></p>
          <p class="mt-4 text-sm leading-6 text-muted-foreground">{{ plan.description }}</p>
          <ul class="mt-6 space-y-3 text-sm leading-6 text-slate-600">
            <li v-for="feature in plan.features" :key="feature" class="flex items-start gap-2"><Check :class="['mt-1 size-4 shrink-0', plan.slug === 'premium' ? 'text-violet-600' : 'text-primary']" aria-hidden="true" /><span class="min-w-0 break-words">{{ feature }}</span></li>
          </ul>
        </article>
      </div>
      <div class="mt-6 flex flex-wrap items-center justify-between gap-5 rounded-2xl border border-slate-200 bg-white p-5 sm:p-6">
        <div class="max-w-2xl text-sm leading-6 text-slate-600">
          <p>Every technician needs administrator approval before appearing in search. Paid badges show subscription status, not verified skills. Customer search criteria still apply.</p>
          <p class="mt-2">DEMO only: paid-plan requests simulate payment and require administrator approval. No real money is collected.</p>
        </div>
        <Button v-if="technician" as-child><RouterLink to="/dashboard/subscription">View plans in my workspace<ArrowRight aria-hidden="true" /></RouterLink></Button>
        <Button v-else-if="!auth.user" as-child><RouterLink to="/register/technician">Create technician account<ArrowRight aria-hidden="true" /></RouterLink></Button>
        <p v-else class="text-sm font-medium text-slate-600">These optional plans are for technician accounts.</p>
      </div>
    </template>
  </div>
</section>
</template>
