<?php

declare(strict_types=1);

namespace Hartenthaler\WebtreesModules\History\HhHistoricEvents;

use Fisharebest\Webtrees\I18N;
use Fisharebest\Webtrees\Webtrees;
use Hartenthaler\WebtreesModules\History\HhHistoricEvents\Http\HttpGetClient;
use Hartenthaler\WebtreesModules\History\HhHistoricEvents\Internationalization\MoreI18N;
use Illuminate\Support\Collection;
use Psr\Http\Client\ClientExceptionInterface;

use function array_values;
use function checkdate;
use function basename;
use function dirname;
use function file_exists;
use function file_get_contents;
use function file_put_contents;
use function filemtime;
use function fclose;
use function fgetcsv;
use function fopen;
use function glob;
use function implode;
use function in_array;
use function is_array;
use function is_dir;
use function is_file;
use function json_decode;
use function json_encode;
use function ksort;
use function mkdir;
use function pathinfo;
use function parse_url;
use function preg_match;
use function preg_replace;
use function strtolower;
use function sprintf;
use function str_replace;
use function str_starts_with;
use function substr;
use function time;
use function trim;
use function version_compare;

use const PATHINFO_FILENAME;
use const PHP_URL_SCHEME;

final class GrampsCsvEventProvider implements EventDataProviderInterface
{
    private const SOURCE_API_URL = 'https://api.github.com/repos/kajmikkelsen/HistContext/contents';
    private const SOURCE_URL = 'https://github.com/kajmikkelsen/HistContext';
    private const SOURCE_CACHE_TTL = 86400;
    private const EXCLUDED_CSV_FILE_NAMES = [
        'GermanChancellorsPresidents.csv',
        'swiss-historic-events.csv',
    ];

    /**
     * @param list<string> $folders Folders in descending priority order
     */
    public function __construct(
        private readonly array $folders,
        private readonly HttpGetClient $httpClient
    ) {
    }

    public function id(): string
    {
        return 'gramps-historical-facts';
    }

    public function title(): string
    {
        return I18N::translate('Gramps-compatible CSV historical facts');
    }

    public function description(): string
    {
        return I18N::translate('Historical facts from the Gramps HistContext collection and CSV files provided by administrators or webmasters.');
    }

    public function sourceTitle(): string
    {
        return I18N::translate('Gramps HistContext repository (bundled files)');
    }

    public function sourceUrl(): string
    {
        return self::SOURCE_URL;
    }

    public function sourceStatus(): string
    {
        $remoteVersions = $this->remoteFileVersions();
        if ($remoteVersions === []) {
            return '';
        }

        $updates = [];
        $missing = [];
        $latestLocalVersion = '0.0';
        $latestRemoteVersion = '0.0';
        $localVersions = $this->localFileVersions();

        foreach ($localVersions as $dataSet => $localVersion) {
            $latestLocalVersion = $this->maxVersion($latestLocalVersion, $localVersion);

            if (!isset($remoteVersions[$dataSet])) {
                continue;
            }

            $latestRemoteVersion = $this->maxVersion($latestRemoteVersion, $remoteVersions[$dataSet]);

            if (version_compare($remoteVersions[$dataSet], $localVersion, '>')) {
                $updates[] = sprintf('%s: %s -> %s', $dataSet, $localVersion, $remoteVersions[$dataSet]);
            }
        }

        foreach ($remoteVersions as $dataSet => $remoteVersion) {
            if (!isset($localVersions[$dataSet]) && !$this->ignoreRemoteDataSet($dataSet)) {
                $missing[] = sprintf('%s (%s)', $dataSet, $remoteVersion);
            }
        }

        $messages = [];

        if ($updates !== []) {
            $messages[] = I18N::translate('Newer Gramps data files are available: %s', implode(', ', $updates));
        }

        if ($missing !== []) {
            $messages[] = I18N::translate('Additional Gramps data files are available: %s', implode(', ', $missing));
        }

        if ($messages !== []) {
            return implode(' ', $messages);
        }

        return I18N::translate(
            'Included Gramps data files are current. Local version: %s. Latest source version found: %s.',
            $latestLocalVersion,
            $latestRemoteVersion
        );
    }

