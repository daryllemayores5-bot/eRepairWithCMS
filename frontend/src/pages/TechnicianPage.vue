<script setup lang="ts">
import { computed, ref, watch } from 'vue';
import { RouterLink as Link, useRoute } from 'vue-router';
import { useApiQuery } from '@/composables/query';
import { api, initializeCsrf } from '@/services/api';
import type { Repair, Page, Review } from '@/types';
import type { SubscriptionTechnician as Technician } from '@/types/subscription';
import SubscriptionBadge from '@/components/technician/SubscriptionBadge.vue';
import { BadgeCheck, Wrench, ArrowRight, MapPin, Star, Laptop, ArrowLeft, BriefcaseBusiness } from 'lucide-vue-next';
import Button from '@/components/ui/Button.vue';
import Avatar from '@/components/ui/Avatar.vue';
import Card from '@/components/ui/Card.vue';
import CardHeader from '@/components/ui/CardHeader.vue';
import CardTitle from '@/components/ui/CardTitle.vue';
import CardDescription from '@/components/ui/CardDescription.vue';
import CardContent from '@/components/ui/CardContent.vue';
import Loading from '@/components/common/Loading.vue';
import Failure from '@/components/common/Failure.vue';
import Empty from '@/components/common/Empty.vue';
import Pagination from '@/components/common/Pagination.vue';
import Status from '@/components/common/Status.vue';
const route = useRoute();
const id = computed(() => String(route.params.id));
const reviewPage = ref(1);
watch(id, () => {
  reviewPage.value = 1;
});
const profile = useApiQuery<Technician>(() => ({
  queryKey: ['technician', id.value],
  queryFn: async ({
    signal
  }) => (await api.get<{
    data: Technician;
  }>('/technicians/' + id.value, {
    signal
  })).data.data
}));
const reviews = useApiQuery<Page<Review>>(() => ({
  queryKey: ['reviews', id.value, reviewPage.value],
  queryFn: async ({
    signal
  }) => (await api.get<Page<Review>>('/technicians/' + id.value + '/reviews?page=' + reviewPage.value, {
    signal
  })).data
}));
const t = computed(() => profile.data!);
watch(() => profile.data?.id, async technician => {
  if (!technician || route.path !== `/technicians/${technician}`) return;
  try {
    await initializeCsrf();
    await api.post(`/technicians/${technician}/view`);
  } catch {
    // Optional visit analytics must never block public discovery or inquiries.
  }
}, { immediate: true });
const areas = computed(() => profile.data ? [{
  city: profile.data.city,
  province: profile.data.province
}, ...profile.data.service_areas].filter((a, index, all) => all.findIndex(b => a.city === b.city && a.province === b.province) === index) : []);
</script>

