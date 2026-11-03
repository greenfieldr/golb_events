..  include:: /Includes.rst.txt

..  _editor:

==============
Editing events
==============

..  _editor-event:

Creating an event
=================

#.  Create a new page below the event root page. In the page tree the
    "Event" page type is offered in the drag area of new pages, or change
    the :guilabel:`Type` of an existing page to :guilabel:`Event`.

#.  Fill in the page properties. The :guilabel:`Event` tab holds the
    event data:

    ..  list-table::
        :header-rows: 1

        *   -   Field
            -   Meaning
        *   -   Start, End
            -   Date and time of the event. The end is optional. Times are
                entered as they appear on the invitation, see
                :ref:`editor-timezones`.
        *   -   All day
            -   The event lasts the whole day(s) between start and end;
                the times are ignored.
        *   -   Cancelled
            -   Marks the event as cancelled. It stays listed, templates
                and the structured data show the status.
        *   -   Location
            -   A location record (see :ref:`editor-locations`) or
                :guilabel:`Free text` with a location name in the field
                :guilabel:`Location (free text)`.
        *   -   Organizer
            -   An organizer record or :guilabel:`Free text` with a name.
        *   -   Link
            -   Link for registration or tickets, shown by the templates
                and exported as offer in the structured data.

    The :guilabel:`Recurrence` tab repeats the event, see
    :ref:`editor-recurrence`.

#.  The :guilabel:`Blog` tab of Golb is available for events as well:
    :guilabel:`Publish date`, :guilabel:`Top post`, :guilabel:`Tags` and
    :guilabel:`Related posts` work exactly like for blog posts. Only the
    archived flag is not offered, past events are found by their dates.

#.  Assign :guilabel:`Categories` on the :guilabel:`Categories` tab. The
    plugins can filter by categories, sub categories are included.

#.  Add content elements to the page as usual. The event page is rendered
    by the page template of your site, see :ref:`templating-detail`.

..  tip::

    Events can be organised in sub pages or folders below the event root
    page, for example one folder per year. Hidden pages and pages below
    hidden pages are not listed.

..  _editor-recurrence:

Recurring events
================

On the :guilabel:`Recurrence` tab choose how the event repeats:

..  list-table::
    :header-rows: 1

    *   -   Field
        -   Meaning
    *   -   Repeat
        -   Never, daily, weekly, monthly or yearly.
    *   -   Every
        -   The interval: every second week, every third month and so on.
    *   -   Weekdays
        -   Weekly rules only: the weekdays the event takes place on. If
            none is selected, the weekday of the start is used.
    *   -   Monthly on
        -   Monthly rules only: the same day of the month (the 12th) or
            the same weekday (the second Tuesday). A fifth weekday counts
            as the last one of the month.
    *   -   Ends
        -   Never, on a date or after a number of occurrences.
    *   -   Excluded dates
        -   Dates on which the event does not take place, for example a
            public holiday.

Every date of the event, the *occurrence*, is stored when the page is
saved. Occurrences of never ending events are generated two years ahead
(see :ref:`configuration-extension`); the scheduler extends them, see
:ref:`installation-scheduler`. Lists and the calendar show every
occurrence, so a weekly event appears once per week.

..  _editor-locations:

Locations and organizers
========================

Locations and organizers are records that can be reused by many events.
Create them in any page or folder with the :guilabel:`Web > List` module.

Event location
    Name, address (street, zip, city, country), coordinates, a link and a
    description. The :guilabel:`Time zone` defines the time zone of the
    events held there, see :ref:`editor-timezones`.

Event organizer
    Name, email, phone, link and description. If an email is given, the
    organizer is exported to calendar files.

Both record types are translatable. Text fields are translated, address
and time zone are shared.

..  _editor-timezones:

Time zones
==========

Start and end are *wall clock* times: 19:30 means 19:30 at the place of
the event. If the event has a location record with a time zone, that zone
applies; otherwise the time zone of the server (the website) applies.

Visitors see the times in the time zone of the website. An event at 19:30
in New York is shown as 01:30 of the next day on a website in Berlin;
the calendar file and the structured data carry the New York time with
its zone, so personal calendars show the correct local time.

..  _editor-plugins:

Plugins
=======

The plugins are available in the :guilabel:`Golb` tab of the new content
element wizard.

Event list
    Lists all matching event dates with pagination.

Upcoming events
    Lists a limited number of event dates, for example the next three
    events in a sidebar or on the start page.

Event calendar
    Shows the event dates of a month as calendar with links to the
    previous and next month.

On the :guilabel:`General` tab select the event root page(s) in the field
:guilabel:`Pages`. All events below these pages are collected
recursively. On the :guilabel:`Categories` tab select categories to
restrict the list. The :guilabel:`Plugin` tab contains the settings:

..  list-table::
    :header-rows: 1

    *   -   Setting
        -   Description
    *   -   Event dates
        -   Upcoming (events that have not ended yet, including running
            events), past, all or the dates in a date range. Default:
            upcoming. Not available in the calendar, which always shows
            the displayed month.
    *   -   From, To
        -   The date range for :guilabel:`In a date range`.
    *   -   Sorting field
        -   Date of the event (default), title, publish date, creation
            date, last update or author.
    *   -   Sorting direction
        -   Automatic (chronological, past events with the most recent
            first), ascending or descending.
    *   -   Limit (Upcoming events)
        -   Maximum number of event dates. ``0`` lists all.
    *   -   Items per page (Event list)
        -   Number of event dates per page of the pagination.
    *   -   Offset
        -   Number of event dates to skip.
    *   -   Excluded events
        -   Events that must not be listed, for example the current
            event.
    *   -   Top events
        -   Show all events, top events only or non-top events only.
    *   -   Allow demand overwrite
        -   Allows visitors to change the listing through URL parameters,
            for example a tag filter or another date range. See
            :ref:`configuration-demand`.
