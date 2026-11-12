<script setup lang="ts">
import { computed, useAttrs } from 'vue';
import { RouterLink, useRoute, useRouter, type RouteLocationRaw } from 'vue-router';
defineOptions({
  inheritAttrs: false
});
const props = defineProps<{
  to: RouteLocationRaw;
  end?: boolean;
  navClass?: string | ((state: {
    isActive: boolean;
  }) => string);
}>();
const attrs = useAttrs();
const route = useRoute();
const router = useRouter();
// Preserve the existing navigation's path matching across sibling page records.
const active = computed(() => {
  const current = route.path.toLowerCase();
  const target = router.resolve(props.to).path.toLowerCase();
  const boundary = target !== '/' && target.endsWith('/') ? target.length - 1 : target.length;
  return current === target || (!props.end && current.startsWith(target) && current.charAt(boundary) === '/');
});
</script>

<template>
<RouterLink :to="to" custom v-slot="{ href, navigate }">
    <a v-bind="attrs" :href="href" @click="navigate" :aria-current="active ? 'page' : undefined"
      :class="typeof navClass === 'function' ? navClass({ isActive: active }) : navClass">
<slot />
</a>
  </RouterLink>
</template>
