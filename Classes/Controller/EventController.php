<?php

declare(strict_types=1);

/*
 * This file is part of the TYPO3 extension "golb_events".
 *
 * It is free software; you can redistribute it and/or modify it under
 * the terms of the GNU General Public License, either version 3
 * of the License, or any later version.
 */

namespace Greenfieldr\GolbEvents\Controller;

use Greenfieldr\Golb\Controller\BaseController;
use Greenfieldr\Golb\Domain\Model\Category;
use Greenfieldr\Golb\Domain\Repository\CategoryRepository;
use Greenfieldr\GolbEvents\Calendar\CalendarBuilder;
use Greenfieldr\GolbEvents\Domain\Model\Dto\EventsDemand;
use Greenfieldr\GolbEvents\Domain\Repository\OccurrenceRepository;
use Psr\Http\Message\ResponseInterface;
use TYPO3\CMS\Core\Context\Context;
use TYPO3\CMS\Core\Pagination\ArrayPaginator;
use TYPO3\CMS\Core\Pagination\SimplePagination;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Core\View\ViewInterface;
use TYPO3\CMS\Extbase\Property\TypeConverter\DateTimeConverter;

/**
 * Renders event occurrences for the "Event list", "Upcoming events" and
 * "Event calendar" plugins.
 */
class EventController extends BaseController
{
    /**
     * Root page uids (plugin field "pages") to collect events from
     *
     * @var int[]
     */
    protected array $pages = [];

    /**
     * Categories selected in the plugin record to filter events by
     *
     * @var Category[]
     */
    protected array $categories = [];

    public function __construct(
        protected readonly OccurrenceRepository $occurrenceRepository,
        protected readonly CategoryRepository $categoryRepository,
        protected readonly CalendarBuilder $calendarBuilder,
        protected readonly Context $context,
    ) {}

    public function initializeAction(): void
    {
        parent::initializeAction();

        $this->pages = GeneralUtility::intExplode(',', $this->getContentObjectField('pages'), true);
        $this->categories = $this->categoryRepository->findByRelation((int)$this->getContentObjectField('uid'));

        if ($this->arguments->hasArgument('demand')) {
            $configuration = $this->arguments->getArgument('demand')->getPropertyMappingConfiguration();
            $configuration->allowAllProperties();
            foreach (['from', 'to'] as $property) {
                $configuration->forProperty($property)
                    ->setTypeConverterOption(DateTimeConverter::class, DateTimeConverter::CONFIGURATION_DATE_FORMAT, 'Y-m-d');
            }
        }
    }

    protected function initializeView(ViewInterface $view): void
    {
        $view->assign('contentElementData', $this->getContentObjectData());
    }

    /**
     * Lists occurrences with pagination.
     */
    public function listAction(?EventsDemand $demand = null): ResponseInterface
    {
        $demand = $this->prepareDemandObject($demand);
        // Fetch all occurrences, the paginator slices them
        $demand->setLimit(0);

        $occurrences = $this->occurrenceRepository->findByDemand($demand, $this->pages);
        $paginator = new ArrayPaginator($occurrences, $demand->getCurrentPage(), $demand->getItemsPerPage());
        $pagination = new SimplePagination($paginator);

        $this->view->assign('demand', $demand);
        $this->view->assign('occurrences', $occurrences);
        $this->view->assign('paginator', $paginator);
        $this->view->assign('pagination', $pagination);

        return $this->htmlResponse();
    }

    /**
     * Lists a limited number of occurrences (no pagination).
     */
    public function upcomingAction(?EventsDemand $demand = null): ResponseInterface
    {
        $demand = $this->prepareDemandObject($demand);
        $occurrences = $this->occurrenceRepository->findByDemand($demand, $this->pages);

        $this->view->assign('demand', $demand);
        $this->view->assign('occurrences', $occurrences);

        return $this->htmlResponse();
    }

