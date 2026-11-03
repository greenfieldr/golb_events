..  include:: /Includes.rst.txt

..  _templating:

==========
Templating
==========

..  _templating-templates:

Templates
=========

The default templates are deliberately simple: an unordered list of event
dates with title, date and time, location, organizer, abstract,
categories and tags, and a table for the calendar. Copy them to your site
package and register the path as described in
:ref:`configuration-typoscript`. The layout and the pagination partial
come from Golb and are shared with the blog plugins.

..  code-block:: text

    EXT:golb/Resources/Private/
    ├── Layouts/Default.html         Wraps the output in <div class="golb">
    └── Partials/Pagination.html     Pagination of the event list

    EXT:golb_events/Resources/Private/
    ├── Partials/Occurrence.html     One event date in a list
    └── Templates/Event/
        ├── Calendar.html            Event calendar plugin
        ├── List.html                Event list plugin
        └── Upcoming.html            Upcoming events plugin

Variables available in the templates:

..  list-table::
    :header-rows: 1

    *   -   Variable
        -   Template
        -   Description
    *   -   ``occurrences``
        -   all
        -   Array of :php:`\Greenfieldr\GolbEvents\Domain\Model\Occurrence`
            objects. In the list view this is the complete, unpaginated
            result, in the calendar all dates of the displayed grid.
    *   -   ``paginator``
        -   List
        -   :php:`\TYPO3\CMS\Core\Pagination\ArrayPaginator`, use
            ``paginator.paginatedItems`` for the dates of the current page
    *   -   ``pagination``
        -   List
        -   :php:`\TYPO3\CMS\Core\Pagination\SimplePagination`
    *   -   ``calendar``
        -   Calendar
        -   The month grid, see :ref:`templating-calendar`
    *   -   ``demand``
        -   all
        -   The demand object, e.g. ``demand.mode`` or
            ``demand.currentPage``
    *   -   ``contentElementData``
        -   all
        -   The tt_content record of the plugin
    *   -   ``settings``
        -   all
        -   Plugin settings (FlexForm merged with TypoScript), including
            ``dateFormat`` and ``timeFormat``

..  _templating-occurrence:

The occurrence object
=====================

Lists show *occurrences*: one entry per date of an event. An occurrence
is a :php:`\Greenfieldr\GolbEvents\Domain\Model\Occurrence`:

..  list-table::
    :header-rows: 1

    *   -   Property
        -   Description
    *   -   ``start``, ``end``
        -   :php:`\DateTimeImmutable` objects in the time zone of the
            website. Format them with
            :ref:`f:format.date <t3viewhelper:typo3-fluid-format-date>`.
    *   -   ``allDay``
        -   Boolean; all day occurrences start at midnight and end at the
            last second of their last day
    *   -   ``endTime``
        -   Boolean, whether the end is later than the start
            (``hasEndTime()``)
    *   -   ``multiDay``
        -   Boolean, whether start and end fall on different days
    *   -   ``event``
        -   The event, see :ref:`templating-event`

..  code-block:: html

    <time datetime="{occurrence.start -> f:format.date(format: 'c')}">
        {occurrence.start -> f:format.date(format: settings.dateFormat)}
        <f:if condition="!{occurrence.allDay}">{occurrence.start -> f:format.date(format: settings.timeFormat)}</f:if>
    </time>

..  _templating-event:

The event object
================

An event is a :php:`\Greenfieldr\GolbEvents\Domain\Model\Event`, which
extends Golb's :php:`Page` model: everything documented for blog posts
(``title``, ``abstract``, ``author``, ``categories``, ``tags``,
``relatedPages``, ``topPost``, ``media`` and so on) is available.
Additional properties:

