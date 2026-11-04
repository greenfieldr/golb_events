..  include:: /Includes.rst.txt

..  _configuration:

=============
Configuration
=============

..  _configuration-typoscript:

TypoScript
==========

The static TypoScript "Golb Events" defines the view paths and settings of
the plugins under :typoscript:`plugin.tx_golbevents`. The plugin specific
objects :typoscript:`plugin.tx_golbevents_listview`,
:typoscript:`plugin.tx_golbevents_upcomingview` and
:typoscript:`plugin.tx_golbevents_calendarview` are copied from it, so
settings can be changed for all plugins at once or for a single plugin.
It also defines the page object of the :ref:`iCal download
<configuration-ical>`.

..  confval:: plugin.tx_golbevents.view.templateRootPaths
    :name: typoscript-templateRootPaths
    :type: array

    Template paths. Index ``0`` holds Golb's templates (the layout and the
    pagination partial are reused), index ``5`` the templates of this
    extension. Add your own path with a higher index to override them,
    see :ref:`templating`.

..  confval:: plugin.tx_golbevents.view.partialRootPaths
    :name: typoscript-partialRootPaths
    :type: array

    Partial paths, same mechanism as the template paths.

..  confval:: plugin.tx_golbevents.view.layoutRootPaths
    :name: typoscript-layoutRootPaths
    :type: array

    Layout paths, same mechanism as the template paths.

..  confval:: plugin.tx_golbevents.settings.includeCss
    :name: typoscript-includeCss
    :type: boolean (constant)
    :default: ``1``

    Whether Golb's layout adds the stylesheets of
    ``settings.css`` to the page. Disable it to style
    the markup yourself.

..  confval:: plugin.tx_golbevents.settings.css
    :name: typoscript-css
    :type: array

    Stylesheets added to the page while includeCss is enabled, by default
    ``golb = EXT:golb/Resources/Public/Css/golb.css`` and
    ``golbEvents = EXT:golb_events/Resources/Public/Css/golb_events.css``.
    Remove or add entries to replace or extend the default styles, see
    :ref:`templating-css`.

..  confval:: plugin.tx_golbevents.settings.dateFormat
    :name: typoscript-dateFormat
    :type: string (constant)
    :default: ``%d.%m.%Y``

    Date format used by the default templates, see the
    :ref:`f:format.date <t3viewhelper:typo3-fluid-format-date>` ViewHelper.

..  confval:: plugin.tx_golbevents.settings.timeFormat
    :name: typoscript-timeFormat
    :type: string (constant)
    :default: ``%H:%M``

    Time format used by the default templates.

..  confval:: plugin.tx_golbevents.settings.calendar.firstDayOfWeek
    :name: typoscript-firstDayOfWeek
    :type: integer (constant)
    :default: ``1``

    First day of the week in the calendar plugin: ``1`` for Monday up to
    ``7`` for Sunday.

..  confval:: plugin.tx_golbevents.settings.filterLinks.namespace
    :name: typoscript-filterLinks-namespace
    :type: string
    :default: ``tx_golbevents_listview``

    Plugin namespace of the demand arguments the
    :ref:`filter links <templating-filter-links>` carry.

..  confval:: plugin.tx_golbevents.settings.icalTypeNum
    :name: typoscript-icalTypeNum
    :type: integer (constant)
    :default: ``1764``

    Page type (``typeNum``) that delivers an event page as iCalendar file.

The object :typoscript:`lib.golbEvents.event` renders the detail view of
an event page, see :ref:`templating-detail`. It copies
:typoscript:`plugin.tx_golbevents.settings`, so settings that should
apply to the plugins and the detail rendering alike are best set as
constants.

..  _configuration-constants:

Constants
=========

The following constants are available in the constant editor (category
"plugin.tx_golbevents") and are used by the default templates:

