<script setup lang="ts">
import { computed, useId } from 'vue';
import type { Repair, Dashboard } from '@/types';
import { ChartNoAxesCombined, ChartPie } from 'lucide-vue-next';
import Card from '@/components/ui/Card.vue';
import CardHeader from '@/components/ui/CardHeader.vue';
import CardTitle from '@/components/ui/CardTitle.vue';
import CardDescription from '@/components/ui/CardDescription.vue';
import CardContent from '@/components/ui/CardContent.vue';
import Empty from '@/components/common/Empty.vue';
const props = defineProps<{
  data: Dashboard;
}>();
const fillId = useId();
const model = computed(() => {
  const activity = props.data.activity;
  const maximum = Math.max(4, Math.ceil(Math.max(...(activity?.map(day => day.count) ?? [0])) / 4) * 4);
  const points = activity?.map((day, index) => ({
    ...day,
    x: 38 + index / Math.max(1, activity.length - 1) * 508,
    y: 170 - day.count / maximum * 152
  })) ?? [];
  const line = points.map(point => `${point.x},${point.y}`).join(' ');
  const categories = [{
    label: 'Pending',
    count: Number(props.data.counts.pending ?? 0),
    color: '#F59E0B'
  }, {
    label: 'In progress',
    count: ['accepted', 'scheduled', 'diagnosing', 'awaiting_customer_approval', 'in_repair', 'ready_for_pickup'].reduce((sum, status) => sum + Number(props.data.counts[status] ?? 0), 0),
    color: '#2563EB'
  }, {
    label: 'Completed',
    count: Number(props.data.counts.completed ?? 0),
    color: '#16A34A'
  }, {
    label: 'Closed',
    count: Number(props.data.counts.canceled ?? 0) + Number(props.data.counts.rejected ?? 0),
    color: '#94A3B8'
  }];
  const total = categories.reduce((sum, category) => sum + category.count, 0);
  let offset = 0;
  const arcs = categories.map(category => {
    const arc = {
      ...category,
      offset
    };
    offset += total ? category.count / total : 0;
    return arc;
  });
  return {
    activity,
    maximum,
    points,
    line,
    categories,
    total,
    arcs
  };
});
const activity = computed(() => model.value.activity);
const maximum = computed(() => model.value.maximum);
const points = computed(() => model.value.points);
const line = computed(() => model.value.line);
const categories = computed(() => model.value.categories);
const total = computed(() => model.value.total);
const arcs = computed(() => model.value.arcs);
</script>

