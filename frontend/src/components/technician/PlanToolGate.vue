<script setup lang="ts">
import { RouterLink } from 'vue-router';
import { LockKeyhole } from 'lucide-vue-next';
defineProps<{ minimum: 'Pro' | 'Premium'; title: string; description: string }>();
</script>

<template>
<section class="surface-card my-6 border-dashed p-6 sm:p-8">
  <span class="inline-flex items-center gap-2 rounded-full bg-slate-100 px-3 py-1 text-xs font-semibold text-slate-600"><LockKeyhole class="size-3.5" aria-hidden="true" />{{ minimum }} tool</span>
  <h2 class="mt-4 text-xl font-bold text-navy">{{ title }}</h2>
  <p class="mt-3 max-w-2xl text-sm leading-6 text-slate-600">{{ description }}</p>
  <p class="mt-3 text-xs leading-5 text-slate-500">Requires an active {{ minimum }} subscription. Saved records remain stored when a subscription expires.</p>
  <RouterLink to="/dashboard/subscription" class="mt-5 inline-flex min-h-11 items-center rounded-xl bg-primary px-5 text-sm font-semibold text-white">Compare plans and upgrade</RouterLink>
</section>
</template>
