import { computed, reactive } from 'vue';
import { useQuery, type UseQueryOptions } from '@tanstack/vue-query';
// Keep the whole options object reactive: route params, filters, pagination and
// enabled predicates must all be tracked, rather than copied at setup time.
export function useApiQuery<T>(options: () => UseQueryOptions<T, Error, T, T, readonly unknown[]>) {
  return reactive(useQuery(computed(options)));
}
