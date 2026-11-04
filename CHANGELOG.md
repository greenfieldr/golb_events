# Changelog

All notable changes to the TYPO3 extension "golb_events" are documented in
this file. The format is based on conventional commits; the major version
follows the supported TYPO3 major version (14.x.x for TYPO3 14). The file is
regenerated with git-cliff on every release.

## [14.1.0] - 2026-11-04

### Features

- **recurrence:** The PSR-14 event `AfterOccurrencesSynchronizedEvent` is
  dispatched after the occurrences of an event page were synchronized (by
  the DataHandler hook, the command `golbevents:occurrences:generate` and
  when the occurrences of deleted or orphaned pages are removed). It
  carries the added, updated and removed occurrence records
  (`OccurrenceRecord`: uid, pid, start, end, all day) and the number of
  occurrences the event has now, so extensions that reference occurrences
  can react to moved or removed dates

### Bug fixes

- **recurrence:** Occurrence records are kept stable: an existing
  occurrence is matched by its start and keeps its uid, its end, all day
  flag and pid are updated in place, only dates that no longer exist are
  deleted and only new dates are inserted. Before, every save of an event
  page deleted and reinserted all occurrences and changed their uids

### Build and CI

- The release workflow renders the notes of the pushed tag
  (`git-cliff --current --use-branch-tags`) and fails when their version
  differs from the tag; `--latest` picked the newest tag of the whole
  repository by commit date

## [14.0.0] - 2026-11-04

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
- **routing:** Route enhancer configuration for speaking URLs:
  `Configuration/Routing/RouteEnhancers.yaml` maps the Event list plugin
  (pagination `/page/2`, date mode `/past`, `/upcoming` and `/all`, tag
  filter `/tag/<slug>`, category filter `/category/<title>-<uid>` and
  their combinations) and the Event calendar plugin (`/<year>/<month>`);
  `Configuration/Routing/RouteEnhancersIcal.yaml` maps the iCal download
  to `/<event>/event.ics`

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

### Bug fixes

- Saving an event with excluded dates failed because `foreign_sortby`
  made the DataHandler write the sorting into the `date` column; the
  exceptions are now ordered with `foreign_default_sortby`

### Build and CI

- Extension skeleton with the Golb tooling: coding standards, PHPStan,
  Rector, Fractor, unit and functional tests, CI, release and
  documentation workflows
- Add `ext_emconf.php` for TER uploads and classic installations; the
  release workflow checks its version against the tag
