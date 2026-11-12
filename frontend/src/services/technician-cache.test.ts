import { QueryClient } from '@tanstack/vue-query';
import { expect, it } from 'vitest';
import { refreshTechnicianViews } from './technician-cache';

it('expires all technician views after an edit while retaining unrelated cached data', async () => {
  const cache = new QueryClient();
  const affected = [
    ['my-technician-profile'], ['technicians', 'city=Laguna'],
    ['technician', '1'], ['featured-technicians'],
    ['request-technicians', '1', '2', '', 1],
  ];
  try {
    for (const key of [...affected, ['website-content']]) cache.setQueryData(key, { name: 'Before edit' });
    await refreshTechnicianViews(cache);
    for (const key of affected) expect(cache.getQueryState(key)?.isInvalidated).toBe(true);
    expect(cache.getQueryState(['website-content'])?.isInvalidated).toBe(false);
  } finally {
    cache.clear();
  }
});
