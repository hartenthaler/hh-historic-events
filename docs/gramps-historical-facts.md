# Gramps Historical Facts

This provider shows historical facts in several languages that are based on the [Gramps HistContext gramplet](https://github.com/kajmikkelsen/HistContext).

## Usage

Activate the `Gramps Historical Facts` provider in the settings of `hh-historic-events`.

The structure of historic events provided by this provider is oriented on GEDCOM events:

```gedcom
1 EVEN <event>
2 TYPE <TOPIC>
2 DATE <date period>
2 NOTE [link](<link>)
```

TOPIC is an element in the head of the CSV file. 

The basic information contains historical events with the year they happened and optionally the year they ended. It is stored as CSV files in `resources/data/csv` and can be edited easily.

The semicolon-separated columns in these files are:

* from date
* to date
* event text
* link to event

Example:

```csv
1789;1797;George Washington;https://wikipedia.org/wiki/George_Washington
```

The file names use the `.csv` extension and follow the pattern `<locale>_data_v1_0.csv`, for example `da_DK_data_v1_0.csv` for events related to Denmark.

For links in the notes, Markdown formatting is used. This should be enabled for the tree in `Control panel / Manage family trees / Preferences` in the `Text` section by enabling `Markdown`.

If Markdown is disabled, the links still work, but the formatting is less readable.

Users can view the historical events in the `Facts and events` tab by selecting `Historic events`.

Administrators can modify how historical events are presented in the timeline of a person by using the CSS&JS module. See the [German webtrees manual](https://wiki.genealogy.net/Webtrees_Handbuch/Entwicklungsumgebung#Beispiel_-_Farben_bei_Historischen_Fakten_anpassen).

## Screenshots

![Gramps historical facts screenshot](img/gramps-historical-facts.png)

## Adding New Data, Programming and Testing

You can contribute to this provider by:

* contributing historical facts: become familiar with the structure of the CSV files, change existing data or add new data, test it, create an issue in [hh-historic-events](https://github.com/hartenthaler/hh-historic-events/issues), and link your pull request
* contributing code: check the issues for work that needs attention; if your change is not covered by an existing issue, create one first
* testing: testing is currently manual; please create an issue for any bugs you find
