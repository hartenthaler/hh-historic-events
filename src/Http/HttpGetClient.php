<?php

declare(strict_types=1);

namespace Hartenthaler\WebtreesModules\History\HhHistoricEvents\Http;

use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestFactoryInterface;

use function sprintf;

final class HttpGetClient
{
    public function __construct(
        private readonly ClientInterface $client,
        private readonly RequestFactoryInterface $requestFactory
    ) {
    }

    /**
     * @param array<string,string> $headers
     */
    public function get(string $url, array $headers = []): string
    {
        $request = $this->requestFactory->createRequest('GET', $url);

        foreach ($headers as $name => $value) {
            $request = $request->withHeader($name, $value);
        }

        $response = $this->client->sendRequest($request);
        $status = $response->getStatusCode();

        if ($status < 200 || $status >= 300) {
            throw new HttpRequestException(sprintf('HTTP request failed with status %d.', $status));
        }

        return $response->getBody()->getContents();
    }
}
