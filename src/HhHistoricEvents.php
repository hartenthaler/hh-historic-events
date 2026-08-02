<?php

declare(strict_types=1);

namespace Hartenthaler\WebtreesModules\History\HhHistoricEvents;

use Fisharebest\Webtrees\FlashMessages;
use Fisharebest\Webtrees\I18N;
use Fisharebest\Webtrees\Module\AbstractModule;
use Fisharebest\Webtrees\Module\ModuleConfigInterface;
use Fisharebest\Webtrees\Module\ModuleConfigTrait;
use Fisharebest\Webtrees\Module\ModuleCustomInterface;
use Fisharebest\Webtrees\Module\ModuleCustomTrait;
use Fisharebest\Webtrees\Module\ModuleHistoricEventsInterface;
use Fisharebest\Webtrees\Module\ModuleHistoricEventsTrait;
use Fisharebest\Webtrees\Services\ModuleService;
use Fisharebest\Webtrees\Session;
use Fisharebest\Webtrees\View;
use Fisharebest\Webtrees\Webtrees;
use Hartenthaler\WebtreesModules\History\HhHistoricEvents\Http\HttpGetClient;
use Illuminate\Support\Collection;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use RuntimeException;

use function array_values;
use function dirname;
use function explode;
use function file_exists;
use function file_get_contents;
use function file_put_contents;
use function filemtime;
use function fclose;
use function fopen;
use function implode;
use function is_array;
use function is_dir;
use function is_file;
use function json_decode;
use function json_encode;
use function md5;
use function mkdir;
use function redirect;
use function realpath;
use function rtrim;
use function rawurlencode;
use function str_contains;
use function str_ends_with;
use function substr;
use function time;
use function usort;

final class HhHistoricEvents extends AbstractModule implements ModuleCustomInterface, ModuleHistoricEventsInterface, ModuleConfigInterface
{
    use ModuleCustomTrait;
    use ModuleHistoricEventsTrait;
    use ModuleConfigTrait;

    public const CUSTOM_TITLE = 'Historic Events';
    public const CUSTOM_MODULE = 'hh-historic-events';
    public const CUSTOM_AUTHOR = 'Hermann Hartenthaler';
    public const CUSTOM_GITHUB_USER = 'hartenthaler';
    public const GITHUB_REPO = self::CUSTOM_GITHUB_USER . '/' . self::CUSTOM_MODULE;
    public const CUSTOM_VERSION = '0.1.0';
    public const CUSTOM_WEBSITE = 'https://github.com/' . self::GITHUB_REPO . '/';
    public const CUSTOM_LAST = 'https://github.com/' . self::CUSTOM_GITHUB_USER . '/' .
        self::CUSTOM_MODULE . '/raw/main/latest-version.txt';
    private const EVENTS_CACHE_TTL = 86400;
    private const CUSTOM_CSV_FORM_SESSION_KEY = 'hh-historic-events-custom-csv-form';
    private const LEGACY_MODULE_NAMES = [
        'german-wars-battles-worldwide',
        'german-chancellors-presidents',
        'swiss-historic-events',
        'gramps-historical-facts',
    ];

    public function __construct(
        private readonly HttpGetClient $httpClient,
        private readonly ModuleService $moduleService
    ) {
    }

    public function boot(): void
    {
        View::registerNamespace($this->name(), $this->resourcesFolder() . 'views/');
    }

    public function title(): string
    {
        return I18N::translate('Historic Events');
    }

    public function description(): string
    {
        return I18N::translate('Historical facts from selectable data collections');
    }

    public function customModuleAuthorName(): string
    {
        return self::CUSTOM_AUTHOR;
    }

    public function customModuleVersion(): string
    {
        return self::CUSTOM_VERSION;
    }

    public function customModuleLatestVersionUrl(): string
    {
        return self::CUSTOM_LAST;
    }

    public function customModuleSupportUrl(): string
    {
        return self::CUSTOM_WEBSITE;
    }

