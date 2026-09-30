<?php

namespace App\Actions;

use App\DataTransferObjects\DeleteDraftDirectoryData;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Throwable;

/**
 * Sole entry point for "Annuler" (Editor.vue/Import.vue) deleting its own
 * draft directory (P3 point 1, deferred-work.md 2026-09-29) — an
 * unconditional delete, never checked for existence first: `deleteDirectory()`
 * on the `local` disk (`throw => false`) is already a no-op when the draft
 * never uploaded anything (directory never created) or was already relocated
 * by a successful save that beat this request to it.
 *
 * Reached through a fire-and-forget `useHttp` DELETE the client never
 * inspects the result of (Design Notes) — a `deleteDirectory()` failure
 * (disk error, permissions) is therefore logged and swallowed here rather
 * than left to bubble into an unlogged 500 nobody would ever see (code
 * review finding): DocumentController::destroyDraft() must always be able
 * to answer with a plain 2xx regardless of whether this cleanup succeeded.
 */
class DeleteDraftDirectoryAction
{
    public function __invoke(DeleteDraftDirectoryData $data): void
    {
        try {
            Storage::disk('local')->deleteDirectory("documents/tmp/{$data->draftToken}");
        } catch (Throwable $exception) {
            Log::warning('Failed to delete a draft directory on "Annuler".', [
                'draft_token' => $data->draftToken,
                'exception' => $exception->getMessage(),
            ]);
        }
    }
}
