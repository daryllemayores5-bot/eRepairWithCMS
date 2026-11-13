<script setup lang="ts">
import { computed, nextTick, ref, watch } from 'vue';
import { RouterLink as Link, RouterView, useRoute } from 'vue-router';
import { useAuthStore as useAuth } from '@/stores/auth';
import type { Technician, Repair, Dashboard } from '@/types';
import { Wrench, ArrowUpRight, CalendarDays, CircleUserRound, Clock3, Search, Star, X, Bell, ChevronDown, History, LayoutDashboard, Settings2, FileText, Package, ChartColumn } from 'lucide-vue-next';
import Button from '@/components/ui/Button.vue';
import Avatar from '@/components/ui/Avatar.vue';
import NavLink from '@/components/common/NavLink.vue';
const auth = useAuth(),
  route = useRoute(),
  expanded = ref(false);
const user = computed(() => auth.user),
  tech = computed(() => user.value?.role === 'technician');
const location = computed(() => ({
  pathname: route.path
}));
const links = computed(() => [{
  label: 'Overview',
  path: '/dashboard',
  icon: LayoutDashboard
}, {
  label: 'Find technicians',
  path: '/technicians',
  icon: Search
}, {
  label: tech.value ? 'Repair requests' : 'My repair requests',
  path: '/dashboard/repairs',
  icon: Wrench
}, {
  label: 'Active repairs',
  path: '/dashboard/active-repairs',
  icon: Clock3
}, {
  label: 'Appointments',
  path: '/dashboard/appointments',
  icon: CalendarDays
}, {
  label: 'Repair history',
  path: '/dashboard/history',
  icon: History
}, ...(tech.value ? [{
  label: 'Customer reviews',
  path: '/dashboard/reviews',
  icon: Star
}, {
  label: 'My Subscription',
  path: '/dashboard/subscription',
  icon: Star
}, {
  label: 'Documents & Templates · Pro',
  path: '/dashboard/documents',
  icon: FileText
}, {
  label: 'Parts Inventory · Premium',
  path: '/dashboard/inventory',
  icon: Package
}, {
  label: 'Business Reports · Premium',
  path: '/dashboard/business-reports',
  icon: ChartColumn
}, {
  label: 'Availability',
  path: '/dashboard/availability',
  icon: Settings2
}] : []), {
  label: 'Notifications',
  path: '/dashboard/notifications',
  icon: Bell
}, {
  label: 'Profile settings',
  path: '/dashboard/profile',
  icon: CircleUserRound
}]);
const current = computed(() => links.value.find(link => link.path === route.path));
watch(() => route.fullPath, () => { expanded.value = false; });
async function closeWorkspaceMenu() {
  if (!expanded.value) return;
  expanded.value = false;
  await nextTick();
  document.getElementById('workspace-menu-toggle')?.focus();
}
</script>