    /**
     * Renders the month calendar. Without arguments the current month is
     * shown; the previous and next month are linked.
     */
    public function calendarAction(int $year = 0, int $month = 0): ResponseInterface
    {
        $timezone = new \DateTimeZone(date_default_timezone_get());
        $today = $this->now()->setTimezone($timezone);
        if ($month < 1 || $month > 12 || $year < 1970 || $year > 2999) {
            $year = (int)$today->format('Y');
            $month = (int)$today->format('n');
        }
        $firstDayOfWeek = (int)($this->settings['calendar']['firstDayOfWeek'] ?? 1);

        [$from, $to] = $this->calendarBuilder->gridRange($year, $month, $timezone, $firstDayOfWeek);
        $demand = $this->prepareDemandObject(null)
            ->setMode(EventsDemand::MODE_RANGE)
            ->setFrom($from)
            ->setTo($to)
            ->setLimit(0)
            ->setOffset(0);

        $occurrences = $this->occurrenceRepository->findByDemand($demand, $this->pages);
        $calendar = $this->calendarBuilder->build($year, $month, $occurrences, $timezone, $firstDayOfWeek, $today);

        $this->view->assign('demand', $demand);
        $this->view->assign('occurrences', $occurrences);
        $this->view->assign('calendar', $calendar);

        return $this->htmlResponse();
    }

    /**
     * Completes the demand object with the plugin settings. A demand object
     * submitted with the request is only used if the plugin allows it, except
     * for the current page of the pagination.
     */
    protected function prepareDemandObject(?EventsDemand $demand): EventsDemand
    {
        $settings = $this->settings;
        $allowDemandOverwrite = (bool)($settings['allowDemandOverwrite'] ?? false);

        if (!$demand instanceof EventsDemand) {
            $demand = new EventsDemand();
        } elseif (!$allowDemandOverwrite) {
            // Only the pagination argument is taken from the request
            $demand = (new EventsDemand())->setCurrentPage($demand->getCurrentPage());
        }

        if (!$demand->hasMode()) {
            $demand->setMode((string)($settings['mode'] ?? EventsDemand::MODE_DEFAULT));
        }
        if (!$demand->hasFrom() && (int)($settings['from'] ?? 0) > 0) {
            $demand->setFrom($this->dateFromSetting((int)$settings['from']));
        }
        if (!$demand->hasTo() && (int)($settings['to'] ?? 0) > 0) {
            $demand->setTo($this->dateFromSetting((int)$settings['to'])->setTime(23, 59, 59));
        }
        if (!$demand->hasCategories()) {
            $demand->setCategories($this->categories);
        }
        if (!$demand->hasExcluded()) {
            $demand->setExcluded(GeneralUtility::intExplode(',', (string)($settings['exclude'] ?? ''), true));
        }
        if (!$demand->hasLimit()) {
            $demand->setLimit((int)($settings['limit'] ?? 0));
        }
        if (!$demand->hasItemsPerPage()) {
            $demand->setItemsPerPage((int)($settings['itemsPerPage'] ?? 0));
        }
        if (!$demand->hasOffset()) {
            $demand->setOffset((int)($settings['offset'] ?? 0));
        }

        $topPosts = (string)($settings['topPosts'] ?? '');
        if (!$demand->isTopPostSet() && $topPosts === 'topPosts') {
            $demand->setTopPost(true);
        }
        if (!$demand->isNonTopPostSet() && $topPosts === 'nonTopPosts') {
            $demand->setNonTopPost(true);
        }

        $sorting = (string)($settings['sorting'] ?? '');
        if (!$demand->hasOrder() && $sorting !== '') {
            $demand->setOrder($sorting);
        }
        $sortingDirection = (string)($settings['sortingDirection'] ?? '');
        if (!$demand->hasOrderDirection() && $sortingDirection !== '') {
            $demand->setOrderDirection($sortingDirection);
        }

        return $demand;
    }

    /**
     * FlexForm date fields store UTC timestamps of midnight.
     */
    private function dateFromSetting(int $timestamp): \DateTimeImmutable
    {
        return (new \DateTimeImmutable('@' . $timestamp))->setTimezone(new \DateTimeZone(date_default_timezone_get()));
    }

    private function now(): \DateTimeImmutable
    {
        $now = $this->context->getPropertyFromAspect('date', 'full');

        return $now instanceof \DateTimeImmutable ? $now : new \DateTimeImmutable();
    }
}
