..  include:: /Includes.rst.txt

..  _introduction:

============
Introduction
============

..  _what-it-does:

What does it do?
================

Golb Events extends the blog extension
`Golb <https://github.com/greenfieldr/golb>`__ with events, following the
same idea, "the page way":

*   An **event is a page** with the custom page type (doktype) ``42``
    labelled "Event". Editors create events in the page tree, exactly like
    normal pages and like Golb's blog posts.
*   The **content of an event** is made of regular content elements.
    Backend layouts, grids and every content element type of the
    installation work out of the box.
*   **Event data** lives on the page record: start and end, an all day
    flag, a location and an organizer (free text or a reusable record),
    a link for registration or tickets, a cancelled flag and a recurrence
    rule with exceptions.
*   **Golb's metadata** applies to events as well: publish date, author,
    author image, system categories, tags and related pages.
*   **Listings** are provided by plugins: a paginated *Event list*, a
    limited *Upcoming events* list and a month *Calendar*.
*   Events provide an **iCal download** and **schema.org** metadata.

Because events are pages, everything TYPO3 offers for pages applies:
URL routing, translations, workspaces, access restrictions, SEO fields,
caching and the page module.

..  _compatibility:

Compatibility
=============

..  list-table::
    :header-rows: 1

    *   -   Golb Events version
        -   Golb version
        -   TYPO3 version
        -   PHP version
    *   -   14.x
        -   14.x
        -   14.x
        -   8.2 - 8.5

The major version of the extension follows the supported TYPO3 major
version.

..  _support:

Support and contributions
=========================

Source code, issues and pull requests are hosted on
`GitHub <https://github.com/greenfieldr/golb_events>`__. Commit messages
follow the `Conventional Commits <https://www.conventionalcommits.org/>`__
specification, which is enforced by the CI pipeline.
