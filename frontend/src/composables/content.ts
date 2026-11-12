import { useApiQuery } from './query';
import { api } from '@/services/api';
import type { Lookups } from '@/types';
import type { WebsiteContentWithServices } from '@/types/content';
export const useLookups = () => useApiQuery<Lookups>(() => ({
  queryKey: ['lookups'],
  queryFn: async () => (await api.get<{
    data: Lookups;
  }>('/lookups')).data.data,
  staleTime: 300000
}));
export const useWebsiteContent = () => useApiQuery<WebsiteContentWithServices>(() => ({
  queryKey: ['website-content'],
  queryFn: async () => (await api.get<{
    data: WebsiteContentWithServices;
  }>('/content')).data.data,
  retry: false,
  staleTime: 60000
}));