    /**
     * Privacy information consumed by hh_legal_notice.
     *
     * @return array{third_party_services:list<array{service_id?:string,name:string,url:string,country:string,privacy_url:string,description:string,data:list<string>}>,security_measures:list<string>}
     */
    public function privacyNotices(): array
    {
        foreach ($this->providerFactory()->providers() as $provider) {
            if (!$provider instanceof GermanChancellorsPresidentsWikidataProvider) {
                continue;
            }

            if (!$this->providerIsEnabled($provider) || !$this->providerHasEnabledLanguage($provider)) {
                return [
                    'third_party_services' => [],
                    'security_measures' => [],
                ];
            }

            return [
                'third_party_services' => [
                    [
                        'service_id' => 'wikimedia-foundation',
                        'name' => 'Wikidata',
                        'url' => 'https://www.wikidata.org/',
                        'country' => 'United States',
                        'privacy_url' => 'https://foundation.wikimedia.org/wiki/Policy:Privacy_policy',
                        'description' => I18N::translate('The Historic Events module can query Wikidata for selected historical event data. Responses are cached locally for 24 hours to reduce external requests.'),
                        'data' => [
                            I18N::translate('Server IP address and technical request metadata.'),
                            I18N::translate('Requested Wikidata entities for the enabled historical event provider.'),
                        ],
                    ],
                ],
                'security_measures' => [
                    I18N::translate('Wikidata responses are cached locally for 24 hours to reduce external requests.'),
                ],
            ];
        }

        return [
            'third_party_services' => [],
            'security_measures' => [],
        ];
    }

    public function isEnabledByDefault(): bool
    {
        return false;
    }

    public function resourcesFolder(): string
    {
        return __DIR__ . '/../resources/';
    }

    /**
     * @return array<string,string>
     */
    public function customTranslations(string $language): array
    {
        $baseLanguage = explode('-', $language)[0];
        $languageFiles = $language === $baseLanguage ? [$language] : [$language, $baseLanguage];

        foreach ($languageFiles as $languageFile) {
            $poFile = $this->resourcesFolder() . 'lang/' . $languageFile . '.po';
            $moFile = $this->resourcesFolder() . 'lang/' . $languageFile . '.mo';

            foreach ([$moFile, $poFile] as $file) {
                if (!is_file($file)) {
                    continue;
                }

                // webtrees 2.3 uses its own stream-based translation loader.
                if (class_exists(\Fisharebest\Webtrees\I18N\Translation::class)) {
                    $stream = fopen($file, 'rb');

                    if ($stream === false) {
                        continue;
                    }

                    try {
                        $translation = str_ends_with($file, '.mo')
                            ? \Fisharebest\Webtrees\I18N\Translation::fromMoStream($stream)
                            : \Fisharebest\Webtrees\I18N\Translation::fromPoStream($stream);

                        return $translation->toArray();
                    } finally {
                        fclose($stream);
                    }
                }

                // webtrees 2.2 uses the former file-based localization package.
                if (class_exists(\Fisharebest\Localization\Translation::class)) {
                    return (new \Fisharebest\Localization\Translation($file))->asArray();
                }
            }
        }

        return [];
    }

    public function historicEventsAll(string $language_tag = 'en'): Collection
    {
        $cachedEvents = $this->readEventsCache($language_tag);
        if ($cachedEvents !== null) {
            return new Collection($cachedEvents);
        }

        $events = [];

        foreach ($this->providerFactory()->providers() as $provider) {
            if (!$this->providerIsEnabled($provider) || !$this->providerHasEnabledLanguage($provider)) {
                continue;
            }

            foreach ($provider->historicEvents($language_tag, $this->enabledTypes($provider)) as $event) {
                $events[] = $event;
            }
        }

        $this->writeEventsCache($language_tag, $events);

        return new Collection($events);
    }

