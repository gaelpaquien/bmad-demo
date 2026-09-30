<script setup>
// The one tag design used everywhere a tag is shown (Document Detail, search
// results, Documents list, tag selector, active search filters) so tags always
// read the same (spec-uniformisation-tags). Matches the attachment pills on
// Document Detail (Show.vue).
//
// Read-only by default. Giving `removeLabel` adds a × button (emits `remove`)
// for editable contexts; `active` switches to the primary colour to signal a
// currently applied filter.
defineProps({
    name: {
        type: String,
        required: true,
    },
    active: {
        type: Boolean,
        default: false,
    },
    removeLabel: {
        type: String,
        default: null,
    },
    disabled: {
        type: Boolean,
        default: false,
    },
});

defineEmits(['remove']);
</script>

<template>
    <span
        class="inline-flex w-fit items-center gap-1 rounded-md px-2.5 py-1 text-sm"
        :class="[
            active ? 'bg-primary text-primary-foreground' : 'bg-surface-alt text-foreground',
            removeLabel ? 'pr-1.5' : '',
        ]"
    >
        {{ name }}
        <button
            v-if="removeLabel"
            type="button"
            class="rounded-full px-0.5 text-lg leading-none opacity-70 hover:opacity-100 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-primary focus-visible:ring-2 focus-visible:ring-foreground disabled:cursor-not-allowed dark:focus-visible:ring-background"
            :disabled="disabled"
            :aria-label="removeLabel"
            @click="$emit('remove')"
        >
            <span aria-hidden="true">×</span>
        </button>
    </span>
</template>
