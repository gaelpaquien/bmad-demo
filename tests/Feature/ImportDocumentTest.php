<?php

use App\Actions\SyncDocumentTagsAction;
use App\Enums\DocumentSource;
use App\Enums\ExtractionStatus;
use App\Jobs\ExtractDocumentTextJob;
use App\Models\Document;
use App\Models\DocumentAttachment;
use App\Models\Tag;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

beforeEach(function () {
    Storage::fake('local');
});

function fixtureContents(string $name): string
{
    return file_get_contents(__DIR__.'/../Fixtures/'.$name);
}

it('imports a valid PDF, extracts its text and redirects to the document page', function () {
    $file = UploadedFile::fake()->createWithContent('contract.pdf', fixtureContents('sample.pdf'));

    $response = $this->post('/documents', ['file' => $file]);

    $document = Document::sole();

    $response->assertRedirect("/documents/{$document->id}");
    $response->assertInertiaFlash('toast.message', 'Document importé avec succès.');
    expect($document->source)->toBe(DocumentSource::Imported);
    expect($document->title)->toBe('contract');
    expect($document->file_path)->toBe("documents/{$document->id}/contract.pdf");
    expect($document->extracted_text)->toContain('BMAD Démo sample pdf content');
    expect($document->extraction_status)->toBe(ExtractionStatus::Completed);
    Storage::disk('local')->assertExists($document->file_path);
});

it('uses the submitted title while keeping the original filename on disk', function () {
    $file = UploadedFile::fake()->createWithContent('contract.pdf', fixtureContents('sample.pdf'));

    $this->post('/documents', ['file' => $file, 'title' => 'Contrat fournisseur']);

    $document = Document::sole();

    expect($document->title)->toBe('Contrat fournisseur');
    expect($document->file_path)->toBe("documents/{$document->id}/contract.pdf");
});

it('strips only the last extension from the fallback title', function () {
    $file = UploadedFile::fake()->createWithContent('Rapport.v2.pdf', fixtureContents('sample.pdf'));

    $this->post('/documents', ['file' => $file]);

    expect(Document::sole()->title)->toBe('Rapport.v2');
});

it('rejects a title over 255 characters and creates no document', function () {
    $file = UploadedFile::fake()->createWithContent('contract.pdf', fixtureContents('sample.pdf'));

    $response = $this->post('/documents', ['file' => $file, 'title' => str_repeat('a', 256)]);

    $response->assertSessionHasErrors(['title' => 'Le titre est trop long (255 caractères maximum).']);
    expect(Document::count())->toBe(0);
});

it('redirects to the document page even with no previous URL in session', function () {
    $file = UploadedFile::fake()->createWithContent('contract.pdf', fixtureContents('sample.pdf'));

    $response = $this->post('/documents', ['file' => $file]);

    $response->assertRedirect('/documents/'.Document::sole()->id);
});

it('imports a valid docx and extracts its text via phpword', function () {
    $file = UploadedFile::fake()->createWithContent('report.docx', fixtureContents('sample.docx'));

    $this->post('/documents', ['file' => $file]);

    $document = Document::sole();

    expect($document->source)->toBe(DocumentSource::Imported);
    expect($document->extracted_text)->toContain('BMAD Démo sample docx content');
    Storage::disk('local')->assertExists($document->file_path);
});

it('imports a valid xlsx and extracts its text via phpspreadsheet', function () {
    $file = UploadedFile::fake()->createWithContent('budget.xlsx', fixtureContents('sample.xlsx'));

    $this->post('/documents', ['file' => $file]);

    $document = Document::sole();

    expect($document->source)->toBe(DocumentSource::Imported);
    expect($document->extracted_text)->toContain('BMAD Démo sample xlsx content');
    Storage::disk('local')->assertExists($document->file_path);
});

it('rejects an unsupported format, creates no document, and names the accepted formats', function () {
    $file = UploadedFile::fake()->create('slides.pptx', 10);

    $response = $this->post('/documents', ['file' => $file]);

    $response->assertSessionHasErrors('file');
    expect(session('errors')->first('file'))->toContain('PDF')
        ->toContain('.docx')
        ->toContain('.xlsx');
    expect(Document::count())->toBe(0);
});

it('rejects the request when no file is provided', function () {
    $response = $this->post('/documents', []);

    $response->assertSessionHasErrors('file');
    expect(Document::count())->toBe(0);
});

