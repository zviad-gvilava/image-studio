<script setup lang="ts">
import { cn } from '@/lib/utils';
import type { Option } from '@/types';

defineProps<{
    options: Option[];
    label: string;
    class?: string;
}>();

const model = defineModel<string>({ required: true });
</script>

<template>
    <div
        role="radiogroup"
        :aria-label="label"
        :class="cn('grid auto-cols-fr grid-flow-col gap-2', $props.class)"
    >
        <button
            v-for="option in options"
            :key="option.value"
            type="button"
            role="radio"
            :aria-checked="model === option.value"
            :class="
                cn(
                    'flex flex-col items-center gap-1 rounded-md border px-3 py-2 text-sm font-medium transition-colors outline-none focus-visible:ring-[3px] focus-visible:ring-ring/50',
                    model === option.value
                        ? 'border-primary bg-primary/5 text-foreground'
                        : 'border-input text-muted-foreground hover:bg-accent hover:text-foreground',
                )
            "
            @click="model = option.value"
        >
            <slot :option="option" :selected="model === option.value">
                {{ option.label }}
            </slot>
        </button>
    </div>
</template>
