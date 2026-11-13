<script setup lang="ts">
import { ref } from 'vue';
import { useWorkshopAccess } from '@/composables/workshop';
import { useApiQuery } from '@/composables/query';
import { api } from '@/services/api';
import type { NoteTemplate } from '@/types/workshop';
import Button from '@/components/ui/Button.vue';
import { fieldClass } from '@/components/ui/fields';
defineProps<{ busy: boolean }>();
const emit = defineEmits<{ submit: [remarks: string] }>();
const { auth, paid } = useWorkshopAccess();
const remarks = ref(''), selected = ref('');
const templates = useApiQuery<NoteTemplate[]>(() => ({
  queryKey: ['note-templates', auth.user?.id, paid.value], enabled: paid.value,
  queryFn: async ({ signal }) => (await api.get<{ data: NoteTemplate[] }>('/technician/note-templates', { signal })).data.data,
}));
function insertTemplate() {
  const template = templates.data?.find(item => item.id === Number(selected.value));
  if (template) remarks.value = template.body;
}
</script>

<template>
<form class="mt-6 space-y-3 border-t pt-5" @submit.prevent="emit('submit', remarks)">
  <template v-if="paid">
    <label class="block text-sm">Use a saved note template<select v-model="selected" :class="fieldClass + ' mt-2'" :disabled="busy || templates.isPending" @change="insertTemplate"><option value="">Choose a template (optional)</option><option v-for="template in templates.data" :key="template.id" :value="template.id">{{ template.title }}</option></select></label>
    <p v-if="templates.isError" class="text-xs text-red-700">Templates could not load. You can still write a note. <button type="button" class="underline" @click="templates.refetch()">Retry templates</button></p>
    <RouterLink to="/dashboard/documents" class="inline-block text-xs text-primary">Manage my templates</RouterLink>
  </template>
  <p v-else class="text-xs leading-5 text-slate-500">Pro adds reusable note templates. <RouterLink to="/dashboard/documents" class="text-primary underline">Explore Pro tools</RouterLink></p>
  <label class="block text-sm">Add repair note<textarea v-model="remarks" :class="fieldClass + ' mt-2'" name="remarks" maxlength="2000" required /></label>
  <p v-if="selected" class="text-xs text-slate-500">Review and edit the inserted note before posting. Customers can read repair notes.</p>
  <Button type="submit" :disabled="busy || !remarks.trim()">Add note</Button>
</form>
</template>
