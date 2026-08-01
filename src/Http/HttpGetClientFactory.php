<?php

declare(strict_types=1);

namespace Hartenthaler\WebtreesModules\History\HhHistoricEvents\Http;

use Fisharebest\Webtrees\Registry;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestFactoryInterface;
use RuntimeException;

use function class_exists;

final class HttpGetClientFactory
{
    public static function create(): HttpGetClient
    {
        $container = Registry::container();

        // webtrees 2.3 provides the PSR-18 client and PSR-17 request factory.
        if ($container->has(ClientInterface::class) && $container->has(RequestFactoryInterface::class)) {
            return new HttpGetClient(
                $container->get(ClientInterface::class),
                $container->get(RequestFactoryInterface::class)
            );
        }

        // webtrees 2.2.6 bundles Guzzle, which also implements PSR-18.
        if (class_exists(\GuzzleHttp\Client::class) && class_exists(\GuzzleHttp\Psr7\HttpFactory::class)) {
            return new HttpGetClient(
                new \GuzzleHttp\Client(['timeout' => 15]),
                new \GuzzleHttp\Psr7\HttpFactory()
            );
        }

        throw new RuntimeException('No PSR-18 HTTP client and PSR-17 request factory are available.');
    }
}
