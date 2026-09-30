<script setup>
import { ref } from 'vue';
import ConfigurationLayout from '@/Layouts/ConfigurationLayout.vue';
import { COLOR_TOKENS, PALETTES, useTheme } from '@/Composables/useTheme';

const { mode, customBase, customColors, setMode, setCustomBase, setCustomColor, resetCustomColor, resetCustom } = useTheme();

// Live samples for the lexicon: they use the CSS variables in effect, so
// they follow the theme being applied (including custom colours).
const samples = {
    background: { backgroundColor: 'var(--color-background)', color: 'var(--color-foreground)', borderColor: 'var(--color-border)' },
    surface: { backgroundColor: 'var(--color-surface)', color: 'var(--color-foreground)', borderColor: 'var(--color-border)' },
    'surface-alt': { backgroundColor: 'var(--color-surface-alt)', color: 'var(--color-foreground)', borderColor: 'var(--color-border)' },
    border: { backgroundColor: 'var(--color-background)', color: 'var(--color-foreground)', borderColor: 'var(--color-border)', borderWidth: '3px' },
    foreground: { backgroundColor: 'var(--color-background)', color: 'var(--color-foreground)', borderColor: 'transparent' },
    muted: { backgroundColor: 'var(--color-background)', color: 'var(--color-muted)', borderColor: 'transparent' },
    primary: { backgroundColor: 'var(--color-primary)', color: 'var(--color-primary-foreground)', borderColor: 'transparent' },
    'primary-hover': { backgroundColor: 'var(--color-primary-hover)', color: 'var(--color-primary-foreground)', borderColor: 'transparent' },
    'primary-foreground': { backgroundColor: 'var(--color-primary)', color: 'var(--color-primary-foreground)', borderColor: 'transparent' },
};

const sampleTexts = {
    background: 'Exemple',
    surface: 'Exemple',
    'surface-alt': 'Exemple',
    border: 'Exemple',
    foreground: 'Exemple',
    muted: 'Exemple',
    primary: 'Exemple',
    'primary-hover': 'Exemple',
    'primary-foreground': 'Exemple',
};

const presetThemes = [
    { value: 'light', label: 'Clair', palette: PALETTES.light },
    { value: 'dark', label: 'Sombre', palette: PALETTES.dark },
];

// The lexicon is folded by default, like the search help (Search.vue); the
// reader's choice is remembered.
const LEXICON_OPEN_STORAGE_KEY = 'bmad-demo-theme-lexicon-open';

function readStoredLexiconOpen() {
    try {
        return localStorage.getItem(LEXICON_OPEN_STORAGE_KEY) === 'true';
    } catch (e) {
        return false;
    }
}

const isLexiconOpen = ref(readStoredLexiconOpen());

function toggleLexicon() {
    isLexiconOpen.value = !isLexiconOpen.value;

    try {
        localStorage.setItem(LEXICON_OPEN_STORAGE_KEY, isLexiconOpen.value ? 'true' : 'false');
    } catch (e) {
        // Private browsing or storage disabled: the state just won't persist across reloads.
    }
}

const baseOptions = [
    { value: 'light', label: 'Clair' },
    { value: 'dark', label: 'Sombre' },
];
</script>

