<?php

declare(strict_types=1);

namespace Hartenthaler\WebtreesModules\History\HhHistoricEvents;

use DateTime;
use Fisharebest\Webtrees\I18N;
use Fisharebest\Webtrees\Webtrees;
use Hartenthaler\WebtreesModules\History\HhHistoricEvents\Http\HttpGetClient;
use Illuminate\Support\Collection;
use Psr\Http\Client\ClientExceptionInterface;

use function date;
use function dirname;
use function file_exists;
use function file_get_contents;
use function file_put_contents;
use function filemtime;
use function implode;
use function is_dir;
use function json_decode;
use function md5;
use function mkdir;
use function preg_match;
use function sprintf;
use function strtolower;
use function substr;
use function time;
use function urlencode;

final class GermanChancellorsPresidentsWikidataProvider implements EventDataProviderInterface
{
    public function __construct(private readonly HttpGetClient $httpClient)
    {
    }

    public function id(): string
    {
        return 'german-chancellors-presidents-wikidata';
    }

    public function title(): string
    {
        return I18N::translate('Chancellors and Presidents (Wikidata)');
    }

    public function description(): string
    {
        return I18N::translate('Historical facts - Chancellors and Presidents of Germany, Austria and Switzerland');
    }

    public function sourceTitle(): string
    {
        return I18N::translate('Wikidata');
    }

    public function sourceUrl(): string
    {
        return 'https://www.wikidata.org/';
    }

    public function sourceStatus(): string
    {
        return '';
    }

    public function eventLanguageOptions(): array
    {
        return ['mul' => I18N::translate('Multilingual / dynamically localized')];
    }

    public function typeOptions(): array
    {
        $types = [];
        foreach ($this->wikidataObjects() as $typeId => $wikidataObject) {
            $types[$typeId] = $wikidataObject[2];
        }

        return $types;
    }

    public function typeLanguage(string $typeId): string
    {
        return I18N::translate('Multilingual / dynamically localized');
    }

    public function typeLanguageId(string $typeId): string
    {
        return 'mul';
    }

    public function typeRegion(string $typeId): string
    {
        return $this->wikidataObjects()[$typeId][3] ?? '';
    }

    public function enabledByDefault(): bool
    {
        return false;
    }

    public function typeEnabledByDefault(string $typeId): bool
    {
        return true;
    }

    public function historicEvents(string $languageTag, array $enabledTypes, array $enabledCategories = []): Collection
    {
        $collection = new Collection();
        $wikipedia = substr($languageTag, 0, 2) ?: 'en';

        foreach ($this->wikidataObjects() as $typeId => $wikidataObject) {
            if (($enabledTypes[$typeId] ?? false) !== true) {
                continue;
            }

            try {
                $persons = $this->getOfficeHolders($wikidataObject, $wikipedia);
            } catch (ClientExceptionInterface) {
                continue;
            }

            foreach ($persons as $person) {
                $startActingDate = !empty($person->startActingDate) ? $this->formatToGedcomDate($person->startActingDate) : '';
                $endActingDate = !empty($person->endActingDate) ? $this->formatToGedcomDate($person->endActingDate) : '';

                $birthDate = !empty($person->birthDate) ? new DateTime($person->birthDate) : null;
                $deathDate = !empty($person->deathDate) ? new DateTime($person->deathDate) : null;
                $startPartyDate = !empty($person->startPartyDate) ? new DateTime($person->startPartyDate) : null;
                $startActingDateForParty = !empty($person->startActingDate) ? new DateTime($person->startActingDate) : null;
                $endPartyDate = !empty($person->endPartyDate) ? new DateTime($person->endPartyDate) : null;
                $showParty = $endPartyDate === null || $startActingDateForParty === null || $endPartyDate > $startActingDateForParty;
                $hasPartyDetails = $showParty && ($startPartyDate !== null || isset($person->partyShortLabel));
                $article = $person->article ?? $person->officeHolder ?? '';
                $articleLabel = isset($person->article) ? ($person->wikiType ?? 'wiki') : I18N::translate('Wikidata');

                $gedcom = sprintf(
                        "1 EVEN %s%s%s%s%s%s%s%s%s\n2 TYPE %s\n2 DATE FROM %s%s\n2 NOTE [%s](%s )",
                        $person->officeHolderLabel ?? '',
                        (isset($person->birthDate) || isset($person->deathDate)) ? ' (' : ' ',
                        isset($person->birthDate) ? '*' . $birthDate->format('d.m.Y') : '',
                        isset($person->deathDate) ? ', +' . $deathDate->format('d.m.Y') : '',
                        isset($person->birthDate) || isset($person->deathDate) ? ')' : '',
                        $hasPartyDetails ? ' (' : '',
                        $hasPartyDetails && $startPartyDate !== null ? I18N::translate('from') . ' ' . $startPartyDate->format('d.m.Y') . ' ' : '',
                        $hasPartyDetails && isset($person->partyShortLabel) ? I18N::translate('member of party %s', $person->partyShortLabel) : '',
                        $hasPartyDetails ? ')' : '',
                        $wikidataObject[2],
                        $startActingDate,
                        $endActingDate !== '' ? ' TO ' . $endActingDate : '',
                        $articleLabel,
                        $article
                    );

                $statementIdentity = $this->statementIdentity($person, $wikidataObject[1]);
                if ($statementIdentity !== null) {
                    $gedcom .= "\n2 _WIKIDATA_STATEMENT " . $statementIdentity['guid'];
                    $gedcom .= "\n2 _WIKIDATA_PROPERTY " . $statementIdentity['property'];
                }

                $collection->push(HistoricEvent::fromGedcom($gedcom, $languageTag, $this->id(), $this->title()));
            }
        }

        return $collection;
    }

