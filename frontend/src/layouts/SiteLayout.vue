<script setup lang="ts">
import { computed, nextTick, ref, watch } from 'vue';
import { RouterLink as Link, RouterView, useRoute, useRouter } from 'vue-router';
import { useAuthStore as useAuth } from '@/stores/auth';
import { toast } from 'vue-sonner';
import { errorMessage } from '@/lib/errors';
import type { Repair } from '@/types';
import { Wrench, ArrowRight, Search, X, Laptop, Bell, LayoutDashboard, LogOut, Menu } from 'lucide-vue-next';
import Button from '@/components/ui/Button.vue';
import Avatar from '@/components/ui/Avatar.vue';
import NavLink from '@/components/common/NavLink.vue';
const auth = useAuth(),
  router = useRouter(),
  route = useRoute(),
  open = ref(false);
const workspace = computed(() => route.path.startsWith('/dashboard'));
const navigate = (to: string) => router.push(to);
async function closePublicMenu() {
  if (!open.value) return;
  open.value = false;
  await nextTick();
  document.getElementById('main-navigation-toggle')?.focus();
}
watch(() => route.fullPath, () => {
  open.value = false;
});
const navClass = ({
  isActive
}: {
  isActive: boolean;
}) => 'public-nav-link rounded-lg px-3 py-2.5 transition-colors ' + (isActive ? 'font-semibold text-blue-700' : 'text-slate-600 hover:bg-blue-50 hover:text-blue-700');
async function logout() {
  try {
    await auth.logout(() => router.push('/'));
    toast.success('Signed out');
  } catch (error) {
    toast.error(errorMessage(error));
  }
}
const FormData = globalThis.FormData;
</script>

