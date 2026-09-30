<?php

use App\Enums\DocumentSource;
use App\Enums\ExtractionStatus;
use App\Mcp\Servers\KnowledgeBaseServer;
use App\Mcp\Tools\ReadDocumentTool;
use App\Models\Document;
use App\Models\DocumentAttachment;
use App\Models\Tag;

function readDocumentTool(array $arguments)
{
    return KnowledgeBaseServer::tool(ReadDocumentTool::class, $arguments);
}

it('reads a document with its metadata, its text and its attachments text', function () {
    $tag = Tag::factory()->create(['name' => 'Contrats']);
    $document = Document::factory()->create([
        'title' => 'Contrat cadre',
        'source' => DocumentSource::Created,
        'mime_type' => null,
        'extracted_text' => 'Texte principal du contrat.',
    ]);
    $document->tags()->attach($tag);
    DocumentAttachment::factory()->for($document)->create([
        'original_filename' => 'annexe.pdf',
        'extracted_text' => 'Contenu de l\'annexe.',
    ]);

    readDocumentTool(['document_id' => $document->id])
        ->assertOk()
        ->assertStructuredContent(fn ($json) => $json
            ->where('id', $document->id)
            ->where('title', 'Contrat cadre')
            ->where('type', 'Document créé')
            ->where('tags', ['Contrats'])
            ->where('notes', [])
            ->where('next_offset', null)
            ->where('text', "Texte principal du contrat.\n\n--- Pièce jointe : annexe.pdf ---\nContenu de l'annexe.")
            ->etc()
        );
});

it('serves a long text in slices that follow each other without loss or overlap', function () {
    $text = 'DEBUT'.str_repeat('x', 30_000).'FIN';
    $document = Document::factory()->create(['extracted_text' => $text]);

    readDocumentTool(['document_id' => $document->id])->assertStructuredContent(fn ($json) => $json
        ->where('total_length', mb_strlen($text))
        ->where('next_offset', ReadDocumentTool::MAX_CHARACTERS)
        ->where('text', fn ($slice) => mb_strlen($slice) === ReadDocumentTool::MAX_CHARACTERS && str_starts_with($slice, 'DEBUT'))
        ->etc()
    );

    readDocumentTool(['document_id' => $document->id, 'offset' => ReadDocumentTool::MAX_CHARACTERS])->assertStructuredContent(fn ($json) => $json
        ->where('offset', ReadDocumentTool::MAX_CHARACTERS)
        ->where('next_offset', null)
        ->where('text', mb_substr($text, ReadDocumentTool::MAX_CHARACTERS))
        ->etc()
    );
});

it('says so when the text of the document or of an attachment is not available', function () {
    $document = Document::factory()->create([
        'extracted_text' => null,
        'extraction_status' => ExtractionStatus::Pending,
    ]);
    DocumentAttachment::factory()->for($document)->create([
        'original_filename' => 'scan.pdf',
        'extracted_text' => null,
        'extraction_status' => ExtractionStatus::Failed,
    ]);

    readDocumentTool(['document_id' => $document->id])->assertStructuredContent(fn ($json) => $json
        ->where('text', '')
        ->where('next_offset', null)
        ->where('notes', [
            "Texte du document non disponible (statut d'extraction : pending).",
            "Texte de la pièce jointe « scan.pdf » non disponible (statut d'extraction : failed).",
        ])
        ->etc()
    );
});

it('answers with a clear error for an unknown document', function () {
    readDocumentTool(['document_id' => 999])->assertHasErrors(["Aucun document n'a l'identifiant 999."]);
});

it('requires a document id and a non-negative offset', function () {
    readDocumentTool([])->assertHasErrors();
    readDocumentTool(['document_id' => 1, 'offset' => -1])->assertHasErrors(['`offset` commence à 0.']);
});

it('never exposes the path of the original file', function () {
    $document = Document::factory()->create(['file_path' => 'documents/secret/dossier/original.pdf']);

    readDocumentTool(['document_id' => $document->id])->assertDontSee('documents/secret');
});
