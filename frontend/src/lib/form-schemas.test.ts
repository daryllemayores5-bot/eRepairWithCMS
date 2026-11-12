import { readFileSync } from 'node:fs'
import { dirname, join, resolve } from 'node:path'
import { fileURLToPath } from 'node:url'
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'
import { z } from 'zod'
import { createAuthSchema, repairRequestSchema, repairRequestSteps, type AuthMode, type RepairRequestValues } from './form-schemas'
import { proposedAppointment } from './repair-request'
import { useValidatedForm } from '@/composables/form'

// Execute the preserved validation expressions with the same Zod installation.
// This compares behavior with the original source, rather than a second copy.
const reference = resolve(dirname(fileURLToPath(import.meta.url)), '../../../frontend-react-reference/src/pages')
const authSource = readFileSync(join(reference, 'AuthPage.tsx'), 'utf8')
const authExpression = authSource.slice(authSource.indexOf('  const schema ='), authSource.indexOf('  const form ='))
const originalAuth = (mode: AuthMode): z.ZodType => new Function('z', 'mode', `const login=mode==='login';${authExpression};return schema`)(z, mode)
const repairSource = readFileSync(join(reference, 'CreateRepairPage.tsx'), 'utf8')
const repairExpression = repairSource.slice(repairSource.indexOf('const schema ='), repairSource.indexOf('type Values'))
const originalRepair: z.ZodType = new Function('z', 'proposedAppointment', `${repairExpression};return schema`)(z, proposedAppointment)
function outcome(schema: z.ZodType, values: unknown) {
  const result = schema.safeParse(values)
  return result.success ? { success: true, data: result.data } : {
    success: false, issues: result.error.issues.map(issue => ({ path: issue.path, message: issue.message, code: issue.code })),
  }
}
const credentials = { name: 'Ana Reyes', email: 'ana@example.test', password: 'ExamplePass123', password_confirmation: 'ExamplePass123', city: 'Santa Cruz', province: 'Laguna' }

describe('authentication validation parity with the React reference', () => {
  const cases: { label: string; mode: AuthMode; patch: Record<string, unknown>; valid: boolean; message?: string }[] = [
    { label: 'customer registration', mode: 'customer', patch: {}, valid: true },
    { label: 'technician registration', mode: 'technician', patch: {}, valid: true },
    { label: 'login without registration fields', mode: 'login', patch: { name: '', password: 'x', password_confirmation: '', city: '', province: '' }, valid: true },
    { label: 'invalid email', mode: 'login', patch: { email: 'not-an-email' }, valid: false },
    { label: 'empty login password', mode: 'login', patch: { password: '' }, valid: false },
    { label: 'blank customer name', mode: 'customer', patch: { name: '  ' }, valid: false, message: 'Enter your full name.' },
    { label: 'short registration password', mode: 'customer', patch: { password: 'Example12', password_confirmation: 'Example12' }, valid: false },
    { label: 'mismatched confirmation', mode: 'customer', patch: { password_confirmation: 'DifferentPass123' }, valid: false, message: 'Passwords must match.' },
    { label: 'letters-only password', mode: 'customer', patch: { password: 'abcdefghijkl', password_confirmation: 'abcdefghijkl' }, valid: false, message: 'Use letters and numbers.' },
    { label: 'digits-only password', mode: 'technician', patch: { password: '123456789012', password_confirmation: '123456789012' }, valid: false, message: 'Use letters and numbers.' },
    { label: 'missing technician city', mode: 'technician', patch: { city: ' ' }, valid: false, message: 'Enter your city and province.' },
    { label: 'missing technician province', mode: 'technician', patch: { province: '' }, valid: false, message: 'Enter your city and province.' },
    { label: 'optional customer location', mode: 'customer', patch: { city: '', province: '' }, valid: true },
    { label: 'unknown registration fields', mode: 'customer', patch: { role: 'admin' }, valid: true },
  ]
  it.each(cases)('$label', ({ mode, patch, valid, message }) => {
    const values = { ...credentials, ...patch }
    const schema = createAuthSchema(mode)
    expect(outcome(schema, values)).toEqual(outcome(originalAuth(mode), values))
    const result = schema.safeParse(values)
    expect(result.success).toBe(valid)
    if (!result.success && message) expect(result.error.issues.some(issue => issue.message === message)).toBe(true)
  })

  it('submits parsed schema output without unknown fields', async () => {
    const form = useValidatedForm(createAuthSchema('customer'), { ...credentials })
    Object.assign(form.values, { role: 'admin' })
    const submit = vi.fn(async (_values: unknown) => {})
    await form.handleSubmit(submit)(new Event('submit'))
    expect(submit).toHaveBeenCalledWith(credentials)
  })
})

