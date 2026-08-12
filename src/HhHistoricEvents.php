<?php

declare(strict_types=1);

namespace Hartenthaler\WebtreesModules\History\HhHistoricEvents;

use Fisharebest\Webtrees\Age;
use Fisharebest\Webtrees\Fact;
use Fisharebest\Webtrees\FlashMessages;
use Fisharebest\Webtrees\I18N;
use Fisharebest\Webtrees\Individual;
use Fisharebest\Webtrees\Module\AbstractModule;
use Fisharebest\Webtrees\Module\ModuleConfigInterface;
use Fisharebest\Webtrees\Module\ModuleConfigTrait;
use Fisharebest\Webtrees\Module\ModuleCustomInterface;
use Fisharebest\Webtrees\Module\ModuleCustomTrait;
use Fisharebest\Webtrees\Module\ModuleGlobalInterface;
use Fisharebest\Webtrees\Module\ModuleGlobalTrait;
use Fisharebest\Webtrees\Module\ModuleHistoricEventsInterface;
use Fisharebest\Webtrees\Module\ModuleHistoricEventsTrait;
use Fisharebest\Webtrees\Services\ModuleService;
use Fisharebest\Webtrees\Session;
use Fisharebest\Webtrees\View;
use Fisharebest\Webtrees\Webtrees;
use Hartenthaler\WebtreesModules\History\HhHistoricEvents\Http\HttpGetClient;
use Hartenthaler\WebtreesModules\History\HhHistoricEvents\Internationalization\MoreI18N;
use Illuminate\Database\Capsule\Manager as DB;
use Illuminate\Support\Collection;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\UploadedFileInterface;
use JsonException;
use RuntimeException;

use function array_values;
use function array_filter;
use function array_map;
use function dirname;
use function e;
use function explode;
use function file_exists;
use function file_get_contents;
use function file_put_contents;
use function filemtime;
use function fclose;
use function fopen;
use function implode;
use function in_array;
use function is_array;
use function is_dir;
use function is_file;
use function json_decode;
use function json_encode;
use function trim;
use function md5;
use function mkdir;
use function preg_replace;
use function preg_match_all;
use function redirect;
use function realpath;
use function rtrim;
use function sha1;
use function rawurlencode;
use function str_contains;
use function str_ends_with;
use function substr;
use function time;
use function usort;
use function uksort;

final class HhHistoricEvents extends AbstractModule implements ModuleCustomInterface, ModuleHistoricEventsInterface, ModuleConfigInterface, ModuleGlobalInterface
{
    use ModuleCustomTrait;
    use ModuleHistoricEventsTrait {
        historicEventsForIndividual as private coreHistoricEventsForIndividual;
    }
    use ModuleGlobalTrait;
    use ModuleConfigTrait;

    public const CUSTOM_TITLE = 'Historic Events';
    public const CUSTOM_MODULE = 'hh-historic-events';
    public const CUSTOM_AUTHOR = 'Hermann Hartenthaler';
    public const CUSTOM_GITHUB_USER = 'hartenthaler';
    public const GITHUB_REPO = self::CUSTOM_GITHUB_USER . '/' . self::CUSTOM_MODULE;
    public const CUSTOM_VERSION = '2.2.6.4';
    public const CUSTOM_WEBSITE = 'https://github.com/' . self::GITHUB_REPO . '/';
    public const CUSTOM_LAST = 'https://github.com/' . self::CUSTOM_GITHUB_USER . '/' .
        self::CUSTOM_MODULE . '/raw/main/latest-version.txt';
    private const EVENTS_CACHE_TTL = 86400;
    private const EVENT_IDENTITY_SCHEMA_VERSION_PREFERENCE = 'event_identity_schema_version';
    private const BUNDLED_EQUIVALENCES_LOADED_PREFERENCE = 'bundled_equivalences_loaded';
    private const MAX_EQUIVALENCE_JSON_BYTES = 1048576;
    private const MAX_EQUIVALENCE_JSON_GROUPS = 5000;
    private const MAX_EQUIVALENCE_GROUP_IDENTITIES = 100;
    private const MAX_EQUIVALENCE_EXTERNAL_REFERENCES_LENGTH = 4096;
    private const SHOW_EVENT_AGES_PREFERENCE = 'show_event_ages';
    private const EVENT_AGE_MARKER = "\u{2063}\u{2063}\u{2063}";
    private const CUSTOM_CSV_FORM_SESSION_KEY = 'hh-historic-events-custom-csv-form';
    /**
     * @var array<string, list<string>>
     */
    private const LEGACY_MODULE_NAME_ALIASES = [
        'german-wars-battles-worldwide' => [
            'german-wars-battles-worldwide',
            'german_wars_battles_worldwide',
        ],
        'german-chancellors-presidents' => [
            'german-chancellors-presidents',
            'german-chancellors-and-presidents',
            'german_chancellors_and_presidents',
        ],
        'swiss-historic-events' => [
            'swiss-historic-events',
            'swiss_historic_events',
        ],
        'gramps-historical-facts' => [
            'gramps-historical-facts',
            'gramps_historical_facts',
        ],
    ];