    public function eventLanguageOptions(): array
    {
        $languages = [
            'da' => MoreI18N::xlate('Danish'),
            'en' => MoreI18N::xlate('English'),
            'sv' => MoreI18N::xlate('Swedish'),
            'uk' => MoreI18N::xlate('Ukrainian'),
        ];

        foreach ($this->csvFiles() as $file) {
            $languageId = $this->fileLanguageId($file);
            if ($languageId !== '' && !isset($languages[$languageId])) {
                $languages[$languageId] = $this->languageLabel($languageId);
            }
        }

        ksort($languages);

        return $languages;
    }

    public function typeOptions(): array
    {
        $options = [];
        foreach ($this->csvFiles() as $file) {
            $id = pathinfo($file, PATHINFO_FILENAME);
            $options[$id] = $this->csvFileLabel($file);
        }

        return $options;
    }

    public function typeLanguage(string $typeId): string
    {
        return $this->languageLabel($this->typeLanguageId($typeId));
    }

    public function typeLanguageId(string $typeId): string
    {
        foreach ($this->csvFiles() as $file) {
            if (pathinfo($file, PATHINFO_FILENAME) === $typeId) {
                $languageId = $this->fileLanguageId($file);
                if ($languageId !== '') {
                    return $languageId;
                }

                break;
            }
        }

        return match ($typeId) {
            'da_DK_data_v1_0' => 'da',
            'sv_SE_data_v1_0' => 'sv',
            'uk_UA_data_v1_0' => 'uk',
            'default_data_v1_0',
            'en_US_data_v1_0',
            'en_US_involuntary_v1_0',
            'en_US_prejudice_v1_0',
            'pandemi_v1_1' => 'en',
            default => '',
        };
    }

    public function typeRegion(string $typeId): string
    {
        foreach ($this->csvFiles() as $file) {
            if (pathinfo($file, PATHINFO_FILENAME) === $typeId) {
                return $this->csvFileMetadata($file)['REGION'] ?? '';
            }
        }

        return '';
    }

    public function enabledByDefault(): bool
    {
        return true;
    }

    public function typeEnabledByDefault(string $typeId): bool
    {
        return true;
    }

    public function typeIsCustom(string $typeId): bool
    {
        $customFolder = $this->folders[0] ?? '';

        return $customFolder !== '' && is_file($customFolder . $typeId . '.csv');
    }

    public function typeOverridesBundled(string $typeId): bool
    {
        $customFolder = $this->folders[0] ?? '';
        $bundledFolder = $this->folders[1] ?? '';

        return $customFolder !== ''
            && $bundledFolder !== ''
            && is_file($customFolder . $typeId . '.csv')
            && is_file($bundledFolder . $typeId . '.csv');
    }

    /**
     * @return array<string,string>
     */
    public function overriddenBundledTypeOptions(): array
    {
        $customFolder = $this->folders[0] ?? '';
        $bundledFolder = $this->folders[1] ?? '';
        if ($customFolder === '' || $bundledFolder === '') {
            return [];
        }

        $options = [];
        foreach (glob($bundledFolder . '*.csv') ?: [] as $file) {
            $fileName = basename($file);
            if (in_array($fileName, self::EXCLUDED_CSV_FILE_NAMES, true) || !is_file($customFolder . $fileName)) {
                continue;
            }

            $options[pathinfo($file, PATHINFO_FILENAME)] = $this->csvFileLabel($file);
        }

        ksort($options);

        return $options;
    }

    public function hasCustomTypes(): bool
    {
        foreach ($this->csvFiles() as $file) {
            if ($this->typeIsCustom(pathinfo($file, PATHINFO_FILENAME))) {
                return true;
            }
        }

        return false;
    }

    public function historicEvents(string $languageTag, array $enabledTypes): Collection
    {
        $collection = new Collection();
        $defaultEventType = I18N::translate('Historic event');

        foreach ($this->csvFiles() as $file) {
            $typeId = pathinfo($file, PATHINFO_FILENAME);
            if (($enabledTypes[$typeId] ?? false) !== true) {
                continue;
            }

            $topic = $this->sanitizeCsvValue($this->csvFileMetadata($file)['TOPIC'] ?? '');

            foreach ($this->loadCsvFile($file) as $event) {
                $date = $event['toDate'] === ''
                    ? $event['fromDate']
                    : 'FROM ' . $event['fromDate'] . ' TO ' . $event['toDate'];
                $eventType = $event['category'] !== ''
                    ? $event['category']
                    : ($topic !== '' ? $topic : $defaultEventType);
                $gedcom = '1 EVEN ' . $event['event'] .
                    "\n2 TYPE " . $eventType .
                    "\n2 DATE " . $date;

                if ($event['link'] !== '') {
                    $gedcom .= "\n2 NOTE [link](" . $event['link'] . ' )';
                }

                $collection->push($gedcom);
            }
        }

        return $collection;
    }

