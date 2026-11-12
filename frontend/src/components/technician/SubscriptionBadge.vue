<script setup lang="ts">
import type { SubscriptionTier } from '@/types/subscription';
defineProps<{ tier?: SubscriptionTier }>();
</script>

<template>
<span v-if="tier === 'pro' || tier === 'premium'"
  :class="['inline-flex w-fit rounded-md border px-2 py-1 text-xs font-semibold', tier === 'premium' ? 'border-violet-200 bg-violet-50 text-violet-900' : 'border-amber-200 bg-amber-50 text-amber-900']"
  :title="`Active ${tier === 'premium' ? 'Premium' : 'Pro'} subscription. This badge does not indicate verification or professional qualifications.`">
  {{ tier === 'premium' ? 'Premium Technician' : 'Pro Technician' }}
  <span class="sr-only">— subscription badge, not verification</span>
</span>
</template>
