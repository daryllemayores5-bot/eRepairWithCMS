<script setup lang="ts">
import { computed, reactive, ref } from 'vue';
import { useQueryClient } from '@tanstack/vue-query';
import { useWorkshopAccess } from '@/composables/workshop';
import { useApiQuery } from '@/composables/query';
import { api, initializeCsrf } from '@/services/api';
import { errorMessage } from '@/lib/errors';
import { formatBusinessDate, formatPeso } from '@/lib/format';
import type { Page } from '@/types';
import type { InventoryPart, InventoryMovement, InventoryResponse, WorkshopRepair } from '@/types/workshop';
import PageHeading from '@/components/common/PageHeading.vue';
import Loading from '@/components/common/Loading.vue';
import Failure from '@/components/common/Failure.vue';
import Empty from '@/components/common/Empty.vue';
import Pagination from '@/components/common/Pagination.vue';
import PlanToolGate from '@/components/technician/PlanToolGate.vue';
import Button from '@/components/ui/Button.vue';
import Input from '@/components/ui/Input.vue';
import { fieldClass } from '@/components/ui/fields';

const { auth, access, premium } = useWorkshopAccess();
const cache = useQueryClient();
const page = ref(1), movementPage = ref(1), repairPage = ref(1), lowOnly = ref(false);
const busy = ref(false), failure = ref(''), success = ref('');
const selected = ref<InventoryPart>(), kind = ref<'restock' | 'usage'>('restock');
const units = ref(1), cost = ref('0.00'), repairId = ref(''), note = ref('');
const movementKey = ref(''), movementSignature = ref('');
const openingKey = ref(''), openingSignature = ref('');
const form = reactive({ sku: '', name: '', quantity: 0, reorder_level: 2, unit_cost: '0.00' });
const parts = useApiQuery<InventoryResponse>(() => ({
  queryKey: ['inventory', auth.user?.id, premium.value, page.value, lowOnly.value], enabled: premium.value,
  queryFn: async ({ signal }) => (await api.get<InventoryResponse>('/technician/inventory', { signal, params: { page: page.value, low_stock: lowOnly.value ? 1 : 0 } })).data,
}));
const movements = useApiQuery<Page<InventoryMovement>>(() => ({
  queryKey: ['inventory-movements', auth.user?.id, premium.value, movementPage.value], enabled: premium.value,
  queryFn: async ({ signal }) => (await api.get<{ data: Page<InventoryMovement> }>('/technician/inventory-movements', { signal, params: { page: movementPage.value } })).data.data,
}));
const repairs = useApiQuery<Page<WorkshopRepair>>(() => ({
  queryKey: ['stockable-repairs', auth.user?.id, premium.value, repairPage.value], enabled: premium.value,
  queryFn: async ({ signal }) => (await api.get<{ data: Page<WorkshopRepair> }>('/technician/workshop/repairs', { signal, params: { stockable: 1, page: repairPage.value } })).data.data,
}));
const rows = computed(() => parts.data?.data.data ?? []);
async function refresh() {
  await Promise.all(['inventory', 'inventory-movements', 'business-report'].map(key => cache.invalidateQueries({ queryKey: [key, auth.user?.id] })));
}
async function create() {
  if (busy.value) return;
  busy.value = true; failure.value = ''; success.value = '';
  const signature = JSON.stringify(form);
  if (!openingKey.value || openingSignature.value !== signature) { openingKey.value = crypto.randomUUID(); openingSignature.value = signature; }
  try {
    await initializeCsrf();
    await api.post('/technician/inventory', { ...form, idempotency_key: openingKey.value });
    Object.assign(form, { sku: '', name: '', quantity: 0, reorder_level: 2, unit_cost: '0.00' });
    openingKey.value = ''; await refresh(); success.value = 'Part added to your inventory.';
  } catch (error) { failure.value = errorMessage(error); }
  finally { busy.value = false; }
}
function openMovement(part: InventoryPart, action: 'restock' | 'usage') {
  selected.value = part; kind.value = action; units.value = 1; cost.value = part.unit_cost; repairId.value = ''; note.value = '';
  movementKey.value = ''; movementSignature.value = '';
  document.getElementById('stock-movement')?.scrollIntoView({ behavior: 'smooth', block: 'center' });
}
async function record() {
  if (busy.value || !selected.value) return;
  busy.value = true; failure.value = ''; success.value = '';
  const data = { kind: kind.value, quantity: units.value, note: note.value, ...(kind.value === 'usage' ? { repair_request_id: Number(repairId.value) } : { unit_cost: cost.value }) };
  const signature = JSON.stringify({ part: selected.value.id, ...data });
  if (!movementKey.value || movementSignature.value !== signature) { movementKey.value = crypto.randomUUID(); movementSignature.value = signature; }
  try {
    await initializeCsrf();
    await api.post('/technician/inventory/' + selected.value.id + '/movements', { ...data, idempotency_key: movementKey.value });
    selected.value = undefined; movementKey.value = ''; await refresh(); success.value = 'Stock movement recorded. Quantities and low-stock alerts are updated.';
  } catch (error) { failure.value = errorMessage(error); }
  finally { busy.value = false; }
}
async function update(part: InventoryPart, event: Event) {
  if (busy.value) return;
  const data = new FormData(event.currentTarget as HTMLFormElement);
  busy.value = true; failure.value = ''; success.value = '';
  try {
    await initializeCsrf();
    await api.patch('/technician/inventory/' + part.id, { name: String(data.get('name')), reorder_level: Number(data.get('reorder_level')), active: data.get('active') === 'on' });
    await refresh(); success.value = 'Part settings updated. Stock history is retained.';
  } catch (error) { failure.value = errorMessage(error); }
  finally { busy.value = false; }
}
</script>

