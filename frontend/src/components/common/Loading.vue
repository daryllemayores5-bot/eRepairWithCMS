<script setup lang="ts">
defineOptions({
  name: 'Loading'
});
</script>

<template>
<div role="status" class="space-y-4 py-8">
<span class="sr-only">Loading…</span>
<template v-for="(n) in [1, 2, 3]" :key="n">
<div class="h-28 animate-pulse rounded-2xl bg-slate-200">
</div>
</template>
</div>
</template>
