<script setup lang="ts">
import { useAttrs } from 'vue';
import { cn } from '@/lib/utils';
defineOptions({
  inheritAttrs: false
});
const attrs = useAttrs();
</script>

<template>
<h2 data-slot="card-title" v-bind="attrs" :class="cn('text-[15px] font-semibold tracking-tight text-navy',attrs.class as string)">
<slot />
</h2>
</template>
