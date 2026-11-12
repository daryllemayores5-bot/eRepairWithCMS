<script setup lang="ts">
import { computed } from 'vue';
import { RouterLink as Link } from 'vue-router';
import type { SubscriptionTechnician as Technician } from '@/types/subscription';
import SubscriptionBadge from './SubscriptionBadge.vue';
import { BadgeCheck, Check, ArrowRight, MapPin, Star, GitCompareArrows } from 'lucide-vue-next';
import Button from '@/components/ui/Button.vue';
import Avatar from '@/components/ui/Avatar.vue';
import Status from '@/components/common/Status.vue';
const props = defineProps<{
  technician: Technician;
  selected?: boolean;
  onCompare?: (technician: Technician) => void;
}>();
const t = computed(() => props.technician);
</script>

<template>
<article :class="`technician-card surface-card flex flex-col p-6 transition-[border-color,box-shadow] hover:border-blue-200 hover:shadow-md ${selected ? 'border-blue-400 ring-2 ring-blue-100' : t.is_premium ? 'border-violet-300 ring-2 ring-violet-100' : t.is_pro ? 'border-amber-200 ring-1 ring-amber-100' : ''}`">
<div v-if="t.is_pro || t.is_premium" class="mb-4 flex flex-wrap items-center gap-2"><SubscriptionBadge :tier="t.subscription_tier ?? (t.is_premium ? 'premium' : 'pro')" /><span class="text-xs text-slate-500">{{ t.is_premium ? 'Premium featured listing' : 'Featured subscription listing' }}</span></div>
<div class="flex items-start gap-4">
<Avatar :name="t.name" :src="t.photo_url">
</Avatar>
<div class="min-w-0">
<h2 class="flex items-center gap-1.5 break-words font-semibold text-navy">
<Link class="min-w-0 break-words" :to="`/technicians/${t.id}`">{{ t.name }}</Link>
<template v-if="t.verified">
<BadgeCheck class="size-4 shrink-0 text-primary" aria-label="Verified technician">
</BadgeCheck>
</template>
</h2>
<p class="mt-1 flex items-center gap-1 text-xs text-muted-foreground">
<MapPin class="size-3" aria-hidden="true">
</MapPin>{{ t.city }}, {{ t.province }}</p>
</div>
</div>
<p class="mt-5 line-clamp-2 min-h-10 text-sm leading-6 text-muted-foreground">{{ t.specializations.map(s => s.name).join(' · ') }}</p>
<div class="mt-4 flex flex-wrap gap-2">
<template v-for="(d) in t.device_types" :key="d.id">
<span class="rounded-md bg-slate-50 px-2 py-1 text-xs text-slate-600">{{ d.name }}</span>
</template>
</div>
<div class="mt-5 flex items-center justify-between text-sm">
<span class="flex items-center gap-1">
<Star class="size-4 fill-amber-400 text-amber-400" aria-hidden="true">
</Star>
<template v-if="t.reviews_count">{{ `${t.average_rating.toFixed(1)} (${t.reviews_count})` }}</template>
<template v-else>{{ 'No reviews yet' }}</template>
</span>
<span class="text-xs text-muted-foreground">{{ t.years_experience }} years’ experience</span>
</div>
<div class="my-5">
<Status :value="t.availability">
</Status>
</div>
<template v-if="Object.keys(t.matching.reasons).some(reason => !['Available', 'Customer rating'].includes(reason))">
<p class="mb-5 text-xs leading-6 text-primary">Why this match: {{ Object.keys(t.matching.reasons).filter(reason => !['Available', 'Customer rating'].includes(reason)).join(' · ') }}</p>
</template>
<div class="mt-auto flex flex-wrap gap-2 border-t pt-5">
<Button as-child class="flex-1">
<Link :to="`/technicians/${t.id}`">View profile<ArrowRight aria-hidden="true">
</ArrowRight>
</Link>
</Button>
<template v-if="onCompare">
<Button variant="outline" :aria-pressed="Boolean(selected)" @click="() => onCompare?.(t)">
<template v-if="selected">
<Check aria-hidden="true">
</Check>
</template>
<template v-else>
<GitCompareArrows aria-hidden="true">
</GitCompareArrows>
</template>
<template v-if="selected">{{ 'Selected' }}</template>
<template v-else>{{ 'Compare' }}</template>
</Button>
</template>
</div>
</article>
</template>