    private const LEGACY_MODULE_TITLES = [
        'Wars and Battles Worldwide 🇩🇪',
        'German Chancellors Presidents',
        'Historic Events: Switzerland 🇨🇭',
        'Gramps Historical Facts',
    ];

    public function __construct(
        private readonly HttpGetClient $httpClient,
        private readonly ModuleService $moduleService
    ) {
    }

    public function boot(): void
    {
        $schemaVersion = (new EventIdentitySchema())->ensureSchema(
            (int) $this->getPreference(self::EVENT_IDENTITY_SCHEMA_VERSION_PREFERENCE, '0')
        );
        if ($schemaVersion !== (int) $this->getPreference(self::EVENT_IDENTITY_SCHEMA_VERSION_PREFERENCE, '0')) {
            $this->setPreference(self::EVENT_IDENTITY_SCHEMA_VERSION_PREFERENCE, (string) $schemaVersion);
        }
        $this->loadBundledEquivalences();
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

    public function headContent(): string
    {
        return '<link rel="stylesheet" href="' . e($this->assetUrl('css/hh-historic-events.css')) . '">';
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

            $providerEvents = $provider->historicEvents($language_tag, $this->enabledTypes($provider), $this->enabledCategories($provider));
            $this->refreshEventIdentityIndex($provider->id(), $provider->title(), $providerEvents->all());
            foreach ($providerEvents as $event) {
                $events[] = $event;
            }
        }

        $events = (new HistoricEventResolver())->resolve($events, $language_tag);
        $events = array_map(static fn (HistoricEvent $event): string => $event->withoutInternalWikidataTags()->gedcom, $events);

        $this->writeEventsCache($language_tag, $events);

        return new Collection($events);
    }

    /** @param list<HistoricEvent> $events */
    private function refreshEventIdentityIndex(string $providerId, string $collectionId, array $events): void
    {
        DB::table(EventIdentitySchema::TABLE_INDEX)->where('provider_id', '=', $providerId)->delete();

        foreach ($events as $position => $event) {
            foreach ($event->identities as $identity) {
                DB::table(EventIdentitySchema::TABLE_INDEX)->insertOrIgnore([
                    'event_identity' => trim($identity),
                    'provider_id' => $providerId,
                    'collection_id' => $event->collectionId,
                    'source_location' => (string) $position,
                    'event_hash' => sha1($event->gedcom),
                    'event_label' => preg_replace('/^1 EVEN ([^\r\n]+).*$/s', '$1', $event->gedcom) ?? '',
                    'event_date' => $event->date(),
                ]);
            }
        }
    }