..  list-table::
    :header-rows: 1

    *   -   Property
        -   Description
    *   -   ``start``, ``end``
        -   The entered wall clock times as :php:`\DateTimeImmutable`
            (first date of a recurring event). Prefer the occurrence
            dates in lists.
    *   -   ``allDay``, ``cancelled``
        -   Boolean flags
    *   -   ``location``, ``locationText``, ``locationName``
        -   The location record (or null), the free text and the name of
            whichever is set
    *   -   ``organizer``, ``organizerText``, ``organizerName``
        -   The organizer record (or null), the free text and the name
    *   -   ``link``
        -   Registration or ticket link (TYPO3 link syntax, use
            :html:`<f:link.typolink>`)
    *   -   ``timezone``
        -   :php:`\DateTimeZone` of the event (location or server)
    *   -   ``recurring``, ``recurrenceFrequency``, ``recurrenceInterval``,
            ``recurrenceWeekdayList``, ``recurrenceEnd``,
            ``recurrenceUntil``, ``recurrenceCount``, ``exceptions``
        -   The recurrence rule
    *   -   ``occurrences``
        -   All generated dates of the event, sorted by start

A location record offers ``name``, ``street``, ``zip``, ``city``,
``country``, ``latitude``, ``longitude``, ``timezone``, ``link`` and
``description``; an organizer ``name``, ``email``, ``phone``, ``link`` and
``description``.

Links to an event are regular page links:

..  code-block:: html

    <f:link.page pageUid="{occurrence.event.uid}">{occurrence.event.title}</f:link.page>

..  _templating-calendar:

The calendar
============

The calendar template receives a :php:`\Greenfieldr\GolbEvents\Calendar\Calendar`:

..  list-table::
    :header-rows: 1

    *   -   Property
        -   Description
    *   -   ``year``, ``month``, ``firstDay``
        -   The displayed month; ``firstDay`` is a :php:`\DateTimeImmutable`
            for headings like ``{calendar.firstDay -> f:format.date(format: '%B %Y')}``
    *   -   ``weeks``
        -   Array of weeks, each an array of seven days
    *   -   ``weekdays``
        -   The days of the first week, for the column headers
    *   -   ``previousYear``, ``previousMonth``, ``nextYear``, ``nextMonth``
        -   Navigation targets for :html:`<f:link.action action="calendar" arguments="{year: ..., month: ...}">`
    *   -   ``events``
        -   Boolean, whether any day has an occurrence

Each day is a :php:`\Greenfieldr\GolbEvents\Calendar\CalendarDay` with
``date`` (midnight), ``inMonth`` (false for the leading and trailing days
of the adjacent months), ``today`` and ``occurrences``. A multi day
occurrence is listed on every day it covers.

..  _templating-detail:

Rendering the event page
========================

An event is rendered by the page template of the site. To access the
event (for example to show the dates and the location in the page
header), its structured data and the iCal download, add the data
processor to the page object:

..  code-block:: typoscript

    page.10 = PAGEVIEW
    page.10 {
        paths.10 = EXT:site_package/Resources/Private/Templates/
        dataProcessing {
            10 = Greenfieldr\GolbEvents\DataProcessing\EventSchemaProcessor
            10.as = event
            10.schemaAs = eventSchema
        }
    }

``event`` is then the :php:`Event` object of the current page (null for
pages that are no events), ``eventSchema`` the schema.org data as array
and ``eventSchemaJson`` the same as JSON for a ``<script>`` tag. The dates
of the structured data are those of the next occurrence (or the last one
once the event is over).

..  code-block:: html

    <f:if condition="{event}">
        <p class="event-meta">
            {event.start -> f:format.date(format: 'd.m.Y H:i')}
            <f:if condition="{event.locationName}">, {event.locationName}</f:if>
            <f:if condition="{event.cancelled}"> (cancelled)</f:if>
        </p>
        <f:link.page pageUid="{event.uid}" pageType="1764">Add to calendar</f:link.page>
        <f:format.raw><script type="application/ld+json">{eventSchemaJson}</script></f:format.raw>
    </f:if>

The page type ``1764`` of the download link is the default of the constant
:typoscript:`plugin.tx_golbevents.settings.icalTypeNum`, see
:ref:`configuration-ical`.

..  _templating-pagination:

Pagination
==========

Golb's pagination partial renders links with the argument
``demand[currentPage]`` of the plugin. Other demand arguments of the
current request (for example a tag filter) are not carried over by the
default partial; extend it with :html:`<f:link.action addQueryString="untrusted">`
or explicit arguments if you need that.

The labels are translated with the keys ``pagination.first``,
``pagination.previous``, ``pagination.next`` and ``pagination.last`` of
:file:`EXT:golb_events/Resources/Private/Language/locallang.xlf`.
