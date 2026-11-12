import { readFileSync } from 'node:fs';
import { fileURLToPath } from 'node:url';
import { dirname, resolve } from 'node:path';
import { afterEach, expect, it } from 'vitest';

const source = readFileSync(resolve(dirname(fileURLToPath(import.meta.url)), '../../../backend/resources/js/revenue-projection.js'), 'utf8');
afterEach(() => { document.body.innerHTML = ''; });

it('updates the real admin projection script with exact centavos and configured pricing', () => {
  document.body.innerHTML = `<form id="revenue-projection" data-price-cents="19900"><input id="projection-count" type="number" min="0" max="1000000" step="1"><strong id="projection-monthly"></strong><strong id="projection-annual"></strong></form>`;
  let onInput!: (event: { target: HTMLInputElement }) => void;
  new Function('document', 'HTMLInputElement', source)({ addEventListener: (_name: string, listener: typeof onInput) => { onInput = listener; } }, HTMLInputElement);
  const input = document.querySelector('input')!;
  const monthly = document.querySelector('#projection-monthly')!;
  const annual = document.querySelector('#projection-annual')!;
  input.value = '10'; onInput({ target: input });
  expect(monthly.textContent).toBe('₱1,990.00');
  expect(annual.textContent).toBe('₱23,880.00');
  input.value = '50'; onInput({ target: input });
  expect(monthly.textContent).toBe('₱9,950.00');
  expect(annual.textContent).toBe('₱119,400.00');
  document.querySelector('form')!.dataset.priceCents = '24950';
  input.value = '3'; onInput({ target: input });
  expect(monthly.textContent).toBe('₱748.50');
  input.value = '-1'; onInput({ target: input });
  expect(monthly.textContent).toBe('₱748.50');
});
