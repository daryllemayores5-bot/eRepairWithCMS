import { computed } from 'vue';
import { useAuthStore } from '@/stores/auth';
import { useApiQuery } from '@/composables/query';
import { api } from '@/services/api';
import type { WorkshopAccess } from '@/types/workshop';

export function useWorkshopAccess() {
  const auth = useAuthStore();
  const access = useApiQuery<WorkshopAccess>(() => ({
    queryKey: ['workshop-access', auth.user?.id],
    enabled: auth.user?.role === 'technician',
    queryFn: async ({ signal }) => (await api.get<{ data: WorkshopAccess }>('/technician/workshop', { signal })).data.data,
    staleTime: 0, refetchInterval: 15000,
  }));
  return { auth, access, paid: computed(() => access.data?.documents === true), premium: computed(() => access.data?.inventory === true) };
}