<template>
    <ConfigurationLayout>
        <div class="mx-auto w-full px-6 py-8 xl:w-3/4">
            <h1 class="sr-only">Thèmes</h1>

            <fieldset class="flex flex-col gap-4">
                <legend class="sr-only">Thème de l'application</legend>

                <label
                    v-for="theme in presetThemes"
                    :key="theme.value"
                    class="flex cursor-pointer flex-col gap-3 rounded-lg border-2 p-4"
                    :class="mode === theme.value ? 'border-foreground' : 'border-border'"
                >
                    <span class="flex items-center gap-2 text-sm font-semibold text-foreground">
                        <input
                            type="radio"
                            name="theme"
                            :value="theme.value"
                            :checked="mode === theme.value"
                            :data-testid="`theme-${theme.value}`"
                            @change="setMode(theme.value)"
                        />
                        {{ theme.label }}
                    </span>
                    <ul class="grid grid-cols-1 gap-2 sm:grid-cols-2 xl:grid-cols-3">
                        <li v-for="token in COLOR_TOKENS" :key="token.key" class="flex items-center gap-2 text-xs text-foreground">
                            <span
                                class="h-5 w-5 shrink-0 rounded border border-border"
                                :style="{ backgroundColor: theme.palette[token.key] }"
                                aria-hidden="true"
                            ></span>
                            <span>{{ token.label }}</span>
                            <code class="text-muted">{{ theme.palette[token.key] }}</code>
                        </li>
                    </ul>
                </label>

                <div
                    class="flex flex-col gap-3 rounded-lg border-2 p-4"
                    :class="mode === 'custom' ? 'border-foreground' : 'border-border'"
                >
                    <div class="flex items-center justify-between gap-2">
                        <label class="flex cursor-pointer items-center gap-2 text-sm font-semibold text-foreground">
                            <input
                                type="radio"
                                name="theme"
                                value="custom"
                                :checked="mode === 'custom'"
                                data-testid="theme-custom"
                                @change="setMode('custom')"
                            />
                            Personnalisé
                        </label>
                        <button
                            v-if="mode === 'custom'"
                            type="button"
                            class="flex items-center gap-1.5 rounded-md border border-border px-3 py-1.5 text-sm text-foreground transition hover:bg-surface-alt focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-primary"
                            data-testid="custom-reset"
                            @click="resetCustom"
                        >
                            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" class="h-4 w-4 shrink-0" aria-hidden="true">
                                <path d="M3 12a9 9 0 1 0 3-6.7L3 8" />
                                <path d="M3 3v5h5" />
                            </svg>
                            Réinitialiser
                        </button>
                    </div>

                    <template v-if="mode === 'custom'">
                        <div class="flex flex-wrap items-center gap-4 text-sm text-foreground">
                            <span id="custom-base-label">Base :</span>
                            <div role="group" aria-labelledby="custom-base-label" class="flex gap-3">
                                <label v-for="option in baseOptions" :key="option.value" class="flex cursor-pointer items-center gap-1.5">
                                    <input
                                        type="radio"
                                        name="custom-base"
                                        :value="option.value"
                                        :checked="customBase === option.value"
                                        :data-testid="`custom-base-${option.value}`"
                                        @change="setCustomBase(option.value)"
                                    />
                                    {{ option.label }}
                                </label>
                            </div>
                        </div>

                        <ul class="grid grid-cols-1 gap-2 sm:grid-cols-2 xl:grid-cols-3">
                            <li v-for="token in COLOR_TOKENS" :key="token.key" class="flex items-center gap-2 text-xs text-foreground">
                                <input
                                    type="color"
                                    class="h-6 w-8 shrink-0 cursor-pointer rounded border border-border bg-transparent p-0"
                                    :value="customColors[token.key]"
                                    :aria-label="token.label"
                                    :data-testid="`custom-color-${token.key}`"
                                    @input="setCustomColor(token.key, $event.target.value)"
                                />
                                <span>{{ token.label }}</span>
                                <code class="text-muted">{{ customColors[token.key] }}</code>
                                <button
                                    type="button"
                                    class="flex h-7 w-7 shrink-0 items-center justify-center rounded-md border border-border text-foreground transition hover:bg-surface-alt focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-1 focus-visible:outline-primary"
                                    :aria-label="`Réinitialiser « ${token.label} »`"
                                    :title="`Réinitialiser « ${token.label} »`"
                                    :data-testid="`custom-reset-${token.key}`"
                                    @click="resetCustomColor(token.key)"
                                >
                                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" class="h-4 w-4" aria-hidden="true">
                                        <path d="M3 12a9 9 0 1 0 3-6.7L3 8" />
                                        <path d="M3 3v5h5" />
                                    </svg>
                                </button>
                            </li>
                        </ul>
                    </template>
                    <p v-else class="text-xs text-muted">
                        Choisissez vos propres couleurs à partir d'une base claire ou sombre.
                    </p>
                </div>
            </fieldset>

            <section class="mt-8 rounded-lg border border-border bg-surface text-sm text-muted">
                <h2 class="text-base font-semibold text-foreground">
                    <button
                        type="button"
                        class="flex w-full items-center justify-between rounded-lg px-4 py-3 text-left focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-primary focus-visible:ring-2 focus-visible:ring-foreground dark:focus-visible:ring-background"
                        :aria-expanded="isLexiconOpen"
                        aria-controls="theme-lexicon"
                        @click="toggleLexicon"
                    >
                        <span>Lexique des couleurs</span>
                        <svg
                            xmlns="http://www.w3.org/2000/svg"
                            viewBox="0 0 24 24"
                            fill="none"
                            stroke="currentColor"
                            stroke-width="2"
                            stroke-linecap="round"
                            stroke-linejoin="round"
                            class="h-4 w-4 shrink-0 transition-transform duration-200 motion-reduce:transition-none"
                            :class="{ 'rotate-180': isLexiconOpen }"
                            aria-hidden="true"
                        >
                            <path d="m6 9 6 6 6-6" />
                        </svg>
                    </button>
                </h2>

                <div
                    id="theme-lexicon"
                    class="grid transition-[grid-template-rows] duration-200 motion-reduce:transition-none"
                    :class="isLexiconOpen ? 'grid-rows-[1fr]' : 'grid-rows-[0fr]'"
                    :inert="isLexiconOpen ? undefined : true"
                    :aria-hidden="isLexiconOpen ? undefined : 'true'"
                >
                    <div class="overflow-hidden">
                        <div class="border-t border-border px-4 py-4">
                            <dl class="flex flex-col gap-2" data-testid="theme-lexicon">
                                <div v-for="token in COLOR_TOKENS" :key="token.key" class="flex items-center gap-4 rounded-lg border border-border px-4 py-2">
                                    <div class="min-w-0 flex-1">
                                        <dt class="text-sm font-semibold text-foreground">{{ token.label }}</dt>
                                        <dd class="text-sm text-foreground">{{ token.description }}</dd>
                                        <dd class="text-sm text-muted" data-testid="lexicon-example">Exemple : {{ token.example }}</dd>
                                    </div>
                                    <span
                                        class="inline-flex min-w-24 shrink-0 items-center justify-center rounded-md border px-3 py-2 text-xs"
                                        :style="samples[token.key]"
                                        aria-hidden="true"
                                        data-testid="lexicon-sample"
                                    >
                                        {{ sampleTexts[token.key] }}
                                    </span>
                                </div>
                            </dl>
                        </div>
                    </div>
                </div>
            </section>
        </div>
    </ConfigurationLayout>
</template>
