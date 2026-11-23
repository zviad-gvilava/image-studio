<script setup lang="ts">
import { Plus } from '@lucide/vue';
import ReferenceImageRow from '@/components/studio/ReferenceImageRow.vue';
import { Button } from '@/components/ui/button';

defineProps<{
    max: number;
    errorFor: (position: number) => string | undefined;
}>();

const urls = defineModel<string[]>({ required: true });

function add(): void {
    urls.value = [...urls.value, ''];
}

function update(position: number, value: string): void {
    urls.value = urls.value.map((url, index) =>
        index === position ? value : url,
    );
}

function remove(position: number): void {
    urls.value = urls.value.filter((_url, index) => index !== position);
}
</script>

<template>
    <div class="grid gap-2">
        <ReferenceImageRow
            v-for="(url, position) in urls"
            :key="position"
            :url="url"
            :label="`Reference image ${position + 1}`"
            :error="errorFor(position)"
            @update="update(position, $event)"
            @remove="remove(position)"
        />

        <Button
            v-if="urls.length < max"
            type="button"
            variant="outline"
            size="sm"
            class="justify-self-start"
            @click="add"
        >
            <Plus /> Add reference image
        </Button>
        <p class="text-xs text-muted-foreground">
            Optional, up to {{ max }}. Each one adds 765 input tokens to the
            price.
        </p>
    </div>
</template>
