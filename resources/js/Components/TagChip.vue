<script setup>
// Read-only display chip for a document's assigned tags (library card,
// Document Detail) — deliberately styled distinctly from the Library's
// active-filter chip (Index.vue: bordered, blue) so the two are never
// visually confused (Code Map, spec-3-1). Mirrors DocumentTypeBadge.vue's
// shape as the badge/chip convention to follow.
//
// `size="regular"` matches the attachment pills on Document Detail
// (Show.vue) so tags and attachments read as one metadata block; the
// compact default stays for the dense Search rows. `size="small"` is the
// Documents list variant (spec-refonte-layout-documents): smaller, regular
// weight and a tighter radius (`rounded-md` is 8px, which turns a chip this
// small into a pill) so the tags sit below the bold title of a row.
defineProps({
    name: {
        type: String,
        required: true,
    },
    size: {
        type: String,
        default: 'compact',
        validator: (value) => ['compact', 'regular', 'small'].includes(value),
    },
});

const SIZE_CLASSES = {
    regular: 'rounded-md px-2.5 py-1 text-sm',
    small: 'rounded-sm px-1.5 py-px text-xs',
    compact: 'rounded-lg px-2.5 py-0.5 text-xs font-medium',
};
</script>

<template>
    <span
        class="inline-flex w-fit items-center bg-surface-alt text-foreground"
        :class="SIZE_CLASSES[size]"
    >
        {{ name }}
    </span>
</template>