it('rejects a file over the 20MB limit and creates no document', function () {
    $file = UploadedFile::fake()->create('big.pdf', 20 * 1024 + 1);

    $response = $this->post('/documents', ['file' => $file]);

    $response->assertSessionHasErrors('file');
    expect(Document::count())->toBe(0);
});

it('extracts text based on the real file content even when the filename extension does not match', function () {
    // Laravel's fake UploadedFile reports its MIME type from the filename
    // extension by default (unlike a real upload, which detects it from
    // the actual bytes via fileinfo) — override it explicitly here to
    // simulate a real docx file whose client-supplied name doesn't match.
    $file = UploadedFile::fake()
        ->createWithContent('mystery-file.bin', fixtureContents('sample.docx'))
        ->mimeType('application/vnd.openxmlformats-officedocument.wordprocessingml.document');

    $response = $this->post('/documents', ['file' => $file]);

    $document = Document::sole();

    $response->assertRedirect("/documents/{$document->id}");
    expect($document->extracted_text)->toContain('BMAD Démo sample docx content');
    Storage::disk('local')->assertExists($document->file_path);
});

it('keeps the import when text extraction fails on an otherwise valid, unreadable file', function () {
    $file = UploadedFile::fake()->createWithContent('scanned.pdf', fixtureContents('sample-corrupt.pdf'));

    $response = $this->post('/documents', ['file' => $file]);

    $document = Document::sole();

    $response->assertRedirect("/documents/{$document->id}");
    expect($document->source)->toBe(DocumentSource::Imported);
    expect($document->extracted_text)->toBeNull();
    expect($document->extraction_status)->toBe(ExtractionStatus::Failed);
    Storage::disk('local')->assertExists($document->file_path);
});

it('renders the document detail page with title, type and date after import', function () {
    $file = UploadedFile::fake()->createWithContent('contract.pdf', fixtureContents('sample.pdf'));

    $this->post('/documents', ['file' => $file]);

    $document = Document::sole();

    $response = $this->get("/documents/{$document->id}");

    $response->assertInertia(fn ($page) => $page
        ->component('Documents/Show')
        ->where('document.id', $document->id)
        ->where('document.title', 'contract')
        ->where('document.source', 'imported')
    );
});

it('renders the library index page at GET /', function () {
    $response = $this->get('/');

    $response->assertInertia(fn ($page) => $page->component('Documents/Index'));
});

// --- Page d'import dédiée (spec-import-document-page) -----------------------

it('renders the dedicated import page at GET /documents/import', function () {
    $response = $this->get('/documents/import');

    $response->assertInertia(fn ($page) => $page->component('Documents/Import'));
});

it('returns immediately with the document pending extraction, dispatching the job instead of running it inline', function () {
    Queue::fake();

    $file = UploadedFile::fake()->createWithContent('contract.pdf', fixtureContents('sample.pdf'));

    $response = $this->post('/documents', ['file' => $file]);

    $document = Document::sole();

    $response->assertRedirect("/documents/{$document->id}");
    expect($document->extraction_status)->toBe(ExtractionStatus::Pending);
    expect($document->extracted_text)->toBeNull();
    Storage::disk('local')->assertExists($document->file_path);

    Queue::assertPushed(ExtractDocumentTextJob::class, fn ($job) => $job->target->is($document));
});

it('lists documents still pending or processing extraction in the shared pendingExtractions prop', function () {
    Queue::fake();

    $file = UploadedFile::fake()->createWithContent('contract.pdf', fixtureContents('sample.pdf'));
    $this->post('/documents', ['file' => $file]);

    $pending = Document::sole();

    $response = $this->get('/');

    $response->assertInertia(fn ($page) => $page
        ->has('pendingExtractions', 1)
        ->where('pendingExtractions.0.id', $pending->id)
        ->where('pendingExtractions.0.extraction_status', 'pending')
    );
});

