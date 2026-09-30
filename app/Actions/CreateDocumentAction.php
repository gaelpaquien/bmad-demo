<?php

namespace App\Actions;

use App\Actions\Concerns\CleansUpDocumentDirectoryOnFailure;
use App\Actions\Concerns\RelocatesDraftAttachments;
use App\Actions\Concerns\SanitizesDocumentContent;
use App\DataTransferObjects\CreateDocumentData;
use App\Enums\DocumentSource;
use App\Enums\ExtractionStatus;
use App\Models\Document;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Sole write point for documents authored directly in the editor (Boundaries
 * & Constraints, spec-2-1). Unlike ImportDocumentAction there is no source
 * file to store on disk and no deferred extraction job to dispatch:
 * `content_html` is the document's content, so `extracted_text` is derived
 * from it synchronously, right here, and marked complete immediately
 * (AD-9) — never left pending/null the way an imported document's can be
 * while its background job runs (or fails).
 *
 * `file_path`/`mime_type` are deliberately left null — a created document
 * has no original file, its content lives in `content_html` instead.
 *
 * An inline `<img>` (spec-2-2) is the one exception to "no file at all":
 * it was already uploaded to a temporary, draft-token-keyed area by
 * UploadEditorImageAction before this document had an `id` (AD-14 without
 * a `document_id` yet available). Closing the draft therefore means two
 * things happen together — the `Document` row is created, and that
 * temporary area is moved into the document's own `documents/{id}/images/`
 * home with `content_html` rewritten to match — wrapped in one
 * `DB::transaction()` (Boundaries & Constraints, spec-2-2) so a move
 * failure rolls back the row too, never a document left pointing at a
 * broken image.
 *
 * The sanitization/derivation/relocation steps themselves live in
 * SanitizesDocumentContent (spec-2-3), shared unchanged with
 * UpdateDocumentAction — here they're always invoked with a single allowed
 * `src` prefix (this draft's own tmp directory), since a document being
 * created has no final directory of its own yet.
 *
 * A draft attachment (spec-3-3, FR13) follows the same "relocate at save
 * time" shape as an inline image, but is never referenced anywhere in
 * `content_html` — the client instead tells this action exactly which draft
 * attachments to keep via `$data->draftAttachments` (Design Notes,
 * spec-3-3). The `DocumentAttachment` rows created by
 * relocateDraftAttachments() (RelocatesDraftAttachments, shared with
 * ImportDocumentAction) are attached to the returned Document as its
 * `attachments` relation so the controller can dispatch one
 * ExtractDocumentTextJob per row after the transaction commits — mirroring
 * how ImportDocumentAction's own caller dispatches its job only once the
 * row is safely persisted.
 *
 * Everything from the first relocation attempt onward (draft images, the
 * conditional `content_html` rewrite, draft attachments) is wrapped in a
 * `try/catch` that hands off to CleansUpDocumentDirectoryOnFailure — shared
 * with DocumentController::store()/storeCreated() — which deletes the whole
 * `documents/{id}` directory before rethrowing (spec-nettoyage-fichiers-
 * orphelins-tmp). A brand-new document's directory never holds anything but
 * what this very call just moved into it, so wiping it whole on any failure
 * is always safe, unlike UpdateDocumentAction/relocateDraftImages()'s own
 * narrower cleanup, which must leave a document's pre-existing images
 * untouched.
 */
class CreateDocumentAction
{
    use CleansUpDocumentDirectoryOnFailure;
    use RelocatesDraftAttachments;
    use SanitizesDocumentContent;

    public function __invoke(CreateDocumentData $data): Document
    {
        $allowedImageSrcPrefixes = $data->draftToken !== null
            ? [self::DRAFT_IMAGE_SRC_PREFIX.$data->draftToken.'/']
            : [];

        $contentHtml = $this->sanitizeContentHtml($data->contentHtml, $allowedImageSrcPrefixes);

        return DB::transaction(function () use ($data, $contentHtml) {
            $document = Document::create([
                'title' => $data->title,
                'source' => DocumentSource::Created,
                'content_html' => $contentHtml,
                'extracted_text' => $this->deriveExtractedText($contentHtml),
                'extraction_status' => ExtractionStatus::Completed,
            ]);

            try {
                $finalContentHtml = $this->relocateDraftImages($data->draftToken, $document, $contentHtml);

                if ($finalContentHtml !== $contentHtml) {
                    $document->forceFill(['content_html' => $finalContentHtml])->save();
                }

                $createdAttachments = $this->relocateDraftAttachments($data->draftToken, $document, $data->draftAttachments);
            } catch (Throwable $exception) {
                // The row rolls back with the transaction; anything already
                // moved into this brand-new document's own directory —
                // relocated images, relocated attachments — must not outlive
                // it on disk.
                $this->cleanUpDocumentDirectoryOnFailure($document->id, $exception);
            }

            $document->setRelation('attachments', $createdAttachments);

            return $document;
        });
    }
}
