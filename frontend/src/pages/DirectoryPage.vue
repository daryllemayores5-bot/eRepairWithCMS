<script setup lang="ts">
import { computed, ref, defineAsyncComponent, watch, onBeforeUnmount } from 'vue';
import { RouterLink as Link, useRoute, useRouter } from 'vue-router';
import { useApiQuery } from '@/composables/query';
import { useLookups } from '@/composables/content';
import { api } from '@/services/api';
import { toast } from 'vue-sonner';
import { fieldClass } from '@/components/ui/fields';
import type { Technician, Repair, Page } from '@/types';
import { BadgeCheck, Search, Map, List, SlidersHorizontal, X } from 'lucide-vue-next';
import Button from '@/components/ui/Button.vue';
import Input from '@/components/ui/Input.vue';
import Loading from '@/components/common/Loading.vue';
import Failure from '@/components/common/Failure.vue';
import Empty from '@/components/common/Empty.vue';
import Pagination from '@/components/common/Pagination.vue';
import TechnicianCard from '@/components/technician/TechnicianCard.vue';
import { vInitialValue, vInitialChecked } from '@/lib/initial-fields';
import { technicianSelection } from '@/lib/technician-selection';
// Query options read params during setup, before template handlers can run.
const URLSearchParams = globalThis.URLSearchParams;
const FormData = globalThis.FormData;
const route = useRoute(),
  router = useRouter();
const params = computed(() => {
  const search = new URLSearchParams(route.fullPath.split('?')[1]?.split('#')[0] ?? '');
  search.delete('compare');
  return search;
});
const TechnicianMap = defineAsyncComponent({
  loader: () => import('@/components/technician/TechnicianMap.vue'),
  loadingComponent: Loading,
  delay: 0,
});
const map = ref(false),
  filtersOpen = ref(false);
const locating = ref(false);
let locationRequest = 0;
function cancelLocation() {
  locationRequest++;
  locating.value = false;
}
watch(() => route.fullPath, cancelLocation, { flush: 'sync' });
onBeforeUnmount(cancelLocation);
const compare = computed(() => technicianSelection(route.query.compare));
const lookups = useLookups();
const result = useApiQuery<Page<Technician>>(() => ({
  queryKey: ['technicians', params.value.toString()],
  refetchInterval: map.value ? 30000 : false,
  queryFn: async ({
    signal
  }) => (await api.get<Page<Technician>>('/technicians?' + params.value, {
    signal
  })).data
}));
function setParams(next: URLSearchParams | Record<string, string>) {
  cancelLocation();
  const search = new URLSearchParams(next);
  if (compare.value.length) search.set('compare', compare.value.join(','));
  return router.push('/technicians' + (search.size ? '?' + search : ''));
}
function setComparison(ids: number[]) {
  return router.replace({
    path: route.path,
    query: { ...route.query, compare: ids.length ? ids.join(',') : undefined },
    hash: route.hash,
  });
}
function toggle(t: Technician) {
  if (compare.value.includes(t.id)) void setComparison(compare.value.filter(id => id !== t.id));
  else if (compare.value.length < 3) void setComparison([...compare.value, t.id]);
  else toast.info('Compare up to three technicians at a time.');
}
function locate() {
  if (locating.value) return;
  if (!navigator.geolocation) {
    toast.info('Location is unavailable. Enter your city manually.');
    return;
  }
  const request = ++locationRequest;
  locating.value = true;
  navigator.geolocation.getCurrentPosition(position => {
    if (request !== locationRequest) return;
    locating.value = false;
    const next = new URLSearchParams(params.value);
    next.set('latitude', position.coords.latitude.toFixed(2));
    next.set('longitude', position.coords.longitude.toFixed(2));
    next.set('sort', 'distance');
    next.delete('page');
    void setParams(next);
  }, () => {
    if (request !== locationRequest) return;
    locating.value = false;
    toast.info('Location access was unavailable. You can enter your city manually.');
  }, {
    enableHighAccuracy: false,
    timeout: 10000
  });
}
function clearLocation() {
  cancelLocation();
  const next = new URLSearchParams(params.value);
  next.delete('latitude');
  next.delete('longitude');
  next.delete('page');
  if (next.get('sort') === 'distance') next.set('sort', 'recommended');
  void setParams(next);
}
</script>

