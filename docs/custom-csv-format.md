# Custom CSV File Format

The purpose of this specification is to define a shared format for historical event collections. The same file should be usable without modification in both [Gramps](https://gramps-project.org/) and [webtrees](https://webtrees.net/).

Administrators can provide website-specific historical events without changing the module. Custom files are stored in the persistent webtrees data directory:

```text
data/modules/hh-historic-events/data/
```

The exact server path is shown in the module settings. A custom file overrides a bundled file with the same filename. The files are UTF-8 encoded and use semicolons as column separators.

For predictable administration and event loading, a custom CSV file may be at most 1 MiB, contain at most 10,000 rows, and use at most 16 KiB per field. Larger files are rejected by the editor.

The module settings include a file manager and a six-column table editor for these administrator-provided files. Administrators can create, edit, copy, and delete them without direct filesystem access. Saving a file clears the module's generated event cache. Bundled collections and the separate German chancellors/presidents CSV provider cannot be changed with this editor.

The editor uses the active webtrees languages for `LANGUAGE`. Date fields accept the usual input order of the current webtrees language or a simple GEDCOM date and display the date in the user's language. When saving, the editor converts the value to the ISO representation required by the shared CSV format. It does not offer calendars, date ranges, or qualifiers such as `ABT` and `CAL`.

Each language variant is stored as its own CSV file. "Save current changes as a new file" writes the complete current form state to the new filename without changing the original file; the administrator can change its `LANGUAGE` metadata and translate its event rows before using this action. The module does not synchronize the contents of separate language files.

A complete German example is available as [`custom-family-events-de.csv`](examples/custom-family-events-de.csv).

## Metadata Header

New custom files should begin with this header:

```csv
########################################################
## FORMAT: webtrees gramps-historical-facts
## TOPIC: Geschichte der Familie Hartenthaler
## LANGUAGE: de
## REGION: Deutschland und Württemberg
## VERSION: 1.0
## AUTHOR: Hermann Hartenthaler
## CONTACT:
## LICENSE:
## SOURCE:
## DESCRIPTION:
########################################################
```

`FORMAT`, `TOPIC`, and `LANGUAGE` are required for new custom files. For compatibility, files without these fields continue to be read.

### Required Metadata

* `FORMAT` identifies the parser format. Its current value is `webtrees gramps-historical-facts`.
* `TOPIC` is the human-readable title shown in the module settings.
* `LANGUAGE` identifies the language of the event texts using a BCP 47 language tag, such as `de`, `en`, or `de-AT`.

### Optional Metadata

* `REGION` describes the **geographical region** covered by the file. It is free text written in the language declared by `LANGUAGE`, for example `Deutschland`, `Österreich`, `Württemberg`, or `Germany`. It is not an ISO country code and must not be used as a substitute for `LANGUAGE`.
* `VERSION` identifies the revision of the collection.
* `AUTHOR` names the author or editor.
* `CONTACT` provides a contact address or URL.
* `LICENSE` states the terms under which the collection can be used.
* `SOURCE` identifies a source shared by the complete collection.
* `DESCRIPTION` briefly explains the scope of the collection.

The filename remains the stable technical identifier of the collection. Metadata values describe its content and may therefore be changed without creating another collection. Gramps files conventionally follow `<locale>_data_v1_0.csv`, for example `de_DE_data_v1_0.csv`. In the administration editor, `.csv` is added automatically when a new filename has no extension; another explicit extension is rejected.

## Event Rows

The event rows contain four required columns and two optional columns:

```text
start date;end date;event text;source link;category;event ID
```

Example:

```csv
1789-04-30;1797-03-04;George Washington;https://en.wikipedia.org/wiki/George_Washington;Politics
```

* `start date` and `end date` always contain a Gregorian date in ISO notation. The supported precision is a year (`1900`), a month and year (`1900-01`), or a complete date (`1900-01-30`). Dates range from the introduction of the Gregorian calendar on 15 October 1582 up to the current date. Thus the day may be omitted, and when the day is omitted the month may also be omitted. A month or day cannot be given without a year. The two columns describe the beginning and optional end; an end date must not precede its start date. Range expressions such as `FROM`, qualifiers such as `ABT`, and non-Gregorian calendars are not part of this shared format.
* `end date` is empty for a single date.
* `Today` is the only special value accepted in either date column, independently of capitalization. It remains stored as `Today` in the CSV file and is resolved to the current Gregorian date when the module reads the events.
* `event text` contains the historical statement in the language declared by `LANGUAGE`.
* `source link` contains an optional URL for the individual event. No GEDCOM `NOTE` is generated when `source link` is empty.
* `category` is optional. If present, it becomes the GEDCOM `TYPE`; otherwise the file's `TOPIC` is used. The translated default type `Historic event` is used only when both are empty. For collections that contain categories, the module settings offer each category as an additional selection. All categories are enabled by default. Built-in category names are translated where a translation is available; categories in administrator-provided files are shown as supplied.
* `event ID` is optional for compatibility. It contains one or more canonical lowercase UUID-v4 values, separated by commas. The editor generates one ID automatically when it saves a new row; administrators can see but cannot edit it. When translating or copying an existing event, retain its event IDs.

Lines beginning with `#` and empty lines are ignored. A column-title row beginning with `Start date;End date` is optional and ignored by the parser. The former `From date;To date` spelling remains accepted for existing files.

## Security

Custom CSV files are treated as untrusted input. Control characters and line breaks are removed from data fields before GEDCOM records are generated, preventing CSV values from injecting additional GEDCOM lines. Source links are used only when they are valid `http` or `https` URLs. All displayed values remain subject to webtrees' normal output escaping.

Only administrators with access to the webtrees data directory can install or replace custom collections. Files should nevertheless be obtained from a trusted source and reviewed before installation.

## Compatibility

Existing bundled files and older custom files remain readable. If `LANGUAGE` is missing, the module retains its legacy language mapping for known bundled filenames. An unknown custom file without `LANGUAGE` has no language assignment and is shown as such in the settings.

The shared Gramps/webtrees contract deliberately uses Gregorian ISO dates at year, month, or day precision, with `Today` as its only special value. The administration editor hides this exchange representation: it accepts the usual localized webtrees input or simple GEDCOM notation and displays dates in the user's language. It converts values to ISO when saving the CSV file. When reading a CSV file, the module converts ISO month and day precision to GEDCOM notation before creating the historic event and resolves `Today` to the current Gregorian date; year-only values need no conversion.

The special file `GermanChancellorsPresidents.csv` uses its own comma-separated provider format. An override with this filename must retain that bundled structure. The separate `swiss-historic-events.csv` collection is semicolon-separated but not Gramps-compatible: its columns are `date;event;note;type;event ID`, and `date` uses GEDCOM date notation rather than ISO dates. Its optional final `event ID` field follows the UUID-v4 rules described above.
