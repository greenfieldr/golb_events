# Golb Events - implementation plan

Agreed setup for the TYPO3 extension `golb_events`, an add-on to
[EXT:golb](https://github.com/greenfieldr/golb) that turns pages into events.
This file describes the first release (14.0.0) and is the reference for the
implementation. It will be replaced by the rendered documentation once the
extension is complete.

## Identity and compatibility

| Item              | Value                                   |
|-------------------|-----------------------------------------|
| Extension key     | `golb_events`                           |
| Composer name     | `greenfieldr/golb_events`               |
| PHP namespace     | `Greenfieldr\GolbEvents`                |
| Page type         | doktype `42` ("Event"), golb uses `41`  |
| TYPO3             | `^14.3` (main branch only)              |
| PHP               | `^8.2` (CI: 8.2, 8.3, 8.4, 8.5)         |
| Dependencies      | `greenfieldr/golb ^14.0`, `rlanvin/php-rrule ^3.0` |
| First release     | `14.0.0`, major follows the TYPO3 major |
| License           | GPL-3.0-or-later                        |

## Reuse from golb (keep redundancy small)

- `Event` model extends `Greenfieldr\Golb\Domain\Model\Page`. Extbase maps it
  to `pages` with `recordType` 42, so event queries are restricted to the
  event doktype automatically.
- Events keep golb's publish date, tags, categories, related pages, top post
  flag and author image. golb's "Golb" tab is shown for doktype 42 too by
  widening the display conditions of golb's columns in a TCA override of
  golb_events. The archived flag is hidden for events (past events are
  derived from their dates).
- `EventRepository` extends golb's `PageRepository` to reuse its tree,
  category, tag and sorting helpers.
- `EventsDemand` extends golb's `PostsDemand` and adds the event specific
  filters (mode, date range).
- `EventController` extends golb's `BaseController`.
- Templates reuse golb's `Default` layout and `Pagination` partial through
  the TypoScript root path chain (golb paths at index 0, golb_events paths at
  index 5, site paths at index 10).
- Out of scope, possibly a separate extension later: registration, booking.

golb changes that this needs are done in golb in separate pull requests and
listed in "golb follow-ups" at the end.

## Dates and time zones

TYPO3 14 enables `extbase.consistentDateTimeHandling` by default and maps TCA
`datetime` columns to `\DateTimeImmutable` properties. Core has no per record
time zone: integer columns store UTC instants, native `DATETIME` columns store
the wall clock time in server local time without an offset.

Decision for events:

- `start` and `end` are native `DATETIME` columns (`dbType = datetime`). They
  hold the wall clock time the editor entered, independent of the server time
  zone.
- `timezone` is an IANA zone name (select field, items from
  `DateTimeZone::listIdentifiers()`). Resolution order: event field, zone of
  the selected location, site or server time zone.
- The real instant of an occurrence is the wall clock time interpreted in the
  resolved zone. Occurrences store that instant as integer UTC timestamps so
  that lists and the calendar can filter and sort in SQL.
- `allDay` events ignore the time part and the zone.
- golb's `publishDate` stays an integer for now. Migrating golb's `Page` model
  to `\DateTimeImmutable` properties is a separate task in golb.

## Recurrence

- Structured TCA fields on the event page, rendered as an "Event" tab:
  frequency (none, daily, weekly, monthly, yearly), interval, weekdays (for
  weekly), monthly mode (same day of month or same weekday position), end
  (never, until date, after N occurrences).
- `RecurrenceRuleBuilder` turns the fields into an RFC 5545 `RRULE` and
  `rlanvin/php-rrule` expands it (`RSet` with `EXDATE` support).
- Exceptions are inline child records (`tx_golbevents_domain_model_exception`
  with the excluded date). Overriding a single occurrence can be added later
  on the same table.
- Occurrences are materialised in `tx_golbevents_domain_model_occurrence`
  (event, start UTC, end UTC, all day flag) by an `OccurrenceGenerator`
  service: on save through a DataHandler hook, and on demand through the
  CLI command `golbevents:occurrences:generate`, which the scheduler runs to
  extend the horizon. The horizon (years ahead, default 2) is an extension
  setting.
- A non recurring event has exactly one occurrence. Translations share the
  occurrences of the default language record (date fields use
  `l10n_mode = exclude`).

## Data model

Fields on `pages` for doktype 42 (prefix `tx_golbevents_`):
`start`, `end`, `all_day`, `timezone`, `location` (relation),
`location_text` (free text, used when no relation is selected), `organizer`
(relation), `organizer_text`, `link` (registration or tickets), `cancelled`,
the recurrence fields above and `exceptions` (inline).

New tables, each with its own TCA, model, repository, icon and language
labels, translatable and workspace aware like golb's tag table:

- `tx_golbevents_domain_model_location`: name, street, zip, city (required),
  country, latitude, longitude, timezone, link, description.
- `tx_golbevents_domain_model_organizer`: name, email, phone, link,
  description.
- `tx_golbevents_domain_model_exception`: event, date.
- `tx_golbevents_domain_model_occurrence`: event, start, end, all_day
  (generated, not edited in the backend).

Models: `Event`, `Location`, `Organizer`, `Exception`, `Occurrence`,
`Dto\EventsDemand`. The schema is generated from TCA, there is no
`ext_tables.sql`.

## Plugins and frontend

Three plugins registered like golb's, each with a FlexForm:

- **Event list**: paginated occurrences. Settings: mode (upcoming, past, all,
  date range), from, to, categories (plugin record), exclude, items per page,
  sorting, sorting direction, allowDemandOverwrite.
- **Upcoming events**: limited number of upcoming occurrences, same filters
  without pagination.
- **Calendar**: server rendered month grid with previous and next month
  navigation through plugin arguments, occurrences per day. Built by a
  `CalendarBuilder` service, no JavaScript dependency.

Lists show occurrences, each with its event, so a recurring event appears once
per date. Default sorting is start ascending for upcoming and descending for
past.

Exports and SEO:

- `EventSchemaProcessor` (DataProcessor) adds schema.org `Event` JSON-LD to
  event pages, including location, organizer, status (cancelled) and the
  next occurrence.
- iCal download of a single event as a frontend page type (`typeNum` from a
  TypoScript constant) rendered by `IcalController`, with a documented route
  enhancer for `/event.ics`. Recurring events export the `RRULE` and
  `EXDATE`s.

## Tooling, identical to golb

- `Build/` with php-cs-fixer (`typo3/coding-standards`), PHPStan level 9 with
  strict rules, phpstan-typo3, phpunit, deprecation and disallowed calls,
  Rector with typo3-rector, Fractor, PHPUnit unit and functional suites.
- `composer ci`, `composer fix`, `composer ci:coverage` scripts as in golb.
- GitHub workflows: CI (code quality matrix, tests on SQLite, coverage to
  Coveralls, documentation rendering), commitlint, release with git-cliff.
  The TER upload step stays in the release workflow but is skipped until the
  `TYPO3_API_TOKEN` secret exists.
- `.editorconfig`, `.gitattributes`, `.gitignore`, `cliff.toml`,
  `.commitlintrc.json`, `CHANGELOG.md`, README with badges, Conventional
  Commits.
- Documentation folder rendered with the TYPO3 render-guides: Introduction,
  Installation, Editor, Configuration, Templating, Developer.
- English language files only.

## Tests

Unit: `EventsDemand`, `RecurrenceRuleBuilder`, `OccurrenceGenerator`
(time zones, daylight saving transitions, all day, exceptions, horizon),
`CalendarBuilder`, iCal rendering, model accessors.

Functional (SQLite, golb loaded as test extension): `OccurrenceRepository`
with CSV fixtures, DataHandler hook regenerating occurrences, the CLI command,
`EventController` rendering list, upcoming and calendar through a real
frontend request, iCal page type, `EventSchemaProcessor`, TCA and persistence
mapping of the new tables.

## Implementation order

1. Skeleton: composer, tooling, workflows, docs scaffold, CI green on an
   empty extension.
2. Doktype, TCA, tables, models, persistence mapping, icons, labels.
3. Recurrence rule builder and occurrence generator with DataHandler hook and
   CLI command.
4. Repositories and demand object.
5. Plugins, controller, templates, calendar builder.
6. JSON-LD processor and iCal page type.
7. Documentation and changelog.

## golb follow-ups (separate pull requests in golb)

- Migrate `Page::publishDate` and the other timestamp properties to
  `\DateTimeImmutable` under the TYPO3 14 consistent DateTime handling.
- Optional: let golb accept additional doktypes for its tab and fields, so
  that golb_events does not need to patch golb's display conditions.
