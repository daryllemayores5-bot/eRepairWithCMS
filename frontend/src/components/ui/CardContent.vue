<script setup lang="ts">
import { useAttrs } from 'vue';
import { cn } from '@/lib/utils';
defineOptions({
  inheritAttrs: false
});
const attrs = useAttrs();
</script>

<template>
<div data-slot="card-content" v-bind="attrs" :class="cn('px-5 pb-5 sm:px-6 sm:pb-6',attrs.class as string)">
<slot />
</div>
</template>
