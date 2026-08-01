# Change Log

## Next release

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
