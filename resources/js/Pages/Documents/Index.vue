<script setup>
import DocumentsLayout from '@/Layouts/DocumentsLayout.vue';

// No document is active here: the paginated list lives in DocumentsLayout
// (spec-refonte-layout-documents) and the content area only invites the user
// to pick one. `documents` is read from the shared page props by the layout.
</script>

<template>
    <DocumentsLayout>
        <div class="mx-auto flex min-h-screen w-full items-center justify-center px-6 py-8">
            <p class="text-center text-muted" data-testid="documents-placeholder">
                Sélectionnez un document dans la liste pour le consulter.
            </p>
        </div>
    </DocumentsLayout>
</template>
