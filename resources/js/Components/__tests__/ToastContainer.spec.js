import { mount } from '@vue/test-utils';
import { afterEach, describe, expect, it, vi } from 'vitest';
import ToastContainer from '@/Components/ToastContainer.vue';
import { dismissToast, notifySuccess, useToasts } from '@/Composables/useToasts';

describe('ToastContainer', () => {
    afterEach(() => {
        useToasts().toasts.map((toast) => toast.id).forEach(dismissToast);
        vi.useRealTimers();
    });

    it('renders nothing while there is no toast', () => {
        const wrapper = mount(ToastContainer);

        expect(wrapper.findAll('[data-testid="toast"]')).toHaveLength(0);
    });

    it('renders one item per toast inside a persistent polite live region', async () => {
        const wrapper = mount(ToastContainer);

        notifySuccess('Document supprimé.');
        notifySuccess('Tag créé.');
        await wrapper.vm.$nextTick();

        const items = wrapper.findAll('[data-testid="toast"]');

        expect(items.map((item) => item.text())).toEqual(['Document supprimé.', 'Tag créé.']);
        expect(wrapper.find('[role="status"]').attributes('aria-live')).toBe('polite');
    });

    it('closes a toast from its close button', async () => {
        const wrapper = mount(ToastContainer);

        notifySuccess('Document supprimé.');
        await wrapper.vm.$nextTick();
        await wrapper.find('button[aria-label="Fermer la notification"]').trigger('click');

        expect(wrapper.findAll('[data-testid="toast"]')).toHaveLength(0);
    });

    it('keeps a toast pushed before it mounted (survives a layout remount)', () => {
        notifySuccess('Document créé.');

        const wrapper = mount(ToastContainer);

        expect(wrapper.text()).toContain('Document créé.');
    });
});
