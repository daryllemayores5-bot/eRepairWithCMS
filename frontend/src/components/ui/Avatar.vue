<script setup lang="ts">
import { computed, ref } from 'vue';
import { cn } from '@/lib/utils';
const props = withDefaults(defineProps<{
  name: string;
  src?: string | null;
  size?: 'sm' | 'md' | 'lg';
}>(), {
  size: 'md'
});
const failed = ref<string | null>(null);
const dimensions = {
  sm: 'size-10 text-sm',
  md: 'size-14 text-lg',
  lg: 'size-24 text-3xl'
};
const initials = computed(() => props.name.trim().split(/\s+/).filter(Boolean).slice(0, 2).map(p => p[0]).join('').toUpperCase());
</script>

<template>
<img v-if="props.src && failed !== props.src" :src="props.src" :alt="props.name+'\'s profile photo'" @error="failed=props.src" loading="lazy" :class="cn('shrink-0 rounded-2xl border border-slate-200 bg-slate-50 object-cover',dimensions[props.size])" />
<span v-else aria-hidden="true" :class="cn('flex shrink-0 items-center justify-center rounded-2xl border border-blue-100 bg-blue-50 font-semibold text-primary',dimensions[props.size])">{{initials || '?'}}</span>
</template>
