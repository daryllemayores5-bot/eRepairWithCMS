<script setup lang="ts">
import { Inbox } from 'lucide-vue-next';
defineProps<{
  title: string;
  description: string;
}>();
</script>

<template>
<div class="rounded-xl border border-dashed border-slate-200 bg-slate-50/60 px-6 py-8 text-center">
<span class="mx-auto flex size-11 items-center justify-center rounded-2xl border border-slate-200 bg-white text-slate-400 shadow-xs">
<Inbox class="size-5" aria-hidden="true">
</Inbox>
</span>
<h2 class="mt-4 text-sm font-semibold text-slate-800">{{ title }}</h2>
<p class="mx-auto mt-2 max-w-sm text-sm leading-6 text-muted-foreground">{{ description }}</p>
</div>
</template>
