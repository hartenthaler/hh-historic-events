# Resolving Equivalent Historical Events

This document defines how the module presents events that are connected by an
event identity or by an administrator-maintained equivalence pair.

## Principles

* Source files remain authoritative and are never rewritten by the resolver.
* Equivalent records are shown as one historical event where this does not hide
  information.
* Conflicting data is never silently replaced by a preferred value.

## Event text and language

Texts are alternatives, not fields to be merged.  The resolver chooses one
text per equivalent group as follows:

1. a text in the current webtrees user language;
2. otherwise a text in English;
3. otherwise a text in any available language.

The other language variants are suppressed when a preferred text was found.
If several records use the selected language, their different texts remain
available as variants; an identical text is displayed once only.

## Date, link, and category

For every non-text field, an empty value yields to a non-empty value.

* If all non-empty dates agree, the event displays that date.
* If all non-empty links agree, the event displays that link.
* If all non-empty categories agree, the event displays that category.

If non-empty values conflict, no value is discarded.  The primary event keeps
its selected value and the other values are retained as labelled variants for
later inspection.  The resolver does not infer that one source is more
authoritative merely because it is global, regional, newer, or translated.

## Identities

All identities in an event's `Event ID` list, GEDCOM `_UID` subtags, and
source-derived Wikidata statement identities participate in the same
equivalence graph.  The manually maintained equivalence table adds edges to
that graph.  Its derived index records where each identity occurs; it is not
authoritative and can be rebuilt automatically.

## Administration

Administrators manage an equivalence group by entering all of its event IDs
at once, separated by commas, semicolons, spaces, or line breaks. Internally
the module stores the group as pairwise relationships. A group may contain
only one event ID when it is used to attach external references. External
references are optional free text, conventionally separated by semicolons,
for example `GND:4079163-4; Wikidata:Q361`.

The settings page lists every group with the indexed event name, date, and
collection for each ID. This is a quality-control aid; opening historic events
rebuilds the derived index automatically when source data changes.

## Examples

The worldwide, Swiss, and wars-and-battles records for the First World War are
one equivalence group.  A German user sees the German text where available; an
English user sees the English text; another user receives English as the
fallback.  Different source notes remain source variants rather than being
concatenated into one invented statement.
