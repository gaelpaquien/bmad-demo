<?php

namespace App\Actions;

use App\Actions\Concerns\RelocatesDraftAttachments;
use App\DataTransferObjects\ImportDocumentData;
use App\Enums\DocumentSource;
use App\Enums\ExtractionStatus;
use App\Jobs\ExtractDocumentTextJob;
use App\Models\Document;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Throwable;

/**
 * Sole write point for imported documents (AD-2, AD-7, AD-9).
 *
 * Stores the original file on the private disk and persists the Document
 * row within the same synchronous request (AD-6), then dispatches text
 * extraction as a queued job instead of running it inline: a large or
 * complex real-world document can take longer to parse than any HTTP
 * request/proxy timeout should reasonably allow. The import itself
 * returns as soon as the file is safely stored — extraction happening
 * later, or failing entirely, can never undo it (AD-9).
 *
 * A storage failure, in contrast, is not tolerated silently: Document
 * creation + file write are wrapped in a transaction so no orphaned or
 * corrupt row is ever left behind.
 *
 * Draft attachments added on the import page before the document existed
 * (spec-refonte-import-formulaire-unique) are relocated inside that same
 * transaction via RelocatesDraftAttachments — shared verbatim with
 * CreateDocumentAction — and exposed as the returned Document's
 * `attachments` relation so the controller can dispatch one
 * ExtractDocumentTextJob per row once the transaction commits.
 */
class ImportDocumentAction
{
    use RelocatesDraftAttachments;

    public function __invoke(ImportDocumentData $data): Document
    {
        $document = DB::transaction(function () use ($data) {
            $document = Document::create([
                'title' => $data->title ?? $this->titleFromFilename($data->file->getClientOriginalName()),
                'source' => DocumentSource::Imported,
                'mime_type' => $data->file->getMimeType(),
                'extraction_status' => ExtractionStatus::Pending,
            ]);

            $document->forceFill([
                'file_path' => $this->storeFile($data, $document),
            ])->save();

            try {
                $createdAttachments = $this->relocateDraftAttachments($data->draftToken, $document, $data->draftAttachments);
            } catch (Throwable $exception) {
                // The row rolls back with the transaction; the original file
                // already stored above must not outlive it on disk.
                Storage::disk('local')->deleteDirectory("documents/{$document->id}");

                throw $exception;
            }

            $document->setRelation('attachments', $createdAttachments);

            return $document;
        });

        ExtractDocumentTextJob::dispatch($document);

        return $document;
    }

    /**
     * Fallback title when none was submitted, mirroring the import page's
     * `titleFromFilename()`: "Rapport.v2.pdf" → "Rapport.v2", a name that is
     * only an extension (".pdf") is kept as-is, and the result is cut by
     * character to the 255-character title limit.
     */
    private function titleFromFilename(string $filename): string
    {
        $title = preg_replace('/\.[^.]+$/', '', $filename);

        return mb_substr($title === '' ? $filename : $title, 0, 255);
    }

    /**
     * Stores the original file on the private disk. Any failure — a thrown
     * exception, or `storeAs()` returning `false` (the `local` disk is
     * configured with `throw => false`) — cleans up the destination
     * directory, logs a clear error, and rethrows so the enclosing
     * transaction rolls back the just-created Document row.
     */
    private function storeFile(ImportDocumentData $data, Document $document): string
    {
        $filename = basename($data->file->getClientOriginalName());
        $directory = "documents/{$document->id}";

        try {
            $path = $data->file->storeAs($directory, $filename, 'local');

            if ($path === false) {
                throw new RuntimeException('Storage::storeAs() returned false for the uploaded file.');
            }

            return $path;
        } catch (Throwable $exception) {
            Storage::disk('local')->deleteDirectory($directory);

            Log::error('Document import failed while storing the original file on the private disk.', [
                'document_id' => $document->id,
                'filename' => $filename,
                'exception' => $exception->getMessage(),
            ]);

            throw $exception;
        }
    }
}
