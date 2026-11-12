<script setup lang="ts">
import { useAttrs } from 'vue';
import { SlotRoot } from './SlotRoot';
import { buttonVariants } from './button-variants';
import { cn } from '@/lib/utils';
defineOptions({
  inheritAttrs: false
});
const props = withDefaults(defineProps<{
  variant?: 'default' | 'outline' | 'ghost' | 'destructive';
  size?: 'default' | 'sm' | 'lg' | 'icon';
  asChild?: boolean;
}>(), {
  variant: 'default',
  size: 'default',
  asChild: false
});
const attrs = useAttrs();
</script>

<template>
<component :is="props.asChild ? SlotRoot : 'button'" v-bind="{...attrs,class:cn(buttonVariants({variant:props.variant,size:props.size}),attrs.class as string)}" data-slot="button">
<slot />
</component>
</template>
