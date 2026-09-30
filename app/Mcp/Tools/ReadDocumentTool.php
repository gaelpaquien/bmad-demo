<?php

namespace App\Mcp\Tools;

use App\Enums\ExtractionStatus;
use App\Mcp\DocumentPresenter;
use App\Models\Document;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\JsonSchema\Types\Type;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[Description("Lit le texte d'un document (celui du document, puis celui de chaque pièce jointe). Un long texte est renvoyé par tranches : si `next_offset` n'est pas nul, rappelle l'outil avec cette valeur comme `offset` pour lire la suite.")]
#[Name('read_document')]
#[IsReadOnly]
class ReadDocumentTool extends Tool
{
    public const MAX_CHARACTERS = 20_000;

    public function handle(Request $request): Response|ResponseFactory
    {
        $validated = $request->validate([
            'document_id' => ['required', 'integer', 'min:1'],
            'offset' => ['nullable', 'integer', 'min:0'],
        ], [
            'document_id.required' => "Indique `document_id`, l'identifiant d'un document trouvé avec `search_documents`.",
            'document_id.integer' => '`document_id` est un nombre entier.',
            'offset.min' => '`offset` commence à 0.',
        ]);

        $document = Document::query()->with(['tags:id,name', 'attachments' => fn ($query) => $query->orderBy('id')])->find($validated['document_id']);

        if ($document === null) {
            return Response::error("Aucun document n'a l'identifiant {$validated['document_id']}.");
        }

        $notes = [];
        $sections = [];

        if ($document->extraction_status === ExtractionStatus::Completed && filled($document->extracted_text)) {
            $sections[] = $document->extracted_text;
        } else {
            $notes[] = "Texte du document non disponible (statut d'extraction : {$document->extraction_status->value}).";
        }

        foreach ($document->attachments as $attachment) {
            if ($attachment->extraction_status === ExtractionStatus::Completed && filled($attachment->extracted_text)) {
                $sections[] = "--- Pièce jointe : {$attachment->original_filename} ---\n{$attachment->extracted_text}";
            } else {
                $notes[] = "Texte de la pièce jointe « {$attachment->original_filename} » non disponible (statut d'extraction : {$attachment->extraction_status->value}).";
            }
        }

        $fullText = implode("\n\n", $sections);
        $totalLength = mb_strlen($fullText);
        $offset = $validated['offset'] ?? 0;
        $text = mb_substr($fullText, $offset, self::MAX_CHARACTERS);
        $end = $offset + mb_strlen($text);

        return Response::structured([
            ...DocumentPresenter::summary($document),
            'notes' => $notes,
            'offset' => $offset,
            'total_length' => $totalLength,
            'next_offset' => $end < $totalLength ? $end : null,
            'text' => $text,
        ]);
    }

    /**
     * @return array<string, Type>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'document_id' => $schema->integer()
                ->description('Identifiant du document (champ `id` des résultats de `search_documents`).')
                ->required(),
            'offset' => $schema->integer()
                ->description("Position (en caractères) à partir de laquelle lire ; 0 pour le début, ou la valeur `next_offset` d'une lecture précédente.")
                ->default(0),
        ];
    }
}
