# Historic Events 2.2.6.0

This release turns the combined historic-events module into a substantially more configurable and maintainable successor to the earlier individual modules.

## Highlights

- Administrators can select event languages, data collections, and individual collection types independently.
- User-specific Gramps-compatible CSV collections can be created, edited, copied, and deleted directly in the control panel. Files remain in the persistent webtrees data directory across module upgrades.
- The CSV editor supports localized Gregorian date entry, ISO storage for exchange with Gramps, partial dates, and `Today`. Invalid individual dates are omitted without discarding the remaining submitted work.
- Collections can identify their language, geographical region, topic, provenance, licence, and other metadata.
- Bundled and user-specific collections are distinguished clearly; custom files can override bundled files with the same name.
- Security checks prevent CSV content from injecting GEDCOM lines, and only valid HTTP(S) links are accepted.
- Administration warnings explain that enabling many collections or very large datasets can noticeably affect webtrees performance.
- The module supports both webtrees 2.2.6 and the changed service and translation APIs in webtrees 2.3.

The [Change Log](../CHANGELOG.md) contains the complete list of changes.
