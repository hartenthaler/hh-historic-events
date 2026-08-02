<?php

declare(strict_types=1);

namespace Hartenthaler\WebtreesModules\History\HhHistoricEvents;

use Illuminate\Support\Collection;

interface EventDataProviderInterface
{
    public function id(): string;

    public function title(): string;

    public function description(): string;

    public function sourceTitle(): string;

    public function sourceUrl(): string;

    public function sourceStatus(): string;

    /**
     * @return array<string,string>
     */
    public function eventLanguageOptions(): array;

    /**
     * @return array<string,string>
     */
    public function typeOptions(): array;

    public function typeLanguage(string $typeId): string;

    public function typeLanguageId(string $typeId): string;

    public function typeRegion(string $typeId): string;

    public function enabledByDefault(): bool;

    public function typeEnabledByDefault(string $typeId): bool;

    /**
     * @param array<string,bool> $enabledTypes
     *
     * @return Collection<string>
     */
    public function historicEvents(string $languageTag, array $enabledTypes): Collection;
}