    public function getAdminAction(ServerRequestInterface $request): ResponseInterface
    {
        $this->layout = 'layouts/administration';
        $this->ensureCustomDataFolder();
        $query = $request->getQueryParams();
        $customCsvManager = $this->customCsvManager();
        $customCsvFiles = $customCsvManager->files();
        foreach ($customCsvFiles as &$customCsvFile) {
            $customCsvFile['edit_url'] = $this->customCsvEditorUrl($customCsvFile['filename']);
        }
        unset($customCsvFile);
        $selectedCustomCsv = null;
        $selectedFilename = (string) ($query['csv_file'] ?? '');
        if ($selectedFilename !== '') {
            try {
                $selectedCustomCsv = $customCsvManager->read($selectedFilename);
            } catch (RuntimeException $exception) {
                FlashMessages::addMessage(I18N::translate($exception->getMessage()), 'danger');
            }
        }
        $submittedCustomCsv = Session::pull(self::CUSTOM_CSV_FORM_SESSION_KEY);
        if (is_array($submittedCustomCsv) && ($submittedCustomCsv['filename'] ?? '') === $selectedFilename) {
            $selectedCustomCsv = [
                'filename' => $selectedFilename,
                'metadata' => is_array($submittedCustomCsv['metadata'] ?? null) ? $submittedCustomCsv['metadata'] : [],
                'rows' => is_array($submittedCustomCsv['rows'] ?? null) ? $submittedCustomCsv['rows'] : [],
            ];
        }

        return $this->viewResponse($this->name() . '::settings', [
            'title' => $this->title(),
            'description' => $this->description(),
            'languages' => $this->adminLanguages(),
            'providers' => $this->adminProviders(),
            'custom_data_folder' => $this->customDataFolderDisplay(),
            'custom_csv_documentation_url' => self::CUSTOM_WEBSITE . 'blob/main/docs/custom-csv-format.md',
            'custom_csv_example_url' => self::CUSTOM_WEBSITE . 'raw/main/docs/examples/custom-family-events-de.csv',
            'active_legacy_modules' => $this->activeLegacyModules(),
            'custom_csv_files' => $customCsvFiles,
            'selected_custom_csv' => $selectedCustomCsv,
        ]);
    }

    public function postAdminAction(ServerRequestInterface $request): ResponseInterface
    {
        $params = (array) $request->getParsedBody();
        $action = (string) ($params['action'] ?? 'save-preferences');

        if ($action !== 'save-preferences') {
            return $this->handleCustomCsvAction($action, $params);
        }

        foreach ($this->providerFactory()->providers() as $provider) {
            $this->setPreference($this->providerPreferenceKey($provider->id()), isset($params[$this->providerFormKey($provider->id())]) ? '1' : '0');

            foreach ($provider->eventLanguageOptions() as $languageId => $languageLabel) {
                $this->setPreference(
                    $this->providerLanguagePreferenceKey($provider->id(), $languageId),
                    isset($params[$this->providerLanguageFormKey($provider->id(), $languageId)]) ? '1' : '0'
                );
            }

            foreach ($provider->typeOptions() as $typeId => $label) {
                $this->setPreference($this->typePreferenceKey($provider->id(), $typeId), isset($params[$this->typeFormKey($provider->id(), $typeId)]) ? '1' : '0');
            }
        }

        FlashMessages::addMessage(I18N::translate('The preferences for the module "%s" have been updated.', $this->title()), 'success');

        return redirect($this->getConfigLink());
    }

    /**
     * @param array<string,mixed> $params
     */
    private function handleCustomCsvAction(string $action, array $params): ResponseInterface
    {
        $manager = $this->customCsvManager();
        $selectedFilename = '';

        try {
            if ($action === 'create-custom-csv') {
                $selectedFilename = (string) ($params['new_filename'] ?? '');
                $manager->create($selectedFilename, $this->customCsvMetadata($params));
                FlashMessages::addMessage(I18N::translate('The custom CSV file has been created.'), 'success');
            } elseif ($action === 'save-custom-csv') {
                $selectedFilename = (string) ($params['filename'] ?? '');
                $result = $manager->save($selectedFilename, $this->customCsvMetadata($params), $this->customCsvRows($params));
                FlashMessages::addMessage(I18N::translate('The custom CSV file has been saved.'), 'success');
                $this->addCustomCsvValidationMessages($result);
            } elseif ($action === 'save-custom-csv-as') {
                $result = $manager->saveAs(
                    (string) ($params['copy_filename'] ?? ''),
                    $this->customCsvMetadata($params),
                    $this->customCsvRows($params)
                );
                $selectedFilename = (string) ($params['copy_filename'] ?? '');
                FlashMessages::addMessage(I18N::translate('The custom CSV file has been saved under the new filename.'), 'success');
                $this->addCustomCsvValidationMessages($result);
            } elseif ($action === 'delete-custom-csv') {
                $manager->delete((string) ($params['filename'] ?? ''));
                FlashMessages::addMessage(I18N::translate('The custom CSV file has been deleted.'), 'success');
            } else {
                throw new RuntimeException('Unknown CSV file action.');
            }

            $this->clearEventsCache();
        } catch (RuntimeException $exception) {
            FlashMessages::addMessage(I18N::translate($exception->getMessage()), 'danger');
            $selectedFilename = (string) ($params['filename'] ?? '');
            if ($selectedFilename !== '') {
                Session::put(self::CUSTOM_CSV_FORM_SESSION_KEY, [
                    'filename' => $selectedFilename,
                    'metadata' => $this->customCsvMetadata($params),
                    'rows' => $this->customCsvRows($params),
                ]);
            }
        }

        return redirect($this->customCsvEditorUrl($selectedFilename));
    }

