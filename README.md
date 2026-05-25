# 📜 **webtrees** module for Historic Events (hh-historic-events)

![Latest Release](https://img.shields.io/github/v/release/hartenthaler/hh-historic-events)
[![License: GPL v3](https://img.shields.io/badge/License-GPL%20v3-blue.svg)](http://www.gnu.org/licenses/gpl-3.0)

![webtrees major version](https://img.shields.io/badge/webtrees-v2.1.x-green)
![webtrees major version](https://img.shields.io/badge/webtrees-v2.2.x-green)

This [webtrees](https://www.webtrees.net) custom module provides selectable historical events for the webtrees timeline.
It combines several earlier historic-event modules into one configurable module, so shared improvements can be maintained in one place.

<a name="Contents"></a>
## 📚 Contents

This Readme contains the following main sections

* [Purpose](#Purpose)
* [Scope](#Scope)
* [Main features](#Features)
* [Data sources](#Data)
* [Configuration](#Configuration)
* [Architecture](#Architecture)
* [Requirements](#Requirements)
* [Installation](#Installation)
* [Upgrade](#Upgrade)
* [Translation](#Translation)
* [Support](#Support)
* [License](#License)

<a name="Purpose"></a>
## 🎯 Purpose

The standard webtrees timeline can display historical events that are provided by custom modules.
This module brings several historic-event data collections together in a single module and lets administrators decide which sources and record types should be used.

The module currently combines data from

* `german-wars-battles-worldwide`
* `german-chancellors-presidents`
* `swiss-historic-events`
* `gramps-historical-facts`

<a name="Scope"></a>
## 🔎 Scope

The module provides GEDCOM-style historical event records through the webtrees historic-events module interface.
It does not create GEDCOM records in family trees and it does not store historical events in a database table.

Static data is stored in files below `resources/data`.
Large legacy PHP event arrays were moved to GEDCOM text files first, so the data can be maintained outside PHP code.
A later conversion of these text files to CSV is planned.

Wikidata support for German chancellors and presidents is available as a separate optional data source.
It is disabled by default because it performs external requests.

<a name="Features"></a>
## 💡 Main features

The module supports

* one combined historic-events module instead of several separate modules
* selectable data sources in the webtrees control panel
* selectable record types inside each data source
* GEDCOM text files for large legacy event lists
* CSV files for structured historic-event data
* optional Wikidata source for German chancellors and presidents
* local file cache for Wikidata responses
* gettext translations reused from the earlier modules where available

<a name="Data"></a>
## 🗂 Data sources

The module currently includes the following data sources.

* **Wars and Battles Worldwide**  
  Stored as GEDCOM text records in `resources/data/gedcom/german-wars-battles-worldwide.ged`.
  See [provider documentation](docs/german-wars-battles-worldwide.md).

* **German Chancellors Presidents (CSV)**  
  Stored as CSV data in `resources/data/csv/GermanChancellorsPresidents.csv`.
  See [provider documentation](docs/german-chancellors-presidents.md).

* **German Chancellors Presidents (Wikidata)**  
  Loaded from Wikidata when enabled. Responses are cached below `data/cache/hh-historic-events/`.

* **Historic Events: Switzerland**  
  Stored as GEDCOM text records in `resources/data/gedcom/swiss-historic-events.ged`.
  See [provider documentation](docs/swiss-historic-events.md).

* **Gramps Historical Facts**  
  Stored as CSV files in `resources/data/csv/*_data_v1_0.csv` and related Gramps CSV files.
  See [provider documentation](docs/gramps-historical-facts.md).

<a name="Configuration"></a>
## ⚙️ Configuration

Administrators can configure the module in the webtrees control panel.

The most important settings are

* which data sources are enabled
* which record types are enabled inside a data source
* whether the Wikidata source is used

The Wikidata source should only be enabled if the web server may perform outbound HTTPS requests to Wikidata.
The module caches Wikidata responses for 24 hours.
If the cache directory is not writable, the module still works, but Wikidata responses are not stored locally.

<a name="Architecture"></a>
## 🧭 Architecture

The module is implemented as a webtrees custom historic-events module.
The main module class `HhHistoricEvents` handles webtrees integration and administration settings.

Historic data is loaded through provider classes:

* `TextGedcomEventProvider`
* `GrampsCsvEventProvider`
* `GermanChancellorsPresidentsCsvProvider`
* `GermanChancellorsPresidentsWikidataProvider`

The `EventDataProviderFactory` creates the available providers.
New historical topics can be added by creating another provider and registering it in the factory.

<a name="Requirements"></a>
## 📌 Requirements

This module requires **webtrees** version 2.1 or later.
It has the same system requirements as [webtrees](https://github.com/fisharebest/webtrees#system-requirements).

The current development version is tested with **webtrees** 2.2.6
and all available themes and custom modules.

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
1. Login to **webtrees** as administrator.
1. Go to <span class="pointer">Control Panel / Modules / Historic events</span>.
1. Enable the module named **Historic Events**.
1. Open the module settings and select the desired data sources and record types.

<a name="Upgrade"></a>
## ⬆️ Upgrade

To update the module, replace the `hh-historic-events` files with the files from the latest release.

If new data sources or record types are added, review the module settings after the upgrade.

<a name="Translation"></a>
## 🌍 Translation

Translation files are stored in `resources/lang`.

Updated translations can be contributed by pull request or by e-mail.
They will be included in a future release of the module.

<a name="Support"></a>
## ❓ Support

* <span style="font-weight: bold;">Issues: </span>You can report errors by creating an issue in this GitHub repository.
* <span style="font-weight: bold;">Feature requests: </span>You can suggest improvements by creating an issue in this GitHub repository.
* <span style="font-weight: bold;">Forum: </span>General webtrees support can be found in the [webtrees forum](https://www.webtrees.net/).

<a name="License"></a>
## 📄 License

This module uses GPL-3.0-or-later as a license.

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
