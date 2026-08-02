# Custom CSV File Format

The purpose of this specification is to define a shared format for historical event collections. The same file should be usable without modification in both [Gramps](https://gramps-project.org/) and [webtrees](https://webtrees.net/).

Administrators can provide website-specific historical events without changing the module. Custom files are stored in the persistent webtrees data directory:

```text
data/modules/hh-historic-events/data/
```

The exact server path is shown in the module settings. A custom file overrides a bundled file with the same filename. The files are UTF-8 encoded and use semicolons as column separators.

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

The filename remains the stable technical identifier of the collection. Metadata values describe its content and may therefore be changed without creating another collection.

## Event Rows

The event rows contain four required columns and one optional column:

```text
start date;end date;event text;source link;category
```

Example:

```csv
1789-04-30;1797-03-04;George Washington;https://en.wikipedia.org/wiki/George_Washington;Politics
```

* `start date` and `end date` use the date syntax accepted by the localized Gramps date parser.
* `end date` is empty for a single date.
* `Today` is accepted in either date column, independently of capitalization, and is replaced with the current date.
* `event text` contains the historical statement in the language declared by `LANGUAGE`.
* `source link` contains an optional URL for the individual event. No GEDCOM `NOTE` is generated when `source link` is empty.
* `category` is optional. If present, it becomes the GEDCOM `TYPE`; otherwise the file's `TOPIC` is used. The translated default type `Historic event` is used only when both are empty.

Lines beginning with `#` and empty lines are ignored. A column-title row beginning with `From date;To date` is optional and ignored by the parser.

## Security

Custom CSV files are treated as untrusted input. Control characters and line breaks are removed from data fields before GEDCOM records are generated, preventing CSV values from injecting additional GEDCOM lines. Source links are used only when they are valid `http` or `https` URLs. All displayed values remain subject to webtrees' normal output escaping.

Only administrators with access to the webtrees data directory can install or replace custom collections. Files should nevertheless be obtained from a trusted source and reviewed before installation.

## Compatibility

Existing bundled files and older custom files remain readable. If `LANGUAGE` is missing, the module retains its legacy language mapping for known bundled filenames. An unknown custom file without `LANGUAGE` has no language assignment and is shown as such in the settings.

The date fields follow the HistContext contract: HistContext passes their contents to the localized Gramps date parser and accepts every date that this parser considers valid. Consequently, accepted localized spellings can depend on the language environment in Gramps. This module preserves these date values for webtrees instead of defining an additional date grammar.

The special file `GermanChancellorsPresidents.csv` uses its own comma-separated provider format. An override with this filename must retain that bundled structure.
