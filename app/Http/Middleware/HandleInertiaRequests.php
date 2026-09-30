<?php

namespace App\Http\Middleware;

use App\Enums\ExtractionStatus;
use App\Models\Document;
use App\Models\DocumentAttachment;
use App\Models\Tag;
use App\Support\DocumentMimeTypes;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that's loaded on the first page visit.
     *
     * @see https://inertiajs.com/server-side-setup#root-template
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determines the current asset version.
     *
     * @see https://inertiajs.com/asset-versioning
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @see https://inertiajs.com/shared-data
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        return [
            ...parent::share($request),
            // Powers the extraction-tasks panel (App/Components/ExtractionTasksPanel.vue):
            // documents and attachments whose text extraction is still
            // queued or running (AD-6).
            'pendingExtractions' => fn () => $this->pendingExtractions(),
            // Powers TagSelector.vue everywhere it's mounted (Import modal,
            // editor, Document Detail, Library filter) — the full list of
            // already-existing tags it's allowed to offer (Boundaries &
            // Constraints, spec-3-1: no free-text creation there). Tag
            // management itself is story 3.5 (TagController).
            //
            // Sorted in PHP via `Str::lower()` rather than `orderBy('name')`
            // (retrospective Epic 3, action item 10) — same rationale as the
            // duplicate check in CreateTagRequest: SQLite's `LOWER()` only
            // folds ASCII. Must stay in lockstep with TagController::index()'s
            // own sort — same mechanism in both, no divergence, including the
            // `orderBy('id')`/`SORT_STRING` determinism fix (see that
            // method's comment for the full rationale).
            'tags' => fn () => Tag::query()
                ->orderBy('id')
                ->get(['id', 'name'])
                ->sortBy(fn (Tag $tag) => Str::lower($tag->name), SORT_STRING)
                ->values(),
            // Powers Index.vue's type filter (`TYPE_OPTIONS`, pdf/word/excel
            // entries only) — same pattern as `tags` above. Derived
            // from DocumentMimeTypes::TYPE_TO_MIME/TYPE_LABELS, the single
            // source of truth also used server-side by DocumentController
            // (Epic 1/2 retrospectives, action item 3). `created` is
            // deliberately absent here too: it stays a client-only entry in
            // Index.vue (not tied to a mime type).
            'documentTypeOptions' => fn () => collect(DocumentMimeTypes::TYPE_TO_MIME)
                ->keys()
                ->map(fn (string $type) => [
                    'value' => $type,
                    'label' => DocumentMimeTypes::TYPE_LABELS[$type] ?? $type,
                ])
                ->values(),
            // Sole channel back from the image-upload endpoint to the
            // editor (AD-13, spec-2-2: `return back()`, never
            // `response()->json()`) — Laravel's own flash bag ages this
            // out automatically after the one request that follows the
            // redirect, so no manual cleanup is needed here.
            //
            // `uploadedAttachment` mirrors it exactly for a draft attachment
            // upload (AD-13, spec-3-3: DocumentController::storeEditorAttachment()).
            //
            // Success toasts do not travel here: they use Inertia's native
            // flash data (App\Support\Toast), read client-side by app.js.
            'flash' => fn () => [
                'uploadedImage' => session('uploadedImage'),
                'uploadedAttachment' => session('uploadedAttachment'),
            ],
        ];
    }

    /**
     * Documents and attachments whose extraction is still queued or
     * running, oldest first. An attachment carries its parent's title so the
     * panel can say which document its text will be searchable under
     * (`documents.attachments_extracted_text`).
     *
     * @return list<array{type: 'document'|'attachment', id: int, title: string, document_title: ?string, extraction_status: string}>
     */
    private function pendingExtractions(): array
    {
        $inProgressStatuses = [ExtractionStatus::Pending, ExtractionStatus::Processing];

        $documents = Document::query()
            ->whereIn('extraction_status', $inProgressStatuses)
            ->get(['id', 'title', 'extraction_status', 'created_at'])
            ->map(fn (Document $document) => [
                'type' => 'document',
                'id' => $document->id,
                'title' => $document->title,
                'document_title' => null,
                'extraction_status' => $document->extraction_status->value,
                'queued_at' => $document->created_at?->getTimestamp() ?? 0,
            ]);

        $attachments = DocumentAttachment::query()
            ->with('document:id,title')
            ->whereIn('extraction_status', $inProgressStatuses)
            ->get(['id', 'document_id', 'original_filename', 'extraction_status', 'created_at'])
            ->map(fn (DocumentAttachment $attachment) => [
                'type' => 'attachment',
                'id' => $attachment->id,
                'title' => $attachment->original_filename,
                'document_title' => $attachment->document?->title,
                'extraction_status' => $attachment->extraction_status->value,
                'queued_at' => $attachment->created_at?->getTimestamp() ?? 0,
            ]);

        return $documents
            ->concat($attachments)
            ->sortBy('queued_at')
            ->map(fn (array $task) => Arr::except($task, 'queued_at'))
            ->values()
            ->all();
    }
}
