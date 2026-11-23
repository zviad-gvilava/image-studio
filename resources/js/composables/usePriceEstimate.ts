import { useHttp } from '@inertiajs/vue3';
import { useDebounceFn } from '@vueuse/core';
import type { ComputedRef, Ref } from 'vue';
import { computed, ref, watch } from 'vue';
import { estimate as estimateRoute } from '@/routes/generations';
import type { GenerationInput, PriceEstimate } from '@/types';

const DEBOUNCE_MS = 300;

export type UsePriceEstimateReturn = {
    estimate: Ref<PriceEstimate | null>;
    unavailable: Ref<string | null>;
    loading: ComputedRef<boolean>;
    errors: ComputedRef<Partial<Record<string, string>>>;
};

/**
 * Asks the server what the current options will cost, as they change.
 * Pricing lives only on the server, so the numbers here always match what gets charged.
 */
export function usePriceEstimate(
    input: () => GenerationInput,
): UsePriceEstimateReturn {
    const request = useHttp<GenerationInput, { data: PriceEstimate }>(
        input(),
    ).transform(() => input());

    const estimate = ref<PriceEstimate | null>(null);
    const unavailable = ref<string | null>(null);

    const refresh = useDebounceFn(() => {
        request.cancel();

        request
            .post(estimateRoute.url(), {
                onSuccess: (response) => {
                    estimate.value = response.data;
                    unavailable.value = null;
                },
                onError: () => {
                    estimate.value = null;
                    unavailable.value = null;
                },
                onHttpException: (response) => {
                    estimate.value = null;
                    unavailable.value = messageFrom(response.data);

                    return false;
                },
                onNetworkError: () => {
                    estimate.value = null;
                    unavailable.value =
                        "Couldn't load the price. Check your connection.";

                    return false;
                },
            })
            .catch(() => {});
    }, DEBOUNCE_MS);

    watch(input, refresh, { deep: true, immediate: true });

    return {
        estimate,
        unavailable,
        loading: computed(() => request.processing),
        errors: computed(
            () => request.errors as Partial<Record<string, string>>,
        ),
    };
}

function messageFrom(body: unknown): string {
    const fallback = "Prices aren't available right now. Please try again.";

    try {
        const parsed: unknown =
            typeof body === 'string' ? JSON.parse(body) : body;

        if (
            parsed !== null &&
            typeof parsed === 'object' &&
            'message' in parsed &&
            typeof parsed.message === 'string'
        ) {
            return parsed.message;
        }
    } catch {
        // Not JSON: fall back to the generic message.
    }

    return fallback;
}
