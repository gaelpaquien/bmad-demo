<script setup>
// Rendered inside a field's label (or section heading) to mark it as
// required ("*") or optional ("optionnel"). The asterisk is hidden from
// assistive tech — the field itself carries `aria-required` instead.
//
// Callers place it right after the label text with no whitespace in
// between: the spacing lives here (`ml-0.5` for the asterisk, `ml-1.5` for
// "optionnel"), so it stays identical everywhere. Same font size as the
// label (inherited) so both share one baseline.
defineProps({
    required: {
        type: Boolean,
        default: false,
    },
});
</script>

<template>
    <span v-if="required" class="ml-0.5 text-red-600 dark:text-red-400" aria-hidden="true">*</span>
    <span v-else class="ml-1.5 font-normal text-muted">optionnel</span>
</template>
