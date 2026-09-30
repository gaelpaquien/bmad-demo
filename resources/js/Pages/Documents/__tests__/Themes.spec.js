import { mount } from '@vue/test-utils';
import { afterEach, describe, expect, it } from 'vitest';
import Themes from '@/Pages/Documents/Themes.vue';
import { PALETTES } from '@/Composables/useTheme';

const globalStubs = {
    ConfigurationLayout: { template: '<div><slot /></div>' },
};

describe('Documents/Themes', () => {
    afterEach(() => {
        document.documentElement.classList.remove('dark');
        document.documentElement.removeAttribute('style');
        localStorage.clear();
    });

    it('shows the colour detail of the light and dark themes', () => {
        const wrapper = mount(Themes, { global: { stubs: globalStubs } });

        expect(wrapper.text()).toContain(PALETTES.light.background);
        expect(wrapper.text()).toContain(PALETTES.dark.background);
    });

    it('marks the selected theme with the focus border colour of form fields, not lime', async () => {
        const wrapper = mount(Themes, { global: { stubs: globalStubs } });
        const card = () => wrapper.find('[data-testid="theme-dark"]').element.closest('label');

        await wrapper.find('[data-testid="theme-dark"]').setValue();

        expect(card().className).toContain('border-foreground');
        expect(card().className).not.toContain('border-primary');
        expect(card().className).not.toContain('ring-primary');
    });

    it('applies and persists the dark theme when chosen', async () => {
        const wrapper = mount(Themes, { global: { stubs: globalStubs } });

        await wrapper.find('[data-testid="theme-dark"]').setValue();

        expect(document.documentElement.classList.contains('dark')).toBe(true);
        expect(localStorage.getItem('bmad-demo-theme')).toBe('dark');
    });

    it('reveals the colour editor only for the custom theme, applies a change and resets it', async () => {
        const wrapper = mount(Themes, { global: { stubs: globalStubs } });

        expect(wrapper.find('[data-testid="custom-color-primary"]').exists()).toBe(false);

        await wrapper.find('[data-testid="theme-custom"]').setValue();
        await wrapper.find('[data-testid="custom-color-primary"]').setValue('#ff0000');

        expect(document.documentElement.style.getPropertyValue('--color-primary')).toBe('#ff0000');

        await wrapper.find('[data-testid="custom-reset"]').trigger('click');

        expect(document.documentElement.style.getPropertyValue('--color-primary')).toBe(PALETTES.light.primary);
    });

    it('resets a single custom colour with an always-clickable, visible button', async () => {
        const wrapper = mount(Themes, { global: { stubs: globalStubs } });

        await wrapper.find('[data-testid="theme-custom"]').setValue();
        expect(wrapper.find('[data-testid="custom-reset-primary"]').attributes('disabled')).toBeUndefined();
        expect(wrapper.find('[data-testid="custom-reset-primary"]').classes()).toContain('border-border');

        await wrapper.find('[data-testid="custom-color-primary"]').setValue('#ff0000');
        await wrapper.find('[data-testid="custom-color-background"]').setValue('#123456');
        await wrapper.find('[data-testid="custom-reset-primary"]').trigger('click');

        expect(document.documentElement.style.getPropertyValue('--color-primary')).toBe(PALETTES.light.primary);
        expect(document.documentElement.style.getPropertyValue('--color-background')).toBe('#123456');
    });

    it('shows the global reset button, with an icon, in the header row of the custom card', async () => {
        const wrapper = mount(Themes, { global: { stubs: globalStubs } });

        expect(wrapper.find('[data-testid="custom-reset"]').exists()).toBe(false);

        await wrapper.find('[data-testid="theme-custom"]').setValue();
        const reset = wrapper.find('[data-testid="custom-reset"]');

        expect(reset.find('svg').exists()).toBe(true);
        expect(reset.element.parentElement.contains(wrapper.find('[data-testid="theme-custom"]').element)).toBe(true);
    });

    it('explains what every colour is used for in a lexicon', () => {
        const wrapper = mount(Themes, { global: { stubs: globalStubs } });
        const lexicon = wrapper.find('[data-testid="theme-lexicon"]');

        expect(lexicon.findAll('dt')).toHaveLength(9);
        expect(lexicon.text()).toContain("Fond général de l'application");
        expect(lexicon.findAll('dd').every((entry) => entry.text().length > 0)).toBe(true);
        expect(lexicon.findAll('[data-testid="lexicon-example"]')).toHaveLength(9);
        expect(lexicon.findAll('[data-testid="lexicon-example"]').every((entry) => entry.text().startsWith('Exemple : '))).toBe(true);
        expect(lexicon.findAll('[data-testid="lexicon-sample"]')).toHaveLength(9);
        expect(lexicon.findAll('[data-testid="lexicon-sample"]').every((sample) => sample.text() === 'Exemple')).toBe(true);
    });

    it('folds the lexicon by default and toggles it, remembering the choice', async () => {
        const wrapper = mount(Themes, { global: { stubs: globalStubs } });
        const toggle = wrapper.find('button[aria-controls="theme-lexicon"]');
        const panel = wrapper.find('#theme-lexicon');

        expect(toggle.text()).toContain('Lexique des couleurs');
        expect(toggle.attributes('aria-expanded')).toBe('false');
        expect(panel.attributes('inert')).toBeDefined();

        await toggle.trigger('click');

        expect(toggle.attributes('aria-expanded')).toBe('true');
        expect(panel.attributes('inert')).toBeUndefined();
        expect(localStorage.getItem('bmad-demo-theme-lexicon-open')).toBe('true');
        expect(mount(Themes, { global: { stubs: globalStubs } }).find('button[aria-controls="theme-lexicon"]').attributes('aria-expanded')).toBe('true');
    });

    it('changes the custom colours and the dark class when the base is switched', async () => {
        const wrapper = mount(Themes, { global: { stubs: globalStubs } });

        await wrapper.find('[data-testid="theme-custom"]').setValue();
        await wrapper.find('[data-testid="custom-base-dark"]').setValue();

        expect(document.documentElement.classList.contains('dark')).toBe(true);
        expect(document.documentElement.style.getPropertyValue('--color-background')).toBe(PALETTES.dark.background);
        expect(wrapper.find('[data-testid="custom-color-background"]').element.value).toBe(PALETTES.dark.background);
    });
});
