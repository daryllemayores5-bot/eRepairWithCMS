<script setup lang="ts">
import { Comment, Fragment, Text, useSlots, type VNode } from 'vue';
defineProps<{
  title: string;
  description?: string;
  eyebrow?: string;
}>();
const slots = useSlots();
function hasContent(nodes: VNode[]): boolean {
  return nodes.some(node => node.type === Fragment ? hasContent(node.children as VNode[]) : node.type !== Comment && (node.type !== Text || String(node.children).trim().length > 0));
}
function hasAction() {
  return hasContent(slots.action?.() ?? []);
}
</script>

<template>
<div class="flex flex-wrap items-start justify-between gap-5">
<div class="min-w-0 max-w-2xl">
<template v-if="eyebrow">
<p class="page-heading-eyebrow mb-3 text-[10px] font-bold uppercase tracking-[0.15em] text-primary">{{ eyebrow }}</p>
</template>
<h1 class="break-words text-2xl font-bold tracking-tight text-navy sm:text-3xl">{{ title }}</h1>
<template v-if="description">
<p class="mt-2 max-w-2xl text-sm leading-7 text-muted-foreground">{{ description }}</p>
</template>
</div>
<template v-if="hasAction()">
<div class="flex min-w-0 flex-wrap gap-2">
<slot name="action" />
</div>
</template>
</div>
</template>