it('lists attachments still pending or processing extraction in the shared pendingExtractions prop, with their document title', function () {
    $document = Document::factory()->create(['extraction_status' => ExtractionStatus::Completed]);
    $pending = DocumentAttachment::factory()->for($document)->create(['extraction_status' => ExtractionStatus::Processing]);
    DocumentAttachment::factory()->for($document)->create(['extraction_status' => ExtractionStatus::Completed]);

    $response = $this->get('/');

    $response->assertInertia(fn ($page) => $page
        ->has('pendingExtractions', 1)
        ->where('pendingExtractions.0.type', 'attachment')
        ->where('pendingExtractions.0.id', $pending->id)
        ->where('pendingExtractions.0.title', $pending->original_filename)
        ->where('pendingExtractions.0.document_title', $document->title)
        ->where('pendingExtractions.0.extraction_status', 'processing')
    );
});

it('excludes completed or failed documents from the shared pendingExtractions prop', function () {
    $file = UploadedFile::fake()->createWithContent('contract.pdf', fixtureContents('sample.pdf'));
    $this->post('/documents', ['file' => $file]);

    $response = $this->get('/');

    $response->assertInertia(fn ($page) => $page->has('pendingExtractions', 0));
});

it('lists previously imported documents on the index page', function () {
    $file = UploadedFile::fake()->createWithContent('contract.pdf', fixtureContents('sample.pdf'));
    $this->post('/documents', ['file' => $file]);

    $document = Document::sole();

    $response = $this->get('/');

    $response->assertInertia(fn ($page) => $page
        ->component('Documents/Index')
        ->has('documents.data', 1)
        ->where('documents.data.0.id', $document->id)
        ->where('documents.data.0.title', 'contract')
    );
});

// --- Formulaire unique (spec-refonte-import-formulaire-unique) ---------------

it('imports a file with its tags and a draft attachment in one request, dispatching one extraction job each', function () {
    Queue::fake();

    $tags = Tag::factory()->count(2)->create();
    $draftToken = Str::uuid()->toString();

    $this->post('/documents/create/attachments', [
        'draft_token' => $draftToken,
        'file' => UploadedFile::fake()->createWithContent('annexe.pdf', fixtureContents('sample.pdf')),
    ]);
    $uploadedAttachment = session('uploadedAttachment');

    $response = $this->post('/documents', [
        'file' => UploadedFile::fake()->createWithContent('contract.pdf', fixtureContents('sample.pdf')),
        'tag_ids' => $tags->pluck('id')->all(),
        'draft_token' => $draftToken,
        'draft_attachments' => [[
            'filename' => $uploadedAttachment['filename'],
            'original_filename' => $uploadedAttachment['original_filename'],
        ]],
    ]);

    $document = Document::sole();
    $attachment = DocumentAttachment::sole();

    $response->assertRedirect("/documents/{$document->id}");
    expect($document->tags->pluck('id')->sort()->values()->all())->toBe($tags->pluck('id')->sort()->values()->all());
    expect($attachment->document_id)->toBe($document->id);
    expect($attachment->original_filename)->toBe('annexe.pdf');
    expect($attachment->file_path)->toBe("documents/{$document->id}/attachments/{$uploadedAttachment['filename']}");
    Storage::disk('local')->assertExists($attachment->file_path);
    Storage::disk('local')->assertMissing("documents/tmp/{$draftToken}/attachments/{$uploadedAttachment['filename']}");

    Queue::assertPushed(ExtractDocumentTextJob::class, 2);
    Queue::assertPushed(ExtractDocumentTextJob::class, fn ($job) => $job->target->is($document));
    Queue::assertPushed(ExtractDocumentTextJob::class, fn ($job) => $job->target->is($attachment));
});

it('rejects a draft attachment filename that is not a server-generated uuid, creating no document', function () {
    $response = $this->post('/documents', [
        'file' => UploadedFile::fake()->createWithContent('contract.pdf', fixtureContents('sample.pdf')),
        'draft_token' => Str::uuid()->toString(),
        'draft_attachments' => [[
            'filename' => '../../contract.pdf',
            'original_filename' => 'contract.pdf',
        ]],
    ]);

    $response->assertSessionHasErrors('draft_attachments.0.filename');
    expect(Document::count())->toBe(0);
    expect(DocumentAttachment::count())->toBe(0);
});

