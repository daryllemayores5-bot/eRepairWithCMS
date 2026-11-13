<script setup lang="ts">
import { ref } from 'vue';
import { useWorkshopAccess } from '@/composables/workshop';
import { downloadWorkshopFile } from '@/services/workshop-download';
import { errorMessage } from '@/lib/errors';
import type { Repair } from '@/types';
import Button from '@/components/ui/Button.vue';
defineProps<{ repair: Repair }>();
const { paid, access } = useWorkshopAccess();
const busy = ref(false), failure = ref('');
async function download(repair: Repair, kind: 'quotation' | 'job-sheet') {
  if (busy.value) return;
  busy.value = true; failure.value = '';
  try { await downloadWorkshopFile('/technician/workshop/repairs/' + repair.id + '/documents/' + kind, repair.reference + '-' + kind + '.pdf'); }
  catch (error) { failure.value = errorMessage(error); }
  finally { busy.value = false; }
}
</script>

<template>
<section class="surface-card p-6">
  <h2 class="section-title">Repair documents <span class="ml-2 rounded-full bg-blue-50 px-2 py-1 text-[10px] font-semibold text-primary">Pro + Premium</span></h2>
  <p v-if="access.isPending" class="mt-3 text-sm text-slate-500">Checking document access…</p>
  <p v-else-if="!paid" class="mt-3 text-sm leading-6 text-slate-500">Upgrade to Pro to download PDF quotations and job sheets. <RouterLink to="/dashboard/subscription" class="text-primary underline">View plans</RouterLink></p>
  <div v-else class="mt-4 flex flex-wrap gap-3"><Button size="sm" variant="outline" :disabled="busy || !repair.estimates?.length" @click="download(repair, 'quotation')">Download quotation PDF</Button><Button size="sm" :disabled="busy" @click="download(repair, 'job-sheet')">Download job sheet PDF</Button></div>
  <p v-if="failure" role="alert" class="mt-3 text-sm text-red-700">{{ failure }}</p>
  <RouterLink v-if="paid" to="/dashboard/documents" class="mt-4 inline-block text-xs text-primary">All documents and templates</RouterLink>
</section>
</template>
