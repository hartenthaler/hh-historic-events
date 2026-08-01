<?php

declare(strict_types=1);

namespace Hartenthaler\WebtreesModules\History\HhHistoricEvents\Http;

use Psr\Http\Client\ClientExceptionInterface;
use RuntimeException;

final class HttpRequestException extends RuntimeException implements ClientExceptionInterface
{
}
