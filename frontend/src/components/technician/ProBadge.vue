<script setup lang="ts">
import SubscriptionBadge from './SubscriptionBadge.vue';
defineProps<{ active?: boolean }>();
</script>
<template>
<SubscriptionBadge :tier="active ? 'pro' : 'free'" />
</template>
