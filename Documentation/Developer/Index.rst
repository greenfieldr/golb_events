..  include:: /Includes.rst.txt

..  _developer:

================
Developer corner
================

..  _developer-demand:

The demand object
=================

:php:`\Greenfieldr\GolbEvents\Domain\Model\Dto\EventsDemand` describes a
listing: which event dates, in which order, how many. It extends Golb's
:php:`PostsDemand` (categories including sub categories, tags, excluded
pages, top post flags, order, limit, offset, pagination) by the mode and
the date range. The controller builds it from the plugin settings and, if
allowed, from the request arguments. A demand can also be built in PHP:

..  code-block:: php

    use Greenfieldr\GolbEvents\Domain\Model\Dto\EventsDemand;

    $demand = (new EventsDemand())
        ->setMode(EventsDemand::MODE_RANGE)
        ->setFrom(new \DateTimeImmutable('first day of this month midnight'))
        ->setTo(new \DateTimeImmutable('last day of this month 23:59:59'))
        ->setTags([3, 4])
        ->setLimit(5);

Modes: ``MODE_UPCOMING`` (occurrences that have not ended yet, the
default), ``MODE_PAST``, ``MODE_ALL`` and ``MODE_RANGE`` (occurrences
overlapping the range). Without an explicit order the dates are sorted by
their start, chronologically for upcoming and all, most recent first for
past.

..  _developer-repositories:

Repositories
============

:php:`\Greenfieldr\GolbEvents\Domain\Repository\OccurrenceRepository`
    ``findByDemand(EventsDemand $demand, array $rootPages, ?\DateTimeImmutable $now = null): Occurrence[]``
    returns the event dates below the root pages according to the demand.
    ``findNextOfEvent(Event $event, \DateTimeImmutable $now): ?Occurrence``
    returns the next date of an event, or its last one.
    ``findPageIdsBelow(array $rootPages): int[]`` returns the uids of all
    visible pages below the root pages.

:php:`\Greenfieldr\GolbEvents\Domain\Repository\EventRepository`
    A plain Extbase repository for events; ``findByUid()`` returns null
    for pages that are no events.

:php:`\Greenfieldr\GolbEvents\Domain\Repository\LocationRepository` and
:php:`OrganizerRepository`
    Plain Extbase repositories for the record tables.

The repositories ignore the storage page setting; the root pages of the
listing define the scope.

..  _developer-how-it-works:

How event dates are found
=========================

#.  Saving an event page (or one of its excluded dates, or the time zone
    of its location) regenerates the *occurrences* of the event in the
    table ``tx_golbevents_domain_model_occurrence``: one row per date with
    the real start and end as UTC timestamps. The DataHandler hook also
    reacts to copying, deleting, undeleting and publishing pages.
    Occurrences of never ending events are generated up to the horizon
    (extension setting ``horizonYears``); the command
    ``golbevents:occurrences:generate`` regenerates all events, so the
    scheduler keeps the horizon moving.

#.  :php:`\Greenfieldr\GolbEvents\Recurrence\EventSchedule` captures the
    wall clock start and end, the time zone and the recurrence rule of an
    event, built from the model or from a raw database row.
    :php:`RecurrenceRuleBuilder` turns the rule into an RFC 5545 ``RRULE``
    and :php:`OccurrenceGenerator` expands it with the library
    `rlanvin/php-rrule <https://github.com/rlanvin/php-rrule>`__ in the
    time zone of the event, so a weekly 19:00 event stays at 19:00 across
    daylight saving transitions. :php:`OccurrenceSynchronizer` writes the
    result to the database.

#.  The occurrence repository queries the occurrence table joined with the
    event pages below the root pages in SQL: enable fields of the pages
    (hidden, timed, access restricted), the date mode, categories, tags,
    excluded pages, the top post flag, sorting, limit and offset. The
    matching occurrences are then loaded as Extbase objects, so language
    overlays and lazy relations of the events are handled by Extbase.

#.  The list view sets the limit to ``0`` and paginates the result with an
    :php:`ArrayPaginator`; the calendar queries the range of the displayed
    grid and files the occurrences under their days with
    :php:`\Greenfieldr\GolbEvents\Calendar\CalendarBuilder`.

..  _developer-timezones:

Time zones
==========

``tx_golbevents_start`` and ``tx_golbevents_end`` are native ``DATETIME``
columns holding the wall clock time the editor entered, independent of the
server time zone. :php:`Event::getTimezone()` resolves the zone of the
location, falling back to the server zone. Occurrences store the resulting
instants as UTC timestamps; Extbase maps them to :php:`\DateTimeImmutable`
objects in the server zone, which is what the frontend displays.
:php:`EventSchedule::getStartInTimezone()` gives the start as instant in
the zone of the event.

..  _developer-export:

Export and structured data
==========================

:php:`\Greenfieldr\GolbEvents\Export\IcalBuilder`
    ``build(Event $event, string $url, string $host, ?\DateTimeImmutable $now): ?string``
    renders an event as iCalendar document with one ``VEVENT``: wall
    clock times with the IANA time zone (``TZID``), ``RRULE`` and
    ``EXDATE`` for recurring events, summary, description, location,
    coordinates, organizer, status and URL. The page type
    :typoscript:`golbEventsIcal` uses it through
    :php:`\Greenfieldr\GolbEvents\Controller\IcalController`.

:php:`\Greenfieldr\GolbEvents\Schema\EventSchemaBuilder`
    ``build(Event $event, ?Occurrence $occurrence, string $url): array``
    returns the schema.org ``Event`` structured data; the data processor
    :php:`\Greenfieldr\GolbEvents\DataProcessing\EventSchemaProcessor`
    provides it to page templates, see :ref:`templating-detail`.

..  _developer-extending:

Extending
=========

*   **Own controller actions or plugins:** Extend
    :php:`\Greenfieldr\GolbEvents\Controller\EventController` or register
    your own plugin with the repositories; all services are autowired.
*   **Additional page fields:** Add columns to the ``pages`` TCA, extend
    the :php:`Event` model (or XCLASS it) and map the property in
    :file:`Configuration/Extbase/Persistence/Classes.php`.
*   **Date arguments:** The extension registers a type converter for
    :php:`\DateTimeImmutable` properties (``Y-m-d`` for the demand); reuse
    it for your own arguments with the option
    :php:`DateTimeConverter::CONFIGURATION_DATE_FORMAT`.
*   **Workspaces:** Occurrences are generated from live records; changes in
    a workspace become visible in the lists when the page is published.

..  _developer-qa:

Development
===========

Clone the repository and install the development dependencies:

..  code-block:: bash

    composer install
    composer ci            # coding standards, PHPStan, Rector, Fractor, tests
    composer fix           # apply Rector, Fractor and coding standard fixes
    composer ci:coverage   # test suites with code coverage (PCOV or Xdebug)

The functional tests run against SQLite by default
(``typo3DatabaseDriver=pdo_sqlite``). The documentation can be rendered
locally with the official Docker image:

..  code-block:: bash

    docker run --rm -it -v "$(pwd)":/project \
        ghcr.io/typo3-documentation/render-guides:latest --config=Documentation
