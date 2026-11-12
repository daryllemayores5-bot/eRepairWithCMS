import type { QueryClient } from '@tanstack/query-core'
import { api } from '@/services/api'
import type { RepairRequestValues } from '@/lib/form-schemas'
import type { proposedAppointment } from '@/lib/repair-request'
import type { Repair } from '@/types'

export async function submitRepairRequest(
  values: RepairRequestValues,
  photos: readonly File[],
  appointment: NonNullable<ReturnType<typeof proposedAppointment>>,
  cache: QueryClient,
): Promise<Repair> {
  const body = new FormData()
  Object.entries(values).forEach(([key, value]) => body.set(key, value))
  body.set('starts_at', appointment.starts_at)
  body.set('ends_at', appointment.ends_at)
  photos.forEach(file => body.append('photos[]', file))
  const response = await api.post<{ data: Repair }>('/repair-requests', body)
  await Promise.all([
    cache.invalidateQueries({ queryKey: ['dashboard'] }),
    cache.invalidateQueries({ queryKey: ['repairs'] }),
  ])
  return response.data.data
}
