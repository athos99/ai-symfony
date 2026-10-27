<?php

declare(strict_types=1);

namespace App\Service;

final class SearchResult
{
    public function __construct(
        public readonly string $id,
        public readonly string $title,
        public readonly string $url,
        public readonly string $content,
        public readonly string $contentType,
        public readonly string $category,
        public readonly string $date,
        public readonly string $breadcrumb,
    ) {
    }
}