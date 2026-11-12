<script setup lang="ts">
import { computed } from 'vue';
import type { Page } from '@/types';
import Button from '@/components/ui/Button.vue';
const props = defineProps<{
  page: Page<unknown>;
  onChange: (page: number) => void;
}>();
const current = computed(() => props.page.meta?.current_page ?? props.page.current_page ?? 1);
const last = computed(() => props.page.meta?.last_page ?? props.page.last_page ?? 1);
</script>

<template>
<nav aria-label="Pagination" class="mt-6 grid grid-cols-2 items-center gap-3 rounded-xl border border-slate-200/80 bg-white px-3 py-3 sm:flex sm:justify-between sm:px-4">
<Button type="button" variant="outline" size="sm" :disabled="current <= 1" @click="() => onChange(current - 1)">Previous</Button>
<span class="order-first col-span-2 text-center text-xs font-medium text-slate-500 sm:order-none" aria-live="polite">Page {{ current }} of {{ last }}</span>
<Button type="button" variant="outline" size="sm" :disabled="current >= last" @click="() => onChange(current + 1)">Next</Button>
</nav>
</template>
