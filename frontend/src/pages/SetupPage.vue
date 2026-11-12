<script setup lang="ts">
import { useApiQuery } from '@/composables/query';
import { getHealth } from '@/services/api';
import { Check, RefreshCw } from 'lucide-vue-next';
import Button from '@/components/ui/Button.vue';
import PageHeading from '@/components/common/PageHeading.vue';
const health = useApiQuery(() => ({
  queryKey: ['system-health'],
  queryFn: getHealth,
  retry: false
}));
</script>

<template>
<section class="page-shell max-w-3xl">
<PageHeading eyebrow="Application connectivity" title="Check your connection" description="This check calls the Laravel API and verifies its PostgreSQL connection.">
</PageHeading>
<div class="mt-8 surface-card p-7" role="status" aria-live="polite">
<template v-if="health.isPending">
<p>Connecting to Laravel…</p>
</template>
<template v-else>
<template v-if="health.isError">
<h2 class="font-semibold text-red-700">Backend is unavailable</h2>
<p class="mt-3 text-sm leading-6 text-muted-foreground">Laravel or PostgreSQL is unavailable. Start the backend and database, then try again.</p>
</template>
<template v-else>
<h2 class="font-semibold text-green-700">Laravel API is responding</h2>
<p class="mt-3 text-sm">PostgreSQL: {{ health.data.database }}</p>
</template>
</template>
<Button class="mt-6" variant="outline" :disabled="health.isFetching" @click="() => void health.refetch()">
<RefreshCw aria-hidden="true">
</RefreshCw>Check again</Button>
</div>
</section>
</template>
