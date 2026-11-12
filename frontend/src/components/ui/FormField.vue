<script setup lang="ts">
import { useId } from 'vue';
import type { FormFields } from '@/composables/form';
import type { Option } from '@/types';
import Input from './Input.vue';
import { fieldClass } from './fields';
const fieldId = useId();
const errorId = `${fieldId}-error`;
withDefaults(defineProps<{
  form: FormFields;
  name: string;
  label: string;
  options?: Option[];
  type?: string;
}>(), {
  type: 'text'
});
</script>

<template>
<div class="block text-sm font-medium">
    <label :for="fieldId">{{ label }}</label>
    <select v-if="options" :id="fieldId" v-model="form.values[name]" :name="name" :class="`${fieldClass} mt-2`" :aria-invalid="!!form.formState.errors[name]" :aria-describedby="form.formState.errors[name] ? errorId : undefined">
      <option value="">Choose…</option>
      <option v-for="option in options" :key="option.id" :value="String(option.id)">{{ option.name }}</option>
    </select>
    <Input v-else :id="fieldId" v-model="form.values[name]" :name="name" class="mt-2" :type="type" :aria-invalid="!!form.formState.errors[name]" :aria-describedby="form.formState.errors[name] ? errorId : undefined" />
    <span v-if="form.formState.errors[name]" :id="errorId" class="mt-2 block text-xs text-red-700">{{ form.formState.errors[name]?.message }}</span>
  </div>
</template>
