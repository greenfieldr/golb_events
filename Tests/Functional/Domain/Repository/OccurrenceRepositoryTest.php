<?php

declare(strict_types=1);

/*
 * This file is part of the TYPO3 extension "golb_events".
 *
 * It is free software; you can redistribute it and/or modify it under
 * the terms of the GNU General Public License, either version 3
 * of the License, or any later version.
 */

namespace Greenfieldr\GolbEvents\Tests\Functional\Domain\Repository;

use Greenfieldr\Golb\Domain\Model\Category;
use Greenfieldr\Golb\Domain\Model\Dto\PostsDemand;
use Greenfieldr\Golb\Domain\Repository\CategoryRepository;
use Greenfieldr\GolbEvents\Domain\Model\Dto\EventsDemand;
use Greenfieldr\GolbEvents\Domain\Model\Event;
use Greenfieldr\GolbEvents\Domain\Model\Occurrence;
use Greenfieldr\GolbEvents\Domain\Repository\EventRepository;
use Greenfieldr\GolbEvents\Domain\Repository\OccurrenceRepository;
use Greenfieldr\GolbEvents\Tests\Functional\AbstractOccurrenceTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;

/**
 * The fixture holds events below the root page 2 (and one below page 8)
 * with occurrences around the frozen "now" 2026-06-01 12:00:00 UTC:
 *
 *   occurrence 1: Alpha (3), two days ago          6: Epsilon (7), ten days ago
 *   occurrence 2: Alpha (3), tomorrow              7: Outside (9), tomorrow, below page 8
 *   occurrence 3: Beta (4), running now            8: Hidden (10), tomorrow, hidden page
 *   occurrence 4: Gamma (5), in two days           9: Nested event (12), in four days
 *   occurrence 5: Delta (6), in three days        10: a blog post (13), tomorrow
 *                                                 11: Epsilon (7), in five days
 *
 * Alpha is a top post with category News (1) and tag php (1); Beta has the
 * categories Music (2) and Jazz (3, child of Music) and the tag typo3 (2).
 */
#[CoversClass(OccurrenceRepository::class)]
#[CoversClass(EventsDemand::class)]
#[CoversClass(Occurrence::class)]
#[CoversClass(Event::class)]
final class OccurrenceRepositoryTest extends AbstractOccurrenceTestCase
{
    private const NOW = 1780315200;

    private OccurrenceRepository $subject;

    protected function setUp(): void
    {
        parent::setUp();
        $this->importCSVDataSet(__DIR__ . '/../../Fixtures/Occurrences.csv');
        $this->freezeTime('2026-06-01 12:00:00');
        $this->subject = $this->get(OccurrenceRepository::class);
    }

    /**
     * @param int[] $rootPages
     * @return int[] occurrence uids
     */
    private function find(EventsDemand $demand, array $rootPages = [2]): array
    {
        return array_map(
            static fn(Occurrence $occurrence): int => (int)$occurrence->getUid(),
            $this->subject->findByDemand($demand, $rootPages)
        );
    }

    private function category(int $uid): Category
    {
        $category = $this->get(CategoryRepository::class)->findByUid($uid);
        self::assertInstanceOf(Category::class, $category);

        return $category;
    }

    #[Test]
    public function upcomingOccurrencesIncludeRunningOnesAndAreSortedByStart(): void
    {
        $occurrences = $this->subject->findByDemand(new EventsDemand(), [2]);

        self::assertSame([3, 2, 4, 5, 9, 11], array_map(static fn(Occurrence $o): int => (int)$o->getUid(), $occurrences));
        self::assertSame('Beta', $occurrences[0]->getEvent()?->getTitle());
        self::assertSame(self::NOW - 3600, $occurrences[0]->getStart()?->getTimestamp());
    }

    #[Test]
    public function pastOccurrencesAreSortedWithTheMostRecentFirst(): void
    {
        self::assertSame([1, 6], $this->find((new EventsDemand())->setMode(EventsDemand::MODE_PAST)));
    }

    #[Test]
    public function allOccurrencesInChronologicalOrder(): void
    {
        self::assertSame([6, 1, 3, 2, 4, 5, 9, 11], $this->find((new EventsDemand())->setMode(EventsDemand::MODE_ALL)));
    }