    /**
     * @return string[]
     */
    private function csvFiles(): array
    {
        $filesByName = [];

        // Folders are ordered by priority. Custom files therefore replace
        // bundled files with the same basename without modifying the module.
        foreach ($this->folders as $folder) {
            foreach (glob($folder . '*.csv') ?: [] as $file) {
                $fileName = basename($file);

                if (in_array($fileName, self::EXCLUDED_CSV_FILE_NAMES, true) || isset($filesByName[$fileName])) {
                    continue;
                }

                $filesByName[$fileName] = $file;
            }
        }

        ksort($filesByName);

        return array_values($filesByName);
    }

    /**
     * @return array<string,string>
     */
    private function localFileVersions(): array
    {
        $versions = [];
        foreach ($this->csvFiles() as $file) {
            $name = pathinfo($file, PATHINFO_FILENAME);
            $version = $this->versionFromFileName($name);
            if ($version !== null) {
                $versions[$this->dataSetFromFileName($name)] = $version;
            }
        }

        return $versions;
    }

    /**
     * @return array<string,string>
     */
    private function remoteFileVersions(): array
    {
        $cacheFile = Webtrees::DATA_DIR . 'cache/hh-historic-events/gramps-source-files.json';
        if (file_exists($cacheFile) && filemtime($cacheFile) !== false && time() - filemtime($cacheFile) < self::SOURCE_CACHE_TTL) {
            $cached = file_get_contents($cacheFile);
            if ($cached !== false && $cached !== '') {
                $versions = json_decode($cached, true);
                if (is_array($versions)) {
                    return $versions;
                }
            }
        }

        try {
            $files = json_decode($this->httpClient->get(self::SOURCE_API_URL, [
                'User-Agent' => 'hh-historic-events',
            ]), true);
        } catch (ClientExceptionInterface) {
            return [];
        }

        if (!is_array($files)) {
            return [];
        }

        $versions = [];
        foreach ($files as $file) {
            if (!is_array($file) || !isset($file['name']) || !is_string($file['name'])) {
                continue;
            }

            $name = pathinfo($file['name'], PATHINFO_FILENAME);
            $version = $this->versionFromFileName($name);
            if ($version !== null) {
                $versions[$this->dataSetFromFileName($name)] = $version;
            }
        }

        $cacheDirectory = dirname($cacheFile);
        if (!is_dir($cacheDirectory)) {
            @mkdir($cacheDirectory, 0775, true);
        }

        $contents = json_encode($versions);
        if ($contents !== false) {
            @file_put_contents($cacheFile, $contents);
        }

        return $versions;
    }

    private function dataSetFromFileName(string $fileName): string
    {
        return (string) preg_replace('/_v\d+_\d+$/', '', $fileName);
    }

    private function versionFromFileName(string $fileName): ?string
    {
        if (preg_match('/_v(\d+)_(\d+)$/', $fileName, $matches) !== 1) {
            return null;
        }

        return $matches[1] . '.' . $matches[2];
    }

    private function maxVersion(string $versionA, string $versionB): string
    {
        return version_compare($versionA, $versionB, '>=') ? $versionA : $versionB;
    }

    private function ignoreRemoteDataSet(string $dataSet): bool
    {
        return $dataSet === 'custom';
    }

    /**
     * @return array<string,string>
     */
    private function csvFileMetadata(string $file): array
    {
        if (!is_file($file)) {
            return [];
        }

        $handle = fopen($file, 'r');
        if ($handle === false) {
            return [];
        }

        $metadata = [];
        while (($row = fgetcsv($handle, 0, ';', '"', '\\')) !== false) {
            $firstColumn = trim($row[0] ?? '');
            if (preg_match('/^##\s+([A-Z][A-Z0-9_-]*):\s*(.*)$/', $firstColumn, $matches) === 1) {
                $metadata[$matches[1]] = trim($matches[2]);
                continue;
            }

            if ($firstColumn !== '' && !str_starts_with($firstColumn, '#') && $firstColumn !== 'From date') {
                break;
            }
        }

        fclose($handle);

        return $metadata;
    }

