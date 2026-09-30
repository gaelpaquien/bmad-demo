import { readFileSync } from 'node:fs';
import { resolve } from 'node:path';
import { afterEach, describe, expect, it, vi } from 'vitest';
import { COLOR_TOKENS, PALETTES } from '@/Composables/useTheme';

// The inline script of app.blade.php applies the stored theme before first
// paint. It duplicates the read half of useTheme.js, so it is evaluated here
// against each stored state to keep the two in lockstep.
const blade = readFileSync(resolve(__dirname, '../../views/app.blade.php'), 'utf8');
const script = blade.match(/<script>([\s\S]*?)<\/script>/)[1];
const root = document.documentElement;

function runScript({ theme, custom, prefersDark = false }) {
    localStorage.clear();

    if (theme) {
        localStorage.setItem('bmad-demo-theme', theme);
    }

    if (custom !== undefined) {
        localStorage.setItem('bmad-demo-theme-custom', typeof custom === 'string' ? custom : JSON.stringify(custom));
    }

    vi.stubGlobal('matchMedia', () => ({ matches: prefersDark }));
    // eslint-disable-next-line no-new-func
    new Function(script)();
}

describe('pre-paint theme script (app.blade.php)', () => {
    afterEach(() => {
        root.classList.remove('dark');
        root.removeAttribute('style');
        localStorage.clear();
        vi.unstubAllGlobals();
    });

    it('applies the dark class for a stored dark theme, and not for a stored light theme on a dark system', () => {
        runScript({ theme: 'dark' });
        expect(root.classList.contains('dark')).toBe(true);

        root.classList.remove('dark');
        runScript({ theme: 'light', prefersDark: true });
        expect(root.classList.contains('dark')).toBe(false);
    });

    it('defaults to the light theme when nothing is stored, even on a dark system', () => {
        runScript({ prefersDark: true });

        expect(root.classList.contains('dark')).toBe(false);
    });

    it.each(['light', 'dark'])('applies a custom theme with a %s base and its nine colours', (base) => {
        const colors = { ...PALETTES[base], primary: '#ff0000' };
        runScript({ theme: 'custom', custom: { base, colors } });

        expect(root.classList.contains('dark')).toBe(base === 'dark');
        COLOR_TOKENS.forEach(({ key }) => {
            expect(root.style.getPropertyValue(`--color-${key}`)).toBe(colors[key]);
        });
    });

    it.each([
        ['corrupted JSON', '{not json'],
        ['an unknown base', { base: 'blue', colors: PALETTES.light }],
        ['a null colours object', { base: 'dark', colors: null }],
        ['a missing colour', { base: 'dark', colors: { ...PALETTES.dark, muted: undefined } }],
        ['a non-hex colour', { base: 'dark', colors: { ...PALETTES.dark, primary: 'rouge' } }],
    ])('falls back to the light theme for %s, even on a dark system', (label, custom) => {
        runScript({ theme: 'custom', custom, prefersDark: true });

        expect(root.classList.contains('dark')).toBe(false);
        expect(root.style.getPropertyValue('--color-primary')).toBe('');
    });
});
