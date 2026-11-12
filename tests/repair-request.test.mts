import assert from 'node:assert/strict'
import { describe, it } from 'node:test'
import { canRequestRepair, devicePhotoError, proposedAppointment } from '../frontend/src/lib/repair-request.ts'
import type { Option, Technician } from '../frontend/src/types/index.ts'

const category: Option = { id: 10, name: 'Laptop battery will not charge', device_type_id: 1, specialization_id: 2 }
const technician: Technician = {
  id: 1, name: 'Ana Reyes', biography: '', city: 'Santa Cruz', province: 'Laguna', years_experience: 3,
  latitude: null, longitude: null, photo_url: null, availability: 'available', verified: true, verification_status: 'approved',
  average_rating: 4.5, reviews_count: 3, specializations: [{ id: 2, name: 'Battery repair' }],
  device_types: [{ id: 1, name: 'Laptop' }], service_areas: [], matching: { score: 0, reasons: {} },
}

describe('repair request technician selection', () => {
  it('accepts an available approved specialist for the selected device and problem', () => {
    assert.equal(canRequestRepair(technician, '1', category), true)
  })
  it('rejects a preselected technician after the customer changes the device', () => {
    assert.equal(canRequestRepair(technician, '3', category), false)
  })
  it('rejects a preselected technician after the problem requires another specialization', () => {
    assert.equal(canRequestRepair(technician, '1', { ...category, specialization_id: 4 }), false)
  })
  it('rejects a supported device paired with a category for another device', () => {
    assert.equal(canRequestRepair({ ...technician, device_types: [...technician.device_types, { id: 3, name: 'Mobile' }] }, '3', category), false)
  })
  for (const availability of ['busy', 'unavailable'] as const) {
    it(`rejects a technician who is now ${availability}`, () => assert.equal(canRequestRepair({ ...technician, availability }, '1', category), false))
  }
  it('rejects an unapproved technician', () => assert.equal(canRequestRepair({ ...technician, verified: false }, '1', category), false))
  it('waits for both technician and category data', () => {
    assert.equal(canRequestRepair(undefined, '1', category), false)
    assert.equal(canRequestRepair(technician, '1', undefined), false)
  })
  it('supports general categories while retaining device compatibility', () => {
    assert.equal(canRequestRepair(technician, '1', { id: 4, name: 'General diagnosis' }), true)
    assert.equal(canRequestRepair(technician, '', category), false)
  })
})

describe('Philippine appointment input', () => {
  const now = Date.parse('2026-10-31T00:00:00Z')
  it('converts Philippine time to UTC with a one-hour duration', () => {
    assert.deepEqual(proposedAppointment('2026-11-02T09:30', now), { starts_at: '2026-11-02T01:30:00.000Z', ends_at: '2026-11-02T02:30:00.000Z' })
  })
  it('handles a Philippine midnight that is on the previous UTC day', () => {
    assert.deepEqual(proposedAppointment('2026-11-02T00:30', now), { starts_at: '2026-11-01T16:30:00.000Z', ends_at: '2026-11-01T17:30:00.000Z' })
  })
  for (const value of ['', 'invalid', '2026-11-02', '2027-02-30T09:00', '2026-11-02T25:00', '2026-10-31T07:59', '2026-10-31T08:00']) {
    it(`rejects an invalid or non-future appointment (${value || 'empty'})`, () => assert.equal(proposedAppointment(value, now), null))
  }
})

describe('device photo selection', () => {
  const photo = { size: 5 * 1024 * 1024, type: 'image/jpeg' }
  it('allows no optional photos or five photos at the size limit', () => {
    assert.equal(devicePhotoError([]), null)
    assert.equal(devicePhotoError(Array.from({ length: 5 }, () => photo)), null)
  })
  it('rejects more than five photos', () => assert.ok(devicePhotoError(Array.from({ length: 6 }, () => photo))))
  it('rejects an empty file', () => assert.ok(devicePhotoError([{ ...photo, size: 0 }])))
  it('rejects a photo larger than the limit', () => assert.ok(devicePhotoError([{ ...photo, size: photo.size + 1 }])))
  it('rejects non-image or unsupported image formats before upload', () => {
    for (const type of ['text/html', 'image/svg+xml', 'application/pdf', '']) assert.ok(devicePhotoError([{ ...photo, type }]))
  })
})