    /** @return list<array{title:string,identities:list<string>,external_references:string,events:list<object>,unindexed_identities:list<string>}> */
    private function adminEventEquivalenceGroups(): array
    {
        $pairs = DB::table(EventIdentitySchema::TABLE_EQUIVALENCES)->orderBy('identity_a')->orderBy('identity_b')->get()->all();
        $parent = [];
        $find = static function (string $identity) use (&$parent, &$find): string {
            $parent[$identity] ??= $identity;
            if ($parent[$identity] !== $identity) {
                $parent[$identity] = $find($parent[$identity]);
            }

            return $parent[$identity];
        };
        foreach ($pairs as $pair) {
            $a = $find((string) $pair->identity_a);
            $b = (string) $pair->identity_b;
            if ($b !== '') {
                $parent[$find($b)] = $a;
            }
        }

        $groups = [];
        foreach ($pairs as $pair) {
            $identity = (string) $pair->identity_a;
            $key = $find($identity);
            $groups[$key]['identities'][] = $identity;
            if ((string) $pair->identity_b !== '') {
                $groups[$key]['identities'][] = (string) $pair->identity_b;
            }
            if ((string) $pair->external_references !== '') {
                $groups[$key]['external_references'][] = (string) $pair->external_references;
            }
            if ((string) ($pair->group_title ?? '') !== '') {
                $groups[$key]['titles'][] = (string) $pair->group_title;
            }
        }

        foreach ($groups as &$group) {
            $group['identities'] = array_values(array_unique($group['identities']));
            sort($group['identities']);
            $group['external_references'] = implode('; ', array_values(array_unique($group['external_references'] ?? [])));
            $group['events'] = DB::table(EventIdentitySchema::TABLE_INDEX)
                ->whereIn('event_identity', $group['identities'])
                ->orderBy('event_identity')
                ->orderBy('collection_id')
                ->get()
                ->all();
            $indexedIdentities = array_values(array_unique(array_map(static fn (object $event): string => (string) $event->event_identity, $group['events'])));
            $group['unindexed_identities'] = array_values(array_diff($group['identities'], $indexedIdentities));
            $group['title'] = $group['titles'][0] ?? $this->suggestEventEquivalenceTitle($group['events'], $group['identities']);
        }
        unset($group);

        return array_values($groups);
    }

    /** @return list<string> */
    private function eventIdentityList(string $value): array
    {
        $identities = preg_split('/[\s,;]+/', trim($value)) ?: [];

        return array_values(array_unique(array_filter(array_map(fn (string $identity): string => $this->normalizeEventIdentity($identity), $identities), static fn (string $identity): bool => $identity !== '')));
    }

    private function normalizeEventIdentity(string $identity): string
    {
        $identity = strtolower(trim($identity));

        return preg_replace('/^q/', 'Q', $identity) ?? $identity;
    }

    private function validEventIdentity(string $identity): bool
    {
        return preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/', $identity) === 1
            || preg_match('/^Q[0-9]+\$[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/', $identity) === 1;
    }

    /** @param list<string> $identities */
    private function saveEventEquivalenceGroup(array $identities, string $externalReferences, string $title = ''): void
    {
        DB::table(EventIdentitySchema::TABLE_EQUIVALENCES)->whereIn('identity_a', $identities)->orWhereIn('identity_b', $identities)->delete();
        $anchor = $identities[0];
        $others = array_slice($identities, 1);
        if ($others === []) {
            $others = [''];
        }
        foreach ($others as $identity) {
            DB::table(EventIdentitySchema::TABLE_EQUIVALENCES)->insertOrIgnore([
                'identity_a' => $anchor,
                'identity_b' => $identity,
                'external_references' => $externalReferences !== '' ? $externalReferences : null,
                'group_title' => $title !== '' ? $title : null,
            ]);
        }
    }

    /** @param list<object> $events
     *  @param list<string> $identities
     */
    private function suggestEventEquivalenceTitle(array $events, array $identities): string
    {
        if ($events !== []) {
            return (string) $events[0]->event_label;
        }

        return I18N::translate('Equivalence group') . ': ' . $identities[0];
    }