    /**
     * @return array<string,array{0:string,1:string,2:string,3:string}>
     */
    private function wikidataObjects(): array
    {
        return [
            'chancellor' => ['Q4970706', 'P1308', I18N::translate('Chancellor of Germany'), I18N::translate('Germany')],
            'president' => ['Q25223', 'P1308', I18N::translate('President of Germany'), I18N::translate('Germany')],
            'gdr-head' => ['Q16957', 'P35', I18N::translate('Head of former state GDR'), I18N::translate('Germany')],
            'austrian-chancellor' => ['Q1006398', 'P1308', I18N::translate('Chancellor of Austria'), I18N::translate('Austria')],
            'austrian-president' => ['Q475658', 'P1308', I18N::translate('President of Austria'), I18N::translate('Austria')],
            'swiss-president' => ['Q688230', 'P1308', I18N::translate('President of the Swiss Confederation'), I18N::translate('Switzerland')],
        ];
    }

    /**
     * @param array{0:string,1:string,2:string,3:string} $wikidataObject
     *
     * @return object[]
     * @throws ClientExceptionInterface
     */
    private function getOfficeHolders(array $wikidataObject, string $wikipediaLanguage): array
    {
        $response = $this->readWikidata($this->buildQuery($wikidataObject, $wikipediaLanguage));
        $data = json_decode($response, true);
        $records = [];

        foreach ($data['results']['bindings'] ?? [] as $binding) {
            $entry = (object) [];
            foreach ($binding as $key => $value) {
                $entry->$key = $value['value'];
            }
            $records[] = $entry;
        }

        return $this->removeDuplicates($records);
    }

    /**
     * @param array{0:string,1:string,2:string,3:string} $wikidataObject
     */
    private function buildQuery(array $wikidataObject, string $wikipediaLanguage): string
    {
        [$wikidataId, $property] = $wikidataObject;

        return "
            SELECT ?statement ?statementProperty ?officeHolder ?officeHolderLabel ?startActingDate ?endActingDate ?birthDate ?deathDate ?partyShortLabel ?startPartyDate ?endPartyDate ?article WHERE {
                {
                    wd:$wikidataId p:$property ?statement.
                    ?statement ps:$property ?officeHolder.
                    BIND('$property' AS ?statementProperty)
                }
                UNION
                {
                    ?officeHolder p:P39 ?statement.
                    ?statement ps:P39 wd:$wikidataId.
                    FILTER NOT EXISTS {
                        wd:$wikidataId p:$property ?directStatement.
                        ?directStatement ps:$property ?officeHolder.
                    }
                    BIND('P39' AS ?statementProperty)
                }
                OPTIONAL { ?statement pq:P580 ?startActingDate. }
                OPTIONAL { ?statement pq:P582 ?endActingDate. }
                OPTIONAL { ?officeHolder wdt:P569 ?birthDate. }
                OPTIONAL { ?officeHolder wdt:P570 ?deathDate. }
                OPTIONAL {
                    ?officeHolder p:P102 ?partyStatement.
                    ?partyStatement ps:P102 ?party.
                    OPTIONAL { ?partyStatement pq:P580 ?startPartyDate. }
                    OPTIONAL { ?partyStatement pq:P582 ?endPartyDate. }
                    OPTIONAL { ?party wdt:P1813 ?partyShortLabel. }
                }
                OPTIONAL {
                    ?article schema:about ?officeHolder;
                             schema:inLanguage '$wikipediaLanguage'.
                }
                OPTIONAL {
                    ?fallbackArticle schema:about ?officeHolder;
                                     schema:inLanguage 'en'.
                }
                BIND(COALESCE(?article, ?fallbackArticle) AS ?article)
                SERVICE wikibase:label { bd:serviceParam wikibase:language '$wikipediaLanguage,en,mul'. }
            }
            ORDER BY DESC(?officeHolderLabel) DESC(?startPartyDate)
        ";
    }

