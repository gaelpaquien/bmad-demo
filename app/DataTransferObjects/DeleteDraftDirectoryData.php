<?php

namespace App\DataTransferObjects;

final readonly class DeleteDraftDirectoryData
{
    public function __construct(
        public string $draftToken,
    ) {}
}