    /** @param array{invalid_dates:int,invalid_periods:int} $result */
    private function addCustomCsvValidationMessages(array $result): void
    {
        if ($result['invalid_dates'] > 0) {
            FlashMessages::addMessage(I18N::plural(
                'One invalid date value was omitted.',
                '%s invalid date values were omitted.',
                $result['invalid_dates'],
                I18N::number($result['invalid_dates'])
            ), 'warning');
        }
        if ($result['invalid_periods'] > 0) {
            FlashMessages::addMessage(I18N::plural(
                'One end date before its start date was omitted.',
                '%s end dates before their start dates were omitted.',
                $result['invalid_periods'],
                I18N::number($result['invalid_periods'])
            ), 'warning');
        }
    }

    /** @param array<string,mixed> $params
     *  @return array<string,string>
     */
    private function customCsvMetadata(array $params): array
    {
        $metadata = [];
        foreach (CustomCsvFileManager::METADATA_FIELDS as $field) {
            $metadata[$field] = (string) ($params['metadata'][$field] ?? '');
        }

        return $metadata;
    }

    /** @param array<string,mixed> $params
     *  @return list<array{from_date:string,to_date:string,event:string,link:string,category:string}>
     */
    private function customCsvRows(array $params): array
    {
        $rows = [];
        foreach ((array) ($params['rows'] ?? []) as $row) {
            if (!is_array($row)) {
                continue;
            }
            $rows[] = [
                'from_date' => (string) ($row['from_date'] ?? ''),
                'to_date' => (string) ($row['to_date'] ?? ''),
                'event' => (string) ($row['event'] ?? ''),
                'link' => (string) ($row['link'] ?? ''),
                'category' => (string) ($row['category'] ?? ''),
            ];
        }

        return $rows;
    }

    private function customCsvManager(): CustomCsvFileManager
    {
        return new CustomCsvFileManager($this->customDataFolder());
    }

    private function customCsvEditorUrl(string $filename): string
    {
        if ($filename === '') {
            return $this->getConfigLink();
        }

        return $this->getConfigLink() . (str_contains($this->getConfigLink(), '?') ? '&' : '?')
            . 'csv_file=' . rawurlencode($filename);
    }

    private function clearEventsCache(): void
    {
        foreach (glob(Webtrees::DATA_DIR . 'cache/hh-historic-events/events-*.json') ?: [] as $cacheFile) {
            @unlink($cacheFile);
        }
    }

