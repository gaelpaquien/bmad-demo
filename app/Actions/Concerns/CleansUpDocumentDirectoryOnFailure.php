<?php

namespace App\Actions\Concerns;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Throwable;

/**
 * Shared "wipe a document's own directory, then rethrow" failure cleanup
 * behind CreateDocumentAction and DocumentController::store()/
 * storeCreated() (spec-nettoyage-fichiers-orphelins-tmp) — extracted rather
 * than left duplicated three times (code review finding), same rationale as
 * RelocatesDraftAttachments/SanitizesDocumentContent already being shared
 * instead of copy-pasted. A failure after a successful disk relocation
 * (draft images/attachments moved by CreateDocumentAction/
 * ImportDocumentAction, or a SyncDocumentTagsAction failure after either)
 * must not leave those files behind once the enclosing transaction rolls
 * the `Document` row back.
 *
 * The `deleteDirectory()` call itself is best-effort: a failure there (disk
 * error, permissions) is logged and swallowed rather than replacing the
 * exception being cleaned up for (code review finding) — the caller must
 * always see the real failure that triggered this cleanup, never a masking
 * storage error.
 */
trait CleansUpDocumentDirectoryOnFailure
{
    private function cleanUpDocumentDirectoryOnFailure(int $documentId, Throwable $exception): never
    {
        try {
            Storage::disk('local')->deleteDirectory("documents/{$documentId}");
        } catch (Throwable $cleanupException) {
            Log::warning('Failed to clean up a document directory after a save failure.', [
                'document_id' => $documentId,
                'exception' => $cleanupException->getMessage(),
            ]);
        }

        throw $exception;
    }
}
