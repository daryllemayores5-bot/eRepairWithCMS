<script setup lang="ts">
import { RouterLink as Link } from 'vue-router';
import type { Review } from '@/types';
import Button from '@/components/ui/Button.vue';
</script>

<template>
<section class="page-shell">
<h1 class="text-3xl font-bold tracking-tight text-navy sm:text-4xl">Good repairs start with the right person.</h1>
<div class="mt-10 grid gap-5 sm:grid-cols-2">
<template v-for="([title, text], i) in [['Find a technician', 'Select your device, repair specialization and service location.'], ['Compare profiles', 'Review experience, availability, ratings and service areas.'], ['Request a repair', 'Share the problem, photos and your preferred appointment. Your technician accepts or declines.'], ['Track progress', 'Confirm an appointment, approve the estimate, follow updates and review the completed repair.']]" :key="title">
<article class="surface-card p-7">
<p class="font-semibold text-primary">0{{ i + 1 }}</p>
<h2 class="mt-4 text-xl font-semibold">{{ title }}</h2>
<p class="mt-4 leading-7 text-muted-foreground">{{ text }}</p>
</article>
</template>
</div>
<Button as-child class="mt-8">
<Link to="/technicians">Find a technician</Link>
</Button>
</section>
</template>
