<script setup lang="ts">
import { ref } from 'vue';
import { useQueryClient } from '@tanstack/vue-query';
import { useWorkshopAccess } from '@/composables/workshop';
import { useApiQuery } from '@/composables/query';
import { api, initializeCsrf } from '@/services/api';
import { downloadWorkshopFile } from '@/services/workshop-download';
import { errorMessage } from '@/lib/errors';
import { statusLabel } from '@/lib/status';
import type { Page } from '@/types';
import type { NoteTemplate, WorkshopRepair } from '@/types/workshop';
import PageHeading from '@/components/common/PageHeading.vue';
import Loading from '@/components/common/Loading.vue';
import Failure from '@/components/common/Failure.vue';
import Empty from '@/components/common/Empty.vue';
import Pagination from '@/components/common/Pagination.vue';
import PlanToolGate from '@/components/technician/PlanToolGate.vue';
import Button from '@/components/ui/Button.vue';
import Input from '@/components/ui/Input.vue';
import { fieldClass } from '@/components/ui/fields';

const { auth, access, paid } = useWorkshopAccess();
const cache = useQueryClient();
const page = ref(1), search = ref(''), appliedSearch = ref('');
const title = ref(''), body = ref(''), editing = ref<number>(), deleting = ref<number>();
const busy = ref(false), failure = ref(''), success = ref('');
const templates = useApiQuery<NoteTemplate[]>(() => ({
  queryKey: ['note-templates', auth.user?.id, paid.value], enabled: paid.value,
  queryFn: async ({ signal }) => (await api.get<{ data: NoteTemplate[] }>('/technician/note-templates', { signal })).data.data,
}));
const repairs = useApiQuery<Page<WorkshopRepair>>(() => ({
  queryKey: ['document-repairs', auth.user?.id, paid.value, page.value, appliedSearch.value], enabled: paid.value,
  queryFn: async ({ signal }) => (await api.get<{ data: Page<WorkshopRepair> }>('/technician/workshop/repairs', { signal, params: { page: page.value, search: appliedSearch.value } })).data.data,
}));
async function save() {
  if (busy.value) return;
  busy.value = true; failure.value = ''; success.value = '';
  try {
    await initializeCsrf();
    const payload = { title: title.value, body: body.value };
    if (editing.value) await api.put('/technician/note-templates/' + editing.value, payload);
    else await api.post('/technician/note-templates', payload);
    title.value = ''; body.value = ''; editing.value = undefined;
    await cache.invalidateQueries({ queryKey: ['note-templates', auth.user?.id] });
    success.value = 'Template saved. Select it when adding a note to one of your repairs.';
  } catch (error) { failure.value = errorMessage(error); }
  finally { busy.value = false; }
}
function edit(template: NoteTemplate) { editing.value = template.id; title.value = template.title; body.value = template.body; document.getElementById('template-title')?.focus(); }
async function remove(id: number) {
  if (busy.value) return;
  busy.value = true; failure.value = ''; success.value = '';
  try {
    await initializeCsrf(); await api.delete('/technician/note-templates/' + id);
    deleting.value = undefined;
    if (editing.value === id) { editing.value = undefined; title.value = ''; body.value = ''; }
    await cache.invalidateQueries({ queryKey: ['note-templates', auth.user?.id] });
    success.value = 'Template deleted. Notes already posted to repairs are unchanged.';
  } catch (error) { failure.value = errorMessage(error); }
  finally { busy.value = false; }
}
async function download(repair: WorkshopRepair, kind: 'quotation' | 'job-sheet') {
  if (busy.value) return;
  busy.value = true; failure.value = ''; success.value = '';
  try {
    await downloadWorkshopFile('/technician/workshop/repairs/' + repair.id + '/documents/' + kind, repair.reference + '-' + kind + '.pdf');
    success.value = 'PDF downloaded.';
  } catch (error) { failure.value = errorMessage(error); }
  finally { busy.value = false; }
}
</script>

