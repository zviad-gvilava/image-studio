<script setup lang="ts">
import { computed } from 'vue';
import { Badge } from '@/components/ui/badge';
import type { GenerationStatus } from '@/types';

const props = defineProps<{
    status: GenerationStatus;
}>();

const appearance: Record<GenerationStatus, { label: string; class: string }> = {
    pending: {
        label: 'Submitting',
        class: 'border-border bg-transparent text-muted-foreground',
    },
    queued: {
        label: 'Queued',
        class: 'border-transparent bg-secondary text-secondary-foreground',
    },
    processing: {
        label: 'Processing',
        class: 'border-transparent bg-sky-100 text-sky-800 dark:bg-sky-950 dark:text-sky-200',
    },
    succeeded: {
        label: 'Succeeded',
        class: 'border-transparent bg-emerald-100 text-emerald-800 dark:bg-emerald-950 dark:text-emerald-200',
    },
    failed: {
        label: 'Failed',
        class: 'border-transparent bg-red-100 text-red-800 dark:bg-red-950 dark:text-red-200',
    },
};

const current = computed(() => appearance[props.status]);
</script>

<template>
    <Badge :class="current.class">{{ current.label }}</Badge>
</template>
