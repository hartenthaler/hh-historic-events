# Custom CSV File Format

Administrators can provide website-specific historical events without changing the module. Custom files are stored in the persistent webtrees data directory:

```text
data/modules/hh-historic-events/data/
```

The exact server path is shown in the module settings. A custom file overrides a bundled file with the same filename. The files are UTF-8 encoded and use semicolons as column separators.

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
1789;1797;George Washington;https://en.wikipedia.org/wiki/George_Washington;Politics
```

* `start date` contains the year or GEDCOM-compatible start date.
* `end date` is empty for a single date. `Today` is accepted for compatibility and treated as an open end.
* `event text` contains the historical statement in the language declared by `LANGUAGE`.
* `source link` contains an optional URL for the individual event.
* `category` is optional. If present, it becomes the GEDCOM `TYPE`; otherwise the file's `TOPIC` is used. The translated default type `Historic event` is used only when both are empty.

No GEDCOM `NOTE` is generated when `source link` is empty.

Lines beginning with `#` and empty lines are ignored. A column-title row beginning with `From date;To date` is optional and ignored by the parser.

## Compatibility

Existing bundled files and older custom files remain readable. If `LANGUAGE` is missing, the module retains its legacy language mapping for known bundled filenames. An unknown custom file without `LANGUAGE` has no language assignment and is shown as such in the settings.

The special file `GermanChancellorsPresidents.csv` uses its own comma-separated provider format. An override with this filename must retain that bundled structure.
