..  include:: /Includes.rst.txt

..  _templating:

==========
Templating
==========

..  _templating-templates:

Templates
=========

The default templates render complete, styled lists and a detail view:
teasers with a date badge, image, flags ("Cancelled", "Top event"),
title, date and time, location, organizer, abstract, categories and tags;
a paginated list; a month calendar; and the article of an event page with
its dates, location and organizer details, "Add to calendar" and
registration links and the schema.org structured data. The markup uses
BEM style classes (``golbevents-event``, ``golbevents-event__title``,
``golbevents-event--cancelled``, ...) on top of Golb's and is styled by a
plain stylesheet without framework or colours, see :ref:`templating-css`.
The layout, the pagination and the categories and tags partial come from
Golb and are shared with the blog plugins.

..  code-block:: text

    EXT:golb/Resources/Private/
    ├── Layouts/Default.html         Adds the stylesheets and wraps the output in <div class="golb">
    └── Partials/
        ├── Pagination.html          Pagination of the event list
        └── Post/Terms.html          Categories and tags, optionally as filter links

    EXT:golb_events/Resources/Private/
    ├── Partials/
    │   ├── Occurrence.html          One event date as teaser in a list
    │   └── Event/
    │       ├── Date.html            Date and time of an occurrence
    │       ├── Flags.html           "Cancelled" and "Top event" badges
    │       ├── Location.html        Location details of the detail rendering
    │       └── Organizer.html       Organizer details of the detail rendering
    └── Templates/
        ├── Event/
        │   ├── Calendar.html        Event calendar plugin
        │   ├── List.html            Event list plugin (list with pagination)
        │   └── Upcoming.html        Upcoming events plugin
        └── Page/Event.html          Detail rendering of an event page (lib.golbEvents.event)

    EXT:golb_events/Resources/Public/Css/golb_events.css   Default stylesheet (with Golb's golb.css)

To change the markup copy the files you need to your site package and
register the path as described in :ref:`configuration-typoscript`; a
file found in your path replaces the default file of the same name. The
elements of the default templates can also be switched off one by one
with the ``show`` settings, see :ref:`configuration-constants`.

Variables available in the plugin templates:

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
            ``demand.currentPage``; the list view shows a "Show all events"
            link while a tag or category filter is active
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

An event is rendered by the page template of the site. The extension
ships the TypoScript object :typoscript:`lib.golbEvents.event`, a
FLUIDTEMPLATE that renders the complete article of the current page:
flags, title and subtitle; the next date (or the last one with a note
once the event is over), location and organizer; an "Add to calendar"
link to the :ref:`iCal download <configuration-ical>` and the
registration link; the first media file, the abstract and the page
content (``styles.content.get`` of fluid_styled_content); the upcoming
dates of a recurring event; the location (address, map and website) and
organizer (email, phone, website) details; categories and tags; and the
schema.org structured data as ``<script type="application/ld+json">``.

Insert it in the page template of the site in place of the content:

..  code-block:: html

    <f:cObject typoscriptObjectPath="lib.golbEvents.event" data="{data}"/>

or let TypoScript replace the content of event pages:

..  code-block:: typoscript

    [page["doktype"] == 42]
    page.10.variables.content =< lib.golbEvents.event
    [END]

The template :file:`Templates/Page/Event.html` and the partials it uses
are overridden like the plugin templates (the ``Page/`` folder below
:typoscript:`plugin.tx_golbevents.view.templateRootPath`); the object
copies :typoscript:`plugin.tx_golbevents.settings`, so the ``show``
settings, the image sizes and the filter links apply to it as well.

If you prefer to build the detail view yourself, add the data processor
to the page object and work with the event:

..  code-block:: typoscript

    page.10 = PAGEVIEW
    page.10 {
        paths.10 = EXT:site_package/Resources/Private/Templates/
        dataProcessing {
            10 = Greenfieldr\GolbEvents\DataProcessing\EventSchemaProcessor
            10.as = event
            10.schemaAs = eventSchema
            10.occurrenceAs = nextOccurrence
            10.upcomingAs = upcomingOccurrences
            10.upcomingLimit = 5
        }
    }

``event`` is then the :php:`Event` object of the current page (null for
pages that are no events), ``eventSchema`` the schema.org data as array
and ``eventSchemaJson`` the same as JSON for a ``<script>`` tag,
``nextOccurrence`` the next date (or the last one once the event is over)
and ``upcomingOccurrences`` the next ``upcomingLimit`` dates that have not
ended (none for past events). The dates of the structured data are those
of the next occurrence.

..  code-block:: html

    <f:if condition="{event}">
        <p class="event-meta">
            {nextOccurrence.start -> f:format.date(format: 'd.m.Y H:i')}
            <f:if condition="{event.locationName}">, {event.locationName}</f:if>
            <f:if condition="{event.cancelled}"> (cancelled)</f:if>
        </p>
        <f:link.page pageUid="{event.uid}" pageType="1764">Add to calendar</f:link.page>
        <f:format.raw><script type="application/ld+json">{eventSchemaJson}</script></f:format.raw>
    </f:if>

The page type ``1764`` of the download link is the default of the constant
:typoscript:`plugin.tx_golbevents.settings.icalTypeNum`, available in the
templates as ``settings.icalTypeNum``, see :ref:`configuration-ical`.

..  _templating-filter-links:

Filter links
============

With the constant
:typoscript:`plugin.tx_golbevents.settings.filterLinks.enabled` the
categories and tags of the teasers and the detail rendering link to the
Event list plugin, filtered by that term. The links point to the page
:typoscript:`filterLinks.pageUid` (by default the current page) and carry
the demand argument of the plugin namespace, e.g.
``tx_golbevents_listview[demand][tag]=3``. The Event list plugin on the
target page has to allow demand overwrites, see
:ref:`configuration-demand`.

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

..  _templating-css:

Styling
=======

The default stylesheet :file:`Resources/Public/Css/golb_events.css`
builds on Golb's :file:`golb.css` (custom properties, lists, flags,
terms, pagination and the detail layout) and adds the event blocks: the
date badge, the facts and actions of the detail rendering, the dates,
location and organizer sections and the calendar grid, which turns into
a list of the days with events on small screens. Both files are plain
CSS without framework or colours of their own: borders and surfaces are
translucent greys and secondary text is dimmed, so the output follows the
font, text colour and background of the site.

Golb's layout adds the files listed in
:typoscript:`plugin.tx_golbevents.settings.css` with :html:`<f:asset.css>`
as long as :typoscript:`settings.includeCss` is enabled. Three ways to
adapt the look:

#.  **Tweak:** Override the custom properties on ``.golb`` in your own
    stylesheet (spacing, border radius, border and surface tints), see
    the Golb manual.

#.  **Extend or replace:** Add your file to the list or remove the
    defaults:

    ..  code-block:: typoscript

        plugin.tx_golbevents.settings.css {
            golbEvents >
            site = EXT:site_package/Resources/Public/Css/events.css
        }

#.  **Switch off:** Set the constant
    :typoscript:`plugin.tx_golbevents.settings.includeCss = 0` and style
    the markup in the stylesheet of the site, or override the templates.
