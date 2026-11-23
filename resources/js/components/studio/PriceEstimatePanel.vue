<script setup lang="ts">
import { ChevronDown } from '@lucide/vue';
import { ref } from 'vue';
import MoneyAmount from '@/components/MoneyAmount.vue';
import PriceRange from '@/components/PriceRange.vue';
import {
    Collapsible,
    CollapsibleContent,
    CollapsibleTrigger,
} from '@/components/ui/collapsible';
import { Spinner } from '@/components/ui/spinner';
import { formatNumber, formatTokenRange } from '@/lib/format';
import type { PriceEstimate } from '@/types';

defineProps<{
    estimate: PriceEstimate | null;
    loading: boolean;
    unavailable: string | null;
}>();

const showBreakdown = ref(false);
</script>

<template>
    <div class="rounded-lg border bg-muted/30 p-4" aria-live="polite">
        <div class="flex items-center justify-between gap-2">
            <span class="text-sm text-muted-foreground">Estimated price</span>
            <Spinner v-if="loading" class="text-muted-foreground" />
        </div>

        <p v-if="estimate" class="mt-1 text-2xl font-semibold tracking-tight">
            <PriceRange :min="estimate.min_price" :max="estimate.max_price" />
        </p>
        <p v-else-if="unavailable" class="mt-1 text-sm text-destructive">
            {{ unavailable }}
        </p>
        <p v-else class="mt-1 text-2xl font-semibold text-muted-foreground">
            —
        </p>

        <p class="mt-2 text-xs text-muted-foreground">
            The final price depends on how much the model has to think, so it's
            only known when the image is ready. You pay that exact amount;
            failed generations are free.
        </p>

        <Collapsible v-if="estimate" v-model:open="showBreakdown" class="mt-3">
            <CollapsibleTrigger
                class="flex items-center gap-1 text-xs font-medium text-foreground/80 hover:text-foreground"
            >
                How this is calculated
                <ChevronDown
                    class="size-3.5 transition-transform"
                    :class="{ 'rotate-180': showBreakdown }"
                />
            </CollapsibleTrigger>
            <CollapsibleContent>
                <dl
                    class="mt-3 grid grid-cols-[1fr_auto] gap-x-4 gap-y-1.5 text-xs"
                >
                    <dt class="text-muted-foreground">Prompt text</dt>
                    <dd class="text-right tabular-nums">
                        {{ formatTokenRange(estimate.tokens.input_text) }}
                        tokens
                    </dd>
                    <dt class="text-muted-foreground">Reference images</dt>
                    <dd class="text-right tabular-nums">
                        {{ formatNumber(estimate.tokens.input_image) }} tokens
                    </dd>
                    <dt class="text-muted-foreground">Image output</dt>
                    <dd class="text-right tabular-nums">
                        {{ formatNumber(estimate.tokens.output) }} tokens
                    </dd>
                    <dt class="text-muted-foreground">Model reasoning</dt>
                    <dd class="text-right tabular-nums">
                        {{ formatTokenRange(estimate.tokens.reasoning) }}
                        tokens
                    </dd>
                    <dt class="border-t pt-1.5 text-muted-foreground">
                        Pixelforge cost
                    </dt>
                    <dd class="border-t pt-1.5 text-right">
                        <PriceRange
                            :min="estimate.min_cost"
                            :max="estimate.max_cost"
                        />
                    </dd>
                    <dt class="text-muted-foreground">
                        Service fee ({{ estimate.markup_percent }}%)
                    </dt>
                    <dd class="text-right text-muted-foreground">included</dd>
                    <dt class="font-medium">Highest possible price</dt>
                    <dd class="text-right font-medium">
                        <MoneyAmount :value="estimate.max_price" />
                    </dd>
                </dl>
            </CollapsibleContent>
        </Collapsible>
    </div>
</template>
