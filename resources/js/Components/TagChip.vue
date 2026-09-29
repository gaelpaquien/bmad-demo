<script setup>
// Read-only display chip for a document's assigned tags (library card,
// Document Detail) — deliberately styled distinctly from the Library's
// active-filter chip (Index.vue: bordered, blue) so the two are never
// visually confused (Code Map, spec-3-1). Mirrors DocumentTypeBadge.vue's
// shape as the badge/chip convention to follow.
//
// `size="regular"` matches the attachment pills on Document Detail
// (Show.vue) so tags and attachments read as one metadata block; the
// compact default stays for the dense Library/Search rows.
defineProps({
    name: {
        type: String,
        required: true,
    },
    size: {
        type: String,
        default: 'compact',
        validator: (value) => ['compact', 'regular'].includes(value),
    },
});
</script>

<template>
    <span
        class="inline-flex w-fit items-center bg-surface-alt px-2.5 text-foreground"
        :class="size === 'regular' ? 'rounded-md py-1 text-sm' : 'rounded-lg py-0.5 text-xs font-medium'"
    >
        {{ name }}
    </span>
</template>
