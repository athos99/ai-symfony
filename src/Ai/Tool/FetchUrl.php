<?php
declare(strict_types=1);

namespace App\Ai\Tool;

use Symfony\AI\Agent\Toolbox\Attribute\AsTool;
use Symfony\Component\DomCrawler\Crawler;
use Symfony\Component\HttpClient\NoPrivateNetworkHttpClient;
use Symfony\Contracts\HttpClient\HttpClientInterface;

#[AsTool(
    'fetch_url',
    'Télécharge une page web et retourne son texte brut (maximum 8000 caractères).'
)]
final class FetchUrl
{
    private const ALLOWED_HOSTS = ['symfony.com', 'www.php.net', 'docs.example.org'];

    private HttpClientInterface $client;

    public function __construct(HttpClientInterface $client)
    {
        // bloque les adresses privées (SSRF) : localhost, 10.x, 192.168.x, metadata cloud…
        $this->client = new NoPrivateNetworkHttpClient($client);
    }

    /** @param string $url URL complète en https */
    public function __invoke(string $url): string
    {
        $parts = parse_url($url);
        // if (($parts['scheme'] ?? '') !== 'https' || !in_array($parts['host'] ?? '', self::ALLOWED_HOSTS, true)) {
        //     return 'Erreur : URL non autorisée.';
        // }

        $response = $this->client->request('GET', $url, [
            'timeout' => 10,
            'max_redirects' => 0,
        ]);
        $html = $response->getContent();

        $text = (new Crawler($html))->filter('body')->text('');

        return mb_substr(preg_replace('/\s+/u', ' ', $text), 0, 8000);
    }
}