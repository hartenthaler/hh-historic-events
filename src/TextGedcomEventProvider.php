<?php

declare(strict_types=1);

namespace Hartenthaler\WebtreesModules\History\HhHistoricEvents;

use Fisharebest\Webtrees\I18N;
use Illuminate\Support\Collection;
use Hartenthaler\WebtreesModules\History\HhHistoricEvents\Internationalization\MoreI18N;

use function array_pad;
use function file_get_contents;
use function fclose;
use function fgetcsv;
use function fopen;
use function is_file;
use function pathinfo;
use function preg_match_all;
use function preg_split;
use function str_replace;
use function str_starts_with;
use function trim;

use const PATHINFO_EXTENSION;

final class TextGedcomEventProvider implements EventDataProviderInterface
{
    /**
     * @param array<string,string> $typeOptions
     */
    public function __construct(
        private readonly string $id,
        private readonly string $title,
        private readonly string $description,
        private readonly string $file,
        private readonly string $language,
        private readonly string $region,
        private readonly string $sourceTitle,
        private readonly string $sourceUrl,
        private readonly array $typeOptions
    ) {
    }

    public function id(): string
    {
        return $this->id;
    }

    public function title(): string
    {
        return match ($this->title) {
            'Historic Events: Switzerland (CSV)' => I18N::translate('Historic Events: Switzerland (CSV)'),
            'Wars and Battles Worldwide' => I18N::translate('Wars and Battles Worldwide'),
            default => $this->title,
        };
    }

    public function description(): string
    {
        return match ($this->description) {
            'Historical facts - events in Switzerland' => I18N::translate('Historical facts - events in Switzerland'),
            'Historical facts - Wars and Battles Worldwide (since 900)' => I18N::translate('Historical facts - Wars and Battles Worldwide (since 900)'),
            default => $this->description,
        };
    }

    public function sourceTitle(): string
    {
        return match ($this->sourceTitle) {
            'Wikipedia' => I18N::translate('Wikipedia'),
            'Peter Jehli-Kamm, baum.jehli.ch' => I18N::translate('Peter Jehli-Kamm, baum.jehli.ch'),
            default => $this->sourceTitle,
        };
    }

    public function sourceUrl(): string
    {
        return $this->sourceUrl;
    }

    public function sourceStatus(): string
    {
        return '';
    }

    public function eventLanguageOptions(): array
    {
        return [$this->language => $this->translateLanguageId($this->language)];
    }

    public function typeOptions(): array
    {
        $options = [];
        foreach ($this->typeOptions as $typeId => $label) {
            $options[$typeId] = $this->translateTypeLabel($label);
        }

        return $options;
    }

    public function typeLanguage(string $typeId): string
    {
        return $this->translateLanguageId($this->language);
    }

    public function typeLanguageId(string $typeId): string
    {
        return $this->language;
    }

    public function typeRegion(string $typeId): string
    {
        return match ($this->region) {
            'Worldwide' => I18N::translate('Worldwide'),
            'Switzerland' => I18N::translate('Switzerland'),
            default => $this->region,
        };
    }

    public function enabledByDefault(): bool
    {
        return true;
    }

    public function typeEnabledByDefault(string $typeId): bool
    {
        return true;
    }

    public function historicEvents(string $languageTag, array $enabledTypes, array $enabledCategories = []): Collection
    {
        $collection = new Collection();

        if (!is_file($this->file)) {
            return $collection;
        }

        if (pathinfo($this->file, PATHINFO_EXTENSION) === 'csv') {
            return $this->csvHistoricEvents($enabledTypes);
        }

        $content = trim((string) file_get_contents($this->file));
        if ($content === '') {
            return $collection;
        }

        foreach (preg_split('/\r\n\r\n|\n\n|\r\r/', $content) ?: [] as $record) {
            $record = trim($record);
            if ($record === '' || !$this->recordTypeIsEnabled($record, $enabledTypes)) {
                continue;
            }

            $collection->push($this->replacePlaceholders($record));
        }

        return $collection;
    }

    /**
     * @param array<string,bool> $enabledTypes
     */
    private function csvHistoricEvents(array $enabledTypes): Collection
    {
        $collection = new Collection();
        $handle = fopen($this->file, 'rb');

        if ($handle === false) {
            return $collection;
        }

        while (($row = fgetcsv($handle, 0, ';', '"', '\\')) !== false) {
            if (($row[0] ?? '') === 'date' || str_starts_with($row[0] ?? '', '#')) {
                continue;
            }

            [$date, $event, $note, $type] = array_pad($row, 4, '');
            if ($event === '' || $type === '' || $date === '') {
                continue;
            }

            $record = '1 EVEN ' . $event . "\n" .
                '2 TYPE {{type:' . $type . "}}\n" .
                '2 DATE ' . $date . "\n" .
                '2 NOTE ' . $note;

            if ($this->recordTypeIsEnabled($record, $enabledTypes)) {
                $collection->push($this->replacePlaceholders($record));
            }
        }

        fclose($handle);

        return $collection;
    }

    /**
     * @param array<string,bool> $enabledTypes
     */
    private function recordTypeIsEnabled(string $record, array $enabledTypes): bool
    {
        preg_match_all('/\{\{type:([^}]+)\}\}/', $record, $matches);
        if ($matches[1] === []) {
            return true;
        }

        foreach ($matches[1] as $label) {
            $typeId = $this->typeIdForLabel($label);
            if (($enabledTypes[$typeId] ?? false) === true) {
                return true;
            }
        }

        return false;
    }

    private function replacePlaceholders(string $record): string
    {
        $record = str_replace('{{wikipedia}}', 'de', $record);

        foreach ($this->typeOptions as $label) {
            $record = str_replace('{{type:' . $label . '}}', $this->translateTypeLabel($label), $record);
        }

        return $record;
    }

    private function typeIdForLabel(string $label): string
    {
        $typeId = array_search($label, $this->typeOptions, true);

        return is_string($typeId) ? $typeId : $label;
    }

    private function translateTypeLabel(string $label): string
    {
        return match ($label) {
            'Historic event: Switzerland' => I18N::translate('Historic event: Switzerland'),
            'revolt' => I18N::translate('revolt'),
            'siege' => I18N::translate('siege'),
            'blockade' => I18N::translate('blockade'),
            'civil war' => I18N::translate('civil war'),
            'conquest' => I18N::translate('conquest'),
            'feud' => I18N::translate('feud'),
            'campaign' => I18N::translate('campaign'),
            'combat' => I18N::translate('combat'),
            'invasion' => I18N::translate('invasion'),
            'struggle' => I18N::translate('struggle'),
            'conflict' => I18N::translate('conflict'),
            'crusade' => I18N::translate('crusade'),
            'war' => I18N::translate('war'),
            'massacre' => I18N::translate('massacre'),
            'offensive' => I18N::translate('offensive'),
            'revolution' => I18N::translate('revolution'),
            'battle' => I18N::translate('battle'),
            'naval battle' => I18N::translate('naval battle'),
            'operation' => I18N::translate('operation'),
            default => $label,
        };
    }

    private function translateLanguageId(string $languageId): string
    {
        return match ($languageId) {
            'de' => MoreI18N::xlate('German'),
            default => $languageId,
        };
    }
}
