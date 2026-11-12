import { afterEach, describe, expect, it } from 'vitest';
import { mount } from '@vue/test-utils';
import { defineComponent, nextTick, ref } from 'vue';
import { vInitialValue } from './initial-fields';

const wrappers: ReturnType<typeof mount>[] = [];
afterEach(() => wrappers.splice(0).forEach(wrapper => wrapper.unmount()));

function renderSelect(initialValue = '2') {
  const options = ref(['1']);
  const value = ref(initialValue);
  const wrapper = mount(defineComponent({
    directives: { initialValue: vInitialValue },
    setup: () => ({ options, value }),
    template: `<form><select name="device_type" v-initial-value="value">
      <option value="">All devices</option>
      <option v-for="option in options" :key="option" :value="option">{{ option }}</option>
    </select></form>`,
  }));
  wrappers.push(wrapper);
  return { wrapper, options, value, select: wrapper.get('select') };
}

describe('initial select values with asynchronous options', () => {
  it('restores the initial filter when its option arrives and submits it', async () => {
    const { wrapper, options, select } = renderSelect();
    options.value = ['1', '2'];
    await nextTick();
    expect(select.element.value).toBe('2');
    expect(new FormData(wrapper.get('form').element).get('device_type')).toBe('2');
  });

  it('preserves a selection made while options are loading', async () => {
    const { options, select } = renderSelect();
    await select.setValue('1');
    options.value = ['1', '2'];
    await nextTick();
    expect(select.element.value).toBe('1');
  });

  it('preserves an explicit all-devices selection while options are loading', async () => {
    const { options, select } = renderSelect();
    await select.setValue('');
    options.value = ['1', '2'];
    await nextTick();
    expect(select.element.value).toBe('');
  });

  it('initializes only once after the option becomes available', async () => {
    const { options, value, select } = renderSelect();
    options.value = ['1', '2'];
    await nextTick();
    await select.setValue('1');
    value.value = '2';
    options.value = ['1', '2', '3'];
    await nextTick();
    expect(select.element.value).toBe('1');
  });

  it('keeps an initially available value through later binding updates', async () => {
    const { value, select } = renderSelect('1');
    value.value = '';
    await nextTick();
    expect(select.element.value).toBe('1');
  });
});
