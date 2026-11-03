<?php

declare(strict_types=1);

/*
 * This file is part of the TYPO3 extension "golb_events".
 *
 * It is free software; you can redistribute it and/or modify it under
 * the terms of the GNU General Public License, either version 3
 * of the License, or any later version.
 */

namespace Greenfieldr\GolbEvents\Domain\Repository;

use Greenfieldr\Golb\Domain\Model\Category;
use Greenfieldr\Golb\Domain\Model\Dto\PostsDemand;
use Greenfieldr\GolbEvents\Constants;
use Greenfieldr\GolbEvents\Domain\Model\Dto\EventsDemand;
use Greenfieldr\GolbEvents\Domain\Model\Event;
use Greenfieldr\GolbEvents\Domain\Model\Occurrence;
use TYPO3\CMS\Core\Context\Context;
use TYPO3\CMS\Core\Database\Connection;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Database\Query\QueryBuilder;
use TYPO3\CMS\Core\Database\Query\Restriction\FrontendRestrictionContainer;
use TYPO3\CMS\Core\Domain\Repository\PageRepository;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Extbase\Persistence\Generic\Typo3QuerySettings;
use TYPO3\CMS\Extbase\Persistence\Repository;

/**
 * Finds event occurrences below given root pages. The filtering by dates,
 * categories, tags and the sorting happen in SQL on the occurrence table
 * joined with the event pages (respecting their enable fields), the
 * matching occurrences are then loaded as Extbase objects.
 *
 * @extends Repository<Occurrence>
 */
class OccurrenceRepository extends Repository
{
    public const TABLE_OCCURRENCES = 'tx_golbevents_domain_model_occurrence';
    public const TABLE_PAGES = 'pages';
    public const TABLE_CATEGORY_MM = 'sys_category_record_mm';
    public const TABLE_TAG_MM = 'tx_golb_page_tag_mm';

    /**
     * Columns the occurrences can be sorted by, keyed by the demand order
     */
    private const ORDER_COLUMNS = [
        EventsDemand::ORDER_START => 'o.start',
        PostsDemand::ORDER_DATE => 'p.tx_golb_publish_date',
        PostsDemand::ORDER_CREATION_DATE => 'p.crdate',
        PostsDemand::ORDER_MODIFICATION_DATE => 'p.tstamp',
        PostsDemand::ORDER_TITLE => 'p.title',
        PostsDemand::ORDER_AUTHOR => 'p.author',
    ];

    public function __construct(
        private readonly ConnectionPool $connectionPool,
        private readonly PageRepository $pageRepository,
        private readonly Context $context,
    ) {
        parent::__construct();
    }

    public function initializeObject(): void
    {
        $querySettings = GeneralUtility::makeInstance(Typo3QuerySettings::class);
        $querySettings->setRespectStoragePage(false);
        $this->setDefaultQuerySettings($querySettings);
    }

    /**
     * Finds the occurrences of the events below the root pages that match the
     * demand, sorted and limited as the demand says. Occurrences whose event
     * is not available (e.g. not translated) are left out.
     *
     * @param int[] $rootPages
     * @param \DateTimeImmutable|null $now reference for upcoming and past, defaults to the request time
     * @return Occurrence[]
     */
    public function findByDemand(EventsDemand $demand, array $rootPages, ?\DateTimeImmutable $now = null): array
    {
        $pageIds = $this->findPageIdsBelow($rootPages);
        if ($pageIds === []) {
            return [];
        }

        $uids = $this->findOccurrenceUids($demand, $pageIds, $now ?? $this->now());
        if ($uids === []) {
            return [];
        }

        $query = $this->createQuery();
        $query->matching($query->in('uid', $uids));
        $query->setLimit(count($uids));
        $occurrencesByUid = [];
        foreach ($query->execute() as $occurrence) {
            if ($occurrence instanceof Occurrence && $occurrence->getEvent() instanceof Event) {
                $occurrencesByUid[(int)$occurrence->getUid()] = $occurrence;
            }
        }

        // Restore the order of the SQL query
        $occurrences = [];
        foreach ($uids as $uid) {
            if (isset($occurrencesByUid[$uid])) {
                $occurrences[] = $occurrencesByUid[$uid];
            }
        }

        return $occurrences;
    }

