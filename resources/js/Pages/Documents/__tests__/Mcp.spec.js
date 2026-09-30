import { mount } from '@vue/test-utils';
import { afterEach, describe, expect, it, vi } from 'vitest';
import Mcp from '@/Pages/Documents/Mcp.vue';

const globalStubs = {
    AppLayout: { template: '<div><slot /></div>' },
};

const WINDOWS_PROJECT_PATH = 'C:\\Sites\\bmad-demo';

function mountPage(projectPath = WINDOWS_PROJECT_PATH) {
    return mount(Mcp, {
        props: { projectPath },
        global: { stubs: globalStubs },
    });
}

describe('Documents/Mcp', () => {
    afterEach(() => {
        vi.restoreAllMocks();
        vi.useRealTimers();
        vi.unstubAllGlobals();
    });

    it('explains that the server is local-only and lists the three tools', () => {
        const wrapper = mountPage();

        expect(wrapper.find('[data-testid="mcp-local-only"]').text()).toContain('uniquement en local');

        const names = wrapper.findAll('[data-testid="mcp-tool"] code').map((code) => code.text());
        expect(names).toEqual(['search_documents', 'read_document', 'list_tags']);
    });

    it('shows a Claude Desktop config carrying the real project path, with forward slashes', () => {
        const wrapper = mountPage(WINDOWS_PROJECT_PATH);

        const config = JSON.parse(wrapper.find('[data-testid="mcp-desktop-config"]').text());

        expect(config).toEqual({
            mcpServers: {
                'bmad-demo': {
                    command: 'php',
                    args: ['C:/Sites/bmad-demo/artisan', 'mcp:start', 'bmad-demo'],
                },
            },
        });
        expect(wrapper.find('[data-testid="mcp-code-command"]').text())
            .toBe('claude mcp add bmad-demo -- php "C:/Sites/bmad-demo/artisan" mcp:start bmad-demo');
    });

    it('states ChatGPT is unavailable and announces remote access as coming soon, without any form', () => {
        const wrapper = mountPage();

        expect(wrapper.find('[data-testid="mcp-chatgpt"]').text()).toContain('serveur distant');
        expect(wrapper.find('[data-testid="mcp-remote-soon"]').text()).toContain('Bientôt');
        expect(wrapper.find('form').exists()).toBe(false);
        expect(wrapper.find('input').exists()).toBe(false);
    });

    it('copies the config and confirms on the button', async () => {
        const writeText = vi.fn().mockResolvedValue(undefined);
        vi.stubGlobal('navigator', { clipboard: { writeText } });
        const wrapper = mountPage();

        await wrapper.find('[data-testid="mcp-copy-desktop"]').trigger('click');
        await Promise.resolve();
        await wrapper.vm.$nextTick();

        expect(writeText).toHaveBeenCalledWith(wrapper.find('[data-testid="mcp-desktop-config"]').text());
        expect(wrapper.find('[data-testid="mcp-copy-desktop"]').text()).toBe('Copié');
        expect(wrapper.find('[data-testid="mcp-copy-code"]').text()).toBe('Copier');
    });

    it('stays silent when the clipboard is unavailable', async () => {
        vi.stubGlobal('navigator', { clipboard: { writeText: vi.fn().mockRejectedValue(new Error('denied')) } });
        const wrapper = mountPage();

        await wrapper.find('[data-testid="mcp-copy-desktop"]').trigger('click');
        await Promise.resolve();
        await wrapper.vm.$nextTick();

        expect(wrapper.find('[data-testid="mcp-copy-desktop"]').text()).toBe('Copier');
    });

    it('stays silent when there is no clipboard API at all', async () => {
        vi.stubGlobal('navigator', {});
        const wrapper = mountPage();

        await wrapper.find('[data-testid="mcp-copy-desktop"]').trigger('click');
        await Promise.resolve();
        await wrapper.vm.$nextTick();

        expect(wrapper.find('[data-testid="mcp-copy-desktop"]').text()).toBe('Copier');
    });

    it('copies the Claude Code command and reverts the confirmation after two seconds', async () => {
        vi.useFakeTimers();
        const writeText = vi.fn().mockResolvedValue(undefined);
        vi.stubGlobal('navigator', { clipboard: { writeText } });
        const wrapper = mountPage();

        await wrapper.find('[data-testid="mcp-copy-code"]').trigger('click');
        await Promise.resolve();
        await wrapper.vm.$nextTick();

        expect(writeText).toHaveBeenCalledWith(wrapper.find('[data-testid="mcp-code-command"]').text());
        expect(wrapper.find('[data-testid="mcp-copy-code"]').text()).toBe('Copié');

        vi.advanceTimersByTime(2000);
        await wrapper.vm.$nextTick();

        expect(wrapper.find('[data-testid="mcp-copy-code"]').text()).toBe('Copier');
    });
});
