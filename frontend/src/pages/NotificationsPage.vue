<script setup lang="ts">
import { ref } from 'vue';
import { RouterLink as Link } from 'vue-router';
import { useQueryClient } from '@tanstack/vue-query';
import { useApiQuery } from '@/composables/query';
import { api } from '@/services/api';
import { toast } from 'vue-sonner';
import { errorMessage } from '@/lib/errors';
import { formatBusinessDate } from '@/lib/format';
import type { Page, Notice } from '@/types';
import Button from '@/components/ui/Button.vue';
import Loading from '@/components/common/Loading.vue';
import Failure from '@/components/common/Failure.vue';
import Empty from '@/components/common/Empty.vue';
import Pagination from '@/components/common/Pagination.vue';
import PageHeading from '@/components/common/PageHeading.vue';
const page = ref(1),
  cache = useQueryClient();
const marking = ref(new Set<string>());
async function markRead(id: string) {
  if (marking.value.has(id)) return;
  marking.value.add(id);
  try {
    await api.patch(`/notifications/${id}/read`);
    await cache.invalidateQueries({ queryKey: ['notifications'] });
  } catch (error) {
    toast.error(errorMessage(error));
  } finally {
    marking.value.delete(id);
  }
}
const query = useApiQuery<Page<Notice>>(() => ({
  queryKey: ['notifications', page.value],
  queryFn: async ({
    signal
  }) => (await api.get<Page<Notice>>('/notifications?page=' + page.value, {
    signal
  })).data
}));
</script>

<template>
<PageHeading eyebrow="Your inbox" title="Notifications" description="Repair and appointment updates, all in one place.">
</PageHeading>
<template v-if="query.isPending">
<Loading>
</Loading>
</template>
<template v-else>
<template v-if="query.isError">
<Failure :error="query.error" :retry="() => query.refetch()">
</Failure>
</template>
<template v-else>
<div class="mt-7">
<template v-if="query.data.data.length">
<template v-for="(n) in query.data.data" :key="n.id">
<article :class="`mb-4 rounded-xl border p-5 ${n.read_at ? 'bg-white' : 'border-blue-200 bg-blue-50'}`">
<Link class="break-words font-medium leading-7 text-navy underline-offset-4 hover:underline" :to="`/dashboard/repairs/${n.data.repair_request_id}`">{{ n.data.message }}</Link>
<p class="mt-2 text-xs text-muted-foreground">{{ formatBusinessDate(n.created_at) }}</p>
<template v-if="!n.read_at">
<Button variant="ghost" size="sm" class="mt-3" :disabled="marking.has(n.id)" @click="() => markRead(n.id)">{{ marking.has(n.id) ? 'Marking as read…' : 'Mark as read' }}</Button>
</template>
</article>
</template>
</template>
<template v-else>
<Empty title="You are all caught up" description="Repair and appointment updates will appear here.">
</Empty>
</template>
<Pagination :page="query.data" :onChange="(value) => page = value">
</Pagination>
</div>
</template>
</template>
</template>
