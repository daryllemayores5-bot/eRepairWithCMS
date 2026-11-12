import { afterEach, describe, expect, it, vi } from 'vitest'
import { createApp, defineComponent, h, nextTick, reactive } from 'vue'
import * as L from 'leaflet'
import { useTechnicianMap } from './technician-map'
import type { Technician } from '@/types'

// jsdom supports SVG nodes but omits the browser feature-detection method.
vi.hoisted(() => {
  Object.defineProperty(window.SVGSVGElement.prototype, 'createSVGRect', { value: () => ({}), configurable: true })
})
const cleanups: (() => void)[] = []
afterEach(() => { cleanups.splice(0).forEach(cleanup => cleanup()); vi.restoreAllMocks() })

function technician(id: number, latitude: number | null, longitude: number | null): Technician {
  return {
    id, name: `Technician ${id}`, latitude, longitude, biography: null,
    city: 'Santa Cruz', province: 'Laguna', years_experience: 5, photo_url: null,
    availability: 'available', verified: true, verification_status: 'approved',
    average_rating: 4.5, reviews_count: 2, specializations: [], device_types: [],
    service_areas: [], matching: { score: 0, reasons: {} },
  }
}

async function flushMap() { await nextTick(); await nextTick(); await nextTick() }
async function mount(technicians: Technician[]) {
  const state = reactive({ technicians })
  let model!: ReturnType<typeof useTechnicianMap>
  const container = document.createElement('div')
  document.body.append(container)
  const app = createApp(defineComponent({
    setup() {
      model = useTechnicianMap(() => state.technicians)
      return () => h('div', [
        model.mapped.value.length ? h('div', {
          ref: (value) => {
            const element = value as HTMLDivElement | null
            if (element) {
              Object.defineProperty(element, 'clientWidth', { value: 800, configurable: true })
              Object.defineProperty(element, 'clientHeight', { value: 500, configurable: true })
            }
            model.element.value = element
          },
        }) : h('p', 'No map locations on this page'),
        h('button', { onClick: model.fit }, 'Fit all locations'),
      ])
    },
  }))
  app.mount(container)
  let mounted = true
  const unmount = () => { if (mounted) { app.unmount(); container.remove(); mounted = false } }
  cleanups.push(unmount)
  await flushMap()
  return { model, state, container, unmount }
}
function circles(map: L.Map) {
  const result: L.CircleMarker[] = []
  map.eachLayer(layer => { if (layer instanceof L.CircleMarker) result.push(layer) })
  return result
}