    /**
     * The uids of all pages below the root pages (not the root pages
     * themselves), like Golb collects blog posts.
     *
     * @param int[] $rootPages
     * @return int[]
     */
    public function findPageIdsBelow(array $rootPages): array
    {
        $pageIds = [];
        foreach (array_unique(array_map(abs(...), $rootPages)) as $rootPage) {
            if ($rootPage > 0) {
                $pageIds = array_merge($pageIds, $this->pageRepository->getDescendantPageIdsRecursive($rootPage, 99));
            }
        }

        return $this->toIntegerList($pageIds);
    }

    /**
     * @param int[] $pageIds
     * @return int[] occurrence uids in the requested order
     */
    protected function findOccurrenceUids(EventsDemand $demand, array $pageIds, \DateTimeImmutable $now): array
    {
        $queryBuilder = $this->connectionPool->getQueryBuilderForTable(self::TABLE_OCCURRENCES);
        $queryBuilder->setRestrictions(GeneralUtility::makeInstance(FrontendRestrictionContainer::class, $this->context));
        $queryBuilder
            ->select('o.uid')
            ->from(self::TABLE_OCCURRENCES, 'o')
            ->join('o', self::TABLE_PAGES, 'p', $queryBuilder->expr()->eq('p.uid', $queryBuilder->quoteIdentifier('o.event')))
            ->where(
                $queryBuilder->expr()->eq('p.doktype', $queryBuilder->createNamedParameter(Constants::EVENT_DOKTYPE, Connection::PARAM_INT)),
                $queryBuilder->expr()->eq('p.sys_language_uid', $queryBuilder->createNamedParameter(0, Connection::PARAM_INT)),
                $queryBuilder->expr()->in('p.uid', $queryBuilder->createNamedParameter($pageIds, Connection::PARAM_INT_ARRAY))
            )
            ->groupBy('o.uid');

        $this->addDateConstraints($queryBuilder, $demand, $now);
        $this->addRelationConstraints($queryBuilder, $demand);
        $this->addSorting($queryBuilder, $demand);

        if ($demand->getLimit() > 0) {
            $queryBuilder->setMaxResults($demand->getLimit());
        }
        if ($demand->getOffset() > 0) {
            $queryBuilder->setFirstResult($demand->getOffset());
        }

        return $this->toIntegerList($queryBuilder->executeQuery()->fetchFirstColumn());
    }

    protected function addDateConstraints(QueryBuilder $queryBuilder, EventsDemand $demand, \DateTimeImmutable $now): void
    {
        $expr = $queryBuilder->expr();
        $timestamp = static fn(\DateTimeImmutable $date): string => $queryBuilder->createNamedParameter($date->getTimestamp(), Connection::PARAM_INT);

        switch ($demand->getMode()) {
            case EventsDemand::MODE_UPCOMING:
                $queryBuilder->andWhere($expr->gte('o.end', $timestamp($now)));
                break;
            case EventsDemand::MODE_PAST:
                $queryBuilder->andWhere($expr->lt('o.end', $timestamp($now)));
                break;
            case EventsDemand::MODE_RANGE:
                $from = $demand->getFrom();
                $to = $demand->getTo();
                if ($from instanceof \DateTimeImmutable) {
                    $queryBuilder->andWhere($expr->gte('o.end', $timestamp($from)));
                }
                if ($to instanceof \DateTimeImmutable) {
                    $queryBuilder->andWhere($expr->lte('o.start', $timestamp($to)));
                }
                break;
            default:
                // All occurrences
        }
    }

