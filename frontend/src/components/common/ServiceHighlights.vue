<script setup lang="ts">
import { RouterLink } from 'vue-router';
import type { WebsiteService } from '@/types/content';
import Button from '@/components/ui/Button.vue';

defineProps<{ services: WebsiteService[] }>();
</script>

<template>
<div v-if="services.length" class="mt-7 grid gap-4 md:grid-cols-2 lg:grid-cols-3" aria-label="Repair service guides">
  <article v-for="service in services" :key="service.slug" class="surface-card flex flex-col p-5 sm:p-6">
    <h3 class="text-base font-semibold text-navy">{{ service.title }}</h3>
    <p class="mt-3 break-words text-sm leading-7 text-muted-foreground">{{ service.summary }}</p>
    <details class="my-4 text-sm">
      <summary class="cursor-pointer py-2 font-semibold text-primary">About this service<span class="sr-only">: {{ service.title }}</span></summary>
      <p class="mt-2 whitespace-pre-wrap break-words leading-7 text-muted-foreground">{{ service.content }}</p>
    </details>
    <Button as-child variant="outline" class="mt-auto self-start">
      <RouterLink to="/technicians" :aria-label="`Find a technician for ${service.title}`">Find a technician</RouterLink>
    </Button>
  </article>
</div>
</template>
