<script setup lang="ts">
import { ref } from 'vue';
import { RouterLink as Link } from 'vue-router';
import { useApiQuery } from '@/composables/query';
import { api } from '@/services/api';
import { formatBusinessDate } from '@/lib/format';
import type { Page, Appointment } from '@/types';
import Loading from '@/components/common/Loading.vue';
import Failure from '@/components/common/Failure.vue';
import Empty from '@/components/common/Empty.vue';
import Pagination from '@/components/common/Pagination.vue';
import Status from '@/components/common/Status.vue';
import PageHeading from '@/components/common/PageHeading.vue';
const page = ref(1);
const query = useApiQuery<Page<Appointment>>(() => ({
  queryKey: ['appointments', page.value],
  queryFn: async ({
    signal
  }) => (await api.get<Page<Appointment>>('/appointments?page=' + page.value, {
    signal
  })).data
}));
</script>

<template>
<PageHeading eyebrow="Your schedule" title="Appointments" description="All times are shown in Philippine time. Confirm or update appointments from the repair details.">
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
<div class="mt-6">
<template v-if="query.data.data.length">
<template v-for="(a) in query.data.data" :key="a.id">
<Link :to="`/dashboard/repairs/${a.repair_request_id}`" class="mb-4 block surface-card p-6">
<div class="flex flex-wrap items-center justify-between gap-3">
<h2 class="font-semibold">{{ a.repair?.reference }} · {{ a.repair?.model }}</h2>
<Status :value="a.status">
</Status>
</div>
<p class="mt-4 text-sm">{{ formatBusinessDate(a.starts_at) }} – {{ formatBusinessDate(a.ends_at) }}</p>
<p class="mt-3 text-sm text-muted-foreground">{{ a.service_location }}</p>
</Link>
</template>
</template>
<template v-else>
<Empty title="No appointments" description="Your proposed and confirmed repair appointments will appear here.">
</Empty>
</template>
<Pagination :page="query.data" :onChange="(value) => page = value">
</Pagination>
</div>
</template>
</template>
</template>
