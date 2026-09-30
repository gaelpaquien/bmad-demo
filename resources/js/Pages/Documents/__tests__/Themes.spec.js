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
});
