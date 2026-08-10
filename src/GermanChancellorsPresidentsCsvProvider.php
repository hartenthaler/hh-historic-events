<?php

declare(strict_types=1);

namespace Hartenthaler\WebtreesModules\History\HhHistoricEvents;

use Fisharebest\Webtrees\I18N;
use Illuminate\Support\Collection;

use function fclose;
use function fgetcsv;
use function fopen;
use function is_file;
use function str_contains;
use function str_replace;
use function str_starts_with;

final class GermanChancellorsPresidentsCsvProvider implements EventDataProviderInterface
{
    public function __construct(private readonly string $file)
    {
    }

    public function id(): string
    {
        return 'german-chancellors-presidents-csv';
    }

    public function title(): string
    {
        return I18N::translate('German Chancellors Presidents (CSV)');
    }

    public function description(): string
    {
        return I18N::translate('Historical facts - Chancellors and Presidents of Germany (since 1949)');
    }

    public function sourceTitle(): string
    {
        return I18N::translate('Wikipedia and Wikimedia Commons');
    }

    public function sourceUrl(): string
    {
        return 'https://de.wikipedia.org/';
    }

    public function sourceStatus(): string
    {
        return '';
    }

    public function eventLanguageOptions(): array
    {
        return ['de' => I18N::translate('German')];
    }

    public function typeOptions(): array
    {
        return [
            'chancellor' => I18N::translate('Chancellor of Germany'),
            'president' => I18N::translate('President of Germany'),
            'acting' => I18N::translate('acting'),
        ];
    }

    public function typeLanguage(string $typeId): string
    {
        return '';
    }

    public function typeLanguageId(string $typeId): string
    {
        return 'de';
    }

    public function typeRegion(string $typeId): string
    {
        return I18N::translate('Germany');
    }

    public function enabledByDefault(): bool
    {
        return true;
    }

    public function typeEnabledByDefault(string $typeId): bool
    {
        return true;
    }

    public function historicEvents(string $languageTag, array $enabledTypes): Collection
    {
        $collection = new Collection();
        $source = I18N::translate('source');
        $wikipedia = 'de';

        foreach ($this->loadCsvFile() as $event) {
            if (!$this->typeIsEnabled($event['typeCode'], $enabledTypes)) {
                continue;
            }

            $collection->push(
                '1 EVEN ' . $event['name'] .
                "\n2 TYPE " . $this->translateType($event['typeCode']) .
                "\n2 DATE " . $event['date'] .
                "\n2 NOTE " . ($event['image'] === ''
                    ? '[wikipedia ' . $wikipedia . '](https://' . $wikipedia . '.wikipedia.org/wiki/' . $event['article'] . ' )'
                    : '[![wikipedia ' . $wikipedia . '](https://' . $event['image'] .
                        ($event['imageTitle'] === '' ? '' : ' "' . $this->escapeMarkdownTitle($event['imageTitle']) . '"') .
                        ')](https://' . $wikipedia . '.wikipedia.org/wiki/' . $event['article'] . ' )' .
                        ($event['attribution'] === '' ? '' : "\n3 CONT " . $source . ': ' . $event['attribution']))
            );
        }

        return $collection;
    }

    /**
     * @return array<int,array<string,string>>
     */
    private function loadCsvFile(): array
    {
        if (!is_file($this->file)) {
            return [];
        }

        $events = [];
        $handle = fopen($this->file, 'r');
        if ($handle === false) {
            return [];
        }

        fgetcsv($handle, 0, ',', '"', '\\');
        while (($row = fgetcsv($handle, 0, ',', '"', '\\')) !== false) {
            if (($row[0] ?? '') === '' || str_starts_with($row[0], '#')) {
                continue;
            }

            $events[] = [
                'name' => $row[0] ?? '',
                'typeCode' => $row[1] ?? '',
                'date' => $row[2] ?? '',
                'article' => $row[3] ?? '',
                'image' => $row[4] ?? '',
                'attribution' => $row[5] ?? '',
                'imageTitle' => $row[6] ?? '',
            ];
        }

        fclose($handle);

        return $events;
    }

    /**
     * @param array<string,bool> $enabledTypes
     */
    private function typeIsEnabled(string $typeCode, array $enabledTypes): bool
    {
        return (str_contains($typeCode, 'C') && ($enabledTypes['chancellor'] ?? false))
            || (str_contains($typeCode, 'P') && ($enabledTypes['president'] ?? false))
            || (str_contains($typeCode, 'A') && ($enabledTypes['acting'] ?? false));
    }

    private function translateType(string $typeCode): string
    {
        return str_replace(
            ['C', 'P', 'A'],
            [I18N::translate('Chancellor of Germany'), I18N::translate('President of Germany'), I18N::translate('acting')],
            $typeCode
        );
    }

    private function escapeMarkdownTitle(string $title): string
    {
        return str_replace('"', '\\"', $title);
    }
}
