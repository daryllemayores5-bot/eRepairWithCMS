<script setup lang="ts">
import { errorMessage } from '@/lib/errors';
import Button from '@/components/ui/Button.vue';
defineProps<{
  error: unknown;
  retry?: () => unknown;
}>();
</script>

<template>
<div role="alert" class="my-6 rounded-xl border border-red-200 bg-red-50 p-6">
<p class="text-sm text-red-800">{{ errorMessage(error) }}</p>
<template v-if="retry">
<Button class="mt-4" variant="outline" @click="retry">Try again</Button>
</template>
</div>
</template>
