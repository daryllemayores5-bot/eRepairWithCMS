import { afterEach, describe, expect, it, vi } from 'vitest';
import { createApp, createCommentVNode, defineComponent, h, nextTick, reactive, ref, type Component } from 'vue';
import { z } from 'zod';
import Button from '@/components/ui/Button.vue';
import Input from '@/components/ui/Input.vue';
import Avatar from '@/components/ui/Avatar.vue';
import Card from '@/components/ui/Card.vue';
import PageHeading from '@/components/common/PageHeading.vue';
import { useValidatedForm } from '@/composables/form';
import { discardPrivateData } from '@/lib/session-cache';
import { QueryClient } from '@tanstack/query-core';
import { vInitialValue, vInitialChecked } from '@/lib/initial-fields';
const cleanups: (() => void)[] = [];
afterEach(() => {
  for (const cleanup of cleanups.splice(0)) cleanup();
});
function mount(component: Component, props: Record<string, unknown> = {}, children?: () => unknown) {
  const element = document.createElement('div');
  document.body.append(element);
  const app = createApp({
    render: () => h(component, props, children ? {
      default: children
    } : undefined)
  });
  app.mount(element);
  cleanups.push(() => {
    app.unmount();
    element.remove();
  });
  return element;
}
describe('Vue shared component behavior', () => {
  it('does not reserve space for a role-hidden heading action', async () => {
    const visible = ref(false);
    const View = defineComponent({ render: () => h(PageHeading, { title: 'My repair requests' }, { action: () => visible.value ? h('button', 'New repair request') : createCommentVNode() }) });
    const element = mount(View);
    expect(element.querySelector('h1')?.textContent).toBe('My repair requests');
    expect(element.firstElementChild?.children).toHaveLength(1);
    visible.value = true;
    await nextTick();
    expect(element.querySelector('button')?.textContent).toBe('New repair request');
    expect(element.firstElementChild?.children).toHaveLength(2);
  });
  it('keeps links as links, merges button classes and runs click handlers once', () => {
    const onClick = vi.fn((event: Event) => event.preventDefault());
    const element = mount(Button, {
      asChild: true,
      variant: 'outline',
      size: 'sm',
      class: 'mt-6',
      onClick
    }, () => h('a', {
      href: '/technicians',
      class: 'w-full'
    }, 'Find a technician'));
    const link = element.querySelector('a')!;
    expect(element.querySelector('button')).toBeNull();
    expect(link.getAttribute('href')).toBe('/technicians');
    expect(link.classList.contains('min-h-11')).toBe(true);
    expect(link.classList.contains('w-full')).toBe(true);
    expect(link.classList.contains('mt-6')).toBe(true);
    link.dispatchEvent(new MouseEvent('click', {
      bubbles: true,
      cancelable: true
    }));
    expect(onClick).toHaveBeenCalledTimes(1);
  });
  it('retains disabled native buttons and custom styles', () => {
    const element = mount(Button, {
      disabled: true,
      variant: 'destructive',
      class: 'w-full'
    }, () => 'Cancel');
    const button = element.querySelector('button')!;
    expect(button.disabled).toBe(true);
    expect(button.classList.contains('text-red-700')).toBe(true);
    expect(button.textContent).toBe('Cancel');
  });
  it('emits Vue model values and retains native form names', async () => {
    const update = vi.fn();
    const element = mount(Input, {
      name: 'email',
      modelValue: 'ana@example.test',
      'onUpdate:modelValue': update
    });
    const input = element.querySelector('input')!;
    expect(input.value).toBe('ana@example.test');
    expect(input.name).toBe('email');
    input.value = 'ben@example.test';
    input.dispatchEvent(new Event('input', {
      bubbles: true
    }));
    await nextTick();
    expect(update).toHaveBeenCalledWith('ben@example.test');
  });
  it('accepts external resets after a controlled text input has been edited', async () => {
    const props = reactive({ value: 'Ana', disabled: false });
    const element = mount(Input, props);
    const input = element.querySelector('input')!;
    input.value = 'Ana Reyes';
    input.dispatchEvent(new Event('input', { bubbles: true }));
    await nextTick();
    props.value = '';
    await nextTick();
    expect(input.value).toBe('');
    props.value = 'Ben';
    await nextTick();
    expect(input.value).toBe('Ben');
  });
  it('notifies live search on each input event before blur', async () => {
    const search = ref('');
    const View = defineComponent({ render: () => h(Input, { value: search.value, onInput: (event: Event) => { search.value = (event.target as HTMLInputElement).value; } }) });
    const element = mount(View);
    const input = element.querySelector('input')!;
    input.value = 'Battery';
    input.dispatchEvent(new Event('input', { bubbles: true }));
    await nextTick();
    expect(search.value).toBe('Battery');
  });
  it('keeps file inputs uncontrolled and forwards their actual change event', async () => {
    const onChange = vi.fn(),
      update = vi.fn();
    const element = mount(Input, {
      type: 'file',
      name: 'photo',
      onChange,
      'onUpdate:modelValue': update
    });
    const input = element.querySelector('input')!;
    const file = new File(['photo'], 'photo.png', {
      type: 'image/png'
    });
    Object.defineProperty(input, 'files', {
      value: [file],
      configurable: true
    });
    input.dispatchEvent(new Event('input', {
      bubbles: true
    }));
    input.dispatchEvent(new Event('change', {
      bubbles: true
    }));
    await nextTick();
    expect(onChange).toHaveBeenCalledTimes(1);
    expect((onChange.mock.calls[0][0].target as HTMLInputElement).files?.[0]).toBe(file);
    expect(update).not.toHaveBeenCalled();
  });
  it('preserves edited default-value fields during unrelated updates', async () => {
    const busy = ref(false);
    const View = defineComponent({
      directives: {
        initialValue: vInitialValue,
        initialChecked: vInitialChecked
      },
      setup: () => ({
        busy
      }),
      template: '<form><select v-initial-value="\'busy\'"><option value="busy">Busy</option><option value="available">Available</option></select><textarea v-initial-value="\'Biography\'" /><input type="checkbox" v-initial-checked="true" /><button :disabled="busy">Save</button></form>'
    });
    const element = mount(View);
    const select = element.querySelector('select')!,
      textarea = element.querySelector('textarea')!,
      checkbox = element.querySelector('input')!;
    expect(select.value).toBe('busy');
    expect(textarea.value).toBe('Biography');
    expect(checkbox.checked).toBe(true);
    select.value = 'available';
    textarea.value = 'My unsaved biography';
    checkbox.checked = false;
    busy.value = true;
    await nextTick();
    expect(select.value).toBe('available');
    expect(textarea.value).toBe('My unsaved biography');
    expect(checkbox.checked).toBe(false);
  });
  it('retains an edited input default after its parent updates', async () => {
    const props = reactive({
      defaultValue: 'Ana',
      disabled: false
    });
    const element = mount(Input, props);
    const input = element.querySelector('input')!;
    expect(input.value).toBe('Ana');
    input.value = 'Ana Reyes';
    input.dispatchEvent(new Event('input', {
      bubbles: true
    }));
    props.disabled = true;
    await nextTick();
    expect(input.value).toBe('Ana Reyes');
  });
  it('uses initials after a failed image and retries a replacement URL', async () => {
    const props = reactive({
      name: 'Ana Reyes',
      src: '/photo/1',
      size: 'sm'
    });
    const element = mount(Avatar, props);
    element.querySelector('img')!.dispatchEvent(new Event('error'));
    await nextTick();
    expect(element.querySelector('img')).toBeNull();
    expect(element.textContent).toBe('AR');
    props.src = '/photo/2';
    await nextTick();
    expect(element.querySelector('img')?.getAttribute('src')).toBe('/photo/2');
  });
  it('merges card overrides while preserving the original surface styling', () => {
    const element = mount(Card, {
      class: 'p-6 bg-blue-50',
      'aria-label': 'Request'
    }, () => 'Repair');
    const card = element.querySelector('[data-slot="card"]')!;
    expect(card.classList.contains('rounded-2xl')).toBe(true);
    expect(card.classList.contains('bg-blue-50')).toBe(true);
    expect(card.classList.contains('bg-white')).toBe(false);
    expect(card.getAttribute('aria-label')).toBe('Request');
    expect(card.textContent).toBe('Repair');
  });
});
describe('Vue validation and private query boundaries', () => {
  it('focuses the first invalid named field when submitting', async () => {
    const form = useValidatedForm(z.object({ email: z.email() }), { email: '' });
    const submit = form.handleSubmit(vi.fn(async () => {}));
    const View = defineComponent({ render: () => h('form', { onSubmit: submit }, h('input', { name: 'email' })) });
    const element = mount(View);
    element.querySelector('form')!.dispatchEvent(new Event('submit', { bubbles: true, cancelable: true }));
    await nextTick();
    expect(document.activeElement).toBe(element.querySelector('input'));
  });
  const schema = z.object({
    model: z.string().min(1, 'Enter the model.'),
    description: z.string().min(15, 'Describe the issue in at least 15 characters.')
  });
  it('validates the current wizard step without surfacing later errors', () => {
    const form = useValidatedForm(schema, {
      model: '',
      description: ''
    });
    expect(form.trigger(['model'])).toBe(false);
    expect(form.formState.errors.model?.message).toBe('Enter the model.');
    expect(form.formState.errors.description).toBeUndefined();
    form.values.model = 'Aspire';
    expect(form.trigger(['model'])).toBe(true);
    expect(form.trigger()).toBe(false);
    expect(form.formState.errors.description?.message).toContain('15 characters');
  });
  it('blocks duplicate submissions and snapshots valid form values', async () => {
    const form = useValidatedForm(schema, {
      model: 'Aspire',
      description: 'Battery no longer holds a charge.'
    });
    let finish!: () => void;
    const submitted = vi.fn((_values: {
      model: string;
      description: string;
    }) => new Promise<void>(resolve => {
      finish = resolve;
    }));
    const submit = form.handleSubmit(submitted);
    const first = submit(new Event('submit'));
    await submit(new Event('submit'));
    expect(form.formState.isSubmitting).toBe(true);
    expect(submitted).toHaveBeenCalledTimes(1);
    form.values.model = 'Another device';
    expect(submitted.mock.calls[0][0].model).toBe('Aspire');
    finish();
    await first;
    expect(form.formState.isSubmitting).toBe(false);
  });
  it('cancels late private queries and retains public directory data', async () => {
    const client = new QueryClient();
    cleanups.push(() => client.clear());
    client.setQueryData(['technicians'], {
      public: true
    });
    client.setQueryData(['dashboard'], {
      private: true
    });
    let finish!: (value: string) => void;
    const old = client.fetchQuery({
      queryKey: ['repair', 99],
      queryFn: () => new Promise<string>(resolve => {
        finish = resolve;
      })
    }).catch(() => undefined);
    await discardPrivateData(client);
    finish('Previous account data');
    await old;
    expect(client.getQueryData(['repair', 99])).toBeUndefined();
    expect(client.getQueryData(['dashboard'])).toBeUndefined();
    expect(client.getQueryData(['technicians'])).toEqual({
      public: true
    });
  });
});