it('rejects more than 10 draft attachments, creating no document', function () {
    $response = $this->post('/documents', [
        'file' => UploadedFile::fake()->createWithContent('contract.pdf', fixtureContents('sample.pdf')),
        'draft_token' => Str::uuid()->toString(),
        'draft_attachments' => array_map(fn (int $index) => [
            'filename' => Str::uuid()->toString().'.pdf',
            'original_filename' => "annexe-{$index}.pdf",
        ], range(1, DocumentAttachment::MAX_PER_DOCUMENT + 1)),
    ]);

    $response->assertSessionHasErrors(['draft_attachments' => '10 pièces jointes maximum par document.']);
    expect(Document::count())->toBe(0);
    expect(DocumentAttachment::count())->toBe(0);
});

it('rejects a main file PHP itself refused (over upload_max_filesize) with the French size message', function () {
    $file = new UploadedFile(__DIR__.'/../Fixtures/sample.pdf', 'big.pdf', 'application/pdf', UPLOAD_ERR_INI_SIZE, true);

    $response = $this->post('/documents', ['file' => $file]);

    $response->assertRedirect();
    $response->assertInertiaFlashMissing('toast');
    expect(sessionErrorMessage('file'))->toStartWith('Fichier trop volumineux (20 Mo maximum).');
    expect(Document::count())->toBe(0);
});

it('redirects back with the French size message, never a 500, when the import body exceeds post_max_size', function () {
    $response = $this
        ->from('/documents/import')
        ->withServerVariables(['CONTENT_LENGTH' => (string) (1024 ** 3)])
        ->post('/documents');

    $response->assertRedirect('/documents/import');
    expect(sessionErrorMessage('file'))->toStartWith('Fichier trop volumineux (20 Mo maximum).');
    expect(Document::count())->toBe(0);
});

it('rejects draft attachments sent without a draft token, creating no document', function () {
    $response = $this->post('/documents', [
        'file' => UploadedFile::fake()->createWithContent('contract.pdf', fixtureContents('sample.pdf')),
        'draft_attachments' => [[
            'filename' => Str::uuid()->toString().'.pdf',
            'original_filename' => 'annexe.pdf',
        ]],
    ]);

    $response->assertSessionHasErrors('draft_token');
    expect(Document::count())->toBe(0);
});

it('rolls back the whole import and leaves no document directory when relocating a draft attachment fails', function () {
    Queue::fake();

    $draftToken = Str::uuid()->toString();

    $this->post('/documents/create/attachments', [
        'draft_token' => $draftToken,
        'file' => UploadedFile::fake()->createWithContent('annexe.pdf', fixtureContents('sample.pdf')),
    ]);
    $uploadedAttachment = session('uploadedAttachment');

    $failingDisk = Mockery::mock(Storage::disk('local'))->makePartial();
    $failingDisk->shouldReceive('move')->andReturn(false);
    Storage::set('local', $failingDisk);

    $this->withoutExceptionHandling();

    expect(fn () => $this->post('/documents', [
        'file' => UploadedFile::fake()->createWithContent('contract.pdf', fixtureContents('sample.pdf')),
        'draft_token' => $draftToken,
        'draft_attachments' => [[
            'filename' => $uploadedAttachment['filename'],
            'original_filename' => $uploadedAttachment['original_filename'],
        ]],
    ]))->toThrow(RuntimeException::class);

    expect(Document::count())->toBe(0);
    expect(DocumentAttachment::count())->toBe(0);
    expect(collect(Storage::disk('local')->allDirectories('documents'))
        ->reject(fn (string $directory) => str_starts_with($directory, 'documents/tmp'))
        ->all())->toBe([]);
    Storage::disk('local')->assertExists("documents/tmp/{$draftToken}/attachments/{$uploadedAttachment['filename']}");
    Queue::assertNothingPushed();
});

it('rolls back the import and leaves no document directory when SyncDocumentTagsAction fails after the file was already stored', function () {
    $this->mock(SyncDocumentTagsAction::class, function ($mock) {
        $mock->shouldReceive('__invoke')->andThrow(new RuntimeException('Forced SyncDocumentTagsAction failure.'));
    });

    $this->withoutExceptionHandling();

    expect(fn () => $this->post('/documents', [
        'file' => UploadedFile::fake()->createWithContent('contract.pdf', fixtureContents('sample.pdf')),
        'tag_ids' => [],
    ]))->toThrow(RuntimeException::class);

    expect(Document::count())->toBe(0);
    expect(collect(Storage::disk('local')->allDirectories('documents'))
        ->reject(fn (string $directory) => str_starts_with($directory, 'documents/tmp'))
        ->all())->toBe([]);
});