    #[Test]
    public function explicitSortingDirectionOverridesTheModeDefault(): void
    {
        self::assertSame([6, 1], $this->find((new EventsDemand())->setMode(EventsDemand::MODE_PAST)->setOrderDirection('asc')));
        self::assertSame([11, 9, 5, 4, 2, 3], $this->find((new EventsDemand())->setOrderDirection('DESC')));
    }

    #[Test]
    public function rangeModeFindsOccurrencesOverlappingTheRange(): void
    {
        $from = new \DateTimeImmutable('@' . (self::NOW + 86400));
        $to = new \DateTimeImmutable('@' . (self::NOW + 3 * 86400));

        self::assertSame([2, 4, 5], $this->find((new EventsDemand())->setMode(EventsDemand::MODE_RANGE)->setFrom($from)->setTo($to)));
        self::assertSame([9, 11], $this->find((new EventsDemand())->setMode(EventsDemand::MODE_RANGE)->setFrom(new \DateTimeImmutable('@' . (self::NOW + 4 * 86400)))));
        self::assertSame([6, 1], $this->find((new EventsDemand())->setMode(EventsDemand::MODE_RANGE)->setTo(new \DateTimeImmutable('@' . (self::NOW - 86400)))));
        self::assertSame([6, 1, 3, 2, 4, 5, 9, 11], $this->find((new EventsDemand())->setMode(EventsDemand::MODE_RANGE)), 'no range means all');
    }

    #[Test]
    public function nowCanBePassedExplicitly(): void
    {
        $uids = array_map(
            static fn(Occurrence $o): int => (int)$o->getUid(),
            $this->subject->findByDemand(new EventsDemand(), [2], new \DateTimeImmutable('@' . (self::NOW + 2 * 86400 + 1)))
        );

        self::assertSame([4, 5, 9, 11], $uids);
    }

    #[Test]
    public function onlyPagesBelowTheRootPagesAreConsidered(): void
    {
        self::assertSame([7], $this->find(new EventsDemand(), [8]));
        self::assertSame([3, 2, 7, 4, 5, 9, 11], $this->find(new EventsDemand(), [2, 8]), 'same start: occurrence uid decides');
        self::assertSame([3, 2, 7, 4, 5, 9, 11], $this->find(new EventsDemand(), [-2, 8, 2]), 'negative and duplicate root pages are tolerated');
        self::assertSame([], $this->find(new EventsDemand(), []));
        self::assertSame([], $this->find(new EventsDemand(), [999]));
        self::assertSame([], $this->find(new EventsDemand(), [3]), 'an event page itself has no sub pages');
    }

    #[Test]
    public function findNextOfEventPrefersTheNextDateAndFallsBackToTheLast(): void
    {
        $eventRepository = $this->get(EventRepository::class);
        $alpha = $eventRepository->findByUid(3);
        $epsilon = $eventRepository->findByUid(7);
        $nested = $eventRepository->findByUid(12);
        self::assertInstanceOf(Event::class, $alpha);
        self::assertInstanceOf(Event::class, $epsilon);
        self::assertInstanceOf(Event::class, $nested);
        $now = new \DateTimeImmutable('@' . self::NOW);

        self::assertSame(2, $this->subject->findNextOfEvent($alpha, $now)?->getUid(), 'tomorrow, not two days ago');
        self::assertSame(11, $this->subject->findNextOfEvent($epsilon, $now)?->getUid(), 'in five days, not ten days ago');
        self::assertSame(11, $this->subject->findNextOfEvent($epsilon, new \DateTimeImmutable('@' . (self::NOW + 10 * 86400)))?->getUid(), 'the last one once all have ended');
        self::assertSame(9, $this->subject->findNextOfEvent($nested, $now)?->getUid());
        self::assertNull($this->subject->findNextOfEvent(new Event(), $now));
    }