    private function csvFileLabel(string $file): string
    {
        $metadata = $this->csvFileMetadata($file);
        $labelParts = [];

        if (($metadata['TOPIC'] ?? '') !== '') {
            $labelParts[] = $metadata['TOPIC'];
        }
        if (($metadata['REGION'] ?? '') !== '') {
            $labelParts[] = $metadata['REGION'];
        }
        $labelParts[] = basename($file);

        return implode(' - ', $labelParts);
    }

    private function fileLanguageId(string $file): string
    {
        $language = strtolower($this->csvFileMetadata($file)['LANGUAGE'] ?? '');

        return str_replace('_', '-', $language);
    }

    private function languageLabel(string $languageId): string
    {
        return match ($languageId) {
            'da' => MoreI18N::xlate('Danish'),
            'sv' => MoreI18N::xlate('Swedish'),
            'uk' => MoreI18N::xlate('Ukrainian'),
            'en' => MoreI18N::xlate('English'),
            'de' => MoreI18N::xlate('German'),
            'nl' => MoreI18N::xlate('Dutch'),
            '' => '',
            default => $languageId,
        };
    }

    /**
     * @return array<int,array<string,string>>
     */
    private function loadCsvFile(string $file): array
    {
        if (!is_file($file)) {
            return [];
        }

        $events = [];
        $handle = fopen($file, 'r');
        if ($handle === false) {
            return [];
        }

        while (($row = fgetcsv($handle, 0, ';', '"', '\\')) !== false) {
            if (($row[0] ?? '') === '' || str_starts_with($row[0], '#')) {
                continue;
            }

            if (($row[0] ?? '') === 'From date' && ($row[1] ?? '') === 'To date') {
                continue;
            }

            $events[] = [
                'fromDate' => $this->normalizeCsvDate($row[0] ?? ''),
                'toDate' => $this->normalizeCsvDate($row[1] ?? ''),
                'event' => $this->sanitizeCsvValue($row[2] ?? ''),
                'link' => $this->sanitizeCsvUrl($row[3] ?? ''),
                'category' => $this->sanitizeCsvValue($row[4] ?? ''),
            ];
        }

        fclose($handle);

        return $events;
    }

    private function normalizeCsvDate(string $date): string
    {
        $date = $this->sanitizeCsvValue($date);

        if (strcasecmp($date, 'Today') === 0) {
            return strtoupper(date('j M Y'));
        }

        if (preg_match('/\A(\d{4})-(\d{2})-(\d{2})\z/', $date, $matches) === 1) {
            $year = (int) $matches[1];
            $month = (int) $matches[2];
            $day = (int) $matches[3];

            return checkdate($month, $day, $year)
                ? $day . ' ' . $this->gedcomMonth($month) . ' ' . $year
                : $date;
        }

        if (preg_match('/\A(\d{4})-(\d{2})\z/', $date, $matches) === 1) {
            $year = (int) $matches[1];
            $month = (int) $matches[2];

            return $year > 0 && $month >= 1 && $month <= 12
                ? $this->gedcomMonth($month) . ' ' . $year
                : $date;
        }

        return $date;
    }

    private function gedcomMonth(int $month): string
    {
        return [1 => 'JAN', 'FEB', 'MAR', 'APR', 'MAY', 'JUN', 'JUL', 'AUG', 'SEP', 'OCT', 'NOV', 'DEC'][$month];
    }

    private function sanitizeCsvValue(string $value): string
    {
        return trim((string) preg_replace('/[\x00-\x1F\x7F]+/u', ' ', $value));
    }

    private function sanitizeCsvUrl(string $url): string
    {
        $url = $this->sanitizeCsvValue($url);
        if ($url === '' || filter_var($url, FILTER_VALIDATE_URL) === false) {
            return '';
        }

        $scheme = strtolower((string) parse_url($url, PHP_URL_SCHEME));

        return in_array($scheme, ['http', 'https'], true) ? $url : '';
    }
}