    private function eventEquivalencesJson(): string
    {
        $groups = array_map(static fn (array $group): array => [
            'title' => $group['title'],
            'event_ids' => $group['identities'],
            'external_references' => $group['external_references'],
        ], $this->adminEventEquivalenceGroups());
        $json = json_encode([
            'format' => 'hh-historic-events-equivalences',
            'version' => 1,
            'groups' => $groups,
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);

        if ($json === false) {
            throw new RuntimeException('The equivalence data could not be exported.');
        }

        return $json . "\n";
    }

    private function importEventEquivalences(string $json): int
    {
        if (strlen($json) > self::MAX_EQUIVALENCE_JSON_BYTES) {
            throw new RuntimeException(I18N::translate('The JSON equivalence file is too large. The maximum size is 1 MiB.'));
        }
        try {
            $data = json_decode($json, true, 16, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            throw new RuntimeException(I18N::translate('The JSON equivalence file is invalid.'));
        }
        if (!is_array($data) || ($data['format'] ?? '') !== 'hh-historic-events-equivalences' || ($data['version'] ?? null) !== 1 || !is_array($data['groups'] ?? null)) {
            throw new RuntimeException(I18N::translate('The JSON equivalence file has an unsupported format.'));
        }
        if (count($data['groups']) > self::MAX_EQUIVALENCE_JSON_GROUPS) {
            throw new RuntimeException(I18N::translate('The JSON equivalence file contains too many groups.'));
        }

        $validatedGroups = [];
        foreach ($data['groups'] as $group) {
            if (!is_array($group)) {
                throw new RuntimeException(I18N::translate('The JSON equivalence file contains an invalid group.'));
            }
            $rawIdentities = $group['event_ids'] ?? null;
            if (!is_array($rawIdentities) || count($rawIdentities) > self::MAX_EQUIVALENCE_GROUP_IDENTITIES || array_filter($rawIdentities, static fn (mixed $identity): bool => !is_string($identity)) !== []) {
                throw new RuntimeException(I18N::translate('The JSON equivalence file contains an invalid group.'));
            }
            $identities = $this->eventIdentityList(implode(',', $rawIdentities));
            if ($identities === [] || array_filter($identities, fn (string $identity): bool => !$this->validEventIdentity($identity)) !== []) {
                throw new RuntimeException(I18N::translate('The JSON equivalence file contains an invalid event ID.'));
            }
            $title = $group['title'] ?? '';
            $externalReferences = $group['external_references'] ?? '';
            if (!is_string($title) || !is_string($externalReferences) || strlen($title) > 255 || strlen($externalReferences) > self::MAX_EQUIVALENCE_EXTERNAL_REFERENCES_LENGTH) {
                throw new RuntimeException(I18N::translate('The JSON equivalence file contains an invalid group.'));
            }
            $validatedGroups[] = [
                'identities' => $identities,
                'external_references' => trim($externalReferences),
                'title' => trim($title),
            ];
        }

        foreach ($validatedGroups as $group) {
            $this->mergeImportedEventEquivalenceGroup($group['identities'], $group['external_references'], $group['title']);
        }

        return count($validatedGroups);
    }

    /** @param list<string> $identities */
    private function mergeImportedEventEquivalenceGroup(array $identities, string $externalReferences, string $title): void
    {
        $pairs = DB::table(EventIdentitySchema::TABLE_EQUIVALENCES)
            ->whereIn('identity_a', $identities)->orWhereIn('identity_b', $identities)->get()->all();
        $allIdentities = $identities;
        foreach ($pairs as $pair) {
            $allIdentities[] = (string) $pair->identity_a;
            if ((string) $pair->identity_b !== '') {
                $allIdentities[] = (string) $pair->identity_b;
            }
            $externalReferences = $externalReferences !== '' ? $externalReferences : (string) ($pair->external_references ?? '');
            $title = $title !== '' ? $title : (string) ($pair->group_title ?? '');
        }
        $allIdentities = array_values(array_unique($allIdentities));
        if ($pairs !== []) {
            DB::table(EventIdentitySchema::TABLE_EQUIVALENCES)->whereIn('identity_a', $allIdentities)->orWhereIn('identity_b', $allIdentities)->delete();
        }
        $this->saveEventEquivalenceGroup($allIdentities, $externalReferences, $title);
    }

    private function loadBundledEquivalences(): void
    {
        if ($this->getPreference(self::BUNDLED_EQUIVALENCES_LOADED_PREFERENCE, '0') === '1') {
            return;
        }
        $file = $this->resourcesFolder() . 'data/event-equivalences.json';
        if (is_file($file)) {
            try {
                $contents = file_get_contents($file);
                if ($contents !== false) {
                    $this->importEventEquivalences($contents);
                }
            } catch (RuntimeException) {
                // A bundled data error must not prevent webtrees from booting.
            }
        }
        $this->setPreference(self::BUNDLED_EQUIVALENCES_LOADED_PREFERENCE, '1');
    }

    /**
     * Keep webtrees' standard lifetime filtering and add display metadata only
     * for historical facts that remain after that filtering.
     *
     * @return Collection<int,Fact>
     */
    public function historicEventsForIndividual(Individual $individual): Collection
    {
        $facts = $this->coreHistoricEventsForIndividual($individual);

        if (!$this->showEventAges()) {
            return $facts;
        }

        $birthDate = $individual->getBirthDate();

        return $facts->map(function (Fact $fact) use ($birthDate, $individual): Fact {
            $age = (string) new Age($birthDate, $fact->date());

            if ($age === '') {
                return $fact;
            }

            $gedcom = preg_replace(
                '/\\n2 TYPE ([^\\n]*)/',
                "\n2 TYPE $1 " . self::EVENT_AGE_MARKER . ' ' . MoreI18N::xlate('(aged %s)', $age),
                $fact->gedcom(),
                1
            );

            return new Fact($gedcom ?? $fact->gedcom(), $individual, $fact->id());
        });
    }

    public function bodyContent(): string
    {
        if (!$this->showEventAges()) {
            return '';
        }

        return '<script>(function () {' .
            'const marker = ' . json_encode(self::EVENT_AGE_MARKER) . ';' .
            'const moveAges = () => {' .
                'const walker = document.createTreeWalker(document.body, NodeFilter.SHOW_TEXT);' .
                'const nodes = [];' .
                'let node;' .
                'while ((node = walker.nextNode())) { if (node.nodeValue.includes(marker)) { nodes.push(node); } }' .
                'nodes.forEach((textNode) => {' .
                    'const parts = textNode.nodeValue.split(marker);' .
                    'const age = (parts[1] || "").trim();' .
                    'textNode.nodeValue = parts[0].trim();' .
                    'const row = textNode.parentElement.closest("tr");' .
                    'const date = row ? row.querySelector(".wt-fact-date-age") : null;' .
                    'if (date && age !== "") { date.append(document.createTextNode(" " + age)); }' .
                '});' .
            '};' .
            'if (document.readyState === "loading") { document.addEventListener("DOMContentLoaded", moveAges); } else { moveAges(); }' .
        '}());</script>';
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
            'regions' => $this->adminRegions(),
            'show_event_ages' => $this->showEventAges(),
            'custom_data_folder' => $this->customDataFolderDisplay(),
            'custom_csv_documentation_url' => self::CUSTOM_WEBSITE . 'blob/main/docs/custom-csv-format.md',
            'custom_csv_example_url' => self::CUSTOM_WEBSITE . 'raw/main/docs/examples/custom-family-events-de.csv',
            'active_legacy_modules' => $this->activeLegacyModules(),
            'custom_csv_files' => $customCsvFiles,
            'selected_custom_csv' => $selectedCustomCsv,
            'event_equivalence_groups' => $this->adminEventEquivalenceGroups(),
        ]);
    }