    protected function addRelationConstraints(QueryBuilder $queryBuilder, EventsDemand $demand): void
    {
        $expr = $queryBuilder->expr();

        $categoryIds = $this->resolveCategoryIds($demand);
        if ($categoryIds !== []) {
            $queryBuilder
                ->join('p', self::TABLE_CATEGORY_MM, 'cm', (string)$expr->and(
                    $expr->eq('cm.uid_foreign', $queryBuilder->quoteIdentifier('p.uid')),
                    $expr->eq('cm.tablenames', $queryBuilder->createNamedParameter(self::TABLE_PAGES)),
                    $expr->eq('cm.fieldname', $queryBuilder->createNamedParameter('categories'))
                ))
                ->andWhere($expr->in('cm.uid_local', $queryBuilder->createNamedParameter($categoryIds, Connection::PARAM_INT_ARRAY)));
        }

        $tagIds = $demand->getTags();
        if ($tagIds !== []) {
            $queryBuilder
                ->join('p', self::TABLE_TAG_MM, 'tm', $expr->eq('tm.uid_local', $queryBuilder->quoteIdentifier('p.uid')))
                ->andWhere($expr->in('tm.uid_foreign', $queryBuilder->createNamedParameter($tagIds, Connection::PARAM_INT_ARRAY)));
        }

        if ($demand->hasExcluded()) {
            $queryBuilder->andWhere($expr->notIn('p.uid', $queryBuilder->createNamedParameter($demand->getExcluded(), Connection::PARAM_INT_ARRAY)));
        }

        if ($demand->isTopPost()) {
            $queryBuilder->andWhere($expr->eq('p.tx_golb_top_post', $queryBuilder->createNamedParameter(1, Connection::PARAM_INT)));
        } elseif ($demand->isNonTopPost()) {
            $queryBuilder->andWhere($expr->eq('p.tx_golb_top_post', $queryBuilder->createNamedParameter(0, Connection::PARAM_INT)));
        }
    }

    protected function addSorting(QueryBuilder $queryBuilder, EventsDemand $demand): void
    {
        $column = self::ORDER_COLUMNS[$demand->getOrder()] ?? self::ORDER_COLUMNS[EventsDemand::ORDER_START];
        $direction = $demand->getOrderDirection() === 'DESC' ? 'DESC' : 'ASC';

        $queryBuilder->orderBy($column, $direction);
        if ($column !== 'o.start') {
            $queryBuilder->addOrderBy('o.start', $direction);
        }
        $queryBuilder->addOrderBy('o.uid', 'ASC');
    }

    /**
     * The uids of the demanded categories including their sub categories.
     *
     * @return int[]
     */
    protected function resolveCategoryIds(EventsDemand $demand): array
    {
        $categories = $demand->getCategories();
        if ($demand->getCategory() instanceof Category) {
            $categories[] = $demand->getCategory();
        }

        $categoryIds = [];
        $this->collectCategoryIds($categories, $categoryIds);

        return $categoryIds;
    }

    /**
     * @param iterable<Category> $categories
     * @param int[] $categoryIds
     */
    private function collectCategoryIds(iterable $categories, array &$categoryIds): void
    {
        foreach ($categories as $category) {
            $uid = $category->getUid();
            if ($uid === null || in_array($uid, $categoryIds, true)) {
                continue;
            }
            $categoryIds[] = $uid;
            $this->collectCategoryIds($category->getSubCategories(), $categoryIds);
        }
    }

    /**
     * @param array<mixed> $values
     * @return int[] the unique positive integers of the values, in order
     */
    private function toIntegerList(array $values): array
    {
        $integers = [];
        foreach ($values as $value) {
            if (is_numeric($value) && (int)$value > 0 && !in_array((int)$value, $integers, true)) {
                $integers[] = (int)$value;
            }
        }

        return $integers;
    }

    private function now(): \DateTimeImmutable
    {
        $now = $this->context->getPropertyFromAspect('date', 'full');

        return $now instanceof \DateTimeImmutable ? $now : new \DateTimeImmutable();
    }
}
