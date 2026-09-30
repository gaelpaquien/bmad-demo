<?php

namespace App\Actions;

use App\DataTransferObjects\UploadEditorImageData;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

/**
 * Sole write point for an image inserted into the editor before its
 * document has an `id` (spec-2-2, AD-14 without a `document_id` yet
 * available). Stores the file under a temporary area keyed by the
 * client-generated draft token — `documents/tmp/{token}/images/{uuid}.ext`
 * on the private disk — mirroring the final `documents/{id}/images/`
 * layout CreateDocumentAction moves it into at save time.
 *
 * The stored filename is always a freshly generated UUID, never the
 * client-supplied one: nothing here is derived from user input, closing
 * off path traversal at the source rather than merely sanitizing it. The
 * extension is likewise never taken from the client-supplied original
 * filename — `extension()` derives it from the file's detected MIME type
 * instead, which UploadEditorImageRequest's validation already guarantees
 * is one of a fixed set of raster image types.
 *
 * Every successful upload also purges stale draft directories left behind
 * by other, abandoned drafts (P3 point 2, deferred-work.md 2026-09-29) —
 * at the fold, with no scheduler involved (Boundaries & Constraints). A
 * purge failure is logged and never allowed to fail this upload.
 */
class UploadEditorImageAction
{
    public function __construct(private PurgeStaleDraftDirectoriesAction $purgeStaleDraftDirectories) {}

    public function __invoke(UploadEditorImageData $data): array
    {
        $extension = $data->image->extension();
        $filename = Str::uuid()->toString().'.'.$extension;
        $directory = "documents/tmp/{$data->draftToken}/images";

        try {
            $path = $data->image->storeAs($directory, $filename, 'local');

            if ($path === false) {
                throw new RuntimeException('Storage::storeAs() returned false for the uploaded editor image.');
            }
        } catch (Throwable $exception) {
            Log::error('Editor image upload failed while storing the file on the private disk.', [
                'draft_token' => $data->draftToken,
                'filename' => $filename,
                'exception' => $exception->getMessage(),
            ]);

            throw $exception;
        }

        try {
            ($this->purgeStaleDraftDirectories)();
        } catch (Throwable $exception) {
            Log::warning('Failed to purge stale draft directories after an editor image upload.', [
                'draft_token' => $data->draftToken,
                'exception' => $exception->getMessage(),
            ]);
        }

        return [
            // Relative, not absolute (`route(..., absolute: false)`) — this
            // is exactly the `src` shape CreateDocumentAction's sanitizer
            // expects to find in `content_html` (its own
            // DRAFT_IMAGE_SRC_PREFIX starts with "/", never a scheme/host),
            // and the prefix Design Notes describes it rewriting at save
            // time.
            'url' => route('documents.editorImages.tmp', [
                'token' => $data->draftToken,
                'filename' => $filename,
            ], absolute: false),
            'alt' => $data->alt,
            'filename' => $filename,
            // Round-tripped back to Editor.vue's flash watcher so it can
            // reject an upload meant for a different draft/tab sharing the
            // same session — see the watcher's own comment for why this is
            // needed even though this action itself never mixes drafts up.
            'draftToken' => $data->draftToken,
        ];
    }
}
