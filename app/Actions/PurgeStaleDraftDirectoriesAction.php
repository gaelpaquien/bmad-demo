<?php

namespace App\Actions;

use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Throwable;

/**
 * Best-effort cleanup of abandoned draft directories (P3 point 2,
 * deferred-work.md 2026-09-29) — run at the tail of every draft upload
 * (UploadEditorImageAction, UploadDraftAttachmentAction) rather than from any
 * scheduler or queued job (Boundaries & Constraints: "pas de scheduler ni de
 * tâche planifiée").
 *
 * Scans only the top-level directories directly under `documents/tmp` — one
 * per draft token, never recursing further — and deletes each one whose most
 * recent activity (`lastActivityAt()`) is older than STALE_AFTER_HOURS.
 *
 * Staleness is deliberately never read from the `{token}` directory's own
 * `lastModified()` alone (code review finding, Blind Hunter/Edge Case
 * Hunter): on the local filesystem, writing a new file into an
 * already-existing `images/`/`attachments/` subdirectory only bumps that
 * subdirectory's own mtime, not its parent's — only the very first write
 * that creates a brand-new subdirectory bumps the parent. Checking only the
 * parent would let a draft session left open past 24h, whose subdirectory
 * already exists, have its still-in-use files deleted mid-session by the
 * very upload that should keep it alive. `lastActivityAt()` instead looks at
 * every file under the directory (at any depth) and keeps the most recent
 * one, falling back to the directory's own `lastModified()` only when it
 * holds no files at all.
 *
 * A failure on any single directory — `lastActivityAt()`/`deleteDirectory()`
 * throwing, or resolving to `false` — is logged and skipped: never
 * rethrown, never interrupting the loop over the remaining directories, and
 * never failing the upload request that triggered this call (Boundaries &
 * Constraints: "la purge... ne doit jamais faire échouer l'upload en
 * cours").
 */
class PurgeStaleDraftDirectoriesAction
{
    private const STALE_AFTER_HOURS = 24;

    public function __invoke(): void
    {
        $disk = Storage::disk('local');
        $staleBefore = now()->subHours(self::STALE_AFTER_HOURS)->timestamp;

        foreach ($disk->directories('documents/tmp') as $directory) {
            try {
                $lastActivity = $this->lastActivityAt($disk, $directory);

                if ($lastActivity !== false && $lastActivity < $staleBefore) {
                    $disk->deleteDirectory($directory);
                }
            } catch (Throwable $exception) {
                Log::warning('Failed to purge a stale draft directory.', [
                    'directory' => $directory,
                    'exception' => $exception->getMessage(),
                ]);
            }
        }
    }

    /**
     * The most recent `lastModified()` among every file found anywhere
     * under `$directory` (`allFiles()` recurses into `images/`/
     * `attachments/` subdirectories), which is what actually reflects when
     * a draft was last touched — not the directory entry's own mtime (see
     * class docblock). Falls back to the directory's own `lastModified()`
     * only when it holds no files (an empty directory, e.g. every file
     * already relocated elsewhere, has nothing else to check). Returns
     * `false` when that too can't be determined, treated by the caller as
     * "can't tell, don't delete".
     *
     * @return int|false
     */
    private function lastActivityAt(Filesystem $disk, string $directory)
    {
        $fileTimestamps = collect($disk->allFiles($directory))
            ->map(fn (string $file) => $disk->lastModified($file))
            ->filter(fn ($timestamp) => $timestamp !== false);

        return $fileTimestamps->isNotEmpty() ? $fileTimestamps->max() : $disk->lastModified($directory);
    }
}
