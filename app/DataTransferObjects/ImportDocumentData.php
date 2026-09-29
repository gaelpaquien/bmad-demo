<?php

namespace App\DataTransferObjects;

use Illuminate\Http\UploadedFile;

final readonly class ImportDocumentData
{
    public function __construct(
        public UploadedFile $file,
        // The title chosen on the import page — null falls back to the
        // file's original name.
        public ?string $title = null,
        // The client-generated draft token the import page's attachments
        // were temporarily stored under (spec-refonte-import-formulaire-unique)
        // — null when no attachment was ever added.
        public ?string $draftToken = null,
        // The draft attachments to keep, same shape as
        // CreateDocumentData::$draftAttachments: the server-generated
        // `filename` (the tmp file to relocate) and the client-supplied
        // `original_filename` (display name only, never trusted for the
        // stored path). Empty when no attachment was ever added.
        //
        // @var list<array{filename: string, original_filename: string}>
        public array $draftAttachments = [],
    ) {}
}
