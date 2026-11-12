<script setup lang="ts">
import { useAttrs } from 'vue';
import { cn } from '@/lib/utils';
defineOptions({
  inheritAttrs: false
});
const attrs = useAttrs();
</script>

<template>
<p data-slot="card-description" v-bind="attrs" :class="cn('text-xs leading-[1.75] text-muted-foreground',attrs.class as string)">
<slot />
</p>
</template>
