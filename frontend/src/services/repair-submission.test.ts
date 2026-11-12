import { afterEach, beforeEach, describe, expect, it } from 'vitest'
import { QueryClient } from '@tanstack/query-core'
import { AxiosError, type AxiosResponse, type InternalAxiosRequestConfig } from 'axios'
import { submitRepairRequest } from './repair-submission'
import { api } from './api'
import { proposedAppointment } from '@/lib/repair-request'
import { errorMessage } from '@/lib/errors'
import type { RepairRequestValues } from '@/lib/form-schemas'
import type { Repair } from '@/types'

const originalAdapter = api.defaults.adapter
let cache: QueryClient
const values: RepairRequestValues = {
  device_type_id: '1', device_brand_id: '2', model: 'Aspire 5', serial_number: '',
  repair_category_id: '10', problem_description: 'The battery will not charge when plugged in.',
  technician_profile_id: '42', starts_at: '2026-11-11T09:00', service_location: 'Santa Cruz town center',
}
const appointment = proposedAppointment(values.starts_at, Date.parse('2026-11-10T00:00:00Z'))!
const created: Repair = {
  id: 73, reference: 'ER-FIXTURE-073', status: 'pending', model: values.model,
  serial_number: null, problem_description: values.problem_description, device_type: 'Laptop',
  device_brand: 'Acer', category: 'Battery', created_at: '2026-11-10T00:00:00Z',
  completed_at: null, customer: { id: 11, name: 'Ana' }, technician: { id: 42, name: 'Ben' },
}
function reply(config: InternalAxiosRequestConfig): AxiosResponse {
  return { config, data: { data: created }, status: 201, statusText: 'Created', headers: {} }
}
beforeEach(() => {
  cache = new QueryClient()
  cache.setQueryData(['dashboard'], { total: 0 })
  cache.setQueryData(['repairs', 1, 'all'], { data: [] })
  cache.setQueryData(['technicians'], { data: [] })
})
afterEach(() => { cache.clear(); api.defaults.adapter = originalAdapter })

describe('Vue repair request transport contract', () => {
  it('sends private files and Philippine appointments in the existing multipart format', async () => {
    let request!: InternalAxiosRequestConfig
    api.defaults.adapter = async config => { request = config; return reply(config) }
    const photo = new File(['fixture photo'], 'battery.png', { type: 'image/png' })
    const result = await submitRepairRequest(values, [photo], appointment, cache)
    expect(request.method).toBe('post')
    expect(request.url).toBe('/repair-requests')
    expect(request.baseURL).toBe('/api/v1')
    expect(request.withCredentials).toBe(true)
    expect(request.withXSRFToken).toBe(true)
    expect(request.data).toBeInstanceOf(FormData)
    const body = request.data as FormData
    expect(body.get('device_type_id')).toBe('1')
    expect(body.get('device_brand_id')).toBe('2')
    expect(body.get('technician_profile_id')).toBe('42')
    expect(body.get('model')).toBe('Aspire 5')
    expect(body.get('problem_description')).toBe(values.problem_description)
    expect(body.get('service_location')).toBe(values.service_location)
    expect(body.get('starts_at')).toBe('2026-11-11T01:00:00.000Z')
    expect(body.get('ends_at')).toBe('2026-11-11T02:00:00.000Z')
    const photos = body.getAll('photos[]') as File[]
    expect(photos.map(file => ({ name: file.name, type: file.type, size: file.size }))).toEqual([{ name: 'battery.png', type: 'image/png', size: photo.size }])
    expect(result).toEqual(created)
    expect(values.starts_at).toBe('2026-11-11T09:00')
  })

  it('invalidates dashboard and every repair-list filter after a successful request', async () => {
    api.defaults.adapter = async config => reply(config)
    await submitRepairRequest(values, [], appointment, cache)
    expect(cache.getQueryState(['dashboard'])?.isInvalidated).toBe(true)
    expect(cache.getQueryState(['repairs', 1, 'all'])?.isInvalidated).toBe(true)
    expect(cache.getQueryState(['technicians'])?.isInvalidated).toBe(false)
  })

  it('preserves optional serial/photo semantics', async () => {
    let body!: FormData
    api.defaults.adapter = async config => { body = config.data; return reply(config) }
    await submitRepairRequest(values, [], appointment, cache)
    expect(body.get('serial_number')).toBe('')
    expect(body.has('photos[]')).toBe(false)
  })

  it('propagates backend validation messages without invalidating successful-data caches', async () => {
    api.defaults.adapter = async config => {
      throw new AxiosError('Validation failed', 'ERR_BAD_REQUEST', config, undefined, {
        config, status: 422, statusText: 'Unprocessable Entity', headers: {},
        data: { errors: { starts_at: ['This appointment conflicts with another booking.'] } },
      })
    }
    let failure: unknown
    try { await submitRepairRequest(values, [], appointment, cache) }
    catch (error) { failure = error }
    expect(failure).toBeInstanceOf(AxiosError)
    expect(errorMessage(failure)).toBe('This appointment conflicts with another booking.')
    expect(cache.getQueryState(['dashboard'])?.isInvalidated).toBe(false)
    expect(cache.getQueryState(['repairs', 1, 'all'])?.isInvalidated).toBe(false)
  })
})