    #[Test]
    public function findUpcomingOfEventListsTheDatesThatHaveNotEnded(): void
    {
        $now = new \DateTimeImmutable('2026-06-01 12:00:00', new \DateTimeZone('UTC'));
        $beta = $this->get(EventRepository::class)->findByUid(4);
        self::assertInstanceOf(Event::class, $beta);

        $upcoming = $this->subject->findUpcomingOfEvent($beta, $now);

        self::assertNotSame([], $upcoming);
        foreach ($upcoming as $occurrence) {
            self::assertSame(4, $occurrence->getEvent()?->getUid());
            self::assertGreaterThanOrEqual($now->getTimestamp(), $occurrence->getEnd()?->getTimestamp());
        }
        $starts = array_map(static fn(Occurrence $occurrence): int => (int)$occurrence->getStart()?->getTimestamp(), $upcoming);
        $sorted = $starts;
        sort($sorted);
        self::assertSame($sorted, $starts, 'sorted by start');

        self::assertCount(1, $this->subject->findUpcomingOfEvent($beta, $now, 1));
        self::assertSame([], $this->subject->findUpcomingOfEvent($beta, new \DateTimeImmutable('2999-01-01', new \DateTimeZone('UTC'))));
    }

    #[Test]
    public function findPageIdsBelowCollectsTheWholeTree(): void
    {
        self::assertSame([3, 4, 5, 6, 7, 11, 12, 13], $this->subject->findPageIdsBelow([2]), 'the hidden page 10 is skipped');
        self::assertSame([], $this->subject->findPageIdsBelow([0, -0]));
    }

    #[Test]
    public function hiddenPagesAndOtherPageTypesAreExcluded(): void
    {
        $uids = $this->find((new EventsDemand())->setMode(EventsDemand::MODE_ALL));

        self::assertNotContains(8, $uids, 'hidden event');
        self::assertNotContains(10, $uids, 'blog post');
    }

    #[Test]
    public function categoriesIncludeTheirSubCategories(): void
    {
        self::assertSame([3], $this->find((new EventsDemand())->setCategories([$this->category(2)])), 'Beta through Jazz, child of Music, once');
        self::assertSame([3], $this->find((new EventsDemand())->setCategory($this->category(3))));
        self::assertSame([1, 2], $this->find((new EventsDemand())->setMode(EventsDemand::MODE_ALL)->setCategories([$this->category(1)])));
        self::assertSame([3, 2], $this->find((new EventsDemand())->setCategories([$this->category(1), $this->category(2)])));
    }

    #[Test]
    public function tagsFilterTheEvents(): void
    {
        self::assertSame([3], $this->find((new EventsDemand())->setTags([2])));
        self::assertSame([3, 2], $this->find((new EventsDemand())->setTags([1, 2])));
        self::assertSame([2], $this->find((new EventsDemand())->setTag(1)));
        self::assertSame([], $this->find((new EventsDemand())->setTags([999])));
    }

    #[Test]
    public function excludedPagesAreLeftOut(): void
    {
        self::assertSame([2, 4, 5, 9, 11], $this->find((new EventsDemand())->setExcluded([4])));
    }

    #[Test]
    public function topPostFlagFiltersTheEvents(): void
    {
        self::assertSame([2], $this->find((new EventsDemand())->setTopPost(true)));
        self::assertSame([3, 4, 5, 9, 11], $this->find((new EventsDemand())->setNonTopPost(true)));
    }

    #[Test]
    public function limitAndOffsetApply(): void
    {
        self::assertSame([3, 2], $this->find((new EventsDemand())->setLimit(2)));
        self::assertSame([2, 4], $this->find((new EventsDemand())->setLimit(2)->setOffset(1)));
        self::assertSame([3, 2, 4, 5, 9, 11], $this->find((new EventsDemand())->setLimit(0)), 'no limit');
    }

    #[Test]
    public function sortingByPageFieldsWithTheStartAsSecondaryOrder(): void
    {
        self::assertSame([2, 3, 5, 11, 4, 9], $this->find((new EventsDemand())->setOrder(PostsDemand::ORDER_TITLE)));
        self::assertSame([9, 4, 11, 5, 3, 2], $this->find((new EventsDemand())->setOrder(PostsDemand::ORDER_TITLE)->setOrderDirection('DESC')));
        self::assertSame([2, 3, 4, 5, 11, 9], $this->find((new EventsDemand())->setOrder(PostsDemand::ORDER_CREATION_DATE)));
        self::assertSame([3, 2, 4, 5, 9, 11], $this->find((new EventsDemand())->setOrder('unknown')), 'unknown order falls back to the start');
    }
}
