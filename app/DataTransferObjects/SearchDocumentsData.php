<?php

namespace App\DataTransferObjects;

final readonly class SearchDocumentsData
{
    /**
     * @param  array<int, int>  $tagIds
     */
    public function __construct(
        public string $term,
        public array $tagIds = [],
        public ?int $page = null,
        public ?string $path = null,
    ) {}
}
