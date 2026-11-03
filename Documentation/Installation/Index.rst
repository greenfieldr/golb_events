..  include:: /Includes.rst.txt

..  _installation:

============
Installation
============

..  _installation-composer:

Composer installation
=====================

Require the extension in your project:

..  code-block:: bash

    composer require greenfieldr/golb_events

The extension key is ``golb_events``. The extension depends on
`Golb <https://github.com/greenfieldr/golb>`__, which Composer installs
along with it. TYPO3 picks both packages up automatically.

..  _installation-setup:

Setup
=====

#.  **Database:** Run :guilabel:`Admin Tools > Maintenance > Analyze
    Database Structure` to create the additional fields and tables. The
    database schema is derived from the TCA, no :file:`ext_tables.sql` is
    shipped.

#.  **TypoScript:** Include the static TypoScript "Golb" and "Golb Events"
    in your site package (:guilabel:`Web > Template > Includes`) or import
    them in your TypoScript setup:

    ..  code-block:: typoscript

        @import 'EXT:golb/Configuration/TypoScript/setup.typoscript'
        @import 'EXT:golb_events/Configuration/TypoScript/setup.typoscript'

#.  **Event root page:** Create a page (any type) that will contain the
    events, for example "Events". Events can be nested in sub pages or
    folders below it; the plugins collect them recursively.

#.  **Plugins:** Add the content element :guilabel:`Golb > Event list`,
    :guilabel:`Upcoming events` or :guilabel:`Event calendar` to a page
    and select the event root page(s) in the :guilabel:`Pages` field. See
    :ref:`editor-plugins`.

#.  **Frontend:** The plugins come styled: the default templates add the
    plain stylesheets of Golb and Golb Events to the page. Disable them
    with the constant :typoscript:`plugin.tx_golbevents.settings.includeCss = 0`
    if you want to style the markup yourself, and insert
    :typoscript:`lib.golbEvents.event` in the page template to render
    event pages, see :ref:`templating`.

#.  **Optional:** Create locations and organizers in a folder of your
    choice with the :guilabel:`Web > List` module, see
    :ref:`editor-locations`.

..  _installation-scheduler:

Scheduler
=========

The dates of recurring events are generated when an event is saved, two
years ahead by default (see :ref:`configuration-extension`). To keep that
horizon moving, run the command regularly, for example daily through the
:guilabel:`Execute console commands` task of the scheduler or a cron job:

..  code-block:: bash

    vendor/bin/typo3 golbevents:occurrences:generate

The command regenerates the dates of all events. With ``--event=<uid>``
it regenerates a single event, for example after changes made directly in
the database.
