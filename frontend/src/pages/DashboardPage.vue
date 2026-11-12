<script setup lang="ts">
import { computed } from 'vue';
import { RouterLink as Link } from 'vue-router';
import { useApiQuery } from '@/composables/query';
import { useAuthStore as useAuth } from '@/stores/auth';
import { api } from '@/services/api';
import { formatBusinessDate } from '@/lib/format';
import type { Dashboard } from '@/types';
import { BadgeCheck, Wrench, ArrowRight, ArrowUpRight, CalendarDays, CircleCheck, CircleUserRound, ClipboardList, Clock3, MapPin, Search, Sparkles, Star } from 'lucide-vue-next';
import Button from '@/components/ui/Button.vue';
import Card from '@/components/ui/Card.vue';
import CardHeader from '@/components/ui/CardHeader.vue';
import CardTitle from '@/components/ui/CardTitle.vue';
import CardDescription from '@/components/ui/CardDescription.vue';
import CardContent from '@/components/ui/CardContent.vue';
import Loading from '@/components/common/Loading.vue';
import Failure from '@/components/common/Failure.vue';
import Empty from '@/components/common/Empty.vue';
import Status from '@/components/common/Status.vue';
import PageHeading from '@/components/common/PageHeading.vue';
import MetricCard from '@/components/common/MetricCard.vue';
import RepairCharts from '@/components/common/RepairCharts.vue';
import RepairTable from '@/components/common/RepairTable.vue';
const auth = useAuth();
const query = useApiQuery<Dashboard>(() => ({
  queryKey: ['dashboard'],
  queryFn: async ({
    signal
  }) => (await api.get<{
    data: Dashboard;
  }>('/dashboard', {
    signal
  })).data.data
}));
const d = computed(() => query.data!);
const tech = computed(() => auth.user?.role === 'technician');
const ongoing = computed(() => ['accepted', 'scheduled', 'diagnosing', 'awaiting_customer_approval', 'in_repair', 'ready_for_pickup'].reduce((sum, key) => sum + Number(d.value?.counts[key] ?? 0), 0));
const metrics = computed(() => d.value ? [{
  label: 'Total requests',
  value: d.value.total,
  icon: ClipboardList,
  tone: 'bg-blue-50 text-blue-600',
  detail: 'Your repair activity'
}, {
  label: tech.value ? 'New requests' : 'Pending',
  value: d.value.counts.pending ?? 0,
  icon: Clock3,
  tone: 'bg-amber-50 text-amber-700',
  detail: tech.value ? 'Awaiting your response' : 'Awaiting technician response'
}, ...(tech.value ? [{
  label: 'Accepted requests',
  value: d.value.counts.accepted ?? 0,
  icon: BadgeCheck,
  tone: 'bg-indigo-50 text-indigo-600',
  detail: 'Ready to coordinate'
}] : []), {
  label: 'Ongoing repairs',
  value: ongoing.value,
  icon: Wrench,
  tone: 'bg-sky-50 text-sky-600',
  detail: 'Currently in progress'
}, {
  label: 'Completed repairs',
  value: d.value.counts.completed ?? 0,
  icon: CircleCheck,
  tone: 'bg-emerald-50 text-emerald-700',
  detail: 'Finished repair journeys'
}, ...(tech.value ? [{
  label: 'Average rating',
  value: d.value.average_rating ? Number(d.value.average_rating).toFixed(1) + ' / 5' : '—',
  icon: Star,
  tone: 'bg-amber-50 text-amber-600',
  detail: d.value.average_rating ? 'From your customer reviews' : 'No ratings yet'
}] : [])] : []);
</script>

