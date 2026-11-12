<script setup lang="ts">
import { useAttrs } from 'vue';
import { cn } from '@/lib/utils';
defineOptions({
  inheritAttrs: false
});
const attrs = useAttrs();
const props = defineProps<{
  defaultValue?: string | number;
}>();
const model = defineModel<string>();
if (model.value === undefined && props.defaultValue !== undefined) model.value = String(props.defaultValue);
function update(event: Event) {
  if (attrs.type !== 'file' && !('value' in attrs)) model.value = (event.target as HTMLInputElement).value;
}
</script>

<template>
<input data-slot="input" v-bind="attrs" :class="cn('flex h-11 w-full rounded-xl border border-slate-300/80 bg-white px-3.5 text-base text-slate-900 sm:text-sm shadow-xs outline-none transition-[border-color,box-shadow] placeholder:text-slate-400 hover:border-slate-400 focus-visible:border-primary focus-visible:ring-[3px] focus-visible:ring-blue-100 disabled:cursor-not-allowed disabled:bg-slate-50 disabled:opacity-60 aria-invalid:border-red-400 aria-invalid:ring-red-100',attrs.class as string)" :value="'value' in attrs ? attrs.value : model" @input="update" />
</template>
