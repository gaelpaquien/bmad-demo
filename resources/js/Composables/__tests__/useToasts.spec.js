import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { TOAST_DURATION_MS, dismissToast, handleFlash, notifySuccess, useToasts } from '@/Composables/useToasts';

describe('useToasts', () => {
    beforeEach(() => {
        vi.useFakeTimers();
    });

    afterEach(() => {
        useToasts().toasts.map((toast) => toast.id).forEach(dismissToast);
        vi.useRealTimers();
    });

    it('stacks toasts in arrival order, without de-duplicating identical messages', () => {
        notifySuccess('Tag créé.');
        notifySuccess('Tag créé.');

        const { toasts } = useToasts();

        expect(toasts.map((toast) => toast.message)).toEqual(['Tag créé.', 'Tag créé.']);
        expect(new Set(toasts.map((toast) => toast.id)).size).toBe(2);
        expect(toasts.every((toast) => toast.type === 'success')).toBe(true);
    });

    it('dismisses a toast on its own once its duration has elapsed', () => {
        notifySuccess('Document créé.');

        vi.advanceTimersByTime(TOAST_DURATION_MS - 1);
        expect(useToasts().toasts).toHaveLength(1);

        vi.advanceTimersByTime(1);
        expect(useToasts().toasts).toHaveLength(0);
    });

    it('turns a flashed toast into exactly one client toast', () => {
        handleFlash({ detail: { flash: { toast: { type: 'success', message: 'Document créé.' } } } });

        expect(useToasts().toasts.map((toast) => toast.message)).toEqual(['Document créé.']);
    });

    it.each([
        ['no toast key', { detail: { flash: {} } }],
        ['no flash at all', { detail: {} }],
        ['a blank message', { detail: { flash: { toast: { message: '' } } } }],
        ['a non-string message', { detail: { flash: { toast: { message: { text: 'x' } } } } }],
    ])('ignores a flash event carrying %s', (_label, event) => {
        handleFlash(event);

        expect(useToasts().toasts).toHaveLength(0);
    });

    it('dismisses only the requested toast when closed manually', () => {
        const firstId = notifySuccess('Document créé.');
        notifySuccess('Tag créé.');

        dismissToast(firstId);

        expect(useToasts().toasts.map((toast) => toast.message)).toEqual(['Tag créé.']);
    });
});
