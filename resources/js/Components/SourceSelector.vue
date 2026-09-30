<script setup>
import { ref } from 'vue';
import TextInput from '@/Components/TextInput.vue';

// Single-choice sibling of TagSelector for the Recherche "type de document"
// filter: same read-only-looking input (placeholder, focus border) and same
// suggestion list, so both filters read alike. It never displays the choice
// itself: like TagSelector, the host shows it as a removable chip underneath.
// `modelValue` is 'imported' | 'created' | null; the chosen option leaves the
// list (nothing to pick twice).
const props = defineProps({
    modelValue: {
        type: String,
        default: null,
    },
    placeholder: {
        type: String,
        default: 'Choisir un type de document…',
    },
});

const emit = defineEmits(['update:modelValue']);

const OPTIONS = [
    { value: 'imported', label: 'Importé' },
    { value: 'created', label: 'Créé' },
];

const instanceId = `source-selector-${Math.random().toString(36).slice(2)}`;

const isOpen = ref(false);
const highlightedIndex = ref(-1);
const inputRef = ref(null);

function availableOptions() {
    return OPTIONS.filter((option) => option.value !== props.modelValue);
}

function openSuggestions() {
    isOpen.value = true;
    highlightedIndex.value = availableOptions().length > 0 ? 0 : -1;
}

function closeSuggestions() {
    isOpen.value = false;
    highlightedIndex.value = -1;
}

function selectOption(option) {
    emit('update:modelValue', option.value);
    closeSuggestions();
}

function onKeydown(event) {
    const options = availableOptions();

    if (event.key === 'ArrowDown') {
        event.preventDefault();

        if (!isOpen.value) {
            openSuggestions();
        } else if (options.length > 0) {
            highlightedIndex.value = (highlightedIndex.value + 1) % options.length;
        }

        return;
    }

    if (event.key === 'ArrowUp') {
        event.preventDefault();

        if (isOpen.value && options.length > 0) {
            highlightedIndex.value = (highlightedIndex.value - 1 + options.length) % options.length;
        }

        return;
    }

    if (event.key === 'Enter') {
        if (isOpen.value && options[highlightedIndex.value]) {
            event.preventDefault();
            selectOption(options[highlightedIndex.value]);
        }

        return;
    }

    if (event.key === 'Escape' && isOpen.value) {
        event.preventDefault();
        closeSuggestions();
    }
}

// A click on an already focused (so already closed after a pick) field
// reopens the list; focus alone would not fire again.
function reopenOnClick() {
    if (!isOpen.value) {
        openSuggestions();
    }
}
</script>

<template>
    <div class="relative w-full">
        <TextInput
            :id="`${instanceId}-input`"
            ref="inputRef"
            model-value=""
            readonly
            role="combobox"
            aria-autocomplete="list"
            aria-label="Type de document"
            :aria-expanded="isOpen"
            :aria-controls="isOpen ? `${instanceId}-listbox` : undefined"
            :aria-activedescendant="isOpen && highlightedIndex >= 0 && availableOptions()[highlightedIndex]
                ? `${instanceId}-option-${availableOptions()[highlightedIndex].value}`
                : undefined"
            :placeholder="placeholder"
            class="cursor-pointer"
            @focus="openSuggestions"
            @click="reopenOnClick"
            @blur="closeSuggestions"
            @keydown="onKeydown"
        />

        <ul
            v-if="isOpen"
            :id="`${instanceId}-listbox`"
            role="listbox"
            class="absolute z-10 mt-1 max-h-48 w-full overflow-auto rounded-sm border border-border bg-surface py-1 shadow-lg"
        >
            <li v-if="availableOptions().length === 0" role="presentation" class="px-3 py-2 text-sm text-muted">
                Aucun autre type.
            </li>
            <li
                v-for="(option, index) in availableOptions()"
                :id="`${instanceId}-option-${option.value}`"
                :key="option.value"
                role="option"
                :aria-selected="index === highlightedIndex"
                class="cursor-pointer px-3 py-2 text-sm text-foreground"
                :class="{ 'bg-border': index === highlightedIndex }"
                @mousedown.prevent="selectOption(option)"
                @mouseenter="highlightedIndex = index"
            >
                {{ option.label }}
            </li>
        </ul>
    </div>
</template>