describe('Vue technician map with the real Leaflet engine', () => {
  it('fits every located result and refits changed coordinates without resetting identical results', async () => {
    const bounds = vi.spyOn(L.Map.prototype, 'fitBounds')
    const view = await mount([technician(1, 14.28, 121.42), technician(2, 14.6, 120.98)])
    expect(bounds).toHaveBeenLastCalledWith([[14.28, 121.42], [14.6, 120.98]], { padding: [36, 36], maxZoom: 12 })
    const map = bounds.mock.contexts[0] as L.Map
    expect(circles(map)).toHaveLength(2)
    view.state.technicians = [technician(1, 14.28, 121.42), technician(2, 14.6, 120.98)]
    await flushMap()
    expect(bounds).toHaveBeenCalledTimes(1)
    view.state.technicians = [technician(1, 14.3, 121.45)]
    await flushMap()
    expect(bounds).toHaveBeenCalledTimes(2)
    expect(bounds).toHaveBeenLastCalledWith([[14.3, 121.45]], { padding: [36, 36], maxZoom: 12 })
    expect(circles(map)).toHaveLength(1)
  })

  it('groups overlapping professionals into one marker without losing popup participants', async () => {
    const bounds = vi.spyOn(L.Map.prototype, 'fitBounds')
    const { model } = await mount([technician(1, 14.28, 121.42), technician(2, 14.28, 121.42)])
    const markers = circles(bounds.mock.contexts[0] as L.Map)
    expect(markers).toHaveLength(1)
    expect(markers[0].getRadius()).toBe(13)
    expect(model.popups.value[0].group.map(profile => profile.id)).toEqual([1, 2])
    expect(markers[0].getPopup()?.getContent()).toBe(model.popups.value[0].element)
  })

  it('never invents locations for profiles with missing coordinates', async () => {
    const bounds = vi.spyOn(L.Map.prototype, 'fitBounds')
    const { model, container } = await mount([technician(1, null, null)])
    expect(bounds).not.toHaveBeenCalled()
    expect(model.popups.value).toEqual([])
    expect(model.unlocated.value).toBe(1)
    expect(container.textContent).toContain('No map locations on this page')
    expect(container.querySelector('.leaflet-container')).toBeNull()
  })

  it('rejects partial, nonfinite and out-of-range coordinates while preserving zero', async () => {
    const bounds = vi.spyOn(L.Map.prototype, 'fitBounds')
    const { model } = await mount([
      technician(1, 91, 121), technician(2, 14, -181), technician(3, Number.NaN, 121),
      technician(4, 14, null), technician(5, 0, 0),
    ])
    expect(bounds).toHaveBeenLastCalledWith([[0, 0]], { padding: [36, 36], maxZoom: 12 })
    expect(model.unlocated.value).toBe(4)
    expect(circles(bounds.mock.contexts[0] as L.Map)).toHaveLength(1)
  })

  it('allows the user to restore all marker bounds', async () => {
    const bounds = vi.spyOn(L.Map.prototype, 'fitBounds')
    const { container } = await mount([technician(1, 14.28, 121.42)])
    container.querySelector('button')!.click()
    expect(bounds).toHaveBeenCalledTimes(2)
  })

  it('reports tile failures through Vue state', async () => {
    const bounds = vi.spyOn(L.Map.prototype, 'fitBounds')
    const { model } = await mount([technician(1, 14.28, 121.42)])
    const map = bounds.mock.contexts[0] as L.Map
    expect(model.tileError.value).toBe(false)
    map.eachLayer(layer => { if (layer instanceof L.TileLayer) layer.fire('tileerror') })
    expect(model.tileError.value).toBe(true)
  })

  it('retries failed tiles without replacing markers or resetting the map view', async () => {
    const bounds = vi.spyOn(L.Map.prototype, 'fitBounds')
    const redraw = vi.spyOn(L.TileLayer.prototype, 'redraw')
    const { model } = await mount([technician(1, 14.28, 121.42)])
    const map = bounds.mock.contexts[0] as L.Map
    const markers = circles(map)
    map.eachLayer(layer => { if (layer instanceof L.TileLayer) layer.fire('tileerror') })
    model.retryTiles()
    expect(redraw).toHaveBeenCalledTimes(1)
    expect(model.tileError.value).toBe(false)
    expect(circles(map)).toEqual(markers)
    expect(bounds).toHaveBeenCalledTimes(1)
    map.eachLayer(layer => { if (layer instanceof L.TileLayer) layer.fire('tileerror') })
    expect(model.tileError.value).toBe(true)
  })

  it('removes old maps and can recover after a refetch has no located results', async () => {
    const bounds = vi.spyOn(L.Map.prototype, 'fitBounds'), remove = vi.spyOn(L.Map.prototype, 'remove')
    const view = await mount([technician(1, 14.28, 121.42)])
    const map = bounds.mock.contexts[0] as L.Map
    map.eachLayer(layer => { if (layer instanceof L.TileLayer) layer.fire('tileerror') })
    view.state.technicians = [technician(1, null, null)]
    await flushMap()
    expect(remove).toHaveBeenCalledTimes(1)
    expect(view.model.popups.value).toEqual([])
    expect(view.model.tileError.value).toBe(false)
    view.state.technicians = [technician(2, 14.3, 121.45)]
    await flushMap()
    expect(bounds).toHaveBeenCalledTimes(2)
    expect(view.model.popups.value[0].group[0].id).toBe(2)
    view.unmount()
    expect(remove).toHaveBeenCalledTimes(2)
  })
})
