<?php

declare(strict_types=1);

namespace Hartenthaler\WebtreesModules\History\HhHistoricEvents;

use Fisharebest\Webtrees\I18N;
use Illuminate\Database\Capsule\Manager as DB;

use function array_key_exists;
use function array_values;
use function array_unique;
use function explode;
use function strtolower;

/** Groups events by their declared identities and manually maintained equivalences. */
final class HistoricEventResolver
{
    /** @param list<HistoricEvent> $events
     *  @return list<HistoricEvent>
     */
    public function resolve(array $events, string $languageTag): array
    {
        $parent = [];
        $find = static function (string $identity) use (&$parent, &$find): string {
            $parent[$identity] ??= $identity;
            if ($parent[$identity] !== $identity) {
                $parent[$identity] = $find($parent[$identity]);
            }

            return $parent[$identity];
        };
        $union = static function (string $a, string $b) use (&$parent, $find): void {
            $a = $find($a);
            $b = $find($b);
            if ($a !== $b) {
                $parent[$b] = $a;
            }
        };

        foreach ($events as $event) {
            foreach ($event->identities as $identity) {
                $find($identity);
            }
            foreach (array_slice($event->identities, 1) as $identity) {
                $union($event->identities[0], $identity);
            }
        }

        foreach (DB::table(EventIdentitySchema::TABLE_EQUIVALENCES)->get(['identity_a', 'identity_b']) as $equivalence) {
            if ($equivalence->identity_b !== '') {
                $union($equivalence->identity_a, $equivalence->identity_b);
            }
        }

        $groups = [];
        foreach ($events as $position => $event) {
            $key = $event->identities === [] ? 'event:' . $position : $find($event->identities[0]);
            $groups[$key][] = $event;
        }

        $resolved = [];
        foreach ($groups as $group) {
            usort($group, fn (HistoricEvent $a, HistoricEvent $b): int => $this->languageRank($a->language, $languageTag) <=> $this->languageRank($b->language, $languageTag));
            $resolved[] = $this->mergeGroup($group, $languageTag);
        }

        return $resolved;
    }

    private function languageRank(string $eventLanguage, string $requestedLanguage): int
    {
        $eventLanguage = strtolower(str_replace('_', '-', $eventLanguage));
        $requestedLanguage = strtolower(str_replace('_', '-', $requestedLanguage));
        if ($eventLanguage === $requestedLanguage) {
            return 0;
        }
        if (explode('-', $eventLanguage)[0] === explode('-', $requestedLanguage)[0]) {
            return 1;
        }
        if (explode('-', $eventLanguage)[0] === 'en') {
            return 2;
        }

        return 3;
    }

    /** @param list<HistoricEvent> $group */
    private function mergeGroup(array $group, string $languageTag): HistoricEvent
    {
        usort($group, fn (HistoricEvent $a, HistoricEvent $b): int => $this->languageRank($a->language, $languageTag) <=> $this->languageRank($b->language, $languageTag));
        $primary = $group[0];
        $notes = [];

        $type = $this->commonValue($group, static fn (HistoricEvent $event): string => $event->type());
        $date = $this->commonValue($group, static fn (HistoricEvent $event): string => $event->date());
        $link = $this->commonLink($group);

        foreach ([
            I18N::translate('Alternative description') => $this->alternativeValues($group, static fn (HistoricEvent $event): string => $event->eventName(), $primary->eventName(), $languageTag, $primary->language),
            I18N::translate('Alternative dates') => $this->alternativeDates($group, $primary->date()),
            I18N::translate('Alternative categories') => $this->alternativeValues($group, static fn (HistoricEvent $event): string => $event->type(), $primary->type()),
            I18N::translate('Alternative links') => $this->alternativeLinks($group, $primary->sourceLink()),
            I18N::translate('Alternative descriptions') => $this->alternativeValues($group, static fn (HistoricEvent $event): string => implode(' ', $event->notes()), implode(' ', $primary->notes()), $languageTag, $primary->language),
        ] as $label => $values) {
            if ($values !== []) {
                $notes[] = $label . ': ' . implode(' | ', $values);
            }
        }

        $identities = [];
        foreach ($group as $event) {
            $identities = [...$identities, ...$event->identities];
        }
        $externalReferences = DB::table(EventIdentitySchema::TABLE_EQUIVALENCES)
            ->whereIn('identity_a', $identities)
            ->orWhereIn('identity_b', $identities)
            ->pluck('external_references')
            ->filter()
            ->unique()
            ->implode('; ');
        if ($externalReferences !== '') {
            $notes[] = I18N::translate('External references') . ': ' . $externalReferences;
        }

        return $primary->withValues($type, $date, $link, $notes)->withIdentities(array_values(array_unique($identities)));
    }

