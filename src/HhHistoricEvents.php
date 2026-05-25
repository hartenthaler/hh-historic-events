<?php

declare(strict_types=1);

namespace Hartenthaler\WebtreesModules\History\HhHistoricEvents;

use Fisharebest\Localization\Translation;
use Fisharebest\Webtrees\FlashMessages;
use Fisharebest\Webtrees\I18N;
use Fisharebest\Webtrees\Module\AbstractModule;
use Fisharebest\Webtrees\Module\ModuleConfigInterface;
use Fisharebest\Webtrees\Module\ModuleConfigTrait;
use Fisharebest\Webtrees\Module\ModuleCustomInterface;
use Fisharebest\Webtrees\Module\ModuleCustomTrait;
use Fisharebest\Webtrees\Module\ModuleHistoricEventsInterface;
use Fisharebest\Webtrees\Module\ModuleHistoricEventsTrait;
use Fisharebest\Webtrees\View;
use Fisharebest\Webtrees\Webtrees;
use Illuminate\Support\Collection;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

use function array_values;
use function dirname;
use function explode;
use function file_exists;
use function file_get_contents;
use function file_put_contents;
use function filemtime;
use function implode;
use function is_array;
use function is_dir;
use function is_file;
use function json_decode;
use function json_encode;
use function md5;
use function mkdir;
use function redirect;
use function substr;
use function time;

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
        return I18N::translate('Historical facts from selectable data sources');
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

            if (is_file($poFile)) {
                return (new Translation($poFile))->asArray();
            }

            if (is_file($moFile)) {
                return (new Translation($moFile))->asArray();
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

        return $this->viewResponse($this->name() . '::settings', [
            'title' => $this->title(),
            'description' => $this->description(),
            'languages' => $this->adminLanguages(),
            'providers' => $this->adminProviders(),
        ]);
    }

    public function postAdminAction(ServerRequestInterface $request): ResponseInterface
    {
        $params = (array) $request->getParsedBody();

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
     * @return array<int,array<string,mixed>>
     */
    private function adminProviders(): array
    {
        $providers = [];
        foreach ($this->providerFactory()->providers() as $provider) {
            $types = [];
            foreach ($provider->typeOptions() as $typeId => $label) {
                $types[] = [
                    'id' => $typeId,
                    'label' => $label,
                    'language' => $provider->typeLanguage($typeId),
                    'form_key' => $this->typeFormKey($provider->id(), $typeId),
                    'enabled' => $this->typeIsEnabled($provider, $typeId),
                ];
            }

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
            ];
        }

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
                $languages[$languageId]['id'] = $languageId;
                $languages[$languageId]['label'] = $languageLabel;
                $languages[$languageId]['sources'][] = [
                    'provider_id' => $provider->id(),
                    'provider_title' => $provider->title(),
                    'form_key' => $this->providerLanguageFormKey($provider->id(), $languageId),
                    'enabled' => $this->providerLanguageIsEnabled($provider, $languageId),
                ];
            }
        }

        return array_values($languages);
    }

    private function providerFactory(): EventDataProviderFactory
    {
        return new EventDataProviderFactory($this->resourcesFolder());
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
