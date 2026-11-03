# Golb Events - Events The Page Way

[![CI](https://github.com/greenfieldr/golb_events/actions/workflows/ci.yml/badge.svg)](https://github.com/greenfieldr/golb_events/actions/workflows/ci.yml)
[![Coverage Status](https://coveralls.io/repos/github/greenfieldr/golb_events/badge.svg?branch=main)](https://coveralls.io/github/greenfieldr/golb_events?branch=main)
[![Latest release](https://img.shields.io/github/v/release/greenfieldr/golb_events)](https://github.com/greenfieldr/golb_events/releases)
[![License: GPL-3.0-or-later](https://img.shields.io/badge/license-GPL--3.0--or--later-blue.svg)](LICENSE)

Golb Events is an add-on to the TYPO3 blog extension
[Golb](https://github.com/greenfieldr/golb) that uses **pages as events**.
An event is a page of the custom page type "Event": it is edited in the page
tree, built from regular content elements and carries its dates, location,
organizer and recurrence rule on the page record. Golb's tags, categories
and related pages work for events as well.

- Locations (with address, coordinates and time zone) and organizers as
  reusable records or free text
- Recurring events with excluded dates; every date is stored as an
  occurrence and kept in sync when pages are saved, plus a CLI command for
  the scheduler
- Three plugins: a paginated *Event list*, *Upcoming events* and a month
  *Event calendar*, filtered by date mode or range, categories, tags and
  top events
- iCal download of every event page and schema.org structured data through
  a data processor
- A complete frontend out of the box: teasers with date badge, image,
  flags, location and organizer, a responsive month calendar, a detail
  rendering of the event page (`lib.golbEvents.event`) with dates, location
  and organizer details, "Add to calendar" link and structured data, and a
  plain stylesheet without framework or brand colours that adapts to the
  site. Every piece can be switched off, overridden or replaced with your
  own templates and CSS.

## Compatibility

| Golb Events | Golb  | TYPO3 | PHP       |
|-------------|-------|-------|-----------|
| 14.x        | 14.x  | 14.x  | 8.2 - 8.5 |

The major version follows the supported TYPO3 major version.

## Installation

```bash
composer require greenfieldr/golb_events
```

Then run "Analyze Database Structure", include the static TypoScript
"Golb" and "Golb Events", create event pages of type "Event" below an event
root page and add one of the plugins. Run `golbevents:occurrences:generate`
regularly to extend the dates of recurring events. The full documentation
is in the [Documentation](Documentation/) folder.

## Development

```bash
composer install
composer ci             # coding standards, PHPStan, Rector, Fractor, unit and functional tests
composer fix            # apply automated fixes
composer ci:coverage    # test suites with code coverage (needs PCOV or Xdebug)
```

Commit messages follow [Conventional Commits](https://www.conventionalcommits.org/).
Releases are tagged `14.x.x` (TYPO3 14); the changelog is generated from the
commit history with git-cliff.

## License

GPL-3.0-or-later, see [LICENSE](LICENSE).
