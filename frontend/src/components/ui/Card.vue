<script setup lang="ts">
import { useAttrs } from 'vue';
import { cn } from '@/lib/utils';
defineOptions({
  inheritAttrs: false
});
const attrs = useAttrs();
</script>

<template>
<div data-slot="card" v-bind="attrs" :class="cn('rounded-2xl border border-slate-200/80 bg-white text-foreground shadow-[var(--surface-shadow)] transition-shadow hover:shadow-md',attrs.class as string)">
<slot />
</div>
</template>
