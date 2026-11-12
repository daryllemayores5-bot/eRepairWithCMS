<script setup lang="ts">
import { RouterLink as Link } from 'vue-router';
import type { Page } from '@/types';
import Button from '@/components/ui/Button.vue';
</script>

<template>
<section class="page-shell max-w-3xl text-center">
<h1 class="text-3xl font-bold tracking-tight text-navy">Page not found</h1>
<p class="mt-4 text-muted-foreground">This address is not available.</p>
<Button as-child class="mt-6">
<Link to="/">Return home</Link>
</Button>
</section>
</template>
