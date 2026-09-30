import { mount } from '@vue/test-utils';
import { describe, expect, it } from 'vitest';
import TagChip from '@/Components/TagChip.vue';

describe('TagChip', () => {
    it('renders the given tag name as read-only text', () => {
        const wrapper = mount(TagChip, { props: { name: 'Factures' } });

        expect(wrapper.text()).toBe('Factures');
    });

    it.each([
        ['small', 'rounded-sm', 'text-xs', false],
        ['regular', 'rounded-md', 'text-sm', false],
        ['compact', 'rounded-lg', 'text-xs', true],
    ])('applies the %s size: %s radius, %s, medium weight %s', (size, radius, textSize, isMedium) => {
        const classes = mount(TagChip, { props: { name: 'Factures', size } }).classes();

        expect(classes).toContain(radius);
        expect(classes).toContain(textSize);
        expect(classes.includes('font-medium')).toBe(isMedium);
    });

    it('renders no interactive element — read-only display, never a filter/remove affordance', () => {
        const wrapper = mount(TagChip, { props: { name: 'Contrats' } });

        expect(wrapper.find('button').exists()).toBe(false);
        expect(wrapper.findAll('input, select, textarea, a')).toHaveLength(0);
    });
});
