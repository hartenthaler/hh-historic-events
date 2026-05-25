# Architecture

`hh-historic-events` is a webtrees custom module that implements the historic-events module interface.
It combines several formerly separate historic-event modules into one configurable provider-based module.

## Main Module

The main integration class is `HhHistoricEvents`.

It is responsible for:

* webtrees module metadata
* custom-module version information
* loading translations from `resources/lang/*.po` and `resources/lang/*.mo`
* registering the administration view namespace
* reading and saving module preferences
* combining events from enabled data providers
* caching the final GEDCOM event list

The public historic-events entry point is:

```php
public function historicEventsAll(string $language_tag = 'en'): Collection
```

webtrees calls this method when historical events are needed for timeline display.

## Provider Model

All data sources implement `EventDataProviderInterface`.

The interface exposes:

* provider identity and translated display text
* available event languages
* selectable record types
* default enablement
* event loading for a given webtrees language tag and enabled-type map

The factory `EventDataProviderFactory` creates the providers in the order used by the README and the administration page.

Current providers:

* `TextGedcomEventProvider`
* `GrampsCsvEventProvider`
* `GermanChancellorsPresidentsCsvProvider`
* `GermanChancellorsPresidentsWikidataProvider`

New historical topics should normally be added as a new provider and then registered in `EventDataProviderFactory`.

## Data Sources

Static data is stored below `resources/data`.

GEDCOM text files:

* `resources/data/gedcom/german-wars-battles-worldwide.ged`
* `resources/data/gedcom/swiss-historic-events.ged`

CSV files:

* `resources/data/csv/GermanChancellorsPresidents.csv`
* Gramps CSV files in `resources/data/csv/*.csv`

Wikidata:

* German chancellors, presidents, and heads of the former GDR can optionally be loaded from Wikidata.
* This source performs external HTTPS requests and is disabled by default.

## Language Handling

Event language is a property of the data source, not necessarily of the webtrees user-interface language.

Examples:

* German wars and battles are German event texts and use German Wikipedia links.
* Swiss historic events are German event texts and use German Wikipedia links.
* Gramps CSV files include multiple event languages such as Danish, English, Swedish, and Ukrainian.

The administration page shows all detected event languages and lets administrators choose which data sources are used for each language.

For Gramps, language filtering is applied per CSV file because the provider contains multiple languages.

## Administration Settings

The settings page has two levels of selection.

First, administrators can enable or disable sources by event language.

Second, each provider has its own detailed settings:

* enable or disable the whole provider
* enable or disable record types inside the provider

Preference keys stored in webtrees are short stable hash keys.
This avoids database-column length problems in `module_setting.setting_name`.

## Event Loading Flow

When webtrees requests historical events:

1. `HhHistoricEvents::historicEventsAll()` checks the final event cache.
1. If the cache exists and is fresh, it returns the cached GEDCOM strings as a `Collection`.
1. If the cache is missing, each enabled provider is called.
1. Providers read their data source and return GEDCOM event strings.
1. The main module combines the strings.
1. The combined result is written to cache.
1. The combined result is returned to webtrees.

## Cache Strategy

The final combined event list is cached as JSON below:

```text
data/cache/hh-historic-events/events-<hash>.json
```

The cache key includes:

* webtrees language tag
* enabled providers
* enabled event languages
* enabled record types

This means a configuration change automatically creates a different cache entry.

The cache currently has a 24-hour TTL.
If the cache directory is not writable, the module still works, but events are rebuilt on every request.

Wikidata responses are cached separately below:

```text
data/cache/hh-historic-events/wikidata-<hash>.json
```

## Translation

Translations are loaded from:

```text
resources/lang/<language>.po
resources/lang/<language>.mo
```

The `.po` file is preferred when both files exist.
If a regional language tag is requested, such as `de-CH`, the module first checks the exact language tag and then falls back to the base language, such as `de`.

## Extensibility

To add a new source:

1. Add the data file below `resources/data` if it is static.
1. Create a provider implementing `EventDataProviderInterface`.
1. Expose event languages and record types through the provider.
1. Register the provider in `EventDataProviderFactory`.
1. Add new translation strings to `resources/lang/de.po` and compile `de.mo`.

The provider should return fully formed GEDCOM event strings.
The main module should remain responsible only for configuration, filtering orchestration, and caching.
