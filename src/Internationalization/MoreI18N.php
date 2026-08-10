<?php

declare(strict_types=1);

namespace Hartenthaler\WebtreesModules\History\HhHistoricEvents\Internationalization;

use Fisharebest\Webtrees\I18N;

/**
 * Reuse webtrees core translations without adding these strings to the module catalog.
 */
final class MoreI18N
{
    public static function xlate(string $message, ...$args): string
    {
        return I18N::translate($message, ...$args);
    }
}
