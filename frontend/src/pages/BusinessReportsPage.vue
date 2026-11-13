<script setup lang="ts">
import { computed, ref } from 'vue';
import { useWorkshopAccess } from '@/composables/workshop';
import { useApiQuery } from '@/composables/query';
import { api } from '@/services/api';
import { downloadWorkshopFile } from '@/services/workshop-download';
import { errorMessage } from '@/lib/errors';
import { formatWorkshopMoney as formatPeso } from '@/lib/workshop-money';
import { statusLabel } from '@/lib/status';
import type { BusinessReport } from '@/types/workshop';
import PageHeading from '@/components/common/PageHeading.vue';
import Loading from '@/components/common/Loading.vue';
import Failure from '@/components/common/Failure.vue';
import Empty from '@/components/common/Empty.vue';
import PlanToolGate from '@/components/technician/PlanToolGate.vue';
import Button from '@/components/ui/Button.vue';
import { fieldClass } from '@/components/ui/fields';

const { auth, access, premium } = useWorkshopAccess();
const today = new Intl.DateTimeFormat('en-CA', { timeZone: 'Asia/Manila', year: 'numeric', month: '2-digit', day: '2-digit' }).format(new Date());
const from = ref(today.slice(0, 7) + '-01'), through = ref(today);
const applied = ref({ from: from.value, through: through.value });
const downloading = ref(false), failure = ref('');
const report = useApiQuery<BusinessReport>(() => ({
  queryKey: ['business-report', auth.user?.id, premium.value, applied.value.from, applied.value.through], enabled: premium.value,
  queryFn: async ({ signal }) => (await api.get<{ data: BusinessReport }>('/technician/business-reports', { signal, params: applied.value })).data.data,
}));
const stats = computed(() => report.data ? [
  { label: 'Requests received', value: report.data.requests_received },
  { label: 'Repairs completed', value: report.data.completed_repairs },
  { label: 'Approved estimates on completed repairs', value: formatPeso(report.data.completed_approved_estimates) },
  { label: 'Parts used', value: report.data.parts_used },
  { label: 'Recorded parts cost', value: formatPeso(report.data.parts_cost) },
  { label: 'Current low-stock items', value: report.data.low_stock_items },
] : []);
async function download(format: 'pdf' | 'csv') {
  if (downloading.value) return;
  downloading.value = true; failure.value = '';
  try { await downloadWorkshopFile('/technician/business-reports/export', 'eRepair-business-' + applied.value.from + '-' + applied.value.through + '.' + format, { ...applied.value, format }); }
  catch (error) { failure.value = errorMessage(error); }
  finally { downloading.value = false; }
}
</script>

<template>
<PageHeading eyebrow="Premium business insights" title="Business Reports" description="Review your repair activity and recorded parts costs, then download a CSV or PDF report." />
<Empty v-if="auth.user?.role !== 'technician'" title="Technician account required" description="Business reports are scoped to an individual technician." />
<Loading v-else-if="access.isPending" />
<Failure v-else-if="access.isError" :error="access.error" :retry="() => access.refetch()" />
<PlanToolGate v-else-if="!premium" minimum="Premium" title="Understand your repair business" description="Filter your repair activity by date, review completed-job estimates and parts usage, and export reports as CSV or PDF." />
<template v-else>
  <form class="surface-card mt-6 flex flex-wrap items-end gap-4 p-5 sm:p-6" @submit.prevent="applied = { from, through }">
    <label class="min-w-0 flex-1 text-sm">From (Philippine date)<input v-model="from" :class="fieldClass + ' mt-2'" type="date" required /></label>
    <label class="min-w-0 flex-1 text-sm">Through (Philippine date)<input v-model="through" :class="fieldClass + ' mt-2'" type="date" required /></label>
    <Button type="submit" :disabled="report.isFetching">Apply dates</Button>
  </form>
  <p v-if="failure" role="alert" class="my-4 rounded-xl bg-red-50 p-4 text-sm text-red-700">{{ failure }}</p>
  <Loading v-if="report.isPending" /><Failure v-else-if="report.isError" :error="report.error" :retry="() => report.refetch()" />
  <template v-else-if="report.data">
    <section class="mt-6 grid gap-4 sm:grid-cols-2 xl:grid-cols-3" aria-label="Business summary"><article v-for="stat in stats" :key="stat.label" class="surface-card p-5"><p class="text-xs leading-5 text-slate-500">{{ stat.label }}</p><p class="mt-3 break-words text-2xl font-bold text-navy">{{ stat.value }}</p></article></section>
    <section class="surface-card mt-6 p-5 sm:p-6">
      <h2 class="section-title">Request statuses</h2>
      <p class="mt-2 text-sm text-slate-500">{{ report.data.from }} to {{ report.data.through }} · Based on request submission dates.</p>
      <Empty v-if="!report.data.requests_received" title="No repair requests in this period" description="Choose another date range. Earlier requests completed during this period can still appear in the completed-repair total." />
      <dl class="mt-5 grid gap-3 sm:grid-cols-2"><div v-for="(count, status) in report.data.status_counts" :key="status" class="flex items-center justify-between gap-3 rounded-xl bg-slate-50 p-4"><dt class="text-sm">{{ statusLabel(String(status)) }}</dt><dd class="font-bold">{{ count }}</dd></div></dl>
      <p class="mt-6 rounded-xl bg-blue-50 p-4 text-xs leading-6 text-blue-900">{{ report.data.scope }}</p>
      <p class="mt-3 text-xs leading-6 text-slate-500">Exports include up to 2,000 requests submitted in the selected period. Use a shorter range if needed. No customer email addresses or private attachments are included.</p>
      <div class="mt-5 flex flex-wrap gap-3"><Button :disabled="downloading" @click="download('csv')">{{ downloading ? 'Preparing…' : 'Download CSV report' }}</Button><Button variant="outline" :disabled="downloading" @click="download('pdf')">Download PDF report</Button></div>
    </section>
  </template>
</template>
</template>
