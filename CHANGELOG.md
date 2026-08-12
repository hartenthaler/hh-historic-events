# Change Log

## Next release

## 2.2.6.3 - 2026-08-12

- Add event IDs, a manual equivalence table, a derived identity index, and language-aware resolution of equivalent historical events.
- Split the administration settings into configuration, custom-CSV, and event-identity views; show the region overview after the collection selections.
- Extend the custom-CSV editor to six columns and generate a UUID-v4 event ID for newly saved rows.
- Document event identities and the distinct GEDCOM-date format of the Swiss CSV collection.
- Add UUID-v4 event IDs to the bundled German chancellors and presidents CSV collection.
- Replace pair-by-pair equivalence administration with complete editable groups, optional external references, and indexed event details for quality control.
- Add individual category selections for CSV collections in the administration settings; category choices are included in the event-cache configuration.
- Add and translate the bundled CSV categories `Epidemic`, `Pandemic`, and `Institutional care`; categorize the Involuntary commitment and institutional care collection.
- Rename the bundled English general-history and pandemic collections with `en_` filename prefixes.
- Add local-language `REGION` metadata to the bundled Gramps CSV collections and show a settings overview that maps each region to its CSV collections.
- Refine the settings order with an Options heading, the age-display preference, and then the explanation of how selections interact.
- Clarify collection configuration and document the planned event-identity model for linking equivalent events across collections and language variants.

## 2.2.6.2 - 2026-08-10

- Keep the Swiss CSV collection separate from Gramps-compatible collections and label it as CSV.
- Extend the Wikidata provider with Austrian and Swiss office holders, and use a Wikidata link when no Wikipedia article is available.
- Add categories for the bundled Pandemics and epidemics data collection.

## 2.2.6.1 - 2026-08-10

- Document how Potts Historical Facts CSV files can be copied to the persistent data directory and converted with the administration editor.
- Add optional image titles for German chancellors and presidents.
- Add an administrator option to show ages for historical events.
- Warn when one of the four predecessor modules is still enabled, including older namespace-derived folder names.
- Add recent wars and conflicts to the worldwide data collection.
- Convert the Swiss historical-events collection from GEDCOM records to CSV data.
- Hide Wikidata party affiliations that ended on or before the corresponding office term started.

## 2.2.6.0 - 2026-08-03

- Describe the module as providing historical facts from selectable data collections, distinguishing collections from their underlying sources.
- Keep submitted custom CSV editor values after validation failures, omit only invalid dates, validate date order and the supported Gregorian period, and refine the date-field presentation.
- Complete extensionless custom filenames with `.csv`, show the Gramps filename convention as the example, and present language before topic and region in the metadata editor.
- Distinguish administrators from webmasters when describing who can provide custom CSV files.
- Refine the custom CSV editor with webtrees language selection, localized Gregorian date entry at year, month, or day precision, explicit metadata translations, and a save-as workflow that preserves unsaved changes.
- Add administration file management and a five-column editor for user-specific Gramps-compatible CSV collections, including create, copy, delete, and cache invalidation actions.
- Describe collections by geographical region without adding another selection filter, and identify Wikidata events as dynamically localized.
- Show the event collections assigned to each language and data source in the administration settings.
- Explain how language, data-source, and individual-collection selections are combined in the administration settings.
- Distinguish bundled Gramps HistContext data from administrator-provided CSV collections in the settings and add Dutch historical events from HistContext PR #13.
- Update the COVID-19 end year to 2023 in the general and pandemic HistContext collections.
- Move implementation architecture details from the README to dedicated documentation and add direct support links.
- Add credits for the original modules, data contributors, and external data sources.
- Document the Gramps date-parser contract for HistContext-compatible CSV files and handle `Today` like the original module.
- Sanitize custom CSV values before generating GEDCOM and accept only valid HTTP(S) source links.
- Warn administrators that many active sources or a single large event collection can significantly affect webtrees performance.
- Mark user-specific CSV collections that override a bundled file with an explanatory icon.
- Keep overridden bundled CSV files visible as non-selectable entries with their own explanatory icon.
- Divide administration settings into language and individual-source sections and show user-specific collections first.
- Display the canonical custom-data path and link the CSV format documentation and a bundled example file from the administration page.
- Warn administrators when superseded historic-event modules are still active and name the modules that must be removed.
- Use the per-row category, file topic, and translated generic type in that order for Gramps event `TYPE` values.
- Do not generate empty link notes for CSV events without a source URL.
- Consolidated general usage, contribution, and testing guidance in the README.
- Added a persistent data directory for user-specific CSV files; custom files override bundled files with the same name.
- Added an optional fifth CSV column for a per-event category used as GEDCOM `TYPE`.
- Added metadata support for the content language and geographical region of custom CSV files.
- Added a dedicated custom CSV file-format specification.
- Documented individual CSV files as a source for user-specific family events.
- Added the GitHub download-count badge to the README.
- Added a shared PSR-18 HTTP adapter for remote event providers.
- Use the webtrees service-container HTTP client under webtrees 2.3 and retain an isolated Guzzle PSR-18 fallback for webtrees 2.2.6.
- Added dual translation loading for the stream-based webtrees 2.3 API and the file-based webtrees 2.2 API.
- Documented the shared 2.2.6/2.3 compatibility strategy and the required beta recheck.

## 0.1.0

- Initial combined release of the historic-events module.