<template>
<div class="mx-auto w-full min-w-0 max-w-[1500px] p-4 sm:p-6 lg:p-8">
<aside class="workspace-sidebar mb-6 flex flex-col overflow-hidden rounded-2xl text-white lg:fixed lg:inset-y-0 lg:left-0 lg:z-50 lg:mb-0 lg:w-64 lg:overflow-y-auto lg:rounded-none" @keydown.esc="closeWorkspaceMenu">
<Link to="/" class="hidden min-h-[76px] items-center gap-2.5 border-b border-white/10 px-6 text-xl font-bold tracking-tight lg:flex">
<span class="rounded-lg bg-primary p-2 shadow-lg shadow-blue-600/20">
<Wrench class="size-5" aria-hidden="true">
</Wrench>
</span>eRepair<span class="ml-auto rounded-md bg-blue-500/20 px-2 py-1 text-[9px] font-semibold tracking-normal text-blue-100">
<template v-if="tech">{{ 'Technician' }}</template>
<template v-else>{{ 'Customer' }}</template>
</span>
</Link>
<div class="flex items-center justify-between gap-3 px-4 py-3 lg:px-6 lg:pb-5 lg:pt-7">
<div class="min-w-0">
<p class="text-[10px] font-bold uppercase tracking-[0.15em] text-blue-200/80">
<template v-if="tech">{{ 'Technician workspace' }}</template>
<template v-else>{{ 'Customer workspace' }}</template>
</p>
<p class="mt-1.5 text-sm font-semibold lg:hidden">{{ current?.label ?? (route.path.endsWith('/new') ? 'New repair request' : 'Repair details') }}</p>
<div class="mt-4 hidden items-center gap-3 lg:flex">
<Avatar :name="user?.name ?? 'Your account'" size="sm">
</Avatar>
<div class="min-w-0">
<p class="break-words text-sm font-semibold text-white">{{ user?.name || 'Your account' }}</p>
<p class="mt-1 text-[11px] text-blue-200">
<template v-if="tech">{{ 'Your professional workspace' }}</template>
<template v-else>{{ 'Your repair workspace' }}</template>
</p>
</div>
</div>
</div>
<Button id="workspace-menu-toggle" class="text-white hover:bg-white/10 hover:text-white lg:hidden" variant="ghost" size="sm" :aria-label="expanded ? 'Close workspace menu' : 'Open workspace menu'" :aria-expanded="expanded" aria-controls="workspace-navigation" @click="() => expanded = !expanded">
<span>{{ expanded ? 'Close' : 'Menu' }}</span>
<template v-if="expanded">
<X aria-hidden="true">
</X>
</template>
<template v-else>
<ChevronDown aria-hidden="true">
</ChevronDown>
</template>
</Button>
</div>
<nav id="workspace-navigation" aria-label="Dashboard navigation" :class="`${expanded ? 'grid' : 'hidden'} gap-1 p-3 lg:grid`">
<template v-for="({
  label,
  path,
  icon: Icon
}) in links" :key="path">
<NavLink :end="path === '/dashboard'" :to="path" @click="() => expanded = false" :nav-class="({
  isActive
}) => `flex min-h-11 items-center gap-3 rounded-xl px-3 py-2.5 text-sm transition-colors ${isActive ? 'bg-primary font-semibold text-white shadow-sm' : 'text-blue-100 hover:bg-white/10 hover:text-white'}`">
<component :is="Icon" class="size-[18px] shrink-0" aria-hidden="true">
</component>{{ label }}</NavLink>
</template>
</nav>
<div class="relative z-10 mx-5 mb-7 mt-auto hidden pt-10 lg:block">
<div class="rounded-2xl border border-blue-400/20 bg-white/[0.045] p-4">
<span class="mb-4 flex size-11 -rotate-12 items-center justify-center rounded-xl border border-blue-300/30 bg-blue-500/20 text-blue-200">
<Wrench class="size-6" aria-hidden="true">
</Wrench>
</span>
<p class="text-sm font-semibold text-white">A little guidance?</p>
<p class="mt-2 text-xs leading-5 text-blue-100/80">Find out how eRepair connects customers and repair professionals.</p>
<Link to="/how-it-works" class="mt-4 flex min-h-10 items-center justify-between rounded-lg border border-blue-400/40 px-3 text-xs font-semibold text-white transition-colors hover:bg-blue-500/20">How eRepair works<ArrowUpRight class="size-3.5" aria-hidden="true">
</ArrowUpRight>
</Link>
</div>
</div>
</aside>
<div class="min-w-0">
<p class="mb-5 flex items-center gap-2 text-[11px] text-slate-500">
<Link to="/dashboard" class="font-semibold text-blue-700">
<template v-if="tech">{{ 'Technician workspace' }}</template>
<template v-else>{{ 'Customer workspace' }}</template>
</Link>
<span aria-hidden="true">›</span>
<span>{{ current?.label ?? (location.pathname.endsWith('/new') ? 'New repair request' : 'Repair details') }}</span>
</p>
<RouterView v-slot="{Component}">
<component :is="Component" :key="route.path" />
</RouterView>
</div>
</div>
</template>
