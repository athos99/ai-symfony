<?php

namespace App\Service;

use Symfony\Component\DependencyInjection\Attribute\AsDecorator;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;
use Symfony\Contracts\HttpClient\ResponseStreamInterface;
use Psr\Log\LoggerInterface;

#[AsDecorator('http_client')]
final class LoggingHttpClient implements HttpClientInterface
{
    public function __construct(
        private HttpClientInterface $inner,
        private LoggerInterface $httpClientLogger, // canal monolog http_client
    ) {}

    public function request(string $method, string $url, array $options = []): ResponseInterface
    {
        $this->httpClientLogger->debug('HTTP request', [
            'method' => $method,
            'url' => $url,
            'body' => $options['body'] ?? $options['json'] ?? null,
        ]);

        return $this->inner->request($method, $url, $options);
    }

    public function stream(ResponseInterface|iterable $responses, ?float $timeout = null): ResponseStreamInterface
    {
        $x=$this->inner->stream($responses, $timeout);
        $this->httpClientLogger->debug('HTTP stream', [
            'response' => $x
            ]);
        return $x;
    }

    public function withOptions(array $options): static
    {
        $clone = clone $this;
        $clone->inner = $this->inner->withOptions($options);

        return $clone;
    }
}