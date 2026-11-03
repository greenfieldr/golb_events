# Changelog

All notable changes to the TYPO3 extension "golb_events" are documented in
this file. The format is based on conventional commits; the major version
follows the supported TYPO3 major version (14.x.x for TYPO3 14). The file is
regenerated with git-cliff on every release.

## [Unreleased]

### Features

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
