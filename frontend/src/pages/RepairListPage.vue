<script setup lang="ts">
import { computed, ref, watch } from 'vue';
import { RouterLink as Link } from 'vue-router';
import { useApiQuery } from '@/composables/query';
import { useAuthStore as useAuth } from '@/stores/auth';
import { api } from '@/services/api';
import { fieldClass } from '@/components/ui/fields';
import type { Repair, Page, Review } from '@/types';
import { ArrowRight } from 'lucide-vue-next';
import Button from '@/components/ui/Button.vue';
import Card from '@/components/ui/Card.vue';
import Loading from '@/components/common/Loading.vue';
import Failure from '@/components/common/Failure.vue';
import Pagination from '@/components/common/Pagination.vue';
import PageHeading from '@/components/common/PageHeading.vue';
import RepairTable from '@/components/common/RepairTable.vue';
const props = withDefaults(defineProps<{
  initialFilter?: 'all' | 'active' | 'history';
}>(), {
  initialFilter: 'all'
});
const initialFilter = computed(() => props.initialFilter);
const page = ref(1),
  status = ref<string>(props.initialFilter);
const auth = useAuth();
watch(() => props.initialFilter, value => {
  status.value = value;
  page.value = 1;
});
const query = useApiQuery<Page<Repair>>(() => ({
  queryKey: ['repairs', page.value, status.value],
  queryFn: async ({
    signal
  }) => (await api.get<Page<Repair>>('/repair-requests?page=' + page.value + '&status=' + status.value, {
    signal
  })).data
}));
</script>

<template>
<PageHeading eyebrow="Repair workspace" :title="initialFilter === 'history' ? 'Repair history' : initialFilter === 'active' ? 'Active repairs' : auth.user?.role === 'technician' ? 'Repair requests' : 'My repair requests'" description="Review requests, follow progress and open the details to take the next step.">
<template #action>
<template v-if="auth.user?.role === 'customer'">
<Button as-child>
<Link to="/dashboard/repairs/new">New repair request<ArrowRight aria-hidden="true">
</ArrowRight>
</Link>
</Button>
</template>
</template>
</PageHeading>
<label class="mt-6 block max-w-xs text-sm">View<select :class="`${fieldClass} mt-2`" :value="status" @change="(e: Event) => {
  status = (e.target as HTMLInputElement).value;
  page = 1;
}">
<option value="all">All requests</option>
<option value="active">Active repairs</option>
<option value="history">Repair history</option>
<option value="pending">Pending requests</option>
</select>
</label>
<div class="mt-6">
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
<Card class="overflow-hidden">
<RepairTable :repairs="query.data.data" :technicianView="auth.user?.role === 'technician'">
</RepairTable>
</Card>
<Pagination :page="query.data" :onChange="(value) => page = value">
</Pagination>
</template>
</template>
</div>
</template>
