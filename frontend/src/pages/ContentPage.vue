<script setup lang="ts">
import { computed } from 'vue';
import { useWebsiteContent } from '@/composables/content';
import type { Page } from '@/types';
import Loading from '@/components/common/Loading.vue';
import Failure from '@/components/common/Failure.vue';
import Empty from '@/components/common/Empty.vue';
import PageHeading from '@/components/common/PageHeading.vue';
const props = defineProps<{
  slug: string;
}>();
const content = useWebsiteContent();
const page = computed(() => content.data?.pages.find(p => p.slug === props.slug));
</script>

<template>
<article class="page-shell">
<div class="mx-auto max-w-3xl">
<template v-if="content.isPending">
<Loading>
</Loading>
</template>
<template v-else>
<template v-if="content.isError">
<Failure :error="content.error" :retry="() => void content.refetch()">
</Failure>
</template>
<template v-else>
<template v-if="page">
<PageHeading eyebrow="eRepair" :title="page.title">
</PageHeading>
<div class="surface-card editorial-copy mt-7 p-6 sm:p-9">{{ page.content }}</div>
</template>
<template v-else>
<Empty title="Page not published" description="The administrator has not published this content yet.">
</Empty>
</template>
</template>
</template>
</div>
</article>
</template>