describe('repair validation parity with the React reference', () => {
  beforeEach(() => { vi.useFakeTimers(); vi.setSystemTime(new Date('2026-11-10T00:00:00Z')) })
  afterEach(() => vi.useRealTimers())
  const values: RepairRequestValues = {
    device_type_id: '1', device_brand_id: '2', model: 'Aspire 5', serial_number: '',
    repair_category_id: '10', problem_description: 'The battery will not charge when plugged in.',
    technician_profile_id: '42', starts_at: '2026-11-11T09:00', service_location: 'Santa Cruz town center',
  }
  const cases: { label: string; patch: Record<string, unknown>; valid: boolean }[] = [
    { label: 'complete repair with optional serial omitted', patch: {}, valid: true },
    ...['device_type_id', 'device_brand_id', 'model', 'repair_category_id', 'technician_profile_id'].map(key => ({ label: `missing ${key}`, patch: { [key]: '' }, valid: false })),
    { label: 'maximum model length', patch: { model: 'x'.repeat(150) }, valid: true },
    { label: 'overlong model', patch: { model: 'x'.repeat(151) }, valid: false },
    { label: 'maximum serial length', patch: { serial_number: 'x'.repeat(150) }, valid: true },
    { label: 'overlong serial', patch: { serial_number: 'x'.repeat(151) }, valid: false },
    { label: 'minimum problem description', patch: { problem_description: 'x'.repeat(15) }, valid: true },
    { label: 'short problem description', patch: { problem_description: 'x'.repeat(14) }, valid: false },
    { label: 'maximum problem description', patch: { problem_description: 'x'.repeat(5000) }, valid: true },
    { label: 'overlong problem description', patch: { problem_description: 'x'.repeat(5001) }, valid: false },
    { label: 'short service location', patch: { service_location: 'Town' }, valid: false },
    { label: 'maximum service location', patch: { service_location: 'x'.repeat(500) }, valid: true },
    { label: 'overlong service location', patch: { service_location: 'x'.repeat(501) }, valid: false },
    { label: 'missing appointment', patch: { starts_at: '' }, valid: false },
    { label: 'past Philippine appointment', patch: { starts_at: '2026-11-10T07:59' }, valid: false },
    { label: 'invalid calendar date', patch: { starts_at: '2027-02-30T09:00' }, valid: false },
  ]
  it.each(cases)('$label', ({ patch, valid }) => {
    const input = { ...values, ...patch }
    expect(outcome(repairRequestSchema, input)).toEqual(outcome(originalRepair, input))
    expect(repairRequestSchema.safeParse(input).success).toBe(valid)
  })

  it('validates only the active wizard step but validates every field on final submission', async () => {
    const form = useValidatedForm(repairRequestSchema, { ...values, repair_category_id: '', problem_description: '', technician_profile_id: '', starts_at: '', service_location: '' })
    expect(form.trigger(repairRequestSteps[0])).toBe(true)
    expect(form.formState.errors.repair_category_id).toBeUndefined()
    expect(form.trigger(repairRequestSteps[1])).toBe(false)
    expect(form.formState.errors.repair_category_id?.message).toBe('Choose a repair category.')
    expect(form.trigger(repairRequestSteps[4])).toBe(true)
    const submit = vi.fn(async () => {})
    await form.handleSubmit(submit)(new Event('submit'))
    expect(submit).not.toHaveBeenCalled()
    expect(form.formState.errors.technician_profile_id?.message).toBe('Select a technician.')
  })
})
