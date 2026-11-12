import type { ObjectDirective } from 'vue';

const pendingSelects = new WeakMap<HTMLSelectElement, { value: string; stop: () => void }>();

// React defaultValue/defaultChecked initialize an uncontrolled field once.
// Binding Vue :value/:checked would overwrite unsaved edits on unrelated renders.
export const vInitialValue: ObjectDirective<HTMLInputElement | HTMLSelectElement | HTMLTextAreaElement, unknown> = {
  mounted(element, binding) {
    const value = String(binding.value ?? '');
    element.value = value;
    if (element instanceof HTMLSelectElement && element.selectedIndex === -1) {
      // Lookup options may arrive after the form mounts. A user edit takes
      // precedence over the pending initial selection, including an empty value.
      const stop = () => {
        pendingSelects.delete(element);
        element.removeEventListener('input', stop);
        element.removeEventListener('change', stop);
      };
      pendingSelects.set(element, { value, stop });
      element.addEventListener('input', stop);
      element.addEventListener('change', stop);
    }
  },
  updated(element) {
    if (!(element instanceof HTMLSelectElement)) return;
    const pending = pendingSelects.get(element);
    if (pending && Array.from(element.options).some(option => option.value === pending.value)) {
      element.value = pending.value;
      pending.stop();
    }
  },
  beforeUnmount(element) {
    if (element instanceof HTMLSelectElement) pendingSelects.get(element)?.stop();
  }
};
export const vInitialChecked: ObjectDirective<HTMLInputElement, boolean> = {
  mounted(element, binding) {
    element.checked = binding.value;
  }
};
