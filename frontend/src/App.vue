<script setup lang="ts">
import { RouterView } from 'vue-router';
import { Toaster } from 'vue-sonner';
import { useAuthStore } from '@/stores/auth';
useAuthStore();
</script>

<template>
<RouterView />
  <Toaster rich-colors position="top-right" />
</template>