    public function postAdminAction(ServerRequestInterface $request): ResponseInterface
    {
        $params = (array) $request->getParsedBody();
        $action = (string) ($params['action'] ?? 'save-preferences');

        if ($action !== 'save-preferences') {
            return $this->handleCustomCsvAction($action, $params, $request);
        }

        $this->setPreference(self::SHOW_EVENT_AGES_PREFERENCE, isset($params['show_event_ages']) ? '1' : '0');

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

                if ($provider instanceof GrampsCsvEventProvider) {
                    foreach ($provider->categoryOptions($typeId) as $categoryId => $categoryLabel) {
                        $this->setPreference(
                            $this->categoryPreferenceKey($provider->id(), $typeId, $categoryId),
                            isset($params[$this->categoryFormKey($provider->id(), $typeId, $categoryId)]) ? '1' : '0'
                        );
                    }
                }
            }
        }

        FlashMessages::addMessage(MoreI18N::xlate('The preferences for the module “%s” have been updated.', $this->title()), 'success');

        return redirect($this->getConfigLink());
    }

    /**
     * @param array<string,mixed> $params
     */
    private function handleCustomCsvAction(string $action, array $params, ?ServerRequestInterface $request = null): ResponseInterface
    {
        if ($action === 'export-event-equivalence-groups') {
            return response($this->eventEquivalencesJson())
                ->withHeader('Content-Type', 'application/json; charset=UTF-8')
                ->withHeader('Content-Disposition', 'attachment; filename="hh-historic-events-equivalences.json"');
        }

        if ($action === 'import-event-equivalence-groups') {
            $file = $request?->getUploadedFiles()['equivalence_json'] ?? null;
            if (!$file instanceof UploadedFileInterface || $file->getError() === UPLOAD_ERR_NO_FILE) {
                FlashMessages::addMessage(I18N::translate('Choose a JSON equivalence file to import.'), 'danger');
            } elseif ($file->getError() !== UPLOAD_ERR_OK) {
                FlashMessages::addMessage(I18N::translate('The JSON equivalence file could not be uploaded.'), 'danger');
            } elseif (($file->getSize() ?? 0) > self::MAX_EQUIVALENCE_JSON_BYTES) {
                FlashMessages::addMessage(I18N::translate('The JSON equivalence file is too large. The maximum size is 1 MiB.'), 'danger');
            } else {
                try {
                    $count = $this->importEventEquivalences($file->getStream()->getContents());
                    $this->clearEventsCache();
                    FlashMessages::addMessage(I18N::plural('One equivalence group was imported.', '%s equivalence groups were imported.', $count, I18N::number($count)), 'success');
                } catch (RuntimeException $exception) {
                    FlashMessages::addMessage($exception->getMessage(), 'danger');
                }
            }

            return redirect($this->getConfigLink());
        }

        if ($action === 'save-event-equivalence-group') {
            $identities = $this->eventIdentityList((string) ($params['event_identities'] ?? ''));
            if ($identities === []) {
                FlashMessages::addMessage(I18N::translate('Enter at least one event identity.'), 'danger');
            } elseif (($invalidIdentities = array_filter($identities, fn (string $identity): bool => !$this->validEventIdentity($identity))) !== []) {
                FlashMessages::addMessage(I18N::translate('Invalid event identity: %s', implode(', ', $invalidIdentities)), 'danger');
            } else {
                $this->saveEventEquivalenceGroup($identities, trim((string) ($params['external_references'] ?? '')), trim((string) ($params['group_title'] ?? '')));
                $this->clearEventsCache();
                FlashMessages::addMessage(I18N::translate('The event equivalence group has been saved.'), 'success');
            }

            return redirect($this->getConfigLink());
        }

        if ($action === 'delete-event-equivalence-group') {
            $identities = $this->eventIdentityList((string) ($params['event_identities'] ?? ''));
            DB::table(EventIdentitySchema::TABLE_EQUIVALENCES)->whereIn('identity_a', $identities)->orWhereIn('identity_b', $identities)->delete();
            $this->clearEventsCache();
            FlashMessages::addMessage(I18N::translate('The event equivalence group has been deleted.'), 'success');

            return redirect($this->getConfigLink());
        }

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
     *  @return list<array{from_date:string,to_date:string,event:string,link:string,category:string,event_id:string}>
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
                'event_id' => (string) ($row['event_id'] ?? ''),
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
            $eventLanguages = $provider->eventLanguageOptions();
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
                    'categories' => $provider instanceof GrampsCsvEventProvider
                        ? $this->adminCategories($provider, $typeId)
                        : [],
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
                'languages' => implode(', ', $eventLanguages),
                'language_count' => count($eventLanguages),
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
     * @return list<array{region:string,collections:list<array{reference:string,label:string}>}>
     */
    private function adminRegions(): array
    {
        $regions = [];

        foreach ($this->providerFactory()->providers() as $provider) {
            foreach ($provider->typeOptions() as $typeId => $label) {
                $region = $provider->typeRegion($typeId);
                if ($region === '') {
                    continue;
                }

                if ($provider instanceof GrampsCsvEventProvider) {
                    $reference = $typeId . '.csv';
                    $collectionLabel = $label;
                } elseif ($provider instanceof GermanChancellorsPresidentsCsvProvider) {
                    $reference = 'GermanChancellorsPresidents.csv';
                    $collectionLabel = $provider->title();
                } elseif ($provider instanceof TextGedcomEventProvider) {
                    $reference = match ($provider->id()) {
                        'german-wars-battles-worldwide' => 'german-wars-battles-worldwide.ged',
                        'swiss-historic-events' => 'swiss-historic-events.csv',
                        default => '',
                    };
                    $collectionLabel = $provider->title();
                } else {
                    $reference = '';
                    $collectionLabel = $label . ' — ' . $provider->title();
                }

                $regions[$region][$reference . '|' . $collectionLabel] = [
                    'reference' => $reference,
                    'label' => $collectionLabel,
                ];
            }
        }

        ksort($regions);
        foreach ($regions as &$collections) {
            $collections = array_values($collections);
            usort($collections, static fn (array $left, array $right): int => ($left['reference'] . $left['label']) <=> ($right['reference'] . $right['label']));
        }
        unset($collections);

        $result = [];
        foreach ($regions as $region => $collections) {
            $result[] = [
                'region' => $region,
                'collections' => $collections,
            ];
        }

        return $result;
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

        uksort($languages, static function (string $first, string $second): int {
            $priority = ['mul' => 0, 'de' => 1];

            return ($priority[$first] ?? 2) <=> ($priority[$second] ?? 2)
                ?: $first <=> $second;
        });

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
        $legacyModuleNames = [];

        foreach (self::LEGACY_MODULE_NAME_ALIASES as $moduleNames) {
            foreach ($moduleNames as $moduleName) {
                $legacyModuleNames[] = $moduleName;
            }
        }

        foreach ($this->moduleService->all(true) as $module) {
            if (!$module->isEnabled()
                || (!in_array($module->name(), $legacyModuleNames, true)
                    && !in_array($module->title(), self::LEGACY_MODULE_TITLES, true))) {
                continue;
            }

            $activeModules[] = [
                'name' => $module->name(),
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

    /**
     * @return array<string,array<string,bool>>
     */
    private function enabledCategories(EventDataProviderInterface $provider): array
    {
        if (!$provider instanceof GrampsCsvEventProvider) {
            return [];
        }

        $enabledCategories = [];
        foreach ($provider->typeOptions() as $typeId => $label) {
            foreach ($provider->categoryOptions($typeId) as $categoryId => $categoryLabel) {
                $enabledCategories[$typeId][$categoryId] = $this->categoryIsEnabled($provider, $typeId, $categoryId);
            }
        }

        return $enabledCategories;
    }

    /**
     * @return list<array{id:string,label:string,form_key:string,enabled:bool}>
     */
    private function adminCategories(GrampsCsvEventProvider $provider, string $typeId): array
    {
        $categories = [];
        foreach ($provider->categoryOptions($typeId) as $categoryId => $categoryLabel) {
            $categories[] = [
                'id' => $categoryId,
                'label' => $categoryLabel,
                'form_key' => $this->categoryFormKey($provider->id(), $typeId, $categoryId),
                'enabled' => $this->categoryIsEnabled($provider, $typeId, $categoryId),
            ];
        }

        return $categories;
    }

    private function showEventAges(): bool
    {
        return $this->getPreference(self::SHOW_EVENT_AGES_PREFERENCE, '0') === '1';
    }

    private function typeIsEnabled(EventDataProviderInterface $provider, string $typeId): bool
    {
        return $this->getPreference(
            $this->typePreferenceKey($provider->id(), $typeId),
            $provider->typeEnabledByDefault($typeId) ? '1' : '0'
        ) === '1';
    }

    private function categoryIsEnabled(GrampsCsvEventProvider $provider, string $typeId, string $categoryId): bool
    {
        return $this->getPreference(
            $this->categoryPreferenceKey($provider->id(), $typeId, $categoryId),
            $provider->categoryEnabledByDefault($typeId, $categoryId) ? '1' : '0'
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

    private function categoryPreferenceKey(string $providerId, string $typeId, string $categoryId): string
    {
        return 'c_' . substr(md5($providerId . '|' . $typeId . '|' . $categoryId), 0, 16);
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

    private function categoryFormKey(string $providerId, string $typeId, string $categoryId): string
    {
        return 'category-' . substr(md5($providerId . '|' . $typeId . '|' . $categoryId), 0, 16);
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
        $signature = ['event_cache_format_version' => 6];

        foreach ($this->providerFactory()->providers() as $provider) {
            $providerSignature = [
                'id' => $provider->id(),
                'enabled' => $this->providerIsEnabled($provider),
                'languages' => [],
                'types' => [],
                'categories' => [],
            ];

            foreach ($provider->eventLanguageOptions() as $languageId => $languageLabel) {
                $providerSignature['languages'][$languageId] = $this->providerLanguageIsEnabled($provider, $languageId);
            }

            foreach ($provider->typeOptions() as $typeId => $label) {
                $providerSignature['types'][$typeId] = $this->typeIsEnabled($provider, $typeId);

                if ($provider instanceof GrampsCsvEventProvider) {
                    foreach ($provider->categoryOptions($typeId) as $categoryId => $categoryLabel) {
                        $providerSignature['categories'][$typeId][$categoryId] = $this->categoryIsEnabled($provider, $typeId, $categoryId);
                    }
                }
            }

            $signature['providers'][] = $providerSignature;
        }

        return (string) json_encode($signature);
    }

}
