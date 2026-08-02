# Change Log

## Next release

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
