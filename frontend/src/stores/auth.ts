import { computed, onScopeDispose } from 'vue'
import { defineStore } from 'pinia'
import { useQuery, useQueryClient } from '@tanstack/vue-query'
import { authKey } from '@/lib/session-cache'
import { createAuthSession } from '@/services/auth-session'
import type { User } from '@/types'

export const useAuthStore = defineStore('auth', () => {
  const session = createAuthSession(useQueryClient())
  onScopeDispose(session.dispose)
  const query = useQuery({
    queryKey: authKey,
    retry: false,
    refetchOnWindowFocus: true,
    queryFn: session.fetchUser,
  })

  function refresh(user?: User) {
    return user ? session.acceptUser(user) : query.refetch()
  }

  return {
    user: computed(() => query.data.value ?? null),
    pending: computed(() => query.isPending.value || session.signingOut.value),
    error: computed(() => query.isError.value),
    refresh,
    logout: session.logout,
  }
})
