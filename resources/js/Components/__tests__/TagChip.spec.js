import { mount } from '@vue/test-utils';
import { describe, expect, it } from 'vitest';
import TagChip from '@/Components/TagChip.vue';

describe('TagChip', () => {
    it('renders the given tag name as read-only text', () => {
        const wrapper = mount(TagChip, { props: { name: 'Factures' } });

        expect(wrapper.text()).toBe('Factures');
    });

    it('has a single design: the Document Detail chip (rounded-md, text-sm, neutral colours)', () => {
        const classes = mount(TagChip, { props: { name: 'Factures' } }).classes();

        expect(classes).toEqual(expect.arrayContaining(['rounded-md', 'px-2.5', 'py-1', 'text-sm', 'bg-surface-alt', 'text-foreground']));
    });

    it('renders no interactive element by default — read-only display', () => {
        const wrapper = mount(TagChip, { props: { name: 'Contrats' } });

        expect(wrapper.find('button').exists()).toBe(false);
        expect(wrapper.findAll('input, select, textarea, a')).toHaveLength(0);
    });

    it('adds a labelled × button emitting `remove` when a removeLabel is given', async () => {
        const wrapper = mount(TagChip, { props: { name: 'Contrats', removeLabel: 'Retirer le tag Contrats' } });

        await wrapper.find('button[aria-label="Retirer le tag Contrats"]').trigger('click');

        expect(wrapper.emitted('remove')).toHaveLength(1);
    });

    it('disables the × button when disabled', () => {
        const wrapper = mount(TagChip, { props: { name: 'Contrats', removeLabel: 'Retirer', disabled: true } });

        expect(wrapper.find('button').attributes('disabled')).toBeDefined();
    });

    it('offers a smaller variant for dense lists, with the same neutral colours', () => {
        const classes = mount(TagChip, { props: { name: 'Finance', size: 'small' } }).classes();

        expect(classes).toEqual(expect.arrayContaining(['rounded-sm', 'text-xs', 'bg-surface-alt', 'text-foreground']));
        expect(classes).not.toContain('text-sm');
    });
});
