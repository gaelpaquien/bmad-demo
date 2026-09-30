import { afterEach, beforeEach, describe, expect, it } from 'vitest';
import { PALETTES, useTheme } from '@/Composables/useTheme';

const root = document.documentElement;

function resetDom() {
    root.classList.remove('dark');
    root.removeAttribute('style');
    localStorage.clear();
}

describe('useTheme', () => {
    beforeEach(resetDom);
    afterEach(resetDom);

    it('applies and persists the dark theme, then the light theme', () => {
        const { setMode } = useTheme();

        setMode('dark');
        expect(root.classList.contains('dark')).toBe(true);
        expect(localStorage.getItem('bmad-demo-theme')).toBe('dark');

        setMode('light');
        expect(root.classList.contains('dark')).toBe(false);
        expect(localStorage.getItem('bmad-demo-theme')).toBe('light');
    });

    it('seeds the custom theme from the palette in use and applies colour changes immediately', () => {
        const { mode, customBase, setMode, setCustomColor } = useTheme();

        setMode('dark');
        setMode('custom');

        expect(mode.value).toBe('custom');
        expect(customBase.value).toBe('dark');
        expect(root.classList.contains('dark')).toBe(true);
        expect(root.style.getPropertyValue('--color-background')).toBe(PALETTES.dark.background);

        setCustomColor('primary', '#FF0000');

        expect(root.style.getPropertyValue('--color-primary')).toBe('#ff0000');
        expect(JSON.parse(localStorage.getItem('bmad-demo-theme-custom')).colors.primary).toBe('#ff0000');
    });

    it('restores the custom theme from storage on the next use', () => {
        useTheme().setMode('custom');
        useTheme().setCustomColor('primary', '#00ff00');

        const reloaded = useTheme();

        expect(reloaded.mode.value).toBe('custom');
        expect(reloaded.customColors.value.primary).toBe('#00ff00');
    });

    it('ignores an invalid colour value', () => {
        const { setMode, setCustomColor, customColors } = useTheme();

        setMode('custom');
        setCustomColor('primary', 'rouge');

        expect(customColors.value.primary).toBe(PALETTES.light.primary);
    });

    it('resets the custom colours to the palette of its base', () => {
        const { setMode, setCustomColor, setCustomBase, resetCustom, customColors } = useTheme();

        setMode('custom');
        setCustomBase('dark');
        setCustomColor('background', '#123456');
        resetCustom();

        expect(customColors.value).toEqual(PALETTES.dark);
        expect(root.style.getPropertyValue('--color-background')).toBe(PALETTES.dark.background);
    });

    it('leaves the custom variables off <html> when switching back to a preset theme', () => {
        const { setMode } = useTheme();

        setMode('custom');
        setMode('light');

        expect(root.style.getPropertyValue('--color-background')).toBe('');
    });

    it('falls back to the system preference when the stored custom theme is corrupted', () => {
        localStorage.setItem('bmad-demo-theme', 'custom');
        localStorage.setItem('bmad-demo-theme-custom', '{not json');

        expect(useTheme().mode.value).not.toBe('custom');
    });

    it('treats a stored custom theme with a missing colour as unusable', () => {
        const colors = { ...PALETTES.dark };
        delete colors.muted;
        localStorage.setItem('bmad-demo-theme', 'custom');
        localStorage.setItem('bmad-demo-theme-custom', JSON.stringify({ base: 'dark', colors }));

        expect(useTheme().mode.value).not.toBe('custom');
    });

    it('seeds a new custom theme from the palette in use when the stored one is corrupted', () => {
        localStorage.setItem('bmad-demo-theme', 'dark');
        localStorage.setItem('bmad-demo-theme-custom', '{not json');
        const { setMode, customBase, customColors } = useTheme();

        setMode('custom');

        expect(customBase.value).toBe('dark');
        expect(customColors.value).toEqual(PALETTES.dark);
        expect(JSON.parse(localStorage.getItem('bmad-demo-theme-custom')).base).toBe('dark');
    });

    it('resets a single custom colour to the palette of its base, leaving the others untouched', () => {
        const { setMode, setCustomColor, resetCustomColor, customColors } = useTheme();

        setMode('custom');
        setCustomColor('primary', '#ff0000');
        setCustomColor('background', '#123456');
        resetCustomColor('primary');

        expect(customColors.value.primary).toBe(PALETTES.light.primary);
        expect(customColors.value.background).toBe('#123456');
        expect(root.style.getPropertyValue('--color-primary')).toBe(PALETTES.light.primary);
        expect(JSON.parse(localStorage.getItem('bmad-demo-theme-custom')).colors.background).toBe('#123456');
    });

    it('moves default colours to the new base palette when the base changes, keeping customised ones', () => {
        const { setMode, setCustomColor, setCustomBase, customColors } = useTheme();

        setMode('custom');
        setCustomColor('primary', '#ff0000');
        setCustomBase('dark');

        expect(root.classList.contains('dark')).toBe(true);
        expect(customColors.value.background).toBe(PALETTES.dark.background);
        expect(customColors.value.foreground).toBe(PALETTES.dark.foreground);
        expect(customColors.value.primary).toBe('#ff0000');
        expect(root.style.getPropertyValue('--color-background')).toBe(PALETTES.dark.background);

        setCustomBase('light');

        expect(root.classList.contains('dark')).toBe(false);
        expect(customColors.value.background).toBe(PALETTES.light.background);
        expect(customColors.value.primary).toBe('#ff0000');
    });
});
