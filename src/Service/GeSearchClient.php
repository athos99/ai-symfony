<?php

declare(strict_types=1);

namespace App\Service;

use Psr\Log\LoggerInterface;
use Symfony\Contracts\HttpClient\Exception\ExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

final class GeSearchClient
{
    private const ENDPOINT = 'https://recherche.ge.ch/api/v2/search';

    /** Types exclus dans l'onglet "Tout" (repris de la requête du site). */
    private const EXCLUDED_TYPES = [
        'actualite',
        'communique_presse',
        'point_presse',
        'evenement',
        'feuillet',
    ];

    private const PROPERTIES = [
        ['formats' => ['VALUE'],    'name' => 'ge_content_type'],
        ['formats' => ['VALUE'],    'name' => 'id'],
        ['formats' => ['PROPERTY'], 'name' => 'title'],
        ['formats' => ['PROPERTY'], 'name' => 'content'],
        ['formats' => ['VALUE'],    'name' => 'category'],
        ['formats' => ['VALUE'],    'name' => 'url'],
        ['formats' => ['VALUE'],    'name' => 'date'],
        ['formats' => ['VALUE'],    'name' => 'image_actualites'],
        ['formats' => ['VALUE'],    'name' => 'breadcrumb_path'],
        ['formats' => ['VALUE'],    'name' => 'breadcrumb_label'],
        ['formats' => ['VALUE'],    'name' => 'ge_livret_title'],
    ];

    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly LoggerInterface $logger,
    ) {
    }

    /**
     * @param string|null $onlyType si fourni, ne retourne que ce ge_content_type
     *                              (remplace les exclusions par défaut)
     */
    public function search(string $terms, int $count = 6, ?string $onlyType = null, int $offset = 0): SearchResponse
    {
        $terms = trim($terms);
        if ('' === $terms) {
            throw new \InvalidArgumentException('Les mots-clés de recherche ne peuvent pas être vides.');
        }

        try {
            $data = $this->httpClient->request('POST', self::ENDPOINT, [
                'json' => $this->buildPayload($terms, $count, $onlyType, $offset),
                'headers' => ['Accept' => 'application/json'],
                'timeout' => 15,
            ])->toArray();
        } catch (ExceptionInterface $e) {
            $this->logger->error('Échec de la recherche ge.ch', ['exception' => $e, 'terms' => $terms]);

            throw new GeSearchException('Le service de recherche ge.ch est indisponible.', 0, $e);
        }

        $results = [];
        foreach ($data['resultset']['results'] ?? [] as $result) {
            $results[] = $this->mapResult($result);
        }

        return new SearchResponse(
            $results,
            (int) ($data['estimated_count'] ?? 0),
            (bool) ($data['resultset']['next_avail'] ?? false),
        );
    }

    /**
     * @return array<string,mixed>
     */
    private function buildPayload(string $terms, int $count, ?string $onlyType, int $offset): array
    {
        $constraints = null !== $onlyType
            ? [['unparsed' => 'ge_content_type:'.$onlyType]]
            : array_map(
                static fn (string $type): array => ['unparsed' => 'NOT ge_content_type:'.$type],
                self::EXCLUDED_TYPES,
            );

        $payload = [
            'count' => $count,
            'properties' => self::PROPERTIES,
            'user' => [
                'query' => ['and' => [['unparsed' => $terms, 'id' => 'query']]],
                'constraints' => $constraints,
            ],
            'query_context_user_query' => $terms,
            'max_page_count' => 50,
            'content_sample_length' => 100,
            'name' => 'all-search',
            'query_context' => [
                'query_id' => substr(bin2hex(random_bytes(8)), 0, 10),
                'prev_query_id' => '',
                'app_tab_id' => 'all',
                'application_id' => 'Default Application',
                'query_trigger_action' => 'submit',
            ],
        ];

        // Pagination : basée sur la doc Mindbreeze, non observée sur le site (à valider).
        if ($offset > 0) {
            $payload['pages'] = [[
                'starts' => [$offset],
                'counts' => [$count],
                'current_page' => true,
            ]];
        }

        return $payload;
    }

    /**
     * @param array<string,mixed> $result
     */
    private function mapResult(array $result): SearchResult
    {
        $props = $this->normalizeProps($result);

        return new SearchResult(
            id: $props['id'] ?? '',
            title: $props['title'] ?? '',
            url: $props['url'] ?? '',
            content: $props['content'] ?? '',
            contentType: $props['ge_content_type'] ?? '',
            category: $props['category'] ?? '',
            date: $props['date'] ?? '',
            breadcrumb: $props['breadcrumb_label'] ?? '',
        );
    }

    /**
     * Gère les deux formes possibles de réponse Mindbreeze :
     * map {title: ...} ou liste [{name: title, ...}].
     *
     * @param array<string,mixed> $result
     *
     * @return array<string,string>
     */
    private function normalizeProps(array $result): array
    {
        $props = $result['properties'] ?? $result['data'] ?? $result;
        $out = [];

        foreach ($props as $key => $value) {
            if (\is_int($key) && \is_array($value) && isset($value['name'])) {
                $out[(string) $value['name']] = $this->fieldText($value['data'] ?? $value['values'] ?? $value);
            } else {
                $out[(string) $key] = $this->fieldText($value);
            }
        }

        return $out;
    }

    private function fieldText(mixed $field): string
    {
        if (\is_string($field)) {
            return trim(html_entity_decode(strip_tags($field)));
        }

        if (\is_array($field)) {
            foreach (['text', 'html', 'value'] as $key) {
                if (isset($field[$key]) && \is_string($field[$key])) {
                    return trim(html_entity_decode(strip_tags($field[$key])));
                }
            }
            foreach ($field as $child) {
                $text = $this->fieldText($child);
                if ('' !== $text) {
                    return $text;
                }
            }
        }

        return '';
    }
}