<script setup lang="ts">
import MoneyAmount from '@/components/MoneyAmount.vue';
import type { Money } from '@/types';

defineProps<{
    min: Money;
    max: Money;
}>();
</script>

<template>
    <span v-if="min.exact === max.exact" class="whitespace-nowrap"
        ><MoneyAmount :value="max"
    /></span>
    <span v-else class="whitespace-nowrap"
        ><MoneyAmount :value="min" />&#8202;–&#8202;<MoneyAmount :value="max"
    /></span>
</template>
