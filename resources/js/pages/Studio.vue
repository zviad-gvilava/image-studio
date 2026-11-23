<script setup lang="ts">
import { Head, usePage, usePoll } from '@inertiajs/vue3';
import { ImagePlus } from '@lucide/vue';
import { computed, ref, watch } from 'vue';
import SimplePagination from '@/components/SimplePagination.vue';
import GenerationCard from '@/components/studio/GenerationCard.vue';
import GenerationForm from '@/components/studio/GenerationForm.vue';
import { studio } from '@/routes';
import type { Generation, Paginated, StudioOptions } from '@/types';

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Studio', href: studio() }],
    },
});

const props = defineProps<{
    generations: Paginated<Generation>;
    options: StudioOptions;
}>();

const POLL_INTERVAL_MS = 2000;

const page = usePage();
const credits = computed(() => page.props.credits);
const form = ref<InstanceType<typeof GenerationForm> | null>(null);

const inProgress = computed(
    () =>
        props.generations.data.filter((generation) => !generation.is_final)
            .length,
);

const { start, stop } = usePoll(
    POLL_INTERVAL_MS,
    { only: ['generations', 'credits'] },
    { autoStart: false },
);

watch(inProgress, (count) => (count > 0 ? start() : stop()), {
    immediate: true,
});
</script>

<template>
    <Head title="Studio" />

    <div
        class="flex flex-1 flex-col gap-8 p-4 md:p-6 xl:flex-row xl:items-start"
    >
        <section
            class="w-full max-w-xl shrink-0 xl:sticky xl:top-6 xl:w-[380px]"
            aria-labelledby="create-heading"
        >
            <h1 id="create-heading" class="mb-4 text-lg font-semibold">
                Create an image
            </h1>
            <GenerationForm ref="form" :options="options" :credits="credits" />
        </section>

        <section class="min-w-0 flex-1" aria-labelledby="history-heading">
            <div class="mb-4 flex items-baseline justify-between gap-4">
                <h2 id="history-heading" class="text-lg font-semibold">
                    Your generations
                </h2>
                <p
                    v-if="inProgress > 0"
                    class="flex items-center gap-2 text-xs text-muted-foreground"
                >
                    <span class="relative flex size-2">
                        <span
                            class="absolute inline-flex h-full w-full animate-ping rounded-full bg-sky-400 opacity-75"
                        />
                        <span
                            class="relative inline-flex size-2 rounded-full bg-sky-500"
                        />
                    </span>
                    {{ inProgress }} in progress, updating live
                </p>
            </div>

            <div
                v-if="generations.data.length === 0"
                class="flex flex-col items-center justify-center gap-3 rounded-xl border border-dashed p-12 text-center"
            >
                <ImagePlus class="size-8 text-muted-foreground" />
                <p class="font-medium">No images yet</p>
                <p class="max-w-sm text-sm text-muted-foreground">
                    Describe what you want to see, check the price, and hit
                    Generate. Your images and what they cost will show up here.
                </p>
            </div>

            <div v-else class="grid gap-4 sm:grid-cols-2 2xl:grid-cols-3">
                <GenerationCard
                    v-for="generation in generations.data"
                    :key="generation.id"
                    :generation="generation"
                    @reuse="form?.reuse($event)"
                />
            </div>

            <SimplePagination
                class="mt-6"
                :page="generations"
                previous-label="Newer"
                next-label="Older"
            />
        </section>
    </div>
</template>
