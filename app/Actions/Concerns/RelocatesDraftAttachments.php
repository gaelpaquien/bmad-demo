<?php

namespace App\Actions\Concerns;

use App\Enums\ExtractionStatus;
use App\Models\Document;
use App\Models\DocumentAttachment;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Throwable;

/**
 * Draft-attachment relocation shared by CreateDocumentAction (spec-3-3) and
 * ImportDocumentAction (spec-refonte-import-formulaire-unique) — extracted
 * verbatim from CreateDocumentAction so the "move each kept draft attachment
 * into the document's own directory and create its row" step never drifts
 * between the editor's and the import page's save paths.
 */
trait RelocatesDraftAttachments
{
    /**
     * No-op (empty collection) when the draft never had a token, or no
     * attachment was ever kept — nothing to relocate. Otherwise moves each
     * kept draft attachment from `documents/tmp/{token}/attachments/` to
     * `documents/{id}/attachments/` and creates its `DocumentAttachment` row
     * (`pending`). Driven solely by the client-supplied kept list: an
     * attachment is never referenced from any document content, so the
     * server cannot infer which draft files are still wanted.
     *
     * `mime_type` is never trusted from the client — redetected from the
     * moved file itself via `Storage::mimeType()` (Boundaries & Constraints:
     * "ne pas faire confiance au mime_type client pour la relocalisation
     * brouillon"). A kept filename no longer present under the draft's tmp
     * directory (already relocated, or never actually uploaded) is silently
     * skipped rather than failing the whole save.
     *
     * A move failure cleans up only the files this call itself already
     * relocated before rethrowing so the enclosing transaction rolls back —
     * the already-created `DocumentAttachment` rows roll back with it, same
     * transaction (Acceptance Criteria: "Échec relocalisation → rollback").
     *
     * @param  list<array{filename: string, original_filename: string}>  $keptDraftAttachments
     * @return Collection<int, DocumentAttachment>
     */
    private function relocateDraftAttachments(?string $draftToken, Document $document, array $keptDraftAttachments): Collection
    {
        $created = collect();

        if ($draftToken === null || $keptDraftAttachments === []) {
            return $created;
        }

        $disk = Storage::disk('local');
        $sourceDirectory = "documents/tmp/{$draftToken}/attachments";
        $destinationDirectory = "documents/{$document->id}/attachments";
        $movedDestinationPaths = [];

        try {
            foreach ($keptDraftAttachments as $draftAttachment) {
                $filename = $draftAttachment['filename'];
                $sourcePath = "{$sourceDirectory}/{$filename}";

                if (! $disk->exists($sourcePath)) {
                    continue;
                }

                $destinationPath = "{$destinationDirectory}/{$filename}";

                if (! $disk->move($sourcePath, $destinationPath)) {
                    throw new RuntimeException('Storage::move() returned false while relocating a draft attachment.');
                }

                $movedDestinationPaths[] = $destinationPath;

                $mimeType = $disk->mimeType($destinationPath);

                // `mimeType()` returns `false` when it can't be determined
                // (code review finding) — never insert that into the
                // non-nullable `mime_type` column; treated the same as a
                // move failure, rolling back the whole transaction.
                if ($mimeType === false) {
                    throw new RuntimeException('Storage::mimeType() returned false while relocating a draft attachment.');
                }

                $created->push(DocumentAttachment::create([
                    'document_id' => $document->id,
                    'file_path' => $destinationPath,
                    'original_filename' => $draftAttachment['original_filename'],
                    'mime_type' => $mimeType,
                    'extraction_status' => ExtractionStatus::Pending,
                ]));
            }
        } catch (Throwable $exception) {
            foreach ($movedDestinationPaths as $movedDestinationPath) {
                $disk->delete($movedDestinationPath);
            }

            Log::error('Document save failed while relocating draft attachments to their final directory.', [
                'draft_token' => $draftToken,
                'document_id' => $document->id,
                'exception' => $exception->getMessage(),
            ]);

            throw $exception;
        }

        // The draft's attachments directory is only reclaimed once nothing
        // is left behind in it. Anything else under tmp/{token} (e.g. the
        // editor's draft images, and tmp/{token} itself) is left for its own
        // cleanup path — the already-accepted deferred-cleanup gap.
        if ($disk->allFiles($sourceDirectory) === []) {
            $disk->deleteDirectory($sourceDirectory);
        }

        return $created;
    }
}