<template>
<div :class="`erepair-platform flex min-h-dvh min-w-0 flex-col ${workspace ? 'workspace-shell lg:pl-64' : 'public-shell'}`">
<a href="#main-content" class="sr-only z-[60] bg-white p-3 focus:not-sr-only focus:fixed focus:top-2 focus:left-2">Skip to content</a>
<template v-if="workspace">
<header class="sticky top-0 z-40 border-b border-slate-200/80 bg-white/95 backdrop-blur">
<div class="mx-auto flex min-h-[76px] max-w-[1500px] flex-wrap items-center gap-3 px-4 py-3 sm:flex-nowrap sm:gap-5 sm:px-6 sm:py-0 lg:px-8">
<Link to="/" class="flex min-h-11 items-center gap-2 text-base font-bold text-navy sm:hidden" aria-label="eRepair home"><Wrench class="size-5 text-primary" aria-hidden="true" />eRepair</Link>
<form role="search" class="order-3 flex w-full min-w-0 basis-full items-center gap-2 rounded-xl border border-slate-200 bg-slate-50/80 px-3 sm:order-none sm:max-w-xl sm:flex-1 sm:basis-auto" @submit="(event: Event) => {
  event.preventDefault();
  const search = String(new FormData(event.currentTarget as HTMLFormElement).get('search') ?? '').trim();
  navigate(`/technicians${search ? `?search=${encodeURIComponent(search)}` : ''}`);
}">
<Search class="size-4 shrink-0 text-slate-500" aria-hidden="true">
</Search>
<label class="sr-only" for="workspace-search">Search technicians</label>
<input id="workspace-search" name="search" type="search" placeholder="Search technicians, skills or locations…" class="min-h-11 w-full min-w-0 bg-transparent text-sm outline-none placeholder:text-slate-500 focus-visible:ring-2 focus-visible:ring-primary">
</input>
<button type="submit" class="min-h-11 shrink-0 rounded-lg px-2 text-sm font-semibold text-primary hover:bg-blue-50 focus-visible:outline-2 focus-visible:outline-primary">Search</button>
</form>
<div class="ml-auto flex shrink-0 items-center gap-1.5 sm:gap-3">
<Button as-child variant="ghost" size="icon">
<Link to="/dashboard/notifications" aria-label="Notifications">
<Bell aria-hidden="true">
</Bell>
</Link>
</Button>
<span class="hidden h-7 border-l sm:block">
</span>
<Link to="/dashboard/profile" class="flex items-center gap-2 rounded-xl p-1 focus-visible:outline-primary" aria-label="Your profile">
<Avatar :name="auth.user?.name ?? 'Your account'" size="sm">
</Avatar>
<span class="hidden max-w-32 truncate text-xs font-semibold text-navy xl:block">{{ auth.user?.name }}</span>
</Link>
<Button variant="ghost" size="sm" :disabled="auth.pending" @click="() => void logout()" aria-label="Sign out">
<LogOut aria-hidden="true">
</LogOut>
<span class="hidden sm:inline">Sign out</span>
</Button>
</div>
</div>
</header>
</template>
<template v-else>
<header class="reference-header sticky top-0 z-40 border-b bg-white/95 backdrop-blur" @keydown.esc="closePublicMenu">
<div class="mx-auto flex max-w-7xl flex-wrap items-center justify-between gap-4 px-5 py-4 sm:px-8">
<Link to="/" class="flex items-center gap-2.5 text-xl font-bold tracking-tight" aria-label="eRepair home">
<span class="brand-symbol rounded-lg bg-primary p-2 text-white">
<Wrench class="size-5" aria-hidden="true">
</Wrench>
</span>
<span class="brand-wordmark">e<span class="text-primary">Repair</span>
</span>
</Link>
<Button id="main-navigation-toggle" variant="outline" size="icon" class="lg:hidden" :aria-label="open ? 'Close menu' : 'Open menu'" :aria-expanded="open" aria-controls="main-navigation" @click="() => open = !open">
<template v-if="open">
<X aria-hidden="true">
</X>
</template>
<template v-else>
<Menu aria-hidden="true">
</Menu>
</template>
</Button>
<nav id="main-navigation" aria-label="Main navigation" :class="`${open ? 'flex' : 'hidden'} w-full flex-col gap-1 text-xs font-medium lg:flex lg:w-auto lg:flex-row lg:items-center`" @click="() => open = false">
<NavLink :nav-class="navClass" to="/" end>Home</NavLink>
<NavLink :nav-class="navClass" to="/technicians">
<span class="flex items-center gap-2">
<Search class="size-4" aria-hidden="true">
</Search>Find technicians</span>
</NavLink>
<template v-if="workspace">
<NavLink :nav-class="navClass" to="/dashboard/notifications">Notifications</NavLink>
</template>
<template v-else>
<NavLink :nav-class="navClass" to="/how-it-works">How it works</NavLink>
<NavLink :nav-class="navClass" to="/pages/about">About</NavLink>
</template>
<template v-if="auth.user">
<NavLink :nav-class="navClass" to="/dashboard">
<span class="flex items-center gap-2">
<LayoutDashboard class="size-4" aria-hidden="true">
</LayoutDashboard>
<span class="max-w-[180px] truncate">{{ auth.user.name.split(' ')[0] }}’s dashboard</span>
</span>
</NavLink>
<Button variant="outline" :disabled="auth.pending" @click="() => void logout()">
<LogOut aria-hidden="true">
</LogOut>Sign out</Button>
</template>
<template v-else>
<NavLink :nav-class="navClass" to="/login">Sign in</NavLink>
<Button as-child>
<Link to="/register">Sign up<ArrowRight aria-hidden="true">
</ArrowRight>
</Link>
</Button>
</template>
</nav>
</div>
</header>
</template>
<main id="main-content" class="min-w-0 w-full flex-1">
<RouterView v-slot="{Component}">
<component :is="Component" :key="route.path" />
</RouterView>
</main>
<template v-if="!workspace">
<footer class="reference-footer border-t border-slate-200/80 bg-navy text-slate-200">
<div class="mx-auto grid max-w-7xl gap-8 px-6 py-12 sm:grid-cols-2 lg:grid-cols-4">
<div>
<p class="flex items-center gap-2 text-lg font-bold text-white"><span class="rounded-md bg-primary p-1.5"><Wrench class="size-4" aria-hidden="true" /></span>eRepair</p>
<p class="mt-3 text-sm leading-6 text-slate-300">Local expertise. A clearer repair journey.</p>
<p class="mt-3 text-xs leading-6 text-slate-400">Discover laptop and mobile repair professionals, compare their skills and stay connected through your repair.</p>
</div>
<div class="space-y-3 text-sm">
<p class="font-semibold">For customers</p>
<Link class="block" to="/technicians">Find a technician</Link>
<Link class="block" to="/how-it-works">How it works</Link>
</div>
<div class="space-y-3 text-sm">
<p class="font-semibold">For professionals</p>
<Link class="block" to="/register/technician">Join as a technician</Link>
<Link class="block" to="/login">Sign in</Link>
</div>
<div class="space-y-3 text-sm">
<Link class="block" to="/pages/about">About</Link>
<Link class="block" to="/pages/privacy">Privacy policy</Link>
<Link class="block" to="/pages/terms">Terms and conditions</Link>
</div>
</div>
<p class="border-t border-white/10 px-6 py-5 text-center text-xs text-slate-400">eRepair · Laptop and mobile repair professionals in the Philippines</p>
</footer>
</template>
<template v-else>
<footer class="border-t border-slate-200/80 bg-white/75 px-5 py-5">
<div class="mx-auto flex max-w-[1500px] flex-wrap items-center justify-between gap-4 text-[11px] text-slate-500">
<Link to="/" class="flex items-center gap-2 font-bold text-navy">
<Wrench class="size-4 text-primary" aria-hidden="true">
</Wrench>eRepair<span class="ml-2 hidden border-l pl-4 font-normal sm:inline">Local expertise. A clearer repair journey.</span>
</Link>
<nav aria-label="Workspace footer" class="flex flex-wrap gap-4">
<Link to="/pages/about">About</Link>
<Link to="/pages/privacy">Privacy policy</Link>
<Link to="/pages/terms">Terms and conditions</Link>
</nav>
</div>
</footer>
</template>
</div>
</template>