    /**
     * @return array<int,array<string,mixed>>
     */
    private function adminProviders(): array
    {
        $providers = [];
        $providerOrder = 0;

        foreach ($this->providerFactory()->providers() as $provider) {
            $types = [];
            $typeOrder = 0;
            foreach ($provider->typeOptions() as $typeId => $label) {
                $isCustom = $provider instanceof GrampsCsvEventProvider && $provider->typeIsCustom($typeId);
                $types[] = [
                    'id' => $typeId,
                    'label' => $label,
                    'language' => $provider->typeLanguage($typeId),
                    'region' => $provider->typeRegion($typeId),
                    'form_key' => $this->typeFormKey($provider->id(), $typeId),
                    'enabled' => $this->typeIsEnabled($provider, $typeId),
                    'custom' => $isCustom,
                    'overrides_bundled' => $provider instanceof GrampsCsvEventProvider
                        && $provider->typeOverridesBundled($typeId),
                    'order' => $typeOrder++,
                ];
            }

            usort($types, static fn (array $left, array $right): int =>
                ($right['custom'] <=> $left['custom']) ?: ($left['order'] <=> $right['order']));

            $providers[] = [
                'id' => $provider->id(),
                'title' => $provider->title(),
                'description' => $provider->description(),
                'source_title' => $provider->sourceTitle(),
                'source_url' => $provider->sourceUrl(),
                'source_status' => $provider->sourceStatus(),
                'languages' => implode(', ', $provider->eventLanguageOptions()),
                'form_key' => $this->providerFormKey($provider->id()),
                'enabled' => $this->providerIsEnabled($provider),
                'types' => $types,
                'overridden_bundled_types' => $provider instanceof GrampsCsvEventProvider
                    ? $provider->overriddenBundledTypeOptions()
                    : [],
                'has_custom_types' => $provider instanceof GrampsCsvEventProvider
                    && $provider->hasCustomTypes(),
                'order' => $providerOrder++,
            ];
        }

        usort($providers, static fn (array $left, array $right): int =>
            ($right['has_custom_types'] <=> $left['has_custom_types']) ?: ($left['order'] <=> $right['order']));

        return $providers;
    }

    /**
     * @return array<int,array<string,mixed>>
     */
    private function adminLanguages(): array
    {
        $languages = [];

        foreach ($this->providerFactory()->providers() as $provider) {
            foreach ($provider->eventLanguageOptions() as $languageId => $languageLabel) {
                $collections = [];
                foreach ($provider->typeOptions() as $typeId => $typeLabel) {
                    if ($provider->typeLanguageId($typeId) !== $languageId) {
                        continue;
                    }

                    $collections[] = [
                        'label' => $typeLabel,
                        'region' => $provider->typeRegion($typeId),
                        'custom' => $provider instanceof GrampsCsvEventProvider
                            && $provider->typeIsCustom($typeId),
                    ];
                }

                $languages[$languageId]['id'] = $languageId;
                $languages[$languageId]['label'] = $languageLabel;
                $languages[$languageId]['sources'][] = [
                    'provider_id' => $provider->id(),
                    'provider_title' => $provider->title(),
                    'form_key' => $this->providerLanguageFormKey($provider->id(), $languageId),
                    'enabled' => $this->providerLanguageIsEnabled($provider, $languageId),
                    'collections' => $collections,
                ];
            }
        }

        return array_values($languages);
    }

    private function providerFactory(): EventDataProviderFactory
    {
        return new EventDataProviderFactory(
            $this->resourcesFolder(),
            $this->customDataFolder(),
            $this->httpClient
        );
    }

    private function customDataFolder(): string
    {
        return Webtrees::DATA_DIR . 'modules/' . self::CUSTOM_MODULE . '/data/';
    }

    private function customDataFolderDisplay(): string
    {
        $resolvedFolder = realpath($this->customDataFolder());

        return rtrim($resolvedFolder !== false ? $resolvedFolder : $this->customDataFolder(), '/\\') . DIRECTORY_SEPARATOR;
    }

    private function ensureCustomDataFolder(): void
    {
        $folder = $this->customDataFolder();

        if (!is_dir($folder)) {
            @mkdir($folder, 0775, true);
        }
    }

    /**
     * @return list<array{name:string,title:string}>
     */
    private function activeLegacyModules(): array
    {
        $activeModules = [];

        foreach (self::LEGACY_MODULE_NAMES as $moduleName) {
            $module = $this->moduleService->findByName($moduleName, true);
            if ($module === null || !$module->isEnabled()) {
                continue;
            }

            $activeModules[] = [
                'name' => $moduleName,
                'title' => $module->title(),
            ];
        }

        return $activeModules;
    }

    private function providerIsEnabled(EventDataProviderInterface $provider): bool
    {
        return $this->getPreference($this->providerPreferenceKey($provider->id()), $provider->enabledByDefault() ? '1' : '0') === '1';
    }

