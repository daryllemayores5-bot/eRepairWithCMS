import { z } from 'zod'
import { proposedAppointment } from '@/lib/repair-request'

export type AuthMode = 'login' | 'customer' | 'technician'

/** The original React schemas, shared with the Vue forms and regression tests. */
export function createAuthSchema(mode: AuthMode) {
  const login = mode === 'login'
  return z.object({
    name: z.string(), email: z.email(), password: z.string().min(login ? 1 : 10),
    password_confirmation: z.string(), city: z.string(), province: z.string(),
  }).superRefine((data, ctx) => {
    if (!login && !data.name.trim()) ctx.addIssue({ code: 'custom', path: ['name'], message: 'Enter your full name.' })
    if (!login && data.password !== data.password_confirmation) ctx.addIssue({ code: 'custom', path: ['password_confirmation'], message: 'Passwords must match.' })
    if (!login && (!/[a-zA-Z]/.test(data.password) || !/\d/.test(data.password))) ctx.addIssue({ code: 'custom', path: ['password'], message: 'Use letters and numbers.' })
    if (mode === 'technician' && (!data.city.trim() || !data.province.trim())) ctx.addIssue({ code: 'custom', path: ['city'], message: 'Enter your city and province.' })
  })
}

export const repairRequestSchema = z.object({
  device_type_id: z.string().min(1, 'Choose a device.'),
  device_brand_id: z.string().min(1, 'Choose a brand.'),
  model: z.string().min(1, 'Enter the model.').max(150),
  serial_number: z.string().max(150),
  repair_category_id: z.string().min(1, 'Choose a repair category.'),
  problem_description: z.string().min(15, 'Describe the issue in at least 15 characters.').max(5000),
  technician_profile_id: z.string().min(1, 'Select a technician.'),
  starts_at: z.string().refine(value => proposedAppointment(value) !== null, 'Choose a future appointment in Philippine time.'),
  service_location: z.string().min(5, 'Enter your preferred service location.').max(500),
})
export type RepairRequestValues = z.infer<typeof repairRequestSchema>
export const repairRequestSteps: (keyof RepairRequestValues)[][] = [
  ['device_type_id', 'device_brand_id', 'model'],
  ['repair_category_id', 'problem_description'],
  ['technician_profile_id'],
  ['starts_at', 'service_location'],
  [],
]
