<?php

declare(strict_types=1);

namespace App\Service;

final class SearchResponse
{
    /**
     * @param list<SearchResult> $results
     */
    public function __construct(
        public readonly array $results,
        public readonly int $estimatedCount,
        public readonly bool $hasNext,
    ) {
    }
}