<?php

declare(strict_types=1);

namespace App\Ai\Tool;

use App\Service\GeSearchClient;
use App\Service\GeSearchException;
use Symfony\AI\Agent\Toolbox\Attribute\AsTool;

#[AsTool(
    name: 'recherche_ge_ch',
    description: 'Recherche des pages officielles sur ge.ch (site de l\'État de Genève) : démarches, publications, dossiers, autorités. À utiliser pour répondre à des questions sur l\'administration genevoise.'
)]
final class GeSearchTool
{
    public function __construct(
        private readonly GeSearchClient $client,
    ) {
    }

    /**
     * @param string      $query       Mots-clés de recherche, en français (ex. "permis de conduire", "taxe d'enlèvement des déchets")
     * @param int         $limit       Nombre maximum de résultats à retourner
     * @param string|null $contentType Restreint à un type de contenu (ex. "actualite", "evenement", "communique_presse"). Laisser vide pour la recherche générale.
     *
     * @return array<string,mixed>
     */
    public function __invoke(
        string $query,
        int $limit = 5,
        ?string $contentType = null,
    ): array {
        try {
            $response = $this->client->search($query, $limit, $contentType ?: null);
        } catch (GeSearchException|\InvalidArgumentException $e) {
            return ['error' => $e->getMessage(), 'results' => []];
        }

        return [
            'total_estimated' => $response->estimatedCount,
            'results' => array_map(static fn ($r): array => [
                'title' => $r->title,
                'url' => $r->url,
                'type' => $r->contentType,
                'date' => $r->date,
                'extract' => mb_strimwidth($r->content, 0, 300, '…'),
            ], $response->results),
        ];
    }
}