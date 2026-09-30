import { ref } from 'vue';

// Theme choice (clair / sombre / personnalisé), resolved before first paint
// by the inline script in app.blade.php — that script duplicates the read
// half of this file (same keys, same validation), keep both in lockstep.
export const THEME_STORAGE_KEY = 'bmad-demo-theme';
export const CUSTOM_THEME_STORAGE_KEY = 'bmad-demo-theme-custom';

// The nine `--color-*` tokens of resources/css/app.css, in display order,
// each with what it is used for and a concrete example (the lexicon on the Thèmes page).
export const COLOR_TOKENS = [
    { key: 'background', label: 'Fond', description: "Fond général de l'application, derrière tout le contenu.", example: "la page sur laquelle s'affichent la liste des documents ou un formulaire." },
    { key: 'surface', label: 'Surface', description: 'Fond des zones en retrait, et des éléments de menu au survol.', example: "le fond d'une entrée de menu quand la souris passe dessus." },
    { key: 'surface-alt', label: 'Surface alternative', description: 'Fond des menus latéraux, des encarts et des blocs de code.', example: "le fond du menu latéral principal ou d'un bloc de code." },
    { key: 'border', label: 'Bordure', description: 'Traits de séparation et contours des cartes et des champs.', example: "le contour d'une carte de document ou d'un champ de saisie." },
    { key: 'foreground', label: 'Texte', description: 'Couleur du texte principal et des icônes.', example: "le titre d'une page ou le nom d'un document." },
    { key: 'muted', label: 'Texte atténué', description: 'Texte secondaire : aides, légendes, informations discrètes.', example: "la date d'un document ou un texte d'aide sous un champ." },
    { key: 'primary', label: 'Couleur primaire', description: "Couleur d'accent : boutons d'action, entrée de menu active, mise en évidence.", example: "le bouton « Enregistrer » ou l'entrée active du menu." },
    { key: 'primary-hover', label: 'Primaire au survol', description: "Variante de la couleur primaire quand la souris survole un bouton d'action.", example: "le bouton « Enregistrer » quand la souris passe dessus." },
    { key: 'primary-foreground', label: 'Texte sur primaire', description: 'Texte et icônes affichés sur un fond de couleur primaire.', example: "le mot « Enregistrer » écrit sur le bouton." },
];

// Must mirror the `@theme` / `.dark` values of resources/css/app.css.
export const PALETTES = {
    light: {
        background: '#f5f3f0',
        surface: '#efebe6',
        'surface-alt': '#eae6e1',
        border: '#dbd5cd',
        foreground: '#221f1a',
        muted: '#7a7266',
        primary: '#c6ff00',
        'primary-hover': '#b6ec00',
        'primary-foreground': '#12130a',
    },
    dark: {
        background: '#1b1815',
        surface: '#211d19',
        'surface-alt': '#24201b',
        border: '#362f27',
        foreground: '#ece7e0',
        muted: '#a69c8d',
        primary: '#c6ff00',
        'primary-hover': '#a8da00',
        'primary-foreground': '#12130a',
    },
};

const HEX_COLOR = /^#[0-9a-f]{6}$/i;

function readStorage(key) {
    try {
        return localStorage.getItem(key);
    } catch (e) {
        return null;
    }
}

function writeStorage(key, value) {
    try {
        localStorage.setItem(key, value);
    } catch (e) {
        // Private browsing or storage disabled: applied for this page only.
    }
}

/**
 * Reads the stored custom theme. `isUsable` requires a known base and all
 * nine colours as valid hex values — anything else (corrupted JSON, unknown
 * base, one missing or non-hex colour) is unusable as a whole, exactly like
 * in the pre-paint script, so first paint and hydration never disagree.
 * When unusable, the colours fall back to the light palette.
 */
function readCustomTheme() {
    let parsed = null;

    try {
        parsed = JSON.parse(readStorage(CUSTOM_THEME_STORAGE_KEY));
    } catch (e) {
        parsed = null;
    }

    const hasValidBase = parsed?.base === 'light' || parsed?.base === 'dark';
    const isUsable = hasValidBase
        && COLOR_TOKENS.every(({ key }) => typeof parsed.colors?.[key] === 'string' && HEX_COLOR.test(parsed.colors[key]));

    if (!isUsable) {
        return { base: 'light', colors: { ...PALETTES.light }, isUsable: false };
    }

    return { base: parsed.base, colors: { ...parsed.colors }, isUsable: true };
}

export function useTheme() {
    const storedMode = readStorage(THEME_STORAGE_KEY);
    const custom = readCustomTheme();
    const isStoredModeUsable = ['light', 'dark'].includes(storedMode) || (storedMode === 'custom' && custom.isUsable);
    const mode = ref(isStoredModeUsable ? storedMode : 'light');
    const customBase = ref(custom.base);
    const customColors = ref(custom.colors);

    function apply() {
        const root = document.documentElement;
        const isCustom = mode.value === 'custom';

        root.classList.toggle('dark', isCustom ? customBase.value === 'dark' : mode.value === 'dark');

        COLOR_TOKENS.forEach(({ key }) => {
            if (isCustom) {
                root.style.setProperty(`--color-${key}`, customColors.value[key]);
            } else {
                root.style.removeProperty(`--color-${key}`);
            }
        });
    }

    function persistCustom() {
        writeStorage(CUSTOM_THEME_STORAGE_KEY, JSON.stringify({
            base: customBase.value,
            colors: customColors.value,
        }));
    }

    function setMode(next) {
        // First switch to "Personnalisé": start from the palette in use
        // rather than always from the light one.
        if (next === 'custom' && mode.value !== 'custom' && !readCustomTheme().isUsable) {
            customBase.value = mode.value;
            customColors.value = { ...PALETTES[mode.value] };
            persistCustom();
        }

        mode.value = next;
        writeStorage(THEME_STORAGE_KEY, next);
        apply();
    }

    // Switching the base drives the `.dark` class (Tailwind `dark:` variants)
    // and moves every colour still at its default to the new base's default;
    // colours the user changed are kept.
    function setCustomBase(base) {
        const previousBase = customBase.value;
        const next = {};

        COLOR_TOKENS.forEach(({ key }) => {
            const isDefault = customColors.value[key] === PALETTES[previousBase][key];
            next[key] = isDefault ? PALETTES[base][key] : customColors.value[key];
        });

        customBase.value = base;
        customColors.value = next;
        persistCustom();
        apply();
    }

    function setCustomColor(key, value) {
        if (!HEX_COLOR.test(value)) {
            return;
        }

        customColors.value = { ...customColors.value, [key]: value.toLowerCase() };
        persistCustom();
        apply();
    }

    function resetCustomColor(key) {
        customColors.value = { ...customColors.value, [key]: PALETTES[customBase.value][key] };
        persistCustom();
        apply();
    }

    function resetCustom() {
        customColors.value = { ...PALETTES[customBase.value] };
        persistCustom();
        apply();
    }

    return { mode, customBase, customColors, setMode, setCustomBase, setCustomColor, resetCustomColor, resetCustom };
}
