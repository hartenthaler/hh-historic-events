# Architecture

`hh-historic-events` is a webtrees custom module that implements the historic-events module interface.
It combines several formerly separate historic-event modules into one configurable provider-based module.

## Scope

The module provides GEDCOM-style historical event records for the webtrees timeline.
It neither creates GEDCOM records in family trees nor stores historical events in a database table.

Static data is stored below `resources/data`.

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
* selectable record categories
* an optional geographical region for each record category
* default enablement
* event loading for a given webtrees language tag and enabled-type map

The factory `EventDataProviderFactory` creates the providers in the order used by the README and the administration page.

Current providers:

* `TextGedcomEventProvider` for static GEDCOM and CSV records
* `GrampsCsvEventProvider`
* `GermanChancellorsPresidentsCsvProvider`
* `GermanChancellorsPresidentsWikidataProvider`

New historical topics should normally be added as a new provider and then registered in `EventDataProviderFactory`.

## Data Sources

Static data is stored below `resources/data`.

GEDCOM text files:

* `resources/data/gedcom/german-wars-battles-worldwide.ged`

This file replaced a large legacy PHP event array so the data can be maintained outside PHP code.

CSV files:

* `resources/data/csv/GermanChancellorsPresidents.csv`
* `resources/data/csv/swiss-historic-events.csv`
* Gramps CSV files in `resources/data/csv/*.csv`

Wikidata:

* Chancellors and presidents from Germany, Austria, and Switzerland can optionally be loaded from Wikidata. German results include the heads of the former GDR.
* This source performs external HTTPS requests and is disabled by default.

## Language Handling

Event language is a property of the data source, not necessarily of the webtrees user-interface language.

Examples:

* German wars and battles are German event texts and use German Wikipedia links.
* Swiss historic events are German event texts and use German Wikipedia links.
* Gramps CSV files include multiple event languages such as Danish, English, Swedish, and Ukrainian.
* The Wikidata provider is multilingual and dynamically requests labels and Wikipedia links in the webtrees user language, with English as its fallback.

The administration page first shows all detected event languages. Administrators choose the languages to use and then select the collections and record categories available for those languages.

For Gramps, language filtering is applied per CSV file because the provider contains multiple languages.

Geographical regions are descriptive metadata rather than another filter. They are shown with collections when a provider supplies them. Static providers define known regions in code, while custom Gramps-compatible CSV files can supply free-text `REGION` metadata in the language of the file.

## Administration Settings

The settings page has two levels of selection.

First, administrators can enable or disable event languages under **Selection by language**.

Second, under **Data sources and individual collections**, each provider has its own detailed settings:

* enable or disable the data collection
* enable or disable record categories inside the collection

Preference keys stored in webtrees are short stable hash keys.
This avoids database-column length problems in `module_setting.setting_name`.

## Event Identity and Cross-Collection Linking

One historical event can occur in several collections with different wording, geographical scope, or language. For example, a Swiss collection and a worldwide collection can describe the same event, and `de_DE` and `en_DE` can contain parallel language variants. A collection-level identifier is not required: relationships are made at event-row level.

The event identity model is:

* CSV files use an optional final `Event ID` column, after the optional `Category` column. Existing four- and five-column files remain valid.
* The column contains a comma-separated list of canonical lowercase UUID-v4 values. More than one UUID is allowed because independently created events can later be recognised as describing the same occurrence.
* When an administrator creates a new row in a user-specific CSV file, the editor generates one UUID automatically. The identity value is technical and is not editable in the user interface.
* When a CSV file is translated, its existing event UUIDs are retained. Equivalent rows can therefore be recognised even when their text, source link, category, or collection differ.
* GEDCOM providers retain one or more optional private `_UID` subtags with UUID-v4 values. CSV providers emit one `_UID` subtag for every UUID in their final event-identity column.
* Wikidata uses a source-derived identity rather than an authored UUID. `Q` plus `P` is not sufficient because an item can have multiple statements for one property. The stable source key is the Wikibase statement/claim GUID, conventionally shaped like `Q…$UUID`; its GEDCOM record carries it in `_WIKIDATA_STATEMENT` and the actual property in `_WIKIDATA_PROPERTY`.

Some equivalent events will still have distinct identifiers. A persistent administrator-maintained equivalence table will initially record pairs of equivalent event identities. Its pair graph defines the equivalence groups; it does not introduce a collection ID. The physical storage format and administration view belong to the implementation work.

The presentation rules for equivalent records are documented in [event-equivalence-resolution.md](event-equivalence-resolution.md). They prefer the current user language, then English, then any available language; they do not silently discard conflicting dates, links, or categories.

The module will generate an index from event identity to every collection and row in which it occurs. The index is derived data, is rebuilt or invalidated when collections change, and is used for lookup rather than scanning every collection on each request. The source CSV, GEDCOM, Wikidata data, and equivalence table remain authoritative.

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
* enabled record categories

This means a configuration change automatically creates a different cache entry.

The cache currently has a 24-hour TTL.
If the cache directory is not writable, the module still works, but events are rebuilt on every request.

Wikidata responses are cached separately below:

```text
data/cache/hh-historic-events/wikidata-<hash>.json
```

## Compatibility Adapters

Remote data sources use a small module-owned PSR-18 adapter. Under webtrees 2.3, the adapter obtains the HTTP client and request factory from the webtrees service container. Under webtrees 2.2.6, the factory uses the bundled Guzzle implementation through the same PSR interfaces.

Translation catalogs are loaded through the stream API of webtrees 2.3 or the file-based localization API of webtrees 2.2.

## Translation

Translations use the GNU gettext PO/MO system and are loaded from:

```text
resources/lang/<language>.po
resources/lang/<language>.mo
```

`default.pot` is the catalog template. Contributors edit a language-specific `.po` file and compile the matching `.mo` file; the `.po` file is preferred when both files exist.
If a regional language tag is requested, such as `de-CH`, the module first checks the exact language tag and then falls back to the base language, such as `de`.

## Extensibility

To add a new source:

1. Add the data file below `resources/data` if it is static.
1. Create a provider implementing `EventDataProviderInterface`.
1. Expose event languages and record categories through the provider.
1. Register the provider in `EventDataProviderFactory`.
1. Add new translation strings to `resources/lang/de.po` and compile `de.mo`.

The provider should return fully formed GEDCOM event strings.
The main module should remain responsible only for configuration, filtering orchestration, and caching.
