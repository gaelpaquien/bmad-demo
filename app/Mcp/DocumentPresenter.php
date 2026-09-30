<?php

namespace App\Mcp;

use App\Enums\DocumentSource;
use App\Models\Document;
use App\Support\DocumentMimeTypes;

/**
 * Shape in which documents are handed to an AI client: metadata and text
 * only, never a disk path (`file_path`) or the original file.
 */
final class DocumentPresenter
{
    public static function typeLabel(Document $document): string
    {
        if ($document->source === DocumentSource::Created) {
            return 'Document créé';
        }

        $key = array_search($document->mime_type, DocumentMimeTypes::TYPE_TO_MIME, true);

        return $key === false ? 'Fichier' : DocumentMimeTypes::TYPE_LABELS[$key];
    }

    /**
     * @return array{id: int, title: string, type: string, tags: array<int, string>, created_at: string|null}
     */
    public static function summary(Document $document): array
    {
        return [
            'id' => $document->id,
            'title' => $document->title,
            'type' => self::typeLabel($document),
            'tags' => $document->tags->pluck('name')->all(),
            'created_at' => $document->created_at?->toIso8601String(),
        ];
    }

    private function __construct() {}
}