<template>
<div class="grid gap-5 lg:grid-cols-[minmax(0,1.35fr)_minmax(0,1fr)]">
<Card>
<CardHeader class="flex-row items-start justify-between gap-3">
<div class="flex items-start gap-3">
<span class="rounded-lg bg-blue-50 p-2 text-primary">
<ChartNoAxesCombined class="size-5" aria-hidden="true">
</ChartNoAxesCombined>
</span>
<div>
<CardTitle>Repair activity</CardTitle>
<CardDescription>Requests submitted · Philippine time</CardDescription>
</div>
</div>
<span class="shrink-0 rounded-lg border bg-white px-2.5 py-1.5 text-[10px] font-semibold text-slate-600">Last 14 days</span>
</CardHeader>
<CardContent>
<template v-if="activity?.some(day => day.count > 0)">
<svg viewBox="0 0 560 210" role="img" aria-label="Daily repair request counts for the last fourteen days" class="h-auto w-full">
<defs>
<linearGradient :id="fillId" x1="0" x2="0" y1="0" y2="1">
<stop offset="0%" stop-color="#2563EB" stop-opacity="0.22">
</stop>
<stop offset="100%" stop-color="#2563EB" stop-opacity="0.015">
</stop>
</linearGradient>
</defs>
<template v-for="(tick) in [0, 1, 2, 3, 4]" :key="tick">
<g>
<line x1="32" x2="550" :y1="170 - tick * 38" :y2="170 - tick * 38" stroke="#E2E8F0" stroke-dasharray="3 5">
</line>
<text x="22" :y="174 - tick * 38" text-anchor="end" fill="#64748B" font-size="10">{{ maximum * tick / 4 }}</text>
</g>
</template>
<polygon :points="`38,170 ${line} ${points.at(-1)?.x ?? 546},170`" :fill="`url(#${fillId})`">
</polygon>
<polyline :points="line" fill="none" stroke="#2563EB" stroke-width="2.5" stroke-linejoin="round" stroke-linecap="round">
</polyline>
<template v-for="(day, index) in points" :key="day.date">
<g>
<circle :cx="day.x" :cy="day.y" r="3" fill="#2563EB" stroke="white" stroke-width="1.5">
<title>{{ day.date }}: {{ day.count }} requests</title>
</circle>
<template v-if="index === 0 || index === points.length - 1 || index % 4 === 0">
<text :x="day.x" y="194" text-anchor="middle" fill="#64748B" font-size="10">{{ day.date.slice(5) }}</text>
</template>
</g>
</template>
</svg>
<details class="mt-2 text-xs text-slate-500">
<summary class="w-fit py-2 font-medium text-primary">View activity data</summary>
<div class="mt-2 max-h-48 overflow-auto rounded-lg border">
<table class="w-full text-left text-xs">
<caption class="sr-only">Daily repair submissions in Philippine time</caption>
<thead>
<tr class="border-b bg-slate-50">
<th class="px-3 py-2">Date</th>
<th class="px-3 py-2">Requests</th>
</tr>
</thead>
<tbody>
<template v-for="(day) in activity" :key="day.date">
<tr class="border-b last:border-0">
<td class="px-3 py-2">{{ day.date }}</td>
<td class="px-3 py-2 tabular-nums">{{ day.count }}</td>
</tr>
</template>
</tbody>
</table>
</div>
</details>
</template>
<template v-else>
<Empty :title="activity ? 'No requests in this period' : 'Activity unavailable'" :description="activity ? 'Requests submitted in the last fourteen days will appear here.' : 'Daily activity has not been returned by the server.'">
</Empty>
</template>
</CardContent>
</Card>
<Card>
<CardHeader class="flex-row items-start gap-3">
<span class="rounded-lg bg-indigo-50 p-2 text-indigo-600">
<ChartPie class="size-5" aria-hidden="true">
</ChartPie>
</span>
<div>
<CardTitle>Repair progress</CardTitle>
<CardDescription>Status of your repair requests.</CardDescription>
</div>
</CardHeader>
<CardContent>
<template v-if="total">
<div class="flex flex-wrap items-center justify-center gap-6 py-3">
<svg viewBox="0 0 120 120" role="img" :aria-label="`Repair progress: ${categories.map(category => `${category.label} ${category.count}`).join(', ')}`" class="size-36 shrink-0">
<circle cx="60" cy="60" r="42" fill="none" stroke="#F1F5F9" stroke-width="13">
</circle>
<template v-for="(arc) in arcs.filter(arc => arc.count > 0)" :key="arc.label">
<circle cx="60" cy="60" r="42" fill="none" :stroke="arc.color" stroke-width="13" :stroke-dasharray="`${arc.count / total * 263.894} 263.894`" :stroke-dashoffset="-arc.offset * 263.894" transform="rotate(-90 60 60)">
</circle>
</template>
<text x="60" y="59" text-anchor="middle" fill="#0B1F41" font-size="24" font-weight="600">{{ total }}</text>
<text x="60" y="76" text-anchor="middle" fill="#64748B" font-size="9">Total requests</text>
</svg>
<dl class="min-w-[125px] flex-1 space-y-3">
<template v-for="(category) in categories" :key="category.label">
<div class="flex items-center justify-between gap-3 rounded-lg border border-slate-100 bg-slate-50/80 px-2.5 py-2 text-xs">
<dt class="flex items-center gap-2 text-slate-500">
<span class="size-2 rounded-full" :style="{
  backgroundColor: category.color
}" aria-hidden="true">
</span>{{ category.label }}</dt>
<dd class="font-semibold tabular-nums text-navy">{{ category.count }}</dd>
</div>
</template>
</dl>
</div>
</template>
<template v-else>
<Empty title="No repair activity yet" description="Your real repair status distribution will appear here after a request is submitted.">
</Empty>
</template>
<p class="mt-3 text-[11px] leading-5 text-slate-500">Closed includes canceled and rejected requests. In progress includes accepted requests through pickup.</p>
</CardContent>
</Card>
</div>
</template>
