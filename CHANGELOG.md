# Changelog

All notable changes to the TYPO3 extension "golb_events" are documented in
this file. The format is based on conventional commits; the major version
follows the supported TYPO3 major version (14.x.x for TYPO3 14). The file is
regenerated with git-cliff on every release.

## [Unreleased]

### Features

- **frontend:** Complete default frontend: teasers with date badge, image,
  flags, location, organizer and categories and tags as optional filter
  links, a responsive month calendar, a plain stylesheet
  (`Resources/Public/Css/golb_events.css`, on top of Golb's) added by the
  layout and switchable with the constant `includeCss`, and `show.*`
  constants to leave single elements out
- **frontend:** Detail rendering of event pages with `lib.golbEvents.event`
  (facts, "Add to calendar" and registration links, content, upcoming
  dates, location and organizer details, structured data); the
  `EventSchemaProcessor` provides the next and the upcoming occurrences
  (`occurrenceAs`, `upcomingAs`, `upcomingLimit`) and
  `OccurrenceRepository::findUpcomingOfEvent()` finds them

- Event page type (doktype 42) with start and end as wall clock times,
  all day and cancelled flags, location and organizer as record or free
  text, a link and Golb's blog fields (publish date, top post, tags,
  related pages)
- Location records with address, coordinates and time zone, organizer
  records with contact data
- Recurring events (daily, weekly, monthly, yearly; interval, weekdays,
  same weekday of the month, end date or count, excluded dates) expanded
  into occurrences in the time zone of the location, kept in sync by a
  DataHandler hook and the command `golbevents:occurrences:generate`
- Plugins "Event list" (paginated), "Upcoming events" (limited) and
  "Event calendar" (month grid) with date mode or range, categories, tags,
  excluded and top events, sorting and URL parameter filtering
- iCal download of event pages as page type and schema.org Event
  structured data through a data processor
- Documentation for editors, integrators and developers

### Build and CI

- Extension skeleton with the Golb tooling: coding standards, PHPStan,
  Rector, Fractor, unit and functional tests, CI, release and
  documentation workflows