..  list-table::
    :header-rows: 1

    *   -   Constant
        -   Default
        -   Description
    *   -   ``settings.includeCss``
        -   ``1``
        -   Adds the default stylesheets to the page, see
            :ref:`templating-css`
    *   -   ``settings.dateFormat``, ``settings.timeFormat``
        -   ``%d.%m.%Y``, ``%H:%M``
        -   Formats of the event dates and times
    *   -   ``settings.show.image``
        -   ``1``
        -   The first file of the page field "Media" as teaser and
            detail image
    *   -   ``settings.show.dateBadge``
        -   ``1``
        -   The day and month badge in front of every teaser
    *   -   ``settings.show.abstract``
        -   ``1``
        -   The abstract of the event
    *   -   ``settings.show.location``
        -   ``1``
        -   The location in teasers and the detail rendering
    *   -   ``settings.show.organizer``
        -   ``1``
        -   The organizer in teasers and the detail rendering
    *   -   ``settings.show.categories``
        -   ``1``
        -   The categories of the event
    *   -   ``settings.show.tags``
        -   ``1``
        -   The tags of the event
    *   -   ``settings.show.flags``
        -   ``1``
        -   The "Cancelled" and "Top event" badges
    *   -   ``settings.show.ical``
        -   ``1``
        -   The "Add to calendar" link of the detail rendering (only
            while the event has upcoming dates)
    *   -   ``settings.show.dates``
        -   ``1``
        -   The list of upcoming dates of a recurring event in the detail
            rendering
    *   -   ``settings.imageWidth.teaser``
        -   ``640``
        -   Width of the teaser image in pixels
    *   -   ``settings.imageWidth.detail``
        -   ``1280``
        -   Width of the image in the detail rendering in pixels
    *   -   ``settings.filterLinks.enabled``
        -   ``0``
        -   Link categories and tags to the filtered Event list, see
            :ref:`templating-filter-links`
    *   -   ``settings.filterLinks.pageUid``
        -   ``0``
        -   Page with the Event list plugin the filter links point to,
            ``0`` for the current page
    *   -   ``settings.calendar.firstDayOfWeek``
        -   ``1``
        -   First day of the week in the calendar
    *   -   ``settings.upcomingLimit``
        -   ``5``
        -   Number of upcoming dates listed in the detail rendering
    *   -   ``settings.icalTypeNum``
        -   ``1764``
        -   Page type of the iCal download, see :ref:`configuration-ical`

The TypoScript constants :typoscript:`plugin.tx_golbevents.view.templateRootPath`,
:typoscript:`partialRootPath` and :typoscript:`layoutRootPath` are
registered at index ``10`` of the respective paths:

..  code-block:: typoscript
    :caption: Constants of the site package

    plugin.tx_golbevents.view {
        templateRootPath = EXT:site_package/Resources/Private/Extensions/GolbEvents/Templates/
        partialRootPath = EXT:site_package/Resources/Private/Extensions/GolbEvents/Partials/
        layoutRootPath = EXT:site_package/Resources/Private/Extensions/GolbEvents/Layouts/
    }

..  _configuration-flexform:

Plugin settings
===============

The FlexForm settings of the plugins are available as
:typoscript:`settings` in the controller and the templates. They can also
be set in TypoScript as defaults, FlexForm values take precedence:

..  code-block:: typoscript

    plugin.tx_golbevents_listview.settings {
        itemsPerPage = 20
        mode = upcoming
    }

..  list-table::
    :header-rows: 1

    *   -   Setting
        -   Values
    *   -   ``mode``
        -   ``upcoming`` (default), ``past``, ``all``, ``range``
    *   -   ``from``, ``to``
        -   Unix timestamps of the range (``range`` mode); ``to`` covers
            the whole day
    *   -   ``sorting``
        -   ``start`` (default), ``title``, ``date``, ``crdate``,
            ``tstamp``, ``author``
    *   -   ``sortingDirection``
        -   empty (automatic), ``ASC``, ``DESC``
    *   -   ``limit``
        -   Integer, ``0`` for no limit (Upcoming events)
    *   -   ``itemsPerPage``
        -   Integer (Event list), default ``10``
    *   -   ``offset``
        -   Integer
    *   -   ``exclude``
        -   Comma separated page uids
    *   -   ``topPosts``
        -   empty (all), ``topPosts``, ``nonTopPosts``
    *   -   ``allowDemandOverwrite``
        -   ``0`` or ``1``

..  _configuration-demand:

Filtering by URL parameters
===========================

If :guilabel:`Allow demand overwrite` is enabled, the listing can be
changed with request arguments in the plugin namespace
(``tx_golbevents_listview`` or ``tx_golbevents_upcomingview``). The
arguments are mapped onto the demand object (see
:ref:`developer-demand`); values that are not given fall back to the
plugin settings.

