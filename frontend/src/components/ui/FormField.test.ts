import { cleanup, fireEvent, render, screen } from '@testing-library/vue';
import { afterEach, expect, it, vi } from 'vitest';
import { defineComponent } from 'vue';
import { z } from 'zod';
import FormField from './FormField.vue';
import { useValidatedForm } from '@/composables/form';

afterEach(cleanup);

it('describes input and select errors, then removes invalid state after correction', async () => {
  const submit = vi.fn(async () => {});
  const component = defineComponent({
    components: { FormField },
    setup() {
      const form = useValidatedForm(z.object({
        model: z.string().min(1, 'Enter a model.'),
        device: z.string().min(1, 'Choose a device.'),
      }), { model: '', device: '' });
      return { form, submit: form.handleSubmit(submit) };
    },
    template: `<form @submit="submit">
      <FormField :form="form" name="model" label="Model" />
      <FormField :form="form" name="device" label="Device" :options="[{ id: 1, name: 'Laptop' }]" />
      <button>Submit</button>
    </form>`,
  });
  render(component);
  const model = screen.getByRole('textbox', { name: 'Model' });
  const device = screen.getByRole('combobox', { name: 'Device' });
  expect(model).toHaveAttribute('aria-invalid', 'false');
  expect(model).not.toHaveAttribute('aria-describedby');
  await fireEvent.click(screen.getByRole('button'));
  expect(model).toHaveAttribute('aria-invalid', 'true');
  expect(model).toHaveAccessibleDescription('Enter a model.');
  expect(device).toHaveAccessibleDescription('Choose a device.');
  expect(submit).not.toHaveBeenCalled();
  await fireEvent.update(model, 'Aspire');
  await fireEvent.update(device, '1');
  await fireEvent.click(screen.getByRole('button'));
  expect(submit).toHaveBeenCalledWith({ model: 'Aspire', device: '1' });
  for (const control of [model, device]) {
    expect(control).toHaveAttribute('aria-invalid', 'false');
    expect(control).not.toHaveAttribute('aria-describedby');
  }
});

it('keeps descriptions unique when multiple forms use the same field name', async () => {
  const component = defineComponent({
    components: { FormField },
    setup() {
      const form = useValidatedForm(z.object({ model: z.string() }), { model: '' });
      form.setError('model', { message: 'Check this model.' });
      return { form };
    },
    template: `<div><FormField :form="form" name="model" label="First model" />
      <FormField :form="form" name="model" label="Second model" /></div>`,
  });
  render(component);
  const fields = screen.getAllByRole('textbox');
  expect(fields[0]!.getAttribute('aria-describedby')).not.toBe(fields[1]!.getAttribute('aria-describedby'));
  for (const field of fields) expect(field).toHaveAccessibleDescription('Check this model.');
});

it('focuses the first enabled invalid control in display order after descriptions render', async () => {
  const submit = vi.fn(async () => {});
  let descriptionAtFocus = '';
  const component = defineComponent({
    components: { FormField },
    setup() {
      const form = useValidatedForm(z.object({
        later: z.string().min(1, 'Enter the later field.'),
        first: z.string().min(1, 'Enter the first field.'),
      }), { later: '', first: '' });
      const recordFocus = (event: FocusEvent) => {
        const control = event.target as HTMLElement;
        descriptionAtFocus = document.getElementById(control.getAttribute('aria-describedby') ?? '')?.textContent ?? '';
      };
      return { form, submit: form.handleSubmit(submit), recordFocus };
    },
    template: `<form @submit="submit" @focusin="recordFocus">
      <input name="later" disabled />
      <FormField :form="form" name="first" label="First" />
      <FormField :form="form" name="later" label="Later" />
      <button>Submit</button>
    </form>`,
  });
  render(component);
  await fireEvent.click(screen.getByRole('button'));
  expect(screen.getByRole('textbox', { name: 'First' })).toHaveFocus();
  expect(descriptionAtFocus).toBe('Enter the first field.');
  expect(submit).not.toHaveBeenCalled();
});
