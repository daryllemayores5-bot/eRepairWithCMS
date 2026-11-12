<script setup lang="ts">
defineProps<{
  title: string;
  description?: string;
  eyebrow?: string;
}>();
</script>

<template>
<div class="flex flex-wrap items-end justify-between gap-4">
<div class="max-w-2xl">
<p class="page-heading-eyebrow text-[10px] font-bold uppercase tracking-[0.15em] text-primary">{{ eyebrow }}</p>
<h2 class="mt-3 text-2xl font-bold tracking-tight text-navy sm:text-3xl">{{ title }}</h2>
<template v-if="description">
<p class="mt-3 text-sm leading-7 text-muted-foreground">{{ description }}</p>
</template>
</div>
<slot name="action" />
</div>
</template>
