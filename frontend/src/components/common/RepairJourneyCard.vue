<script setup lang="ts">
import type { Review } from '@/types';
import { BadgeCheck, Check, Wrench, Search, Laptop, Smartphone, ClipboardCheck } from 'lucide-vue-next';
</script>

<template>
<div class="relative mx-auto w-full max-w-md pb-5 pl-3 sm:pl-5">
<div class="absolute inset-x-6 bottom-0 top-6 rounded-2xl border border-blue-100 bg-blue-50" aria-hidden="true">
</div>
<div class="relative overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-[var(--surface-shadow)]">
<div class="bg-navy p-6 text-white sm:p-7">
<div class="flex items-center justify-between">
<span class="flex size-11 items-center justify-center rounded-2xl border border-white/15 bg-white/10">
<Wrench class="size-5" aria-hidden="true">
</Wrench>
</span>
<div class="flex gap-2 text-slate-300">
<Laptop class="size-5" aria-hidden="true">
</Laptop>
<Smartphone class="size-5" aria-hidden="true">
</Smartphone>
</div>
</div>
<p class="mt-6 text-[10px] font-semibold uppercase tracking-[0.2em] text-blue-300">A simpler repair experience</p>
<h2 class="mt-2 text-2xl font-semibold leading-snug tracking-tight">Local expertise.<br>
</br>Every step connected.</h2>
</div>
<ol class="space-y-0 px-6 py-3 sm:px-7">
<template v-for="({
  title,
  text,
  icon: Icon
}, index) in [{
  title: 'Find your specialist',
  text: 'Match your device, problem and location.',
  icon: Search
}, {
  title: 'Connect with confidence',
  text: 'Review profiles, skills and availability.',
  icon: BadgeCheck
}, {
  title: 'Stay in the loop',
  text: 'Approve estimates and track progress.',
  icon: ClipboardCheck
}]" :key="title">
<li class="flex gap-4 border-b border-slate-100 py-5 last:border-0">
<span class="relative flex size-10 shrink-0 items-center justify-center rounded-xl bg-blue-50 text-primary">
<component :is="Icon" class="size-[18px]" aria-hidden="true">
</component>
<span class="absolute -right-1 -top-1 flex size-4 items-center justify-center rounded-full bg-white text-[9px] font-bold text-slate-500 ring-1 ring-slate-200">{{ index + 1 }}</span>
</span>
<div>
<h3 class="text-sm font-semibold text-slate-800">{{ title }}</h3>
<p class="mt-1 text-xs leading-5 text-slate-500">{{ text }}</p>
</div>
</li>
</template>
</ol>
<div class="flex items-center gap-2 border-t bg-slate-50 px-6 py-4 text-xs text-slate-600 sm:px-7">
<Check class="size-4 text-emerald-600" aria-hidden="true">
</Check>Your choice of technician. A clearer next step.</div>
</div>
</div>
</template>
