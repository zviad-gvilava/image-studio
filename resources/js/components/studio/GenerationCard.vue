<script setup lang="ts">
import { ImageOff, RotateCcw, TriangleAlert } from '@lucide/vue';
import { useTimeAgo } from '@vueuse/core';
import { computed, ref } from 'vue';
import MoneyAmount from '@/components/MoneyAmount.vue';
import PriceRange from '@/components/PriceRange.vue';
import GenerationStatusBadge from '@/components/studio/GenerationStatusBadge.vue';
import { Button } from '@/components/ui/button';
import { Spinner } from '@/components/ui/spinner';
import { formatDate, formatNumber } from '@/lib/format';
import type { Generation, GenerationStatus } from '@/types';

const props = defineProps<{
    generation: Generation;
}>();

defineEmits<{
    reuse: [generation: Generation];
}>();

const createdAgo = useTimeAgo(() => props.generation.created_at);
const imageBroken = ref(false);

const showImage = computed(
    () => props.generation.image_url !== null && !imageBroken.value,
);
const imageExpired = computed(
    () =>
        props.generation.image_expired ||
        (props.generation.image_url !== null && imageBroken.value),
);

const progressLabels: Partial<Record<GenerationStatus, string>> = {
    pending: 'Starting…',
    queued: 'Waiting in line…',
    processing: 'Generating your image…',
};

const progressLabel = computed(() => progressLabels[props.generation.status]);

const details = computed(() => {
    const parts = [
        `${props.generation.size_label} ${props.generation.size.replace('x', '×')}`,
        `${props.generation.quality_label} quality`,
    ];
    const references = props.generation.reference_images.length;

    if (references > 0) {
        parts.push(`${references} reference${references === 1 ? '' : 's'}`);
    }

    return parts.join(' · ');
});

const tokenSummary = computed(() => {
    const tokens = props.generation.tokens;

    return tokens === null
        ? null
        : `${formatNumber(tokens.input_text)} text · ${formatNumber(tokens.input_image)} image · ${formatNumber(tokens.output)} output · ${formatNumber(tokens.reasoning)} reasoning tokens`;
});
</script>

<template>
    <article class="flex flex-col overflow-hidden rounded-xl border bg-card">
        <div
            class="relative flex aspect-square items-center justify-center bg-muted/40"
        >
            <a
                v-if="showImage"
                :href="generation.image_url ?? undefined"
                target="_blank"
                rel="noopener"
                class="block h-full w-full"
            >
                <img
                    :src="generation.image_url ?? undefined"
                    :alt="generation.prompt"
                    loading="lazy"
                    class="h-full w-full object-contain"
                    @error="imageBroken = true"
                />
            </a>
            <div
                v-else-if="imageExpired"
                class="flex flex-col items-center gap-2 p-6 text-center text-sm text-muted-foreground"
            >
                <ImageOff class="size-6" />
                <p>The image link has expired.</p>
                <p class="text-xs">Pixelforge keeps images for 24 hours.</p>
            </div>
            <div
                v-else-if="generation.status === 'failed'"
                class="flex flex-col items-center gap-2 p-6 text-center text-sm"
            >
                <TriangleAlert class="size-6 text-destructive" />
                <p class="text-muted-foreground">{{ generation.message }}</p>
            </div>
            <div
                v-else
                class="flex flex-col items-center gap-2 p-6 text-center text-sm text-muted-foreground"
            >
                <Spinner class="size-6" />
                <p>{{ progressLabel }}</p>
                <p v-if="generation.message" class="text-xs">
                    {{ generation.message }}
                </p>
            </div>
        </div>

        <div class="flex flex-1 flex-col gap-3 p-4">
            <div class="flex items-center justify-between gap-2">
                <GenerationStatusBadge :status="generation.status" />
                <time
                    class="text-xs text-muted-foreground"
                    :datetime="generation.created_at"
                    :title="formatDate(generation.created_at)"
                    >{{ createdAgo }}</time
                >
            </div>

            <div>
                <p class="line-clamp-2 text-sm" :title="generation.prompt">
                    {{ generation.prompt }}
                </p>
                <p class="mt-1 text-xs text-muted-foreground">{{ details }}</p>
            </div>

            <dl class="mt-auto grid grid-cols-2 gap-2 text-sm">
                <div>
                    <dt class="text-xs text-muted-foreground">Estimate</dt>
                    <dd>
                        <PriceRange
                            :min="generation.estimated_min_price"
                            :max="generation.estimated_max_price"
                        />
                    </dd>
                </div>
                <div>
                    <dt class="text-xs text-muted-foreground">Charged</dt>
                    <dd v-if="generation.status === 'failed'">Nothing</dd>
                    <dd v-else-if="generation.price" class="font-medium">
                        <MoneyAmount :value="generation.price" />
                    </dd>
                    <dd v-else class="text-muted-foreground">When ready</dd>
                </div>
            </dl>

            <p
                v-if="tokenSummary"
                class="text-[11px] leading-snug text-muted-foreground"
            >
                {{ tokenSummary }}
            </p>

            <Button
                v-if="generation.is_final"
                type="button"
                variant="outline"
                size="sm"
                @click="$emit('reuse', generation)"
            >
                <RotateCcw />
                {{
                    generation.status === 'failed'
                        ? 'Try again'
                        : 'Reuse these settings'
                }}
            </Button>
        </div>
    </article>
</template>