<template>
<Loading v-if="query.isPending" />
<Failure v-else-if="query.isError" :error="query.error" :retry="() => query.refetch()" />
<template v-else-if="query.data">
<div class="space-y-6">
<PageHeading :title="`Hello, ${auth.user?.name?.split(' ')[0] || 'there'}`" :description="tech?'Here\'s a clear view of your requests, appointments, and active repairs.':'Find the right expert and keep every step of your repair in view.'">
<template #action>
<Button as-child>
<Link :to="tech ? '/dashboard/profile' : '/technicians'">
<template v-if="tech">
<CircleUserRound aria-hidden="true">
</CircleUserRound>
</template>
<template v-else>
<Search aria-hidden="true">
</Search>
</template>
<template v-if="tech">{{ 'Manage professional profile' }}</template>
<template v-else>{{ 'Find a technician' }}</template>
</Link>
</Button>
</template>
</PageHeading>
<div :class="`grid gap-4 sm:grid-cols-2 ${tech ? 'md:grid-cols-3' : 'xl:grid-cols-4'}`">
<template v-for="(metric) in metrics" :key="metric.label">
<MetricCard v-bind="metric">
</MetricCard>
</template>
</div>
<RepairCharts :data="d">
</RepairCharts>
<Card class="overflow-hidden">
<CardHeader class="flex-row flex-wrap items-center justify-between gap-3">
<div>
<CardTitle>Recent requests</CardTitle>
<CardDescription class="mt-1">The latest updates from your repair journey.</CardDescription>
</div>
<Button as-child variant="ghost" size="sm">
<Link to="/dashboard/repairs">View all requests<ArrowRight aria-hidden="true">
</ArrowRight>
</Link>
</Button>
</CardHeader>
<RepairTable :repairs="d.recent" :technicianView="tech">
</RepairTable>
</Card>
<div class="grid items-start gap-6 xl:grid-cols-2">
<Card>
<CardHeader>
<CardTitle class="flex items-center gap-2">
<CalendarDays class="size-[18px] text-primary" aria-hidden="true">
</CalendarDays>Upcoming appointments</CardTitle>
<CardDescription>Scheduled visits, shown in Philippine time.</CardDescription>
</CardHeader>
<CardContent>
<template v-if="d.appointments.length">
<div class="divide-y divide-slate-100">
<template v-for="(a) in d.appointments" :key="a.id">
<Link class="group flex items-start justify-between gap-4 rounded-lg py-4 first:pt-0 last:pb-0" :to="`/dashboard/repairs/${a.repair_request_id}`">
<div>
<p class="text-sm font-semibold text-slate-800 group-hover:text-primary">{{ formatBusinessDate(a.starts_at) }}</p>
<p class="mt-1.5 flex items-center gap-1.5 text-xs leading-5 text-muted-foreground">
<MapPin class="size-3.5 shrink-0" aria-hidden="true">
</MapPin>{{ a.service_location }}</p>
<div class="mt-3">
<Status :value="a.status">
</Status>
</div>
</div>
<ArrowUpRight class="mt-1 size-4 shrink-0 text-slate-400 group-hover:text-primary" aria-hidden="true">
</ArrowUpRight>
</Link>
</template>
</div>
</template>
<template v-else>
<Empty title="No upcoming appointments" description="Appointments will appear here when a repair visit is proposed or confirmed.">
</Empty>
</template>
</CardContent>
</Card>
<Card class="border-blue-100 bg-blue-50/40">
<CardHeader>
<span class="mb-1 flex size-10 items-center justify-center rounded-xl bg-white text-primary shadow-xs">
<Sparkles class="size-5" aria-hidden="true">
</Sparkles>
</span>
<CardTitle>
<template v-if="tech">{{ 'Keep your profile current' }}</template>
<template v-else>{{ 'A smoother repair starts here' }}</template>
</CardTitle>
<CardDescription>
<template v-if="tech">{{ 'Help customers choose confidently with up-to-date skills and availability.' }}</template>
<template v-else>{{ 'Compare specializations, availability and reviews before sending a request.' }}</template>
</CardDescription>
</CardHeader>
<CardContent class="space-y-3">
<Button variant="outline" as-child class="w-full">
<Link :to="tech ? '/dashboard/availability' : '/technicians'">
<template v-if="tech">{{ 'Update availability' }}</template>
<template v-else>{{ 'Explore technicians' }}</template>
<ArrowRight aria-hidden="true">
</ArrowRight>
</Link>
</Button>
<template v-if="tech">
<p class="flex items-center gap-1.5 text-xs text-slate-600">
<Star class="size-3.5 text-amber-500" aria-hidden="true">
</Star>Average customer rating: {{ d.average_rating || 'No ratings yet' }}</p>
</template>
<template v-else>
<p class="text-xs leading-5 text-slate-500">Your technician sends an estimate for approval before repair begins.</p>
</template>
</CardContent>
</Card>
</div>
</div>
</template>
</template>
