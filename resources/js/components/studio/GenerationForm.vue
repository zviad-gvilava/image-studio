<script setup lang="ts">
import { Link, useForm } from '@inertiajs/vue3';
import { Sparkles, Wallet } from '@lucide/vue';
import { computed, ref } from 'vue';
import InputError from '@/components/InputError.vue';
import MoneyAmount from '@/components/MoneyAmount.vue';
import OptionGroup from '@/components/OptionGroup.vue';
import PriceEstimatePanel from '@/components/studio/PriceEstimatePanel.vue';
import ReferenceImagesInput from '@/components/studio/ReferenceImagesInput.vue';
import { Alert, AlertDescription } from '@/components/ui/alert';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { Textarea } from '@/components/ui/textarea';
import { usePriceEstimate } from '@/composables/usePriceEstimate';
import { formatNumber } from '@/lib/format';
import { isLessThan, isZero } from '@/lib/money';
import { index as creditsPage } from '@/routes/credits';
import { store } from '@/routes/generations';
import type {
    CreditSummary,
    Generation,
    GenerationInput,
    StudioOptions,
} from '@/types';

const props = defineProps<{
    options: StudioOptions;
    credits: CreditSummary | null;
}>();

const form = useForm<GenerationInput>({
    prompt: '',
    size: props.options.sizes[0]?.value ?? '1024x1024',
    quality: 'medium',
    reference_images: [],
});

const promptField = ref<InstanceType<typeof Textarea> | null>(null);

/** Blank reference rows are just unfinished input, so they are never sent. */
function payload(): GenerationInput {
    return {
        prompt: form.prompt,
        size: form.size,
        quality: form.quality,
        reference_images: filledReferences().map(({ url }) => url),
    };
}

function filledReferences(): { url: string; index: number }[] {
    return form.reference_images
        .map((url, index) => ({ url: url.trim(), index }))
        .filter(({ url }) => url !== '');
}

const { estimate, unavailable, loading, errors } = usePriceEstimate(payload);

const formErrors = computed(
    () => form.errors as Partial<Record<string, string>>,
);

const characters = computed(() => [...form.prompt].length);

const shortfall = computed(() => {
    if (props.credits === null || estimate.value === null) {
        return null;
    }

    return isLessThan(props.credits.available, estimate.value.max_price)
        ? {
              needed: estimate.value.max_price,
              available: props.credits.available,
          }
        : null;
});

const canSubmit = computed(
    () =>
        form.prompt.trim() !== '' &&
        estimate.value !== null &&
        shortfall.value === null &&
        !form.processing,
);

/** Server errors point at positions among the filled rows; map them back to the row on screen. */
function referenceError(rowIndex: number): string | undefined {
    const position = filledReferences().findIndex(
        ({ index }) => index === rowIndex,
    );

    if (position === -1) {
        return undefined;
    }

    const key = `reference_images.${position}`;

    return formErrors.value[key] ?? errors.value[key];
}

function submit(): void {
    form.transform(payload).post(store.url(), { preserveScroll: true });
}

function reuse(generation: Generation): void {
    form.prompt = generation.prompt;
    form.size = generation.size;
    form.quality = generation.quality;
    form.reference_images = [...generation.reference_images];
    form.clearErrors();

    const element = promptField.value?.$el as HTMLTextAreaElement | undefined;
    element?.scrollIntoView({ behavior: 'smooth', block: 'center' });
    element?.focus({ preventScroll: true });
}

defineExpose({ reuse });
</script>

<template>
    <form class="grid gap-6" @submit.prevent="submit">
        <div class="grid gap-2">
            <Label for="prompt">Prompt</Label>
            <Textarea
                id="prompt"
                ref="promptField"
                v-model="form.prompt"
                rows="5"
                :maxlength="options.max_prompt_length"
                placeholder="A lighthouse on a cliff at dawn, oil painting"
                :aria-invalid="form.errors.prompt ? true : undefined"
                @keydown.meta.enter="submit"
                @keydown.ctrl.enter="submit"
            />
            <div
                class="flex items-start justify-between gap-4 text-xs text-muted-foreground"
            >
                <span
                    >Testing? Pixelforge keywords like [fail], [slow] or
                    [duplicate] work here.</span
                >
                <span class="shrink-0 tabular-nums"
                    >{{ formatNumber(characters) }} /
                    {{ formatNumber(options.max_prompt_length) }}</span
                >
            </div>
            <InputError :message="form.errors.prompt" />
        </div>

        <div class="grid gap-2">
            <Label>Size</Label>
            <OptionGroup
                v-model="form.size"
                label="Size"
                :options="options.sizes"
            >
                <template #default="{ option }">
                    <span class="flex h-5 items-center" aria-hidden="true">
                        <span
                            class="rounded-[3px] border-2 border-current"
                            :class="{
                                'h-4 w-4': option.value === '1024x1024',
                                'h-3.5 w-5': option.value === '1536x1024',
                                'h-5 w-3.5': option.value === '1024x1536',
                            }"
                        />
                    </span>
                    <span>{{ option.label }}</span>
                    <span
                        class="text-[11px] font-normal text-muted-foreground"
                        >{{ option.value.replace('x', '×') }}</span
                    >
                </template>
            </OptionGroup>
            <InputError :message="form.errors.size" />
        </div>

        <div class="grid gap-2">
            <Label>Quality</Label>
            <OptionGroup
                v-model="form.quality"
                label="Quality"
                :options="options.qualities"
            />
            <InputError :message="form.errors.quality" />
        </div>

        <div class="grid gap-2">
            <Label>Reference images</Label>
            <ReferenceImagesInput
                v-model="form.reference_images"
                :max="options.max_reference_images"
                :error-for="referenceError"
            />
            <InputError :message="form.errors.reference_images" />
        </div>

        <PriceEstimatePanel
            :estimate="estimate"
            :loading="loading"
            :unavailable="unavailable"
        />

        <Alert v-if="shortfall" variant="destructive">
            <Wallet />
            <AlertDescription>
                <p>
                    This can cost up to
                    <MoneyAmount :value="shortfall.needed" />, but you have
                    <MoneyAmount :value="shortfall.available" /> available.
                </p>
                <Link
                    :href="creditsPage()"
                    class="font-medium underline underline-offset-4"
                    >Buy credits</Link
                >
            </AlertDescription>
        </Alert>
        <InputError :message="formErrors.credits" />

        <div class="grid gap-2">
            <Button type="submit" size="lg" :disabled="!canSubmit">
                <Spinner v-if="form.processing" />
                <Sparkles v-else />
                Generate
            </Button>
            <p v-if="credits" class="text-center text-xs text-muted-foreground">
                <MoneyAmount :value="credits.available" /> available
                <template v-if="!isZero(credits.reserved)">
                    (<MoneyAmount :value="credits.reserved" /> held for
                    generations in progress)
                </template>
            </p>
        </div>
    </form>
</template>
