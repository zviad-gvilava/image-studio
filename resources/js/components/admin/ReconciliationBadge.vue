<script setup lang="ts">
import { CircleCheck, CircleHelp, CircleX } from '@lucide/vue';
import MoneyAmount from '@/components/MoneyAmount.vue';
import { countOf } from '@/lib/format';
import type { PixelforgeComparison } from '@/types';

defineProps<{
    comparison: PixelforgeComparison;
}>();
</script>

<template>
    <span
        v-if="comparison.matches === null"
        class="inline-flex items-center gap-1 text-muted-foreground"
        title="Pixelforge's usage report couldn't be loaded"
    >
        <CircleHelp class="size-4" /> Unknown
    </span>
    <span
        v-else-if="comparison.matches"
        class="inline-flex items-center gap-1 text-emerald-700 dark:text-emerald-400"
    >
        <CircleCheck class="size-4" /> Matches
    </span>
    <span v-else class="inline-flex items-center gap-1 text-destructive">
        <CircleX class="size-4" />
        <span v-if="comparison.pixelforge"
            >Pixelforge billed
            {{ countOf(comparison.pixelforge.generations, 'generation') }} for
            <MoneyAmount :value="comparison.pixelforge.cost" exact
        /></span>
    </span>
</template>
