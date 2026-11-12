import type { Option, Technician } from '../types/index.ts'

/** Category and technician compatibility must agree before the appointment step. */
export function canRequestRepair(technician: Technician | undefined, deviceTypeId: string, category: Option | undefined): boolean {
  if (!technician || !category || !technician.verified || technician.availability !== 'available') return false
  const device = Number(deviceTypeId)
  return device > 0
    && (!category.device_type_id || category.device_type_id === device)
    && technician.device_types.some(type => type.id === device)
    && (!category.specialization_id || technician.specializations.some(type => type.id === category.specialization_id))
}

/** Interpret datetime-local input in Philippine time, independent of the browser zone. */
export function proposedAppointment(value: string, now = Date.now()): { starts_at: string; ends_at: string } | null {
  if (!/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}$/.test(value)) return null
  const start = new Date(`${value}:00+08:00`)
  const timestamp = start.getTime()
  // A round trip rejects dates such as February 30 that Date otherwise normalizes.
  if (!Number.isFinite(timestamp) || timestamp <= now || new Date(timestamp + 8 * 3600000).toISOString().slice(0, 16) !== value) return null
  return { starts_at: start.toISOString(), ends_at: new Date(timestamp + 3600000).toISOString() }
}

export function devicePhotoError(files: ReadonlyArray<Pick<File, 'size' | 'type'>>): string | null {
  if (files.length > 5) return 'Choose up to five device photos.'
  if (files.some(file => file.size === 0 || file.size > 5 * 1024 * 1024)) return 'Each photo must contain data and be at most 5 MB.'
  if (files.some(file => !['image/jpeg', 'image/png', 'image/webp'].includes(file.type))) return 'Choose JPG, PNG or WebP device photos.'
  return null
}
