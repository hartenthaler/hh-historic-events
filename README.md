# 📜 **webtrees** module for Historic Events (hh-historic-events)

[![License: GPL v3](https://img.shields.io/badge/License-GPL%20v3-blue.svg)](http://www.gnu.org/licenses/gpl-3.0)
![Latest Release](https://img.shields.io/github/v/release/hartenthaler/hh-historic-events)
![Downloads](https://img.shields.io/github/downloads/hartenthaler/hh-historic-events/total)

![webtrees major version](https://img.shields.io/badge/webtrees-v2.1.x-green)
![webtrees major version](https://img.shields.io/badge/webtrees-v2.2.x-green)
![webtrees major version](https://img.shields.io/badge/webtrees-v2.3.x-green)

This [webtrees](https://www.webtrees.net) custom module provides selectable historical events for the webtrees timeline of a person.
It combines several earlier historic-event modules into one configurable module, so shared improvements can be maintained in one place.

<a name="Contents"></a>
## 📚 Contents

This README contains the following main sections:

* [Purpose](#Purpose)
* [Main features](#Features)
* [Data sources](#Data)
* [Configuration](#Configuration)
* [Usage](#Usage)
* [Architecture](docs/architecture.md)
* [Requirements](#Requirements)
* [Installation](#Installation)
* [Upgrade](#Upgrade)
* [Translation](#Translation)
* [Contributing and testing](#Contributing)
* [Credits](#Credits)
* [Support](#Support)
* [License](#License)

<a name="Purpose"></a>
## 🎯 Purpose

The standard webtrees timeline can display historical events that are provided by core and custom modules
(see the [German webtrees manual](https://wiki.genealogy.net/Webtrees_Handbuch/Anleitung_f%C3%BCr_Besucher#Historische_Ereignisse).
This module brings several historical event data collections together in a single module and lets administrators decide which collections and record categories should be used.

The module currently combines data from

* `german-wars-battles-worldwide`
* `german-chancellors-presidents`
* `swiss-historic-events`
* `gramps-historical-facts`
* user-specific family events from individual CSV files

<a name="Features"></a>
## 💡 Main features

The module supports

* one combined historic-events module instead of several separate modules
* selectable data collections in the webtrees control panel of this module
* selectable record categories inside each collection
* CSV and GEDCOM files for structured historic-event data
* individual CSV files for user-specific event lists
* optional Wikidata source for chancellors and presidents from Germany, Austria, and Switzerland (using a local file cache for Wikidata responses)

<a name="Data"></a>
## 🗂 Data sources

The module currently includes the following data sources.

* **Wars and Battles Worldwide**  
  Stored as GEDCOM text records in `resources/data/gedcom/german-wars-battles-worldwide.ged`.
  See [provider documentation](docs/german-wars-battles-worldwide.md).

* **German Chancellors Presidents (CSV)**  
  Stored as CSV data in `resources/data/csv/GermanChancellorsPresidents.csv`.
  See [provider documentation](docs/german-chancellors-presidents.md).

* **Chancellors and Presidents from Germany, Austria and Switzerland (Wikidata)**
  Loaded from Wikidata when enabled. Responses are cached below `data/cache/hh-historic-events/`.

* **Historic Events: Switzerland**  
  Stored as CSV data in `resources/data/csv/swiss-historic-events.csv`.
  See [provider documentation](docs/swiss-historic-events.md).

* **Gramps Historical Facts**  
  Gramps CSV files are stored in `resources/data/csv/`.
  See [provider documentation](docs/gramps-historical-facts.md).

* **User-specific event lists:** Administrators can create and manage individual semicolon-separated CSV files in the module settings or webmasters can place them in `data/modules/hh-historic-events/data/`. The built-in five-column editor supports creating, editing, saving the current form state under a new filename, and deleting these persistent files. The supported columns are the start date, end date, event text, source link, and an optional category. New custom files identify their content language, the topic, and the geographical region in the metadata header. Files in this persistent directory survive module upgrades and override bundled CSV files with the same name. See the [custom CSV format specification](docs/custom-csv-format.md) and the [German example file](docs/examples/custom-family-events-de.csv).

  Data files from [Potts Historical Facts](https://github.com/PottsNet/potts-historical-facts) can be converted for use by this module. A webmaster copies a Potts CSV file to `data/modules/hh-historic-events/data/`. The administrator then opens the file under **Control panel / Historic Events / Manage custom CSV files**, completes the language, topic, and optional region metadata, reviews the event rows, and saves the file. Saving rewrites the dates in the Gramps-compatible ISO date representation and adds the metadata header used by this module.

<a name="Configuration"></a>
## ⚙️ Configuration

Administrators can configure the module in the webtrees control panel.

The settings page has two selection steps:

1. Under **Selection by language**, choose the event languages to use. A language controls which collections are available for its historical event texts.
1. Under **Data sources and individual collections**, choose the required collections and, where available, the record categories within each collection. This also controls whether the optional Wikidata collection is used.

For good performance, enable only the data sources and collections that are actually needed. Processing many sources, or a single large collection such as Wars and Battles Worldwide, can noticeably slow down webtrees.
The module caches Wikidata responses for 24 hours.

<a name="Usage"></a>
## 👤 Usage

Users can view the enabled historical events in the `Facts and events` tab by selecting `Historic events`. webtrees places matching events into the timeline and applies its own date and lifetime filtering.

Links supplied by a data source are stored as Markdown notes. Markdown should be enabled for the tree under `Control panel / Manage family trees / Preferences / Text`. Without Markdown, the link remains available, but its presentation is less readable.

Administrators can modify the visual presentation of historical events with the CSS and JS module. The [German webtrees manual](https://wiki.genealogy.net/Webtrees_Handbuch/Entwicklungsumgebung#Beispiel_-_Farben_bei_Historischen_Fakten_anpassen) contains an example.

<a name="Requirements"></a>
## 📌 Requirements

This module requires **webtrees** version 2.2 or later.
It has the same system requirements as [webtrees](https://github.com/fisharebest/webtrees#system-requirements).

The current development version targets **webtrees** 2.2.6 and 2.3 with one shared codebase. Compatibility with the final webtrees 2.3 APIs will be checked again when a beta release of webtrees 2.3 is available.

The optional Wikidata source requires outbound HTTPS access from the web server.

<a name="Installation"></a>
## 📥 Installation

Install and use [Custom Module Manager](https://github.com/Jefferson49/CustomModuleManager)
for an easy and convenient installation of **webtrees** custom modules when this module is available there.

**Manual installation**:
1. Make a database backup.
1. Download the latest release.
1. Unzip the package into the `webtrees/modules_v4` directory of your web server.
1. Rename the folder to `hh-historic-events`.
1. Log in to **webtrees** as an administrator.
1. Go to <span class="pointer">Control Panel / Modules / Historic events</span>.
1. Enable the module named **Historic Events**.

Open the module settings, select the desired event languages, and then select the required data collections and record categories.

<a name="Upgrade"></a>
## ⬆️ Upgrade

To update the module, replace the `hh-historic-events` files with the files from the latest release.

If new data collections or record categories are added, review the module settings after the upgrade.

This combined module replaces `german-wars-battles-worldwide`, `german-chancellors-presidents`, `swiss-historic-events`, and `gramps-historical-facts`.
Do not operate these older modules in parallel.
Disable them and delete their folders from `modules_v4`; the administration page displays a prominent warning while any of them remains active.

<a name="Translation"></a>
## 🌍 Translation

The user-interface translations use GNU gettext catalogs in `resources/lang`: edit the `.po` file, for example with Poedit, and compile the matching `.mo` file. `default.pot` is the template for new or updated translations.

Updated translations can be contributed via a pull request or by email.
They will be included in a future release of the module.

The content of the historical data files cannot be translated. The Wikidata collection **Chancellors and Presidents from Germany, Austria and Switzerland** is multilingual.

<a name="Contributing"></a>
## 🤝 Contributing and testing

Contributions can add or improve historical data, translations, documentation, or module code.

Before changing a data collection, review its provider documentation and file format. Changes to existing collections and proposals for new collections should be tested in webtrees and submitted through a new issue.

<a name="Credits"></a>
## 👏 Credits

This module combines four earlier historic-event modules and preserves their data sources and acknowledgements.
* **Gramps Historical Facts:** Thanks to [Kaj Mikkelsen](https://github.com/kajmikkelsen) and the other contributors to the [HistContext gramplet](https://github.com/kajmikkelsen/HistContext) for the Gramps module and its multilingual historical-event collections. Thanks to [Tazio de Bruin](https://github.com/Tazi0) for the Dutch event collection contributed in [HistContext PR #13](https://github.com/kajmikkelsen/HistContext/pull/13).
* **Historic Events: Switzerland:** Thanks to Peter Jehli-Kamm of [baum.jehli.ch](https://baum.jehli.ch/) for the original collection of events from Swiss history.

The following data collections were prepared by Hermann Hartenthaler.
* **Wars and Battles Worldwide:** [Wikipedia](https://www.wikipedia.org/) is the principal reference source for the worldwide wars and battles collection; individual records link to the relevant articles.
* **German Chancellors and Presidents:** The bundled CSV collection uses the [German Wikipedia](https://de.wikipedia.org/) for biographical references and [Wikimedia Commons](https://commons.wikimedia.org/) for images and attribution. The optional online provider obtains structured data for Germany, Austria, and Switzerland from [Wikidata](https://www.wikidata.org/).

Thanks also to the [webtrees development team](https://github.com/fisharebest/webtrees) for webtrees and its historic-events module interface.

<a name="Support"></a>
## ❓ Support

* <span style="font-weight: bold;">Issues: </span>You can report errors in the [GitHub issue tracker](https://github.com/hartenthaler/hh-historic-events/issues).
* <span style="font-weight: bold;">Feature requests: </span>You can suggest improvements in the [GitHub issue tracker](https://github.com/hartenthaler/hh-historic-events/issues).
* <span style="font-weight: bold;">Forum: </span>General webtrees support can be found in the [webtrees forum](https://www.webtrees.net/).

<a name="License"></a>
## 📄 License

This module is licensed under GPL-3.0-or-later.

* Copyright (C) 2026 Hermann Hartenthaler
* Derived from **webtrees** - Copyright 2026 webtrees development team.

This program is free software: you can redistribute it and/or modify it
under the terms of the GNU General Public License as published by
the Free Software Foundation, either version 3 of the License, or
(at your option) any later version.

This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
GNU General Public License for more details.
