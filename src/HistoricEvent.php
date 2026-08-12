<?php

declare(strict_types=1);

namespace Hartenthaler\WebtreesModules\History\HhHistoricEvents;

use function array_unique;
use function array_values;
use function explode;
use function preg_match;
use function preg_match_all;
use function preg_replace;
use function trim;

/** A provider-neutral historic event, before it is rendered as GEDCOM. */
final class HistoricEvent
{
    /** @param list<string> $identities */
    public function __construct(
        public readonly string $gedcom,
        public readonly string $language,
        public readonly string $providerId,
        public readonly string $collectionId,
        public readonly array $identities,
    ) {
    }

    public static function fromGedcom(string $gedcom, string $language, string $providerId, string $collectionId): self
    {
        preg_match_all('/^2 (_UID|_WIKIDATA_STATEMENT) ([^\r\n]+)/m', $gedcom, $matches, PREG_SET_ORDER);
        $identities = [];
        foreach ($matches as $match) {
            $identities[] = trim($match[2]);
        }

        return new self($gedcom, $language, $providerId, $collectionId, array_values(array_unique($identities)));
    }

    public function eventName(): string
    {
        return $this->gedcomValue('1', 'EVEN');
    }

    public function type(): string
    {
        return $this->gedcomValue('2', 'TYPE');
    }

    public function date(): string
    {
        return $this->gedcomValue('2', 'DATE');
    }

    public function sourceLink(): string
    {
        preg_match('/^2 NOTE (.*\]\([^\r\n]+\).*)$/m', $this->gedcom, $matches);

        return trim($matches[1] ?? '');
    }

    /** @return list<string> */
    public function notes(): array
    {
        preg_match_all('/^2 NOTE ([^\r\n]*)$/m', $this->gedcom, $matches);
        $sourceLink = $this->sourceLink();

        return array_values(array_unique(array_filter(
            array_map(static fn (string $note): string => trim($note), $matches[1] ?? []),
            static fn (string $note): bool => $note !== '' && $note !== $sourceLink
        )));
    }

    public function withValues(?string $type = null, ?string $date = null, ?string $sourceLink = null, array $notes = []): self
    {
        $gedcom = $this->gedcom;
        if ($type !== null) {
            $gedcom = $this->replaceGedcomValue($gedcom, '2', 'TYPE', $type);
        }
        if ($date !== null) {
            $gedcom = $this->replaceGedcomValue($gedcom, '2', 'DATE', $date);
        }
        if ($sourceLink !== null && $sourceLink !== '') {
            $gedcom = preg_replace('/^2 NOTE .*\]\([^\r\n]+\).*$/m', '2 NOTE ' . $sourceLink, $gedcom, 1) ?? $gedcom;
            if (!str_contains($gedcom, $sourceLink)) {
                $gedcom .= "\n2 NOTE " . $sourceLink;
            }
        }
        foreach ($notes as $note) {
            $gedcom .= "\n2 NOTE " . $note;
        }

        return self::fromGedcom($gedcom, $this->language, $this->providerId, $this->collectionId);
    }

    /** @param list<string> $identities */
    public function withIdentities(array $identities): self
    {
        $gedcom = preg_replace('/^2 _UID [^\r\n]*\r?\n?/m', '', $this->gedcom) ?? $this->gedcom;
        foreach ($identities as $identity) {
            $gedcom .= "\n2 _UID " . $identity;
        }

        return self::fromGedcom($gedcom, $this->language, $this->providerId, $this->collectionId);
    }

    public function withoutInternalWikidataTags(): self
    {
        $gedcom = preg_replace('/^2 _WIKIDATA_(?:STATEMENT|PROPERTY) [^\r\n]*\r?\n?/m', '', $this->gedcom) ?? $this->gedcom;

        return new self($gedcom, $this->language, $this->providerId, $this->collectionId, $this->identities);
    }

    private function gedcomValue(string $level, string $tag): string
    {
        preg_match('/^' . $level . ' ' . $tag . ' ([^\r\n]*)$/m', $this->gedcom, $matches);

        return trim($matches[1] ?? '');
    }

    private function replaceGedcomValue(string $gedcom, string $level, string $tag, string $value): string
    {
        $pattern = '/^' . $level . ' ' . $tag . ' [^\r\n]*$/m';
        if (preg_match($pattern, $gedcom) === 1) {
            return preg_replace($pattern, $level . ' ' . $tag . ' ' . $value, $gedcom, 1) ?? $gedcom;
        }

        return $gedcom . "\n" . $level . ' ' . $tag . ' ' . $value;
    }
}