    private function providerLanguageIsEnabled(EventDataProviderInterface $provider, string $languageId): bool
    {
        return $this->getPreference($this->providerLanguagePreferenceKey($provider->id(), $languageId), '1') === '1';
    }

    private function providerHasEnabledLanguage(EventDataProviderInterface $provider): bool
    {
        foreach ($provider->eventLanguageOptions() as $languageId => $languageLabel) {
            if ($this->providerLanguageIsEnabled($provider, $languageId)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return array<string,bool>
     */
    private function enabledTypes(EventDataProviderInterface $provider): array
    {
        $enabledTypes = [];
        foreach ($provider->typeOptions() as $typeId => $label) {
            $languageId = $provider->typeLanguageId($typeId);
            $enabledTypes[$typeId] = $this->typeIsEnabled($provider, $typeId)
                && ($languageId === '' || $this->providerLanguageIsEnabled($provider, $languageId));
        }

        return $enabledTypes;
    }

    private function typeIsEnabled(EventDataProviderInterface $provider, string $typeId): bool
    {
        return $this->getPreference(
            $this->typePreferenceKey($provider->id(), $typeId),
            $provider->typeEnabledByDefault($typeId) ? '1' : '0'
        ) === '1';
    }

    private function providerPreferenceKey(string $providerId): string
    {
        return 's_' . substr(md5($providerId), 0, 16);
    }

    private function typePreferenceKey(string $providerId, string $typeId): string
    {
        return 't_' . substr(md5($providerId . '|' . $typeId), 0, 16);
    }

    private function providerLanguagePreferenceKey(string $providerId, string $languageId): string
    {
        return 'l_' . substr(md5($providerId . '|' . $languageId), 0, 16);
    }

    private function providerFormKey(string $providerId): string
    {
        return 'source-' . $providerId;
    }

    private function typeFormKey(string $providerId, string $typeId): string
    {
        return 'type-' . $providerId . '-' . $typeId;
    }

    private function providerLanguageFormKey(string $providerId, string $languageId): string
    {
        return 'language-source-' . $languageId . '-' . $providerId;
    }

    /**
     * @return array<int,string>|null
     */
    private function readEventsCache(string $languageTag): ?array
    {
        $cacheFile = $this->eventsCacheFile($languageTag);

        if (!file_exists($cacheFile) || filemtime($cacheFile) === false || time() - filemtime($cacheFile) >= self::EVENTS_CACHE_TTL) {
            return null;
        }

        $contents = file_get_contents($cacheFile);
        if ($contents === false || $contents === '') {
            return null;
        }

        $events = json_decode($contents, true);

        return is_array($events) ? $events : null;
    }

    /**
     * @param array<int,string> $events
     */
    private function writeEventsCache(string $languageTag, array $events): void
    {
        $cacheFile = $this->eventsCacheFile($languageTag);
        $cacheDirectory = dirname($cacheFile);

        if (!is_dir($cacheDirectory)) {
            @mkdir($cacheDirectory, 0775, true);
        }

        $contents = json_encode($events);
        if ($contents !== false) {
            @file_put_contents($cacheFile, $contents);
        }
    }

    private function eventsCacheFile(string $languageTag): string
    {
        return Webtrees::DATA_DIR . 'cache/hh-historic-events/events-' . md5($languageTag . '|' . $this->configurationSignature()) . '.json';
    }

    private function configurationSignature(): string
    {
        $signature = [];

        foreach ($this->providerFactory()->providers() as $provider) {
            $providerSignature = [
                'id' => $provider->id(),
                'enabled' => $this->providerIsEnabled($provider),
                'languages' => [],
                'types' => [],
            ];

            foreach ($provider->eventLanguageOptions() as $languageId => $languageLabel) {
                $providerSignature['languages'][$languageId] = $this->providerLanguageIsEnabled($provider, $languageId);
            }

            foreach ($provider->typeOptions() as $typeId => $label) {
                $providerSignature['types'][$typeId] = $this->typeIsEnabled($provider, $typeId);
            }

            $signature[] = $providerSignature;
        }

        return (string) json_encode($signature);
    }

}