..  list-table::
    :header-rows: 1

    *   -   Argument
        -   Example
    *   -   ``demand[mode]``
        -   ``?tx_golbevents_listview[demand][mode]=past``
    *   -   ``demand[from]``, ``demand[to]``
        -   Dates as ``Y-m-d``, e.g.
            ``?tx_golbevents_listview[demand][mode]=range&tx_golbevents_listview[demand][from]=2026-06-01&tx_golbevents_listview[demand][to]=2026-06-30``
    *   -   ``demand[tag]``, ``demand[tags]``
        -   ``?tx_golbevents_listview[demand][tag]=3`` lists events with
            tag 3
    *   -   ``demand[category]``
        -   ``?tx_golbevents_listview[demand][category]=7`` lists events in
            category 7 or one of its sub categories
    *   -   ``demand[currentPage]``
        -   Page of the pagination, generated by the pagination partial
    *   -   ``demand[order]``, ``demand[orderDirection]``
        -   Sorting, same values as the plugin settings
    *   -   ``demand[topPost]``, ``demand[nonTopPost]``
        -   Boolean flags

The calendar plugin takes the arguments ``year`` and ``month`` in its
namespace ``tx_golbevents_calendarview``; they are generated by the
navigation links of the calendar.

For speaking URLs see :ref:`configuration-routing`.

..  _configuration-routing:

Speaking URLs
=============

The extension ships a route enhancer configuration for the Event list and
the Event calendar plugins in
:file:`EXT:golb_events/Configuration/Routing/RouteEnhancers.yaml`. Import
it into the site configuration and restrict the enhancers to the pages
holding the plugins; the imported configuration is merged with the site
configuration, so single keys can be added or changed below the import
(see the :ref:`routing configuration <t3coreapi:routing-advanced-routing-configuration>`
of TYPO3 for all options):

..  code-block:: yaml
    :caption: config/sites/main/config.yaml

    imports:
      - { resource: 'EXT:golb_events/Configuration/Routing/RouteEnhancers.yaml' }

    routeEnhancers:
      GolbEventsList:
        limitToPages: [12]
      GolbEventsCalendar:
        limitToPages: [13]

The links of the pagination partial, the category and tag links of the
default templates and the month navigation of the calendar then look like
this (Event list on :samp:`/events`, Event calendar on :samp:`/calendar`):

..  list-table::
    :header-rows: 1

    *   -   URL
        -   Arguments
    *   -   :samp:`/events`
        -   first page without arguments, also the target of the
            :guilabel:`Show all events` link
    *   -   :samp:`/events/page/2`
        -   ``demand[currentPage]=2``
    *   -   :samp:`/events/past`
        -   ``demand[mode]=past``; ``upcoming`` and ``all`` likewise
    *   -   :samp:`/events/past/page/2`
        -   mode and pagination
    *   -   :samp:`/events/tag/typo3`
        -   ``demand[tag]`` with the uid of the tag whose slug is ``typo3``
    *   -   :samp:`/events/tag/typo3/page/2`
        -   tag and pagination
    *   -   :samp:`/events/category/News-7`
        -   ``demand[category]=7``
    *   -   :samp:`/events/category/News-7/page/2`
        -   category and pagination
    *   -   :samp:`/calendar/2026/03`
        -   ``year=2026``, ``month=3``

The filters of the Event list require :guilabel:`Allow demand overwrite`
in the plugin. The Upcoming events plugin does not generate URL
parameters: its category and tag links point to the Event list plugin and
use these routes. Tags are mapped by their slug field. System categories
have no slug, so the category routes carry the title and the uid of the
record. The date range (``demand[from]``, ``demand[to]``) has no route and
stays a query parameter. The years of the calendar are limited to 2000 to
2199. Copy the file into the site configuration instead of importing it if
you want to change the paths or the aspects. The iCal download has its own
configuration, see :ref:`configuration-ical`.

The complete configuration:

