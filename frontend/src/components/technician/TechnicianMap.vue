<script setup lang="ts">
import { RouterLink } from 'vue-router'
import { Crosshair, MapPin, WifiOff } from 'lucide-vue-next'
import type { Technician } from '@/types'
import Button from '@/components/ui/Button.vue'
import { useTechnicianMap } from '@/composables/technician-map'
import 'leaflet/dist/leaflet.css'

const props = defineProps<{ technicians: Technician[] }>()
const { tileError, element, mapped, points, unlocated, popups, fit, retryTiles } = useTechnicianMap(() => props.technicians)
</script>

<template>
<div v-if="!mapped.length" class="rounded-2xl border border-dashed bg-slate-50 p-8 text-center">
    <MapPin class="mx-auto size-8 text-slate-400" aria-hidden="true" />
<h3 class="mt-3 font-semibold">No map locations on this page</h3>
    <p class="mt-2 text-sm leading-6 text-muted-foreground">These technicians are still available in List view. We do not guess a location when coordinates are missing.</p>
  </div>
  <div v-else class="overflow-hidden rounded-2xl border bg-white shadow-sm">
    <div class="flex flex-wrap items-start justify-between gap-3 border-b px-5 py-4">
<div>
<h3 class="flex items-center gap-2 text-sm font-semibold">
<MapPin class="size-4 text-primary" aria-hidden="true" />Approximate service locations</h3>
<p class="mt-1.5 text-xs leading-5 text-muted-foreground">{{ mapped.length }} technicians at {{ points.length }} locations on this results page. Shared markers include every technician at that point.</p>
</div>
<span class="rounded-full bg-blue-50 px-3 py-1 text-xs font-semibold text-blue-700">City-level locations</span>
</div>
    <div v-if="tileError" role="status" class="flex flex-wrap items-center gap-3 border-b bg-amber-50 px-5 py-3 text-sm text-amber-900">
<WifiOff class="size-4 shrink-0" aria-hidden="true" /><p class="min-w-0 flex-1">Map tiles could not load. Check your connection or switch to List view.</p>
<Button type="button" variant="outline" size="sm" @click="retryTiles">Retry map tiles</Button></div>
    <div class="relative">
      <div ref="element" class="relative h-[420px] w-full sm:h-[520px]" />
      <div class="absolute top-3 right-3 z-[1000]">
<Button type="button" variant="outline" size="sm" @click="fit">
<Crosshair aria-hidden="true" />Fit all locations</Button>
</div>
    </div>
    <p class="border-t px-5 py-3 text-xs text-muted-foreground">Approved technician locations refresh automatically. Location references: <a href="https://simplemaps.com/data/ph-cities" target="_blank" rel="noopener noreferrer" class="underline">SimpleMaps</a> and <a href="https://open-meteo.com/en/docs/geocoding-api" target="_blank" rel="noopener noreferrer" class="underline">Open-Meteo / GeoNames</a>.</p>
    <Teleport v-for="popup in popups" :key="popup.key" :to="popup.element">
      <p class="font-semibold">{{ popup.group[0].city }}, {{ popup.group[0].province }}</p>
      <p class="text-xs text-slate-500">Approximate location · {{ popup.group.length }} {{ popup.group.length === 1 ? 'technician' : 'technicians' }}</p>
      <ul class="space-y-3">
<li v-for="technician in popup.group" :key="technician.id" class="border-t border-slate-200 pt-2">
        <strong class="block">{{ technician.name }}</strong>
<span class="block text-xs text-slate-600">{{ technician.city }}, {{ technician.province }}</span>
        <span class="block text-xs text-slate-600">{{ technician.availability === 'available' ? 'Accepting requests' : technician.availability === 'busy' ? 'Currently busy' : 'Currently unavailable' }}</span>
        <RouterLink class="mt-1 inline-block py-1" :aria-label="`View profile of ${technician.name}`" :to="`/technicians/${technician.id}`">View profile<span class="sr-only"> of {{ technician.name }}</span>
</RouterLink>
      </li>
</ul>
    </Teleport>
    <p class="border-t bg-slate-50 px-5 py-3 text-xs leading-5 text-muted-foreground">Markers are rounded service-location references, not exact shop or home addresses. No service radius or travel distance is implied.{{ unlocated ? ` ${unlocated} ${unlocated === 1 ? 'profile has' : 'profiles have'} no valid map coordinates; use List view to see all results.` : '' }}</p>
  </div>
</template>
