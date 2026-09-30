<script setup>
import ConfigurationLayout from '@/Layouts/ConfigurationLayout.vue';
import { COLOR_TOKENS, PALETTES, useTheme } from '@/Composables/useTheme';

const { mode, customBase, customColors, setMode, setCustomBase, setCustomColor, resetCustom } = useTheme();

const presetThemes = [
    { value: 'light', label: 'Clair', palette: PALETTES.light },
    { value: 'dark', label: 'Sombre', palette: PALETTES.dark },
];

const baseOptions = [
    { value: 'light', label: 'Clair' },
    { value: 'dark', label: 'Sombre' },
];
</script>

<template>
    <ConfigurationLayout>
        <div class="mx-auto w-full px-6 py-8 xl:w-3/4">
            <h1 class="mb-2 text-2xl font-semibold text-foreground">
                Thèmes
            </h1>
            <p class="mb-6 text-sm text-muted">
                Le thème choisi est conservé dans ce navigateur.
            </p>

            <fieldset class="flex flex-col gap-4">
                <legend class="sr-only">Thème de l'application</legend>

                <label
                    v-for="theme in presetThemes"
                    :key="theme.value"
                    class="flex cursor-pointer flex-col gap-3 rounded-lg border p-4"
                    :class="mode === theme.value ? 'border-primary ring-2 ring-primary' : 'border-border'"
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
                            <code class="ml-auto text-muted">{{ theme.palette[token.key] }}</code>
                        </li>
                    </ul>
                </label>

                <div
                    class="flex flex-col gap-3 rounded-lg border p-4"
                    :class="mode === 'custom' ? 'border-primary ring-2 ring-primary' : 'border-border'"
                >
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
                            <button
                                type="button"
                                class="ml-auto rounded-md border border-border px-3 py-1.5 text-sm text-foreground transition hover:bg-surface-alt focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-primary"
                                data-testid="custom-reset"
                                @click="resetCustom"
                            >
                                Réinitialiser
                            </button>
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
                                <code class="ml-auto text-muted">{{ customColors[token.key] }}</code>
                            </li>
                        </ul>
                    </template>
                    <p v-else class="text-xs text-muted">
                        Choisissez vos propres couleurs à partir d'une base claire ou sombre.
                    </p>
                </div>
            </fieldset>
        </div>
    </ConfigurationLayout>
</template>
