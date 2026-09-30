<script setup>
import { computed, onBeforeUnmount, ref } from 'vue';
import AppLayout from '@/Layouts/AppLayout.vue';

const props = defineProps({
    projectPath: {
        type: String,
        default: '',
    },
});

const SERVER_HANDLE = 'bmad-demo';

const tools = [
    {
        name: 'search_documents',
        role: "Recherche des documents par mots-clés dans leur titre et leur contenu (pièces jointes comprises), avec un filtre optionnel par tag. Renvoie 10 résultats par page, chacun avec un extrait.",
    },
    {
        name: 'read_document',
        role: "Lit le texte d'un document, puis celui de ses pièces jointes. Un long texte est renvoyé par tranches, à poursuivre avec `next_offset`.",
    },
    {
        name: 'list_tags',
        role: 'Liste les tags avec le nombre de documents de chacun, pour filtrer la recherche.',
    },
];

// Path separators are normalised so the same string works in the JSON
// snippet on every OS; PHP accepts forward slashes on Windows too.
const artisanPath = computed(() => `${props.projectPath.replace(/\\/g, '/')}/artisan`);

const claudeDesktopConfig = computed(() => JSON.stringify({
    mcpServers: {
        [SERVER_HANDLE]: {
            command: 'php',
            args: [artisanPath.value, 'mcp:start', SERVER_HANDLE],
        },
    },
}, null, 2));

const claudeCodeCommand = computed(() => `claude mcp add ${SERVER_HANDLE} -- php "${artisanPath.value}" mcp:start ${SERVER_HANDLE}`);

// Which snippet was just copied ('desktop' | 'code' | null) — drives the
// brief "Copié" confirmation on its button.
const copiedKey = ref(null);
let copiedTimer = null;

async function copySnippet(key, text) {
    try {
        await navigator.clipboard.writeText(text);
    } catch (e) {
        // Clipboard unavailable (insecure context, denied permission): the
        // snippet stays visible and selectable, no blocking error.
        return;
    }

    copiedKey.value = key;
    clearTimeout(copiedTimer);
    copiedTimer = setTimeout(() => {
        copiedKey.value = null;
    }, 2000);
}

onBeforeUnmount(() => clearTimeout(copiedTimer));
</script>

<template>
    <AppLayout>
        <div class="mx-auto w-full px-6 py-8 xl:w-3/4">
            <h1 class="mb-6 text-2xl font-semibold text-foreground">
                MCP
            </h1>

            <section class="mb-8 flex flex-col gap-2" aria-labelledby="mcp-role">
                <h2 id="mcp-role" class="text-lg font-semibold text-foreground">À quoi ça sert ?</h2>
                <p class="text-sm text-foreground">
                    Le serveur MCP (Model Context Protocol) permet à un assistant IA comme Claude d'utiliser vos documents
                    comme base de connaissance : il les recherche par mots-clés puis lit leur texte pour répondre à vos questions.
                    L'accès est en lecture seule : l'assistant ne peut ni créer, ni modifier, ni supprimer de document, et les fichiers
                    originaux ne sont jamais transmis, seulement le texte extrait.
                </p>
                <p class="rounded-md border border-border bg-surface-alt px-4 py-2 text-sm text-foreground" data-testid="mcp-local-only">
                    Pour le moment, le serveur fonctionne uniquement en local : c'est l'assistant, lancé sur cette machine, qui démarre le serveur.
                </p>
            </section>

            <section class="mb-8 flex flex-col gap-3" aria-labelledby="mcp-tools">
                <h2 id="mcp-tools" class="text-lg font-semibold text-foreground">Outils disponibles</h2>
                <ul class="flex flex-col gap-2">
                    <li v-for="tool in tools" :key="tool.name" class="rounded-lg border border-border p-4" data-testid="mcp-tool">
                        <code class="text-sm font-semibold text-foreground">{{ tool.name }}</code>
                        <p class="mt-1 text-sm text-foreground">{{ tool.role }}</p>
                    </li>
                </ul>
            </section>

            <section class="mb-8 flex flex-col gap-3" aria-labelledby="mcp-claude">
                <h2 id="mcp-claude" class="text-lg font-semibold text-foreground">Utiliser avec Claude</h2>
                <p class="text-sm text-foreground">
                    <strong>Claude Desktop :</strong> ajoutez ce bloc au fichier de configuration
                    (<em>Paramètres → Développeur → Modifier la configuration</em>, fichier <code>claude_desktop_config.json</code>),
                    puis redémarrez l'application.
                </p>
                <div class="relative">
                    <pre class="overflow-x-auto rounded-lg border border-border bg-surface-alt p-4 text-xs text-foreground" data-testid="mcp-desktop-config"><code>{{ claudeDesktopConfig }}</code></pre>
                    <button
                        type="button"
                        class="absolute right-2 top-2 rounded-md border border-border bg-background px-2 py-1 text-xs text-foreground transition hover:bg-surface focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-primary"
                        data-testid="mcp-copy-desktop"
                        @click="copySnippet('desktop', claudeDesktopConfig)"
                    >
                        {{ copiedKey === 'desktop' ? 'Copié' : 'Copier' }}
                    </button>
                </div>

                <p class="text-sm text-foreground">
                    <strong>Claude Code :</strong> depuis un terminal, lancez cette commande.
                </p>
                <div class="relative">
                    <pre class="overflow-x-auto rounded-lg border border-border bg-surface-alt p-4 pr-20 text-xs text-foreground" data-testid="mcp-code-command"><code>{{ claudeCodeCommand }}</code></pre>
                    <button
                        type="button"
                        class="absolute right-2 top-2 rounded-md border border-border bg-background px-2 py-1 text-xs text-foreground transition hover:bg-surface focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-primary"
                        data-testid="mcp-copy-code"
                        @click="copySnippet('code', claudeCodeCommand)"
                    >
                        {{ copiedKey === 'code' ? 'Copié' : 'Copier' }}
                    </button>
                </div>

                <p class="text-sm text-foreground">
                    Une fois branché, posez une question dont la réponse se trouve dans vos documents :
                    l'assistant appelle lui-même les outils ci-dessus.
                </p>
            </section>

            <section class="mb-8 flex flex-col gap-2" aria-labelledby="mcp-chatgpt">
                <h2 id="mcp-chatgpt" class="text-lg font-semibold text-foreground">ChatGPT</h2>
                <p class="text-sm text-foreground" data-testid="mcp-chatgpt">
                    ChatGPT ne peut pas lancer un serveur local : il exige un serveur distant, accessible par une URL HTTPS.
                    Il n'est donc pas utilisable pour le moment.
                </p>
            </section>

            <section class="flex flex-col gap-2" aria-labelledby="mcp-remote">
                <h2 id="mcp-remote" class="text-lg font-semibold text-foreground">Accès distant</h2>
                <p class="rounded-md border border-dashed border-border px-4 py-2 text-sm text-foreground" data-testid="mcp-remote-soon">
                    Bientôt : c'est depuis cette page que l'on pourra configurer un jeton ou une URL pour rendre le serveur MCP accessible à distance.
                </p>
            </section>
        </div>
    </AppLayout>
</template>
