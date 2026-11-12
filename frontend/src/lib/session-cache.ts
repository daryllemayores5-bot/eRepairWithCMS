import type { Query, QueryClient } from '@tanstack/query-core';

// Only these queries contain public directory/editorial data. Unknown keys are
// treated as private so new authenticated features are cleared by default.
const publicKeys = new Set(['auth-user', 'lookups', 'website-content', 'system-health', 'technicians', 'technician', 'reviews', 'featured-technicians', 'request-technicians']);
export const authKey = ['auth-user'] as const;
export async function discardPrivateData(client: QueryClient) {
  const predicate = (query: Query) => !publicKeys.has(String(query.queryKey[0]));
  await client.cancelQueries({
    predicate
  });
  client.removeQueries({
    predicate
  });
  client.getMutationCache().clear();
}
