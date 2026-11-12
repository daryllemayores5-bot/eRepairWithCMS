import { computed, nextTick, onBeforeUnmount, onMounted, ref, shallowRef, watch } from 'vue'
import * as L from 'leaflet'
import type { Technician } from '@/types'
import { hasMapLocation } from '@/lib/map'

export function useTechnicianMap(technicians: () => Technician[]) {
  const tileError = ref(false)
  const element = ref<HTMLDivElement | null>(null)
  const mapped = computed(() => technicians().filter(hasMapLocation))
  const points = computed(() => {
    const groups = new Map<string, Technician[]>()
    for (const technician of mapped.value) {
      const key = `${technician.latitude},${technician.longitude}`
      groups.set(key, [...(groups.get(key) ?? []), technician])
    }
    return [...groups.values()]
  })
  const coordinates = computed(() => JSON.stringify(points.value.map(group => [group[0].latitude, group[0].longitude])))
  const unlocated = computed(() => technicians().length - mapped.value.length)
  const popups = shallowRef<{ key: string; element: HTMLElement; group: Technician[] }[]>([])
  let map: L.Map | undefined
  let markers: L.LayerGroup | undefined
  let tiles: L.TileLayer | undefined
  let disposed = false
  let lastCoordinates = ''

  function fit() {
    const locations: [number, number][] = points.value.map(group => [group[0].latitude!, group[0].longitude!])
    if (locations.length) map?.fitBounds(locations, { padding: [36, 36], maxZoom: 12 })
  }

  function retryTiles() {
    tileError.value = false
    tiles?.redraw()
  }

  async function refresh() {
    await nextTick()
    if (disposed) return
    if (!mapped.value.length || !element.value) {
      map?.remove(); map = undefined; markers = undefined; tiles = undefined
      tileError.value = false
      popups.value = []; lastCoordinates = ''
      return
    }
    if (!map) {
      map = L.map(element.value, {
        center: [mapped.value[0].latitude!, mapped.value[0].longitude!],
        zoom: 10, maxZoom: 18, scrollWheelZoom: false,
      })
      tileError.value = false
      tiles = L.tileLayer('https://tile.openstreetmap.org/{z}/{x}/{y}.png', {
        attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors',
      }).on('tileerror', () => { tileError.value = true }).addTo(map)
      markers = L.layerGroup().addTo(map)
    }
    markers!.clearLayers()
    popups.value = points.value.map(group => {
      const target = document.createElement('div')
      const key = `${group[0].latitude},${group[0].longitude}`
      L.circleMarker([group[0].latitude!, group[0].longitude!], {
        radius: group.length > 1 ? 13 : 9, color: '#fff', weight: 3, fillColor: '#2563eb', fillOpacity: 1,
      }).bindPopup(target).addTo(markers!)
      return { key, element: target, group }
    })
    if (coordinates.value !== lastCoordinates) { fit(); lastCoordinates = coordinates.value }
  }

  onMounted(refresh)
  watch(technicians, refresh, { deep: true, flush: 'post' })
  onBeforeUnmount(() => { disposed = true; map?.remove(); map = undefined; tiles = undefined; popups.value = [] })
  return { tileError, element, mapped, points, unlocated, popups, fit, retryTiles }
}
