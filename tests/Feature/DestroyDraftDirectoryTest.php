<?php

use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

beforeEach(function () {
    Storage::fake('local');
});

// --- Suppression inconditionnelle (P3 point 1) ------------------------------

it('deletes an existing draft directory and returns a bare 204', function () {
    $token = Str::uuid()->toString();
    Storage::disk('local')->put("documents/tmp/{$token}/images/photo.jpg", 'fake-image-bytes');

    $response = test()->delete("/documents/create/draft/{$token}");

    $response->assertNoContent();
    Storage::disk('local')->assertMissing("documents/tmp/{$token}");
});

it('is a no-op when the draft directory never existed', function () {
    $token = Str::uuid()->toString();

    $response = test()->delete("/documents/create/draft/{$token}");

    $response->assertNoContent();
});

it('deletes a draft directory holding both images and attachments in one call', function () {
    $token = Str::uuid()->toString();
    Storage::disk('local')->put("documents/tmp/{$token}/images/photo.jpg", 'fake-image-bytes');
    Storage::disk('local')->put("documents/tmp/{$token}/attachments/annexe.pdf", 'fake-pdf-bytes');

    test()->delete("/documents/create/draft/{$token}");

    Storage::disk('local')->assertMissing("documents/tmp/{$token}");
});

it('leaves another draft token\'s directory untouched', function () {
    $token = Str::uuid()->toString();
    $otherToken = Str::uuid()->toString();
    Storage::disk('local')->put("documents/tmp/{$otherToken}/images/photo.jpg", 'fake-image-bytes');

    test()->delete("/documents/create/draft/{$token}");

    Storage::disk('local')->assertExists("documents/tmp/{$otherToken}/images/photo.jpg");
});

// --- Contrainte de route (anti path traversal) ------------------------------

it('never matches the draft destroy route for a non-uuid token', function () {
    $response = test()->delete('/documents/create/draft/not-a-uuid');

    $response->assertNotFound();
});
