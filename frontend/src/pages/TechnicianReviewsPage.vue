<script setup lang="ts">
import { ref } from 'vue';
import { useApiQuery } from '@/composables/query';
import { api } from '@/services/api';
import type { Page, Review } from '@/types';
import { Star } from 'lucide-vue-next';
import Avatar from '@/components/ui/Avatar.vue';
import Loading from '@/components/common/Loading.vue';
import Failure from '@/components/common/Failure.vue';
import Empty from '@/components/common/Empty.vue';
import Pagination from '@/components/common/Pagination.vue';
import PageHeading from '@/components/common/PageHeading.vue';
const page = ref(1);
const query = useApiQuery<Page<Review>>(() => ({
  queryKey: ['my-reviews', page.value],
  queryFn: async ({
    signal
  }) => (await api.get<Page<Review>>('/technician/reviews?page=' + page.value, {
    signal
  })).data
}));
</script>

<template>
<PageHeading eyebrow="Customer feedback" title="Customer reviews" description="Read feedback from the customers whose repairs you completed.">
</PageHeading>
<template v-if="query.isPending">
<Loading>
</Loading>
</template>
<template v-else>
<template v-if="query.isError">
<Failure :error="query.error" :retry="() => void query.refetch()">
</Failure>
</template>
<template v-else>
<div class="mt-7 space-y-4">
<template v-if="query.data.data.length">
<template v-for="(r) in query.data.data" :key="r.id">
<article class="surface-card p-6">
<div class="flex items-center justify-between gap-4">
<div class="flex items-center gap-3">
<Avatar :name="r.customer?.name || 'Customer'" size="sm">
</Avatar>
<h2 class="text-sm font-semibold text-navy">{{ r.customer?.name || 'Customer' }}</h2>
</div>
<span class="flex items-center gap-1.5 text-xs font-semibold text-slate-600">
<Star class="size-3.5 fill-amber-400 text-amber-400" aria-hidden="true">
</Star>{{ r.rating }}/5</span>
</div>
<p class="mt-4 whitespace-pre-line break-words text-sm leading-7 text-muted-foreground">{{ r.body }}</p>
</article>
</template>
</template>
<template v-else>
<Empty title="No reviews yet" description="Customers can leave a review after completing a repair.">
</Empty>
</template>
<Pagination :page="query.data" :onChange="(value) => page = value">
</Pagination>
</div>
</template>
</template>
</template>
