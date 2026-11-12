<script setup lang="ts">
import Card from '@/components/ui/Card.vue';
import type { Component } from 'vue';
const props = defineProps<{
  label: string;
  value: number | string;
  detail: string;
  icon: Component;
  tone: string;
}>();
</script>

<template>
<Card class="metric-card flex items-start gap-4 p-4 transition-shadow hover:shadow-md sm:p-5">
<span :class="`flex size-12 shrink-0 items-center justify-center rounded-2xl ${tone}`">
<component :is="props.icon" class="size-6" aria-hidden="true">
</component>
</span>
<div class="min-w-0">
<p class="text-xs font-semibold leading-5 text-slate-600">{{ label }}</p>
<p class="mt-1 text-[1.75rem] font-bold leading-none tracking-tight text-navy tabular-nums">{{ value }}</p>
<p class="mt-2 text-[11px] leading-5 text-slate-500">{{ detail }}</p>
</div>
</Card>
</template>