<template>
<PageHeading eyebrow="Pro productivity" title="Documents & Templates" description="Download professional repair documents and reuse your own repair notes. Included in Pro and Premium." />
<Empty v-if="auth.user?.role !== 'technician'" title="Technician account required" description="These tools belong to a technician workspace." />
<Loading v-else-if="access.isPending" />
<Failure v-else-if="access.isError" :error="access.error" :retry="() => access.refetch()" />
<PlanToolGate v-else-if="!paid" minimum="Pro" title="Create documents and save time on updates" description="Download PDF quotations and job sheets from your repairs. Save reusable notes for diagnosis, approval requests and pickup instructions." />
<template v-else>
  <p v-if="failure" role="alert" class="my-4 rounded-xl bg-red-50 p-4 text-sm text-red-700">{{ failure }}</p>
  <p v-if="success" role="status" class="my-4 rounded-xl bg-green-50 p-4 text-sm text-green-800">{{ success }}</p>
  <section class="surface-card mt-6 p-5 sm:p-6">
    <h2 class="section-title">PDF repair documents</h2>
    <p class="mt-2 text-sm leading-6 text-slate-500">A quotation uses the latest estimate. A job sheet includes the reported issue, appointment and repair history. These documents are not payment receipts.</p>
    <form class="mt-5 flex flex-wrap gap-3" @submit.prevent="appliedSearch = search; page = 1">
      <label class="min-w-0 flex-1 text-sm">Find a repair<Input v-model="search" class="mt-2" maxlength="100" placeholder="Reference or device model" /></label>
      <Button class="self-end" type="submit">Search</Button>
    </form>
    <Loading v-if="repairs.isPending" />
    <Failure v-else-if="repairs.isError" :error="repairs.error" :retry="() => repairs.refetch()" />
    <template v-else-if="repairs.data">
      <Empty v-if="!repairs.data.data.length" title="No matching repair requests" description="Your assigned repairs will appear here." />
      <article v-for="repair in repairs.data.data" :key="repair.id" class="mt-4 flex flex-wrap items-center justify-between gap-4 rounded-xl border p-4">
        <div class="min-w-0"><RouterLink :to="'/dashboard/repairs/' + repair.id" class="font-semibold text-primary">{{ repair.reference }}</RouterLink><p class="mt-1 break-words text-sm">{{ repair.model }} · {{ repair.customer.name }}</p><p class="mt-1 text-xs text-slate-500">{{ statusLabel(repair.status) }}</p></div>
        <div class="flex flex-wrap gap-2"><Button size="sm" variant="outline" :disabled="busy || !repair.estimates_count" @click="download(repair, 'quotation')">Download quotation</Button><Button size="sm" :disabled="busy" @click="download(repair, 'job-sheet')">Download job sheet</Button></div>
      </article>
      <Pagination :page="repairs.data" :on-change="value => page = value" />
    </template>
  </section>
  <section class="mt-6 grid gap-6 xl:grid-cols-2">
    <form class="surface-card space-y-4 p-5 sm:p-6" @submit.prevent="save">
      <h2 class="section-title">{{ editing ? 'Edit note template' : 'Create note template' }}</h2>
      <label class="block text-sm">Template title<Input id="template-title" v-model="title" class="mt-2" required maxlength="100" placeholder="For example: Ready for pickup" /></label>
      <label class="block text-sm">Reusable note<textarea v-model="body" :class="fieldClass + ' mt-2 min-h-32'" required maxlength="2000" placeholder="Write a note you can review and edit before posting to a repair." /></label>
      <div class="flex flex-wrap gap-3"><Button type="submit" :disabled="busy">{{ busy ? 'Saving…' : 'Save template' }}</Button><Button v-if="editing" variant="outline" type="button" :disabled="busy" @click="editing = undefined; title = ''; body = ''">Cancel edit</Button></div>
    </form>
    <div class="surface-card p-5 sm:p-6">
      <h2 class="section-title">My saved templates</h2>
      <Loading v-if="templates.isPending" />
      <Failure v-else-if="templates.isError" :error="templates.error" :retry="() => templates.refetch()" />
      <Empty v-else-if="!templates.data?.length" title="No templates yet" description="Create your first reusable repair note using the form." />
      <article v-for="template in templates.data" :key="template.id" class="mt-4 border-t pt-4">
        <h3 class="break-words font-semibold">{{ template.title }}</h3><p class="mt-2 whitespace-pre-wrap break-words text-sm text-slate-600">{{ template.body }}</p>
        <div class="mt-3 flex flex-wrap gap-2"><Button size="sm" variant="outline" :disabled="busy" @click="edit(template)">Edit template</Button><Button size="sm" variant="ghost" :disabled="busy" @click="deleting = template.id">Delete template</Button></div>
        <div v-if="deleting === template.id" class="mt-3 rounded-lg bg-red-50 p-3"><p class="text-sm">Delete this saved template?</p><div class="mt-2 flex gap-2"><Button size="sm" variant="destructive" :disabled="busy" @click="remove(template.id)">Confirm delete</Button><Button size="sm" variant="outline" :disabled="busy" @click="deleting = undefined">Keep template</Button></div></div>
      </article>
    </div>
  </section>
</template>
</template>
