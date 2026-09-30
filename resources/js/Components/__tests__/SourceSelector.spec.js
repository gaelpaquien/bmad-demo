import { mount } from '@vue/test-utils';
import { describe, expect, it } from 'vitest';
import SourceSelector from '@/Components/SourceSelector.vue';

describe('SourceSelector', () => {
    it('opens the suggestions on focus and emits the chosen source', async () => {
        const wrapper = mount(SourceSelector);
        const input = wrapper.find('input');

        expect(wrapper.find('[role="listbox"]').exists()).toBe(false);

        await input.trigger('focus');

        expect(wrapper.findAll('[role="option"]').map((option) => option.text())).toEqual(['Importé', 'Créé']);

        await wrapper.findAll('[role="option"]')[1].trigger('mousedown');

        expect(wrapper.emitted('update:modelValue')).toEqual([['created']]);
        expect(wrapper.find('[role="listbox"]').exists()).toBe(false);
    });

    it('drops the already chosen source from the list', async () => {
        const wrapper = mount(SourceSelector, { props: { modelValue: 'imported' } });

        await wrapper.find('input').trigger('focus');

        expect(wrapper.findAll('[role="option"]').map((option) => option.text())).toEqual(['Créé']);
    });

    it('picks the highlighted option with the keyboard and closes on Escape', async () => {
        const wrapper = mount(SourceSelector);
        const input = wrapper.find('input');

        await input.trigger('keydown', { key: 'ArrowDown' });
        await input.trigger('keydown', { key: 'ArrowDown' });
        await input.trigger('keydown', { key: 'Enter' });

        expect(wrapper.emitted('update:modelValue')).toEqual([['created']]);

        await input.trigger('keydown', { key: 'ArrowDown' });
        await input.trigger('keydown', { key: 'Escape' });

        expect(wrapper.find('[role="listbox"]').exists()).toBe(false);
    });
});
