<script setup lang="ts">
import { computed, reactive } from 'vue';
import { RouterLink as Link, useRoute } from 'vue-router';
import { useQueries } from '@tanstack/vue-query';
import { api } from '@/services/api';
import type { SubscriptionTechnician as Technician } from '@/types/subscription';
import SubscriptionBadge from '@/components/technician/SubscriptionBadge.vue';
import { technicianSelection } from '@/lib/technician-selection';
import { ArrowRight, ArrowLeft } from 'lucide-vue-next';
import Button from '@/components/ui/Button.vue';
import Avatar from '@/components/ui/Avatar.vue';
import Card from '@/components/ui/Card.vue';
import Loading from '@/components/common/Loading.vue';
import Failure from '@/components/common/Failure.vue';
import Empty from '@/components/common/Empty.vue';
import Status from '@/components/common/Status.vue';
import PageHeading from '@/components/common/PageHeading.vue';
const route = useRoute();
const ids = computed(() => technicianSelection(route.query.ids));
const backToSearch = computed(() => {
  const search = new URLSearchParams(typeof route.query.search === 'string' ? route.query.search : '');
  search.delete('compare');
  if (ids.value.length) search.set('compare', ids.value.join(','));
  return '/technicians' + (search.size ? '?' + search.toString() : '');
});
const queries = reactive(useQueries({
  queries: computed(() => ids.value.map(id => ({
    queryKey: ['technician', String(id)],
    queryFn: async ({
      signal
    }: {
      signal: AbortSignal;
    }) => (await api.get<{
      data: Technician;
    }>('/technicians/' + id, {
      signal
    })).data.data
  })))
}));
</script>

<template>
<div class="page-shell">
<PageHeading eyebrow="Choose with confidence" title="Compare technicians" description="Review their skills, experience and availability side by side.">
<template #action>
<Button variant="outline" as-child>
<Link :to="backToSearch">
<ArrowLeft aria-hidden="true">
</ArrowLeft>Back to search</Link>
</Button>
</template>
</PageHeading>
<template v-if="!ids.length">
<div class="mt-8">
<Empty title="Select technicians first" description="Use Compare on directory cards to choose up to three profiles.">
</Empty>
<Button class="mt-5" as-child>
<Link :to="backToSearch">Find technicians</Link>
</Button>
</div>
</template>
<template v-else>
<div class="mt-8 grid items-start gap-5 md:grid-cols-3">
<template v-for="(q, i) in queries" :key="ids[i]">
<template v-if="q.isPending">
<Loading>
</Loading>
</template>
<template v-else>
<template v-if="q.isError">
<Failure :key="ids[i]" :error="q.error" :retry="() => q.refetch()">
</Failure>
</template>
<template v-else>
<Card :key="ids[i]" class="p-6">
<Avatar :name="q.data.name" :src="q.data.photo_url">
</Avatar>
<h2 class="mt-5 text-lg font-semibold text-navy">{{ q.data.name }}</h2>
<SubscriptionBadge class="mt-2" :tier="q.data.subscription_tier ?? (q.data.is_premium ? 'premium' : q.data.is_pro ? 'pro' : 'free')" />
<p class="mt-2 text-xs leading-6 text-slate-500">{{ q.data.city }}, {{ q.data.province }}</p>
<dl class="mt-5 space-y-4 border-y py-5 text-sm">
<div>
<dt class="text-xs text-slate-500">Experience</dt>
<dd class="mt-1 font-medium">{{ q.data.years_experience }} years</dd>
</div>
<div>
<dt class="text-xs text-slate-500">Customer feedback</dt>
<dd class="mt-1 font-medium">
<template v-if="q.data.reviews_count">{{ `${q.data.average_rating}/5 · ${q.data.reviews_count} reviews` }}</template>
<template v-else>{{ 'No reviews yet' }}</template>
</dd>
</div>
<div>
<dt class="mb-2 text-xs text-slate-500">Availability</dt>
<dd>
<Status :value="q.data.availability">
</Status>
</dd>
</div>
</dl>
<h3 class="mt-5 text-xs font-semibold text-navy">Supported devices</h3>
<p class="mt-2 text-sm leading-7 text-slate-500">{{ q.data.device_types.map(device => device.name).join(' · ') || 'No supported devices listed' }}</p>
<h3 class="mt-5 text-xs font-semibold text-navy">Service locations</h3>
<ul class="mt-2 space-y-1 text-sm leading-7 text-slate-500">
<li v-for="area in [{ city: q.data.city, province: q.data.province }, ...q.data.service_areas].filter((area, index, all) => all.findIndex(other => other.city === area.city && other.province === area.province) === index)" :key="`${area.city}|${area.province}`">{{ area.city }}, {{ area.province }}</li>
</ul>
<h3 class="mt-5 text-xs font-semibold text-navy">Specializations</h3>
<p class="mt-2 text-sm leading-7 text-slate-500">{{ q.data.specializations.map(s => s.name).join(' · ') }}</p>
<Button class="mt-6 w-full" as-child>
<Link :to="`/technicians/${q.data.id}`">View profile<ArrowRight aria-hidden="true">
</ArrowRight>
</Link>
</Button>
</Card>
</template>
</template>
</template>
</div>
</template>
</div>
</template>
