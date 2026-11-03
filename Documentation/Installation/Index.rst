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