    /** @param list<HistoricEvent> $events */
    private function commonValue(array $events, callable $value): ?string
    {
        $values = array_values(array_unique(array_filter(array_map($value, $events), static fn (string $item): bool => $item !== '')));

        return count($values) === 1 ? $values[0] : null;
    }

    /** @param list<HistoricEvent> $events
     *  @return list<string>
     */
    private function alternativeValues(array $events, callable $value, string $primaryValue, ?string $languageTag = null, ?string $primaryLanguage = null): array
    {
        if ($languageTag !== null && $primaryLanguage !== null) {
            $events = array_filter($events, fn (HistoricEvent $event): bool => $this->languageRank($event->language, $languageTag) === $this->languageRank($primaryLanguage, $languageTag));
        }

        return array_values(array_unique(array_filter(array_map($value, $events), static fn (string $item): bool => $item !== '' && $item !== $primaryValue)));
    }

    /** @param list<HistoricEvent> $events */
    private function commonLink(array $events): ?string
    {
        $links = array_values(array_filter(array_map(static fn (HistoricEvent $event): string => $event->sourceLink(), $events), static fn (string $link): bool => $link !== ''));
        $normalized = array_unique(array_map(fn (string $link): string => $this->normalizedLink($link), $links));

        return count($normalized) === 1 ? $links[0] : null;
    }

    /** @param list<HistoricEvent> $events
     *  @return list<string>
     */
    private function alternativeLinks(array $events, string $primaryLink): array
    {
        $primaryLink = $this->normalizedLink($primaryLink);

        return array_values(array_unique(array_filter(array_map(static fn (HistoricEvent $event): string => $event->sourceLink(), $events), fn (string $link): bool => $link !== '' && $this->normalizedLink($link) !== $primaryLink)));
    }

    /** @param list<HistoricEvent> $events
     *  @return list<string>
     */
    private function alternativeDates(array $events, string $primaryDate): array
    {
        $normalizedPrimaryDate = $this->normalizedDate($primaryDate);

        return array_values(array_unique(array_filter(
            array_map(static fn (HistoricEvent $event): string => $event->date(), $events),
            fn (string $date): bool => $date !== '' && $this->normalizedDate($date) !== $normalizedPrimaryDate
        )));
    }

    private function normalizedDate(string $date): string
    {
        return preg_replace_callback(
            '/(?<![0-9])(\d{1,2})\s+(JAN|FEB|MAR|APR|MAY|JUN|JUL|AUG|SEP|OCT|NOV|DEC)\s+(\d{4})(?![0-9])/i',
            static fn (array $matches): string => str_pad($matches[1], 2, '0', STR_PAD_LEFT) . ' ' . strtoupper($matches[2]) . ' ' . $matches[3],
            $date
        ) ?? $date;
    }

    private function normalizedLink(string $link): string
    {
        if (preg_match('/\]\((https?:\/\/[^\s)]+)/', $link, $matches) === 1) {
            $link = $matches[1];
        }

        return preg_replace('#https://(?:[a-z]{2}\.)?wikipedia\.org/#i', 'https://wikipedia.org/', $link) ?? $link;
    }
}
