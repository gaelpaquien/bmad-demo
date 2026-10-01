import { mount } from '@vue/test-utils';
import { afterEach, describe, expect, it, vi } from 'vitest';
import ToastContainer from '@/Components/ToastContainer.vue';
import { TOAST_DURATION_MS, dismissToast, notifySuccess, useToasts } from '@/Composables/useToasts';

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

    it('shows a progress bar running for the same duration as the automatic dismissal', async () => {
        const wrapper = mount(ToastContainer);

        notifySuccess('Document créé.');
        await wrapper.vm.$nextTick();

        const progress = wrapper.find('[data-testid="toast-progress"]');

        expect(progress.exists()).toBe(true);
        expect(progress.attributes('style')).toContain(`--toast-duration: ${TOAST_DURATION_MS}ms`);
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
