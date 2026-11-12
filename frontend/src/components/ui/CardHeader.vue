<script setup lang="ts">
import { useAttrs } from 'vue';
import { cn } from '@/lib/utils';
defineOptions({
  inheritAttrs: false
});
const attrs = useAttrs();
</script>

<template>
<div data-slot="card-header" v-bind="attrs" :class="cn('flex flex-col gap-1.5 p-5 pb-4 sm:p-6 sm:pb-4',attrs.class as string)">
<slot />
</div>
</template>