    /**
     * @throws ClientExceptionInterface
     */
    private function readWikidata(string $query): string
    {
        $ttl = 86400;
        $url = 'https://query.wikidata.org/sparql?format=json&query=' . urlencode($query);
        $cacheFile = Webtrees::DATA_DIR . 'cache/hh-historic-events/wikidata-' . md5($url) . '.json';

        if (file_exists($cacheFile) && filemtime($cacheFile) !== false && time() - filemtime($cacheFile) < $ttl) {
            $cached = file_get_contents($cacheFile);
            if ($cached !== false && $cached !== '') {
                return $cached;
            }
        }

        $data = $this->httpClient->get($url, [
            'User-Agent' => 'hh-historic-events/0.1 (+https://github.com/hartenthaler/hh-historic-events)',
        ]);

        $cacheDirectory = dirname($cacheFile);
        if (!is_dir($cacheDirectory)) {
            @mkdir($cacheDirectory, 0775, true);
        }

        @file_put_contents($cacheFile, $data);

        return $data;
    }

    private function formatToGedcomDate(string $dateString): string
    {
        $gedcomMonths = ['JAN', 'FEB', 'MAR', 'APR', 'MAY', 'JUN', 'JUL', 'AUG', 'SEP', 'OCT', 'NOV', 'DEC'];
        $month = (int) substr($dateString, 5, 2);

        return substr($dateString, 8, 2) . ' ' . $gedcomMonths[$month - 1] . ' ' . substr($dateString, 0, 4);
    }

    /**
     * @return array{guid:string,property:string}|null
     */
    private function statementIdentity(object $person, string $fallbackProperty): ?array
    {
        $statement = $person->statement ?? '';
        if (preg_match('#/statement/(Q[0-9]+)-([^/]+)$#i', $statement, $matches) !== 1) {
            return null;
        }

        $property = $person->statementProperty ?? $fallbackProperty;
        if (preg_match('/^P[0-9]+$/', $property) !== 1) {
            return null;
        }

        return [
            'guid' => $matches[1] . '$' . strtolower($matches[2]),
            'property' => $property,
        ];
    }

    /**
     * @param object[] $records
     *
     * @return object[]
     */
    private function removeDuplicates(array $records): array
    {
        $byOfficeTerm = [];
        foreach ($records as $record) {
            $wikiType = $this->extractWikiType($record->article ?? '');
            $officeTerm = implode('|', [
                $record->officeHolderLabel ?? '',
                $record->startActingDate ?? '',
                $record->endActingDate ?? '',
            ]);
            $byOfficeTerm[$officeTerm][] = [
                'record' => $record,
                'wikiType' => 'wiki' . $wikiType,
                'priority' => $this->getPriority($wikiType, $record->startPartyDate ?? null, $record->endActingDate ?? null),
            ];
        }

        $uniqueRecords = [];
        foreach ($byOfficeTerm as $recordsForLabel) {
            $best = $recordsForLabel[0];
            foreach ($recordsForLabel as $candidate) {
                if ($candidate['priority'] > $best['priority']) {
                    $best = $candidate;
                }
            }

            $best['record']->wikiType = $best['wikiType'];
            $uniqueRecords[] = $best['record'];
        }

        return $uniqueRecords;
    }

    private function extractWikiType(string $link): string
    {
        if (preg_match('#\.wiki([^.]+)\.#', $link, $matches)) {
            return $matches[1];
        }

        return '';
    }

    private function getPriority(string $wikiType, ?string $startPartyDate, ?string $endActingDate): int
    {
        $priority = [
            'pedia' => 1000,
            'quote' => 600,
            'news' => 500,
            'voyage' => 200,
        ][$wikiType] ?? 0;

        $startParty = !empty($startPartyDate) ? new DateTime($startPartyDate) : null;
        $endActing = !empty($endActingDate) ? new DateTime($endActingDate) : null;

        if ($startParty !== null && $endActing !== null && $startParty >= $endActing) {
            $priority -= 10000;
        }

        if ($startParty !== null) {
            $priority -= (int) date('Y') - (int) $startParty->format('Y');
        }

        return $priority;
    }
}