..  code-block:: yaml
    :caption: EXT:golb_events/Configuration/Routing/RouteEnhancers.yaml

    routeEnhancers:
      GolbEventsList:
        type: Extbase
        extension: GolbEvents
        plugin: ListView
        routes:
          - routePath: '/'
            _controller: 'Event::list'
          - routePath: '/page/{page}'
            _controller: 'Event::list'
            _arguments:
              page: 'demand/currentPage'
          - routePath: '/{mode}'
            _controller: 'Event::list'
            _arguments:
              mode: 'demand/mode'
          - routePath: '/{mode}/page/{page}'
            _controller: 'Event::list'
            _arguments:
              mode: 'demand/mode'
              page: 'demand/currentPage'
          - routePath: '/tag/{tag}'
            _controller: 'Event::list'
            _arguments:
              tag: 'demand/tag'
          - routePath: '/tag/{tag}/page/{page}'
            _controller: 'Event::list'
            _arguments:
              tag: 'demand/tag'
              page: 'demand/currentPage'
          - routePath: '/category/{category}'
            _controller: 'Event::list'
            _arguments:
              category: 'demand/category'
          - routePath: '/category/{category}/page/{page}'
            _controller: 'Event::list'
            _arguments:
              category: 'demand/category'
              page: 'demand/currentPage'
        defaultController: 'Event::list'
        aspects:
          page:
            type: StaticRangeMapper
            start: '1'
            end: '999'
          mode:
            type: StaticValueMapper
            map:
              upcoming: upcoming
              past: past
              all: all
          tag:
            type: PersistedAliasMapper
            tableName: tx_golb_domain_model_tag
            routeFieldName: slug
          category:
            type: PersistedPatternMapper
            tableName: sys_category
            routeFieldPattern: '^(?P<title>.+)-(?P<uid>\d+)$'
            routeFieldResult: '{title}-{uid}'

      GolbEventsCalendar:
        type: Extbase
        extension: GolbEvents
        plugin: CalendarView
        routes:
          - routePath: '/{year}/{month}'
            _controller: 'Event::calendar'
            _arguments:
              year: 'year'
              month: 'month'
        defaultController: 'Event::calendar'
        aspects:
          year:
            type: StaticRangeMapper
            start: '2000'
            end: '2199'
          month:
            type: StaticValueMapper
            map:
              '01': '1'
              '02': '2'
              '03': '3'
              '04': '4'
              '05': '5'
              '06': '6'
              '07': '7'
              '08': '8'
              '09': '9'
              '10': '10'
              '11': '11'
              '12': '12'

..  _configuration-ical:

iCal download
=============

Every event page can be downloaded as iCalendar file (``.ics``) through
the page type :typoscript:`golbEventsIcal` of the static TypoScript, by
default ``?type=1764``. Pages that are no events answer with a 404
response. The shipped `PageType route enhancer
<https://docs.typo3.org/m/typo3/reference-coreapi/14.3/en-us/ApiOverview/Routing/AdvancedRoutingConfiguration.html#pagetype-enhancer>`__
in :file:`EXT:golb_events/Configuration/Routing/RouteEnhancersIcal.yaml`
gives the download the speaking URL :samp:`/events/conference/event.ics`:

..  code-block:: yaml
    :caption: config/sites/main/config.yaml

    imports:
      - { resource: 'EXT:golb_events/Configuration/Routing/RouteEnhancersIcal.yaml' }

If the site configuration already has a PageType enhancer, for example
for a :file:`sitemap.xml` or a ``.html`` suffix, do not import the file
but add the mapping to the existing enhancer. The page type has to match
the constant
:typoscript:`plugin.tx_golbevents.settings.icalTypeNum`:

..  code-block:: yaml
    :caption: config/sites/main/config.yaml

    routeEnhancers:
      PageTypeSuffix:
        type: PageType
        map:
          'sitemap.xml': 1533906435
          'event.ics': 1764

The file carries the dates with the time zone of the event, the
recurrence rule and the excluded dates, title, description, location,
coordinates, organizer and the URL of the event page. See
:ref:`templating-detail` for the download link.

..  _configuration-extension:

Extension configuration
=======================

..  confval:: horizonYears
    :name: extconf-horizonYears
    :type: integer
    :default: ``2``

    Occurrences of recurring events are generated this many years ahead
    (:guilabel:`Admin Tools > Settings > Extension Configuration`). Run
    the command ``golbevents:occurrences:generate`` regularly to extend
    the horizon, see :ref:`installation-scheduler`.

..  _configuration-tsconfig:

TSconfig
========

The extension ships :file:`Configuration/page.tsconfig` (content element
wizard entries for the plugins) and :file:`Configuration/user.tsconfig`
(offers the "Event" page type in the drag area of the page tree). Both
are loaded automatically.

..  _configuration-doktype:

Page type
=========

The "Event" page type uses doktype ``42``, available as the constant
:php:`\Greenfieldr\GolbEvents\Constants::EVENT_DOKTYPE` (Golb's blog posts
use ``41``). Events behave like standard pages: they are shown in menus
unless hidden in navigation, can be translated and use backend layouts.
The dates, the recurrence and the location of a translated event are
taken from the default language.
