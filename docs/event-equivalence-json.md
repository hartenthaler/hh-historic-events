# Event equivalence JSON format

Administrators can export and import complete historical-event equivalence groups as JSON. This portable format transfers only manually maintained groups; the derived event-identity index is rebuilt automatically from the enabled collections.

```json
{
  "format": "hh-historic-events-equivalences",
  "version": 1,
  "groups": [
    {
      "title": "First World War",
      "event_ids": ["uuid-v4", "another-uuid-v4"],
      "external_references": "GND:4079163-4; Wikidata:Q361"
    }
  ]
}
```

`format` and `version` are required. Every group needs one or more valid event IDs. `title` and `external_references` are optional. A missing title is suggested from the first indexed event and can be edited in the settings.

Imports merge groups that share an event ID and ignore duplicate identity pairs. Existing manual groups are retained. The bundled `resources/data/event-equivalences.json` is imported once when the module first creates or upgrades its equivalence storage; it provides initial links between equivalent bundled records.

## Import limits and validation

Only administrators can import the file. The module accepts at most 1 MiB of JSON, 5,000 groups, and 100 event IDs per group. JSON is decoded with a shallow maximum nesting depth. Titles are limited to 255 characters and external references to 4,096 characters. The complete file is validated before the module changes the equivalence table; file names and MIME types are not trusted as a security decision.
