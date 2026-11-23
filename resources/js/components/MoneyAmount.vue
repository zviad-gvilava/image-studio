<script setup lang="ts">
import { formatExactMoney } from '@/lib/money';
import type { Money } from '@/types';

const { value, exact = false } = defineProps<{
    value: Money;
    exact?: boolean;
}>();
</script>

<template>
    <span class="tabular-nums" :title="formatExactMoney(value)">{{
        exact ? formatExactMoney(value) : value.formatted
    }}</span>
</template>
