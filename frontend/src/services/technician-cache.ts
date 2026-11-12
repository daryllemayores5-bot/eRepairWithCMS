import type { QueryClient } from '@tanstack/vue-query';

/** Profile, photo and availability edits affect every technician discovery view. */
export async function refreshTechnicianViews(cache: QueryClient) {
  await Promise.all([
    'my-technician-profile', 'technicians', 'technician',
    'featured-technicians', 'request-technicians',
  ].map(key => cache.invalidateQueries({ queryKey: [key] })));
}