<template>
<div class="page-shell">
<div class="surface-card p-6 sm:p-8">
<p class="flex items-center gap-2 text-xs font-bold uppercase tracking-widest text-primary">
<BadgeCheck class="size-4" aria-hidden="true">
</BadgeCheck>Local expertise, matched to your device</p>
<h1 class="mt-3 text-3xl font-bold tracking-tight text-navy sm:text-4xl">Find your repair technician</h1>
<p class="mt-3 max-w-2xl text-sm leading-7 text-muted-foreground">Choose your device and location, compare approved professionals, then request a repair from the technician you trust.</p>
</div>
<Button type="button" variant="outline" class="mt-5 w-full lg:hidden" :aria-expanded="filtersOpen" aria-controls="technician-filters" @click="() => filtersOpen = !filtersOpen">
<SlidersHorizontal aria-hidden="true">
</SlidersHorizontal>
<template v-if="filtersOpen">{{ 'Hide search filters' }}</template>
<template v-else>{{ 'Show search filters' }}</template>
</Button>
<div class="mt-8 grid gap-7 lg:grid-cols-[250px_minmax(0,1fr)]">
<aside id="technician-filters" :class="`${filtersOpen ? 'block' : 'hidden'} lg:block`">
<form :key="params.toString()" class="surface-card space-y-5 p-5" @submit="(e: Event) => {
  e.preventDefault();
  const next = new URLSearchParams();
  new FormData(e.currentTarget as HTMLFormElement).forEach((value, key) => {
    if (value) next.set(key, String(value));
  });
  setParams(next);
  filtersOpen = false;
}">
<h2 class="flex items-center gap-2 font-semibold">
<SlidersHorizontal class="size-4 text-primary" aria-hidden="true">
</SlidersHorizontal>Refine your search</h2>
<Button type="button" variant="outline" class="w-full" :disabled="locating" :aria-busy="locating" @click="locate">{{ locating ? 'Finding your location…' : 'Use approximate location' }}</Button>
<p v-if="locating" role="status" class="text-xs leading-5 text-muted-foreground">Waiting for location permission. You can still search by city.</p>
<template v-if="params.has('latitude') && params.has('longitude')">
<p class="text-xs leading-5 text-muted-foreground">Approximate location is applied to this search.</p>
<Button type="button" variant="ghost" class="w-full" @click="clearLocation">Remove approximate location</Button>
</template>
<template v-if="params.has('latitude')">
<input type="hidden" name="latitude" :value="params.get('latitude') ?? ''">
</input>
<input type="hidden" name="longitude" :value="params.get('longitude') ?? ''">
</input>
</template>
<label class="block text-sm">Name or repair problem<Input class="mt-2" name="search" :default-value="params.get('search') ?? ''" placeholder="Screen, battery, technician…">
</Input>
</label>
<label class="block text-sm">City / municipality<Input class="mt-2" name="city" :default-value="params.get('city') ?? ''" placeholder="Santa Cruz">
</Input>
</label>
<label class="block text-sm">Province<Input class="mt-2" name="province" :default-value="params.get('province') ?? ''" placeholder="Laguna">
</Input>
</label>
<label class="block text-sm">Device type<select :class="`${fieldClass} mt-2`" name="device_type" v-initial-value="params.get('device_type') ?? ''">
<option value="">All devices</option>
<template v-for="(o) in lookups.data?.device_types" :key="o.id">
<option :value="o.id">{{ o.name }}</option>
</template>
</select>
</label>
<label class="block text-sm">Repair problem<select :class="`${fieldClass} mt-2`" name="repair_category" v-initial-value="params.get('repair_category') ?? ''">
<option value="">All repair problems</option>
<template v-for="(o) in lookups.data?.repair_categories" :key="o.id">
<option :value="o.id">{{ o.name }}</option>
</template>
</select>
</label>
<label class="block text-sm">Specialization<select :class="`${fieldClass} mt-2`" name="specialization" v-initial-value="params.get('specialization') ?? ''">
<option value="">All repair services</option>
<template v-for="(o) in lookups.data?.specializations" :key="o.id">
<option :value="o.id">{{ o.name }}</option>
</template>
</select>
</label>
<label class="block text-sm">Availability<select :class="`${fieldClass} mt-2`" name="availability" v-initial-value="params.get('availability') ?? ''">
<option value="">Any availability</option>
<option value="available">Accepting requests</option>
<option value="busy">Busy</option>
</select>
</label>
<label class="block text-sm">Minimum rating<select :class="`${fieldClass} mt-2`" name="min_rating" v-initial-value="params.get('min_rating') ?? ''">
<option value="">All ratings</option>
<option value="4">4 stars and above</option>
<option value="3">3 stars and above</option>
</select>
</label>
<label class="block text-sm">Sort by<select :class="`${fieldClass} mt-2`" name="sort" v-initial-value="params.get('sort') ?? 'recommended'">
<option value="recommended">Recommended</option>
<option value="rating">Highest rating</option>
<option value="experience">Most experienced</option>
<template v-if="params.has('latitude')">
<option value="distance">Nearest service area</option>
</template>
</select>
</label>
<p class="text-xs leading-5 text-muted-foreground">Only administrator-approved, active technicians appear.</p>
<Button type="submit" class="w-full">
<Search>
</Search>Apply filters</Button>
<Button type="button" variant="ghost" class="w-full" @click="() => setParams({})">Reset filters</Button>
</form>
<template v-if="lookups.isError">
<Failure :error="lookups.error" :retry="() => void lookups.refetch()">
</Failure>
</template>
</aside>
<section class="min-w-0" aria-label="Technician results">
<div class="mb-5 flex flex-wrap items-center justify-between gap-3">
<p role="status" class="text-sm font-medium text-slate-600">
<template v-if="result.data">{{ `${result.data.meta?.total ?? result.data.data.length} matching technicians` }}</template>
<template v-else>{{ result.isError ? 'Search unavailable' : 'Searching technicians…' }}</template>
</p>
<div class="flex gap-1 rounded-xl border bg-white p-1" role="group" aria-label="Results display">
<Button size="sm" :variant="map ? 'ghost' : 'default'" :aria-pressed="!map" @click="() => map = false">
<List aria-hidden="true">
</List>List view</Button>
<Button size="sm" :variant="map ? 'default' : 'ghost'" :aria-pressed="map" @click="() => map = true">
<Map aria-hidden="true">
</Map>Map view</Button>
</div>
</div>
<template v-if="result.isPending">
<Loading>
</Loading>
</template>
<template v-else>
<template v-if="result.isError && !result.data">
<Failure :error="result.error" :retry="() => void result.refetch()">
</Failure>
</template>
<template v-else>
<div v-if="result.isError" role="alert" class="mb-5 rounded-xl border border-amber-200 bg-amber-50 p-4 text-sm text-amber-900">
<p>Unable to refresh results. Showing the last successful search; availability may have changed.</p>
<Button type="button" variant="outline" class="mt-3" :disabled="result.isFetching" @click="() => void result.refetch()">Retry results refresh</Button>
</div>
<template v-if="!result.data.data.length">
<Empty title="No matching technicians" description="Try a nearby city, another specialization, or fewer filters.">
</Empty>
</template>
<template v-else>
<template v-if="map">
<TechnicianMap :key="params.toString()" :technicians="result.data.data">
</TechnicianMap>
</template>
<template v-else>
<div class="grid gap-5 xl:grid-cols-2">
<template v-for="(t) in result.data.data" :key="t.id">
<TechnicianCard :technician="t" :selected="compare.includes(t.id)" :onCompare="toggle">
</TechnicianCard>
</template>
</div>
</template>
<Pagination :page="result.data" :onChange="page => {
  const next = new URLSearchParams(params);
  next.set('page', String(page));
  setParams(next);
}">
</Pagination>
</template>
</template>
</template>
</section>
</div>
<template v-if="compare.length">
<div class="sticky bottom-4 z-30 mt-6 flex flex-wrap items-center justify-between gap-3 rounded-2xl bg-navy p-4 text-white shadow-lg">
<span class="text-sm">{{ compare.length }} of 3 technicians selected</span>
<div class="grid w-full grid-cols-[auto_minmax(0,1fr)] items-center gap-2 sm:flex sm:w-auto">
<Button variant="ghost" class="text-white hover:bg-slate-800 hover:text-white" @click="() => setComparison([])">
<X aria-hidden="true">
</X>Clear</Button>
<Button as-child>
<Link :to="{ path: '/compare', query: { ids: compare.join(','), search: params.toString() } }">Compare profiles</Link>
</Button>
</div>
</div>
</template>
</div>
</template>