<template>
<div class="page-shell" v-if="profile.isPending">
<Loading />
</div>
<div class="page-shell" v-else-if="profile.isError">
<Failure :error="profile.error" :retry="()=>profile.refetch()" />
</div>
<template v-else-if="profile.data">
<div class="page-shell">
<Button as-child variant="ghost" size="sm">
<Link to="/technicians">
<ArrowLeft aria-hidden="true">
</ArrowLeft>Back to technicians</Link>
</Button>
<Card class="mt-5 overflow-hidden">
<div class="h-20 border-b border-blue-100 bg-blue-50 sm:h-24" aria-hidden="true">
</div>
<div class="px-5 pb-6 sm:px-8 sm:pb-8">
<div class="-mt-10 w-fit rounded-2xl bg-white p-1">
<Avatar :name="t.name" :src="t.photo_url" size="lg">
</Avatar>
</div>
<div class="mt-4 flex flex-wrap items-start justify-between gap-4">
<div class="min-w-0">
<div class="flex flex-wrap items-center gap-2">
<h1 class="min-w-0 max-w-full break-words text-2xl font-bold tracking-tight text-navy sm:text-3xl">{{ t.name }}</h1>
<SubscriptionBadge :tier="t.subscription_tier ?? (t.is_premium ? 'premium' : t.is_pro ? 'pro' : 'free')" />
<template v-if="t.verified">
<BadgeCheck class="size-6 shrink-0 text-primary" aria-label="Verified technician">
</BadgeCheck>
</template>
</div>
<p class="mt-3 flex items-center gap-2 text-sm text-slate-500">
<MapPin class="size-4 shrink-0" aria-hidden="true">
</MapPin>{{ t.city }}, {{ t.province }}</p>
<div class="mt-4 flex flex-wrap items-center gap-x-6 gap-y-3 text-sm text-slate-600">
<span class="flex items-center gap-2">
<BriefcaseBusiness class="size-4 text-slate-400" aria-hidden="true">
</BriefcaseBusiness>{{ t.years_experience }} years of experience</span>
<span class="flex items-center gap-2">
<Star class="size-4 fill-amber-400 text-amber-400" aria-hidden="true">
</Star>
<template v-if="t.reviews_count">{{ `${t.average_rating} from ${t.reviews_count} reviews` }}</template>
<template v-else>{{ 'No reviews yet' }}</template>
</span>
</div>
</div>
<Status :value="t.availability">
</Status>
</div>
</div>
</Card>
<div class="mt-6 grid items-start gap-6 lg:grid-cols-[minmax(0,1fr)_320px]">
<div class="min-w-0 space-y-6">
<Card>
<CardHeader>
<CardTitle>About this technician</CardTitle>
</CardHeader>
<CardContent>
<p class="whitespace-pre-line break-words text-sm leading-8 text-slate-600">{{ t.biography || 'This technician has not provided a biography.' }}</p>
</CardContent>
</Card>
<Card>
<CardHeader>
<CardTitle class="flex items-center gap-2">
<Wrench class="size-4 text-primary" aria-hidden="true">
</Wrench>Repair specializations</CardTitle>
<CardDescription>Find the skills that match your device.</CardDescription>
</CardHeader>
<CardContent>
<ul class="flex flex-wrap gap-2">
<template v-for="(s) in t.specializations" :key="s.id">
<li class="rounded-lg border border-blue-100 bg-blue-50 px-3 py-2 text-xs font-medium text-blue-700">{{ s.name }}</li>
</template>
</ul>
<h3 class="mt-6 flex items-center gap-2 text-sm font-semibold text-navy">
<Laptop class="size-4 text-primary" aria-hidden="true">
</Laptop>Supported devices</h3>
<div class="mt-3 flex flex-wrap gap-2">
<template v-for="(d) in t.device_types" :key="d.id">
<span class="rounded-lg border bg-slate-50 px-3 py-1.5 text-xs text-slate-600">{{ d.name }}</span>
</template>
</div>
</CardContent>
</Card>
<section aria-labelledby="customer-reviews">
<h2 id="customer-reviews" class="mb-4 text-xl font-semibold tracking-tight text-navy">Customer reviews</h2>
<template v-if="reviews.isPending">
<Loading>
</Loading>
</template>
<template v-else>
<template v-if="reviews.isError">
<Failure :error="reviews.error" :retry="() => void reviews.refetch()">
</Failure>
</template>
<template v-else>
<template v-if="reviews.data.data.length">
<div class="space-y-4">
<template v-for="(r) in reviews.data.data" :key="r.id">
<Card class="p-5 sm:p-6">
<div class="flex items-start justify-between gap-3">
<div class="flex items-center gap-3">
<Avatar :name="r.customer?.name || 'Customer'" size="sm">
</Avatar>
<p class="text-sm font-semibold text-navy">{{ r.customer?.name || 'Customer' }}</p>
</div>
<span class="flex items-center gap-1 text-xs font-semibold text-slate-600">
<Star class="size-3.5 fill-amber-400 text-amber-400" aria-hidden="true">
</Star>{{ r.rating }}/5</span>
</div>
<p class="mt-4 whitespace-pre-line break-words text-sm leading-7 text-slate-600">{{ r.body }}</p>
</Card>
</template>
</div>
</template>
<template v-else>
<Empty title="No reviews yet" description="Reviews can be submitted after a completed repair.">
</Empty>
</template>
</template>
</template>
<template v-if="reviews.data">
<Pagination :page="reviews.data" :onChange="(value) => reviewPage = value">
</Pagination>
</template>
</section>
</div>
<aside class="space-y-5 lg:sticky lg:top-24">
<Card>
<CardHeader>
<CardTitle>Connect with {{ t.name.split(' ')[0] }}</CardTitle>
<CardDescription>Share your device problem and a preferred appointment.</CardDescription>
</CardHeader>
<CardContent>
<template v-if="t.availability === 'available'">
<Button as-child class="w-full" size="lg">
<Link :to="`/dashboard/repairs/new?technician=${t.id}`">Request repair<ArrowRight aria-hidden="true">
</ArrowRight>
</Link>
</Button>
</template>
<template v-else>
<Button class="w-full" size="lg" disabled>Not accepting requests</Button>
</template>
<p class="mt-4 text-xs leading-6 text-slate-500">Your technician reviews the request and provides an estimate for your approval before repair begins.</p>
</CardContent>
</Card>
<Card>
<CardHeader>
<CardTitle class="flex items-center gap-2">
<MapPin class="size-4 text-primary" aria-hidden="true">
</MapPin>Service locations</CardTitle>
</CardHeader>
<CardContent>
<ul class="space-y-3 text-sm text-slate-600">
<template v-for="(area) in areas" :key="`${area.city}|${area.province}`">
<li>{{ area.city }}, {{ area.province }}</li>
</template>
</ul>
<p class="mt-5 border-t pt-4 text-xs leading-6 text-slate-500">Verification confirms administrator approval of this profile. Agree on the repair scope and estimate before work begins.</p>
</CardContent>
</Card>
</aside>
</div>
</div>
</template>
</template>