<template>
<PageHeading eyebrow="Premium shop management" title="Parts Inventory" description="Track spare parts, record parts used on repairs, and see what needs restocking." />
<Empty v-if="auth.user?.role !== 'technician'" title="Technician account required" description="Inventory belongs to an individual technician workspace." />
<Loading v-else-if="access.isPending" />
<Failure v-else-if="access.isError" :error="access.error" :retry="() => access.refetch()" />
<PlanToolGate v-else-if="!premium" minimum="Premium" title="Manage the parts behind every repair" description="Keep your own stock list, deduct parts against your in-progress repairs, record restocking and receive low-stock alerts." />
<template v-else>
  <p v-if="failure" role="alert" class="my-4 rounded-xl bg-red-50 p-4 text-sm text-red-700">{{ failure }}</p>
  <p v-if="success" role="status" class="my-4 rounded-xl bg-green-50 p-4 text-sm text-green-800">{{ success }}</p>
  <div v-if="parts.data" role="status" class="my-6 rounded-xl border border-amber-200 bg-amber-50 p-4 text-sm text-amber-900"><strong>{{ parts.data.low_stock_count }} low-stock items.</strong> Active parts at or below their reorder level need attention, including items with zero stock.</div>
  <section class="mt-6 grid gap-6 xl:grid-cols-2">
    <form class="surface-card space-y-4 p-5 sm:p-6" @submit.prevent="create">
      <h2 class="section-title">Add a spare part</h2>
      <div class="grid gap-4 sm:grid-cols-2"><label class="text-sm">SKU<Input v-model="form.sku" class="mt-2" maxlength="64" pattern="[A-Za-z0-9._-]+" placeholder="BAT-IP11" required /></label><label class="text-sm">Part name<Input v-model="form.name" class="mt-2" maxlength="120" placeholder="iPhone 11 battery" required /></label></div>
      <div class="grid gap-4 sm:grid-cols-3"><label class="text-sm">Opening stock<input v-model.number="form.quantity" :class="fieldClass + ' mt-2'" type="number" min="0" max="1000000" step="1" required /></label><label class="text-sm">Reorder level<input v-model.number="form.reorder_level" :class="fieldClass + ' mt-2'" type="number" min="0" max="1000000" step="1" required /></label><label class="text-sm">Unit cost (PHP)<input v-model="form.unit_cost" :class="fieldClass + ' mt-2'" type="number" min="0" max="9999999999.99" step="0.01" required /></label></div>
      <Button type="submit" :disabled="busy">Add part</Button>
    </form>
    <section id="stock-movement" class="surface-card p-5 sm:p-6">
      <h2 class="section-title">Record a stock movement</h2>
      <p v-if="!selected" class="mt-4 text-sm leading-6 text-slate-500">Choose Restock or Use on repair on a part below. Usage is recorded only against your repairs currently marked In repair.</p>
      <form v-else class="mt-4 space-y-4" @submit.prevent="record">
        <p class="break-words text-sm font-semibold">{{ selected.name }} · {{ selected.sku }} · {{ selected.quantity }} on hand</p>
        <p class="text-sm">{{ kind === 'usage' ? 'Deduct parts used on a repair' : 'Add stock received' }}</p>
        <label class="block text-sm">Quantity<input v-model.number="units" :class="fieldClass + ' mt-2'" type="number" min="1" max="1000000" step="1" required /></label>
        <label v-if="kind === 'restock'" class="block text-sm">New unit cost (PHP)<input v-model="cost" :class="fieldClass + ' mt-2'" type="number" min="0" step="0.01" max="9999999999.99" required /></label>
        <div v-else>
          <Loading v-if="repairs.isPending" /><Failure v-else-if="repairs.isError" :error="repairs.error" :retry="() => repairs.refetch()" />
          <template v-else-if="repairs.data">
            <label class="block text-sm">Your in-progress repair<select v-model="repairId" :class="fieldClass + ' mt-2'" required><option value="">Choose a repair</option><option v-for="repair in repairs.data.data" :key="repair.id" :value="repair.id">{{ repair.reference }} · {{ repair.model }}</option></select></label>
            <p v-if="!repairs.data.data.length" class="mt-2 text-xs text-slate-500">No repairs in progress. Complete estimate approval first.</p>
            <Pagination :page="repairs.data" :on-change="value => { repairPage = value; repairId = ''; }" />
          </template>
        </div>
        <label class="block text-sm">Movement note (optional)<Input v-model="note" class="mt-2" maxlength="500" /></label>
        <div class="flex flex-wrap gap-3"><Button type="submit" :disabled="busy || (kind === 'usage' && !repairId)">Confirm {{ kind === 'usage' ? 'parts used' : 'restock' }}</Button><Button variant="outline" type="button" :disabled="busy" @click="selected = undefined">Cancel</Button></div>
      </form>
    </section>
  </section>
  <section class="surface-card mt-6 p-5 sm:p-6">
    <div class="flex flex-wrap items-center justify-between gap-4"><h2 class="section-title">My parts</h2><label class="flex items-center gap-2 text-sm"><input v-model="lowOnly" type="checkbox" @change="page = 1" />Show only low stock</label></div>
    <Loading v-if="parts.isPending" /><Failure v-else-if="parts.isError" :error="parts.error" :retry="() => parts.refetch()" />
    <template v-else-if="parts.data">
      <Empty v-if="!rows.length" title="No parts in this list" description="Add a part or turn off the low-stock filter." />
      <article v-for="part in rows" :key="part.id" class="mt-4 rounded-xl border p-4">
        <div class="flex flex-wrap items-center justify-between gap-3"><div class="min-w-0"><h3 class="break-words font-semibold">{{ part.name }}</h3><p class="mt-1 text-xs text-slate-500">{{ part.sku }} · Unit cost {{ formatPeso(part.unit_cost) }} · Reorder at {{ part.reorder_level }}</p></div><span :class="['rounded-full px-3 py-1 text-xs font-semibold', !part.active ? 'bg-slate-100 text-slate-500' : part.quantity <= part.reorder_level ? 'bg-amber-100 text-amber-900' : 'bg-green-50 text-green-800']">{{ part.quantity }} in stock{{ !part.active ? ' · Inactive' : part.quantity <= part.reorder_level ? ' · Low stock' : '' }}</span></div>
        <div class="mt-4 flex flex-wrap gap-2"><Button size="sm" :disabled="busy || !part.active" @click="openMovement(part, 'restock')">Restock</Button><Button size="sm" variant="outline" :disabled="busy || !part.active || part.quantity === 0" @click="openMovement(part, 'usage')">Use on repair</Button></div>
        <details class="mt-4 text-sm"><summary class="cursor-pointer text-primary">Edit name, reorder level or availability</summary><form class="mt-3 grid gap-3 sm:grid-cols-2" @submit.prevent="update(part, $event)"><label>Part name<input name="name" :value="part.name" :class="fieldClass + ' mt-2'" maxlength="120" required /></label><label>Reorder level<input name="reorder_level" :value="part.reorder_level" :class="fieldClass + ' mt-2'" type="number" min="0" max="1000000" required /></label><label class="flex items-center gap-2"><input name="active" type="checkbox" :checked="part.active" />Active part</label><Button type="submit" size="sm" :disabled="busy">Save part settings</Button></form></details>
      </article>
      <Pagination :page="parts.data.data" :on-change="value => page = value" />
    </template>
  </section>
  <section class="surface-card mt-6 p-5 sm:p-6">
    <h2 class="section-title">Stock movement history</h2>
    <p class="mt-2 text-xs leading-5 text-slate-500">Usage stores the unit cost at the time of deduction. Restocking sets the current unit cost for future usage; previous records are unchanged.</p>
    <Loading v-if="movements.isPending" /><Failure v-else-if="movements.isError" :error="movements.error" :retry="() => movements.refetch()" />
    <template v-else-if="movements.data">
      <Empty v-if="!movements.data.data.length" title="No stock movements yet" description="Opening quantities, restocking and parts usage appear here." />
      <article v-for="movement in movements.data.data" :key="movement.id" class="mt-4 border-t pt-4 text-sm"><p class="break-words font-semibold">{{ movement.part.name }} · {{ movement.kind }} · {{ movement.quantity }} units</p><p class="mt-1 text-xs text-slate-500">{{ formatBusinessDate(movement.created_at) }} · {{ formatPeso(movement.unit_cost) }} per unit</p><RouterLink v-if="movement.repair" :to="'/dashboard/repairs/' + movement.repair.id" class="mt-2 inline-block text-primary">{{ movement.repair.reference }}</RouterLink><p v-if="movement.note" class="mt-2 break-words text-slate-600">{{ movement.note }}</p></article>
      <Pagination :page="movements.data" :on-change="value => movementPage = value" />
    </template>
  </section>
</template>
</template>
