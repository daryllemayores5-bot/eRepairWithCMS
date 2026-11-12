<script setup lang="ts">
import { computed } from 'vue';
import { statusLabel } from '@/lib/status';
const props = defineProps<{
  value: string;
}>();
const tone = computed(() => ['completed', 'approved', 'available', 'confirmed'].includes(props.value) ? 'bg-emerald-50 text-emerald-700 ring-emerald-600/15' : ['rejected'].includes(props.value) ? 'bg-red-50 text-red-700 ring-red-600/15' : ['pending', 'awaiting_customer_approval', 'proposed', 'busy'].includes(props.value) ? 'bg-amber-50 text-amber-800 ring-amber-600/15' : ['canceled', 'unavailable', 'superseded'].includes(props.value) ? 'bg-slate-100 text-slate-600 ring-slate-500/15' : 'bg-blue-50 text-blue-700 ring-blue-600/15');
</script>

<template>
<span :class="`inline-flex items-center gap-1.5 whitespace-nowrap rounded-full px-2.5 py-1 text-[11px] font-semibold ring-1 ring-inset ${tone}`">
<span class="size-1.5 rounded-full bg-current" aria-hidden="true">
</span>{{ statusLabel(value) }}</span>
</template>
